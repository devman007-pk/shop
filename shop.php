<?php
declare(strict_types=1);

// shop.php - หน้าแสดงสินค้า + API (ajax=1 คืน JSON)
// ใช้ config.php ที่มีฟังก์ชัน getPDO()

// โหลด config/ฟังก์ชันการเชื่อมต่อ
require_once __DIR__ . '/config.php';

try {
    $pdo = getPDO();
} catch (Exception $e) {
    http_response_code(500);
    echo "Database connection error";
    exit;
}

/**
 * Simple helper to read integer params
 */
function getIntParam(string $key, ?int $default = null): ?int {
    if (!isset($_GET[$key])) return $default;
    $v = filter_var($_GET[$key], FILTER_VALIDATE_INT);
    return $v === false ? $default : $v;
}

/**
 * Meta endpoints (AJAX)
 */
if (isset($_GET['meta'])) {
    $meta = (string)($_GET['meta'] ?? '');
    if ($meta === 'tags' && isset($_GET['group'])) {
        $group = (string)$_GET['group'];
        $sql = "
          SELECT pt.tag_value AS value, COUNT(DISTINCT pt.product_id) AS cnt
          FROM product_tags pt
          JOIN products p ON p.id = pt.product_id AND p.active = 1
          WHERE pt.tag_group = :group
          GROUP BY pt.tag_value
          ORDER BY cnt DESC, pt.tag_value ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':group', $group, PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($meta === 'brands') {
        $sql = "
          SELECT b.id, b.name, COALESCE(b.logo_url, '') AS logo_url,
                 (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.active = 1) AS cnt
          FROM brands b
          WHERE COALESCE(b.active, 1) = 1
          ORDER BY b.name ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    // Serve JSON API for products
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $category = getIntParam('category', null);
    $min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'default';
    $page = getIntParam('page', 1) ?? 1;
    $per_page = getIntParam('per_page', 16) ?? 16;

    $page = max(1, $page);
    $per_page = max(1, min(100, $per_page));
    $offset = ($page - 1) * $per_page;

    $params = [];
    $where = ["p.active = 1"];

    if ($q !== '') {
        $where[] = "(p.name LIKE :q OR p.description LIKE :q OR p.sku LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }

    if ($category) {
        $where[] = "EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = :category_id)";
        $params[':category_id'] = $category;
    }

    if ($min_price !== null) {
        $where[] = "p.price_base >= :min_price";
        $params[':min_price'] = $min_price;
    }
    if ($max_price !== null) {
        $where[] = "p.price_base <= :max_price";
        $params[':max_price'] = $max_price;
    }

    if (isset($_GET['in_stock']) && $_GET['in_stock'] === '1') {
        $where[] = "EXISTS (SELECT 1 FROM inventory i WHERE i.product_id = p.id AND (i.quantity - IFNULL(i.reserved,0)) > 0)";
    }

    $where_sql = '';
    if (count($where) > 0) $where_sql = 'WHERE ' . implode(' AND ', $where);

    $order_sql = 'ORDER BY p.id DESC';
    if ($sort === 'price-asc') $order_sql = 'ORDER BY p.price_base ASC';
    elseif ($sort === 'price-desc') $order_sql = 'ORDER BY p.price_base DESC';
    elseif ($sort === 'name') $order_sql = 'ORDER BY p.name ASC';

    // Count total
    $countSql = "SELECT COUNT(DISTINCT p.id) as total FROM products p {$where_sql}";
    $stmt = $pdo->prepare($countSql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();

    // Fetch items with main image and brand
    $sql = "
        SELECT
          p.id,
          p.name AS title,
          p.slug,
          p.sku,
          p.price_base AS price,
          p.description,
          p.active,
          p.featured,
          b.name AS brand,
          (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url,
          (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM product_categories pc JOIN categories c ON c.id = pc.category_id WHERE pc.product_id = p.id) AS categories
        FROM products p
        LEFT JOIN brands b ON b.id = p.brand_id
        {$where_sql}
        {$order_sql}
        LIMIT :offset, :per_page
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', (int)$per_page, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize and add in_stock
    $stockStmt = $pdo->prepare("SELECT SUM(quantity - IFNULL(reserved,0)) AS qty FROM inventory WHERE product_id = :pid");
    foreach ($items as &$it) {
        if (empty($it['image_url'])) {
            $it['image_url'] = 'https://via.placeholder.com/300x200?text=No+image';
        }
        $stockStmt->execute([':pid' => $it['id']]);
        $qty = (int)$stockStmt->fetchColumn();
        $it['in_stock'] = $qty > 0;
        $it['price'] = (float)$it['price'];
    }
    unset($it);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'items' => $items
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// HTML rendering: fetch categories
$catsStmt = $pdo->prepare("SELECT id, name, parent_id, slug FROM categories ORDER BY name ASC");
$catsStmt->execute();
$allCats = $catsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ร้านค้า - Shop</title>

  <link rel="stylesheet" href="styles.css" />
  <style>
    .page-main .toolbar {
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      margin-bottom:18px;
    }
    .page-main .toolbar .left {
      display:flex;
      align-items:center;
      gap:8px;
      color: rgba(11,47,74,0.75);
      font-weight:600;
    }
    .page-main .toolbar .right {
      display:flex;
      align-items:center;
      gap:12px;
      justify-content:flex-end;
    }
    .page-main .toolbar .pricelist {
      background: #fff;
      border: 1px solid rgba(11,47,74,0.06);
      padding: 8px 14px;
      border-radius: 8px;
      font-weight:700;
      color: var(--navy);
      white-space:nowrap;
      box-shadow: 0 6px 20px rgba(9,30,45,0.03);
    }
    .page-main .toolbar .sort-by {
      display:flex;
      align-items:center;
      gap:8px;
      background:#fff;
      border:1px solid rgba(11,47,74,0.06);
      padding:8px 12px;
      border-radius:8px;
      box-shadow: 0 6px 20px rgba(9,30,45,0.03);
      font-weight:700;
      color: rgba(11,47,74,0.8);
      white-space:nowrap;
    }
    .page-main .toolbar .sort-by label { margin-right:6px; font-weight:700; color: rgba(11,47,74,0.7); }
    .page-main .toolbar .sort-by select {
      appearance:none; -webkit-appearance:none; border:0; background:transparent;
      font-weight:700; padding:4px 6px; cursor:pointer;
    }
    .page-main .toolbar .view-controls { display:none !important; }
    .load-more-wrap { display:none !important; }

    /* =========================================
       Custom Native Price Slider (ปรับดีไซน์ใหม่)
       ========================================= */
    .page-main .sidebar .filter-section.price-section {
      padding-bottom: 20px !important; 
    }
    
    /* ตัวเลขด้านบนแถบเลื่อน (แยกซ้าย-ขวา) และขนาดใหญ่ขึ้น */
    .price-label-top-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }
    .price-label-top-row span {
      font-size: 1.05rem; /* ขยายขนาดฟอนต์ให้ใหญ่ขึ้น */
      font-weight: 800; /* ทำให้ตัวหนาขึ้น */
      color: var(--navy);
    }

    .custom-price-slider {
      position: relative;
      width: 100%;
      height: 4px;
      background: rgba(0, 0, 0, 0.06);
      border-radius: 4px;
      margin: 8px 0 6px 0;
    }
    .custom-price-slider .slider-track {
      position: absolute;
      height: 100%;
      background: #222; 
      border-radius: 4px;
      z-index: 1;
      left: 0%;
      right: 0%;
    }
    .custom-price-slider input[type="range"] {
      position: absolute;
      width: 100%;
      height: 4px;
      top: 0;
      background: none;
      pointer-events: none;
      -webkit-appearance: none;
      appearance: none;
      z-index: 2;
      margin: 0;
      outline: none;
    }
    .custom-price-slider input[type="range"]::-webkit-slider-thumb {
      height: 18px;
      width: 18px;
      border-radius: 50%;
      background: #fff;
      border: 3px solid #222;
      pointer-events: auto;
      -webkit-appearance: none;
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
      cursor: grab;
      position: relative;
      z-index: 3;
      transition: transform 0.1s ease;
    }
    .custom-price-slider input[type="range"]::-webkit-slider-thumb:active {
      cursor: grabbing;
      transform: scale(1.2);
    }
    .custom-price-slider input[type="range"]::-moz-range-thumb {
      height: 18px;
      width: 18px;
      border-radius: 50%;
      background: #fff;
      border: 3px solid #222;
      pointer-events: auto;
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
      cursor: grab;
      z-index: 3;
      transition: transform 0.1s ease;
    }
    .custom-price-slider input[type="range"]::-moz-range-thumb:active {
      cursor: grabbing;
      transform: scale(1.2);
    }

    /* ตัวเลขด้านล่างแถบเลื่อน */
    .price-range-labels {
      display: flex;
      justify-content: space-between;
      font-size: 0.85rem;
      font-weight: 600;
      color: rgba(11, 47, 74, 0.5);
      margin-bottom: 14px;
    }

    /* แถวสำหรับกล่องตัวเลข */
    .price-inputs {
      display: flex;
      align-items: center;
      gap: 8px;
      width: 100%;
      margin-bottom: 14px; /* เว้นระยะห่างก่อนปุ่ม Filter */
    }
    .price-inputs .price-input {
      flex: 1;
      width: 100%;
      min-width: 0;
      font-size: 0.95rem !important;
      font-weight: 600 !important;
      color: var(--navy) !important;
      padding: 8px 12px !important;
      border-radius: 6px;
      border: 1px solid rgba(0, 0, 0, 0.12);
      text-align: center;
      background: #fff;
      outline: none;
    }
    .price-inputs .dash {
      font-size: 1.1rem;
      font-weight: 700;
      color: rgba(11, 47, 74, 0.5);
    }

    /* จัดปุ่ม FILTER ชิดขวาล่างสุดแยกบรรทัด */
    .filter-btn-row {
      display: flex;
      justify-content: flex-end; /* จัดชิดขวา */
      width: 100%;
    }
    .btn-filter {
      position: static !important;
      height: 36px !important;
      padding: 0 16px !important;
      font-size: 0.85rem !important;
      border-radius: 8px !important;
      background: linear-gradient(90deg, var(--blue), var(--teal)) !important;
      color: #fff !important;
      border: 0 !important;
      font-weight: 800 !important;
      cursor: pointer !important;
      box-shadow: 0 6px 16px rgba(9,30,45,0.12) !important;
      transition: transform 0.15s ease, box-shadow 0.15s ease !important;
    }
    .btn-filter:hover {
      transform: translateY(-2px) !important;
      box-shadow: 0 12px 24px rgba(9,30,45,0.15) !important;
    }
  </style>
</head>
<body>
  <?php
  include __DIR__ . '/navbar.php';
  ?>

  <main class="container page-main">
    <aside class="sidebar">
      <h3>Categories</h3>
      <ul class="categories" id="categoryList">
        <?php foreach ($allCats as $c): ?>
          <li data-id="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></li>
        <?php endforeach; ?>
      </ul>

      <div class="filter-section" data-collapsible data-collapsed="true">
        <h4>ตัวเลือก <span class="chev" aria-hidden="true">›</span></h4>
        <div class="filter-body">
          <label><input type="checkbox" id="inStockChk" value="1" /> มีสินค้า</label>
        </div>
      </div>

      <div class="filter-section price-section" data-collapsible data-collapsed="true">
        <h4>ราคา <span class="chev" aria-hidden="true">›</span></h4>
        <div class="filter-body">
          
          <div class="price-label-top-row">
            <span id="labelMin">฿0</span>
            <span id="labelMax">฿3,000,000</span>
          </div>

          <div class="custom-price-slider">
            <div class="slider-track"></div>
            <input type="range" min="0" max="3000000" value="0" id="sliderMin" step="100">
            <input type="range" min="0" max="3000000" value="3000000" id="sliderMax" step="100">
          </div>

          <div class="price-range-labels">
            <span>0</span>
            <span>3,000,000</span>
          </div>

          <div class="price-inputs">
            <input type="number" class="price-input" id="inputMin" placeholder="ต่ำสุด" name="min_price" value="0">
            <span class="dash">-</span>
            <input type="number" class="price-input" id="inputMax" placeholder="สูงสุด" name="max_price" value="3000000">
          </div>
          
          <div class="filter-btn-row">
            <button id="priceFilterBtn" class="btn-filter">FILTER</button>
          </div>
          
        </div>
      </div>

      <div class="filter-section color-section" data-collapsible data-collapsed="true">
        <h4>สี <span class="chev" aria-hidden="true">›</span></h4>
        <div class="filter-body">
          <div class="color-list" id="colorList"></div>
        </div>
      </div>

      <div class="filter-section brand-section" data-collapsible data-collapsed="true">
        <h4>แบรนด์ <span class="chev" aria-hidden="true">›</span></h4>
        <div class="filter-body">
          <div class="brand-search-wrap">
            <input id="brandSearch" type="search" placeholder="Search..." />
            <button id="brandClear" type="button" aria-label="Clear">✕</button>
          </div>
          <div class="brand-grid" id="brandGrid" data-placeholder-count="12" aria-live="polite"></div>
          <div class="brand-note">* แบรนด์จริงจะถูกดึงจากฐานข้อมูล เมื่อติดตั้งแล้ว</div>
        </div>
      </div>

      <button id="resetFilters" class="btn-reset">RESET</button>
    </aside>

    <section class="content">
      <div class="toolbar">
        <div class="left">
          <div id="productsCount">All Products: <span id="totalCount">0</span> items</div>
        </div>

        <div class="right">
          <div class="pricelist">Pricelist: <span style="font-weight:800">Default</span></div>

          <div class="sort-by" aria-label="Sort By">
            <label for="sortSelect">Sort By :</label>
            <select id="sortSelect" name="sort">
              <option value="default">ฟีเจอร์</option>
              <option value="price-asc">ราคาต่ำ → สูง</option>
              <option value="price-desc">ราคาสูง → ต่ำ</option>
              <option value="name">ชื่อ (A-Z)</option>
            </select>
          </div>
        </div>
      </div>

      <div id="productGrid" class="product-grid grid-view">
        </div>
    </section>
  </main>

  <div class="quick-contact">
    <button title="แชท"><i class="fa fa-comment-dots"></i></button>
    <button title="โทร"><i class="fa fa-phone"></i></button>
  </div>

  <?php
  $footerPath = __DIR__ . '/footer.php';
  if (file_exists($footerPath)) {
      include_once $footerPath;
  }
  ?>

  <script>
    window.SHOP_API = '<?php echo basename(__FILE__); ?>';
  </script>

  <script src="script.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const sliderMin = document.getElementById('sliderMin');
      const sliderMax = document.getElementById('sliderMax');
      const inputMin = document.getElementById('inputMin');
      const inputMax = document.getElementById('inputMax');
      const track = document.querySelector('.custom-price-slider .slider-track');
      
      const labelMin = document.getElementById('labelMin');
      const labelMax = document.getElementById('labelMax');

      if (!sliderMin || !sliderMax) return;

      const gap = 10000; // ระยะห่างขั้นต่ำไม่ให้ชนกัน
      const maxLimit = parseInt(sliderMin.max);

      // ฟังก์ชันใส่ลูกน้ำให้ตัวเลข
      function formatNumber(num) {
        return num.toLocaleString('th-TH');
      }

      function updateTrack() {
        const minVal = parseInt(sliderMin.value);
        const maxVal = parseInt(sliderMax.value);
        
        // อัปเดตตัวเลขด้านบน (แยกซ้าย-ขวา)
        labelMin.textContent = `฿${formatNumber(minVal)}`;
        labelMax.textContent = `฿${formatNumber(maxVal)}`;
        
        // คำนวณ % เพื่อวาดเส้นสีดำตรงกลาง
        const percentMin = (minVal / maxLimit) * 100;
        const percentMax = (maxVal / maxLimit) * 100;
        
        track.style.left = percentMin + '%';
        track.style.right = (100 - percentMax) + '%';
      }

      // เลื่อนตัวซ้าย
      sliderMin.addEventListener('input', () => {
        if (parseInt(sliderMax.value) - parseInt(sliderMin.value) <= gap) {
          sliderMin.value = parseInt(sliderMax.value) - gap;
        }
        inputMin.value = sliderMin.value;
        updateTrack();
      });

      // เลื่อนตัวขวา
      sliderMax.addEventListener('input', () => {
        if (parseInt(sliderMax.value) - parseInt(sliderMin.value) <= gap) {
          sliderMax.value = parseInt(sliderMin.value) + gap;
        }
        inputMax.value = sliderMax.value;
        updateTrack();
      });

      // พิมพ์กล่องซ้าย
      inputMin.addEventListener('input', () => {
        if(inputMin.value === '') return;
        sliderMin.value = inputMin.value;
        updateTrack();
      });

      // พิมพ์กล่องขวา
      inputMax.addEventListener('input', () => {
        if(inputMax.value === '') return;
        sliderMax.value = inputMax.value;
        updateTrack();
      });

      updateTrack(); // วาดเส้นและตัวเลขครั้งแรกตอนโหลด
    });
  </script>

</body>
</html>