<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config.php';

try {
    $pdo = getPDO();
} catch (Exception $e) {
    http_response_code(500);
    echo "Database connection error";
    exit;
}

function getIntParam(string $key, ?int $default = null): ?int {
    if (!isset($_GET[$key])) return $default;
    $v = filter_var($_GET[$key], FILTER_VALIDATE_INT);
    return $v === false ? $default : $v;
}

// ---------------------------------------------------------
// ส่วนที่ 1: API ส่งข้อมูลให้ JS
// ---------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $brandFilter = isset($_GET['brand']) ? trim((string)$_GET['brand']) : ''; // รับค่า Tag แบรนด์
    $catTagFilter = isset($_GET['cat_tag']) ? trim((string)$_GET['cat_tag']) : ''; // รับค่า Tag ประเภท
    
    $min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'default';
    $page = getIntParam('page', 1) ?? 1;
    $per_page = getIntParam('per_page', 16) ?? 16;

    $page = max(1, $page);
    $per_page = max(1, min(100, $per_page));
    $offset = ($page - 1) * $per_page;

    $params = [];
    $where = ["p.is_active = 1"];

    if ($q !== '') {
        $where[] = "(p.name LIKE :q OR p.description LIKE :q OR p.sku LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }

    // กรองด้วย TAG แบรนด์
    if ($brandFilter !== '') {
        $where[] = "EXISTS (SELECT 1 FROM product_tags pt WHERE pt.product_id = p.id AND pt.tag_group = 'brand' AND pt.tag_value = :brand)";
        $params[':brand'] = $brandFilter;
    }

    // กรองด้วย TAG ประเภทสินค้า
    if ($catTagFilter !== '') {
        $where[] = "EXISTS (SELECT 1 FROM product_tags pt WHERE pt.product_id = p.id AND pt.tag_group = 'category' AND pt.tag_value = :cat_tag)";
        $params[':cat_tag'] = $catTagFilter;
    }

    if ($min_price !== null) {
        $where[] = "p.price >= :min_price";
        $params[':min_price'] = $min_price;
    }
    if ($max_price !== null) {
        $where[] = "p.price <= :max_price";
        $params[':max_price'] = $max_price;
    }

    if (isset($_GET['in_stock']) && $_GET['in_stock'] === '1') {
        $where[] = "EXISTS (SELECT 1 FROM inventory i WHERE i.product_id = p.id AND (i.quantity - IFNULL(i.reserved,0)) > 0)";
    }

    $where_sql = '';
    if (count($where) > 0) $where_sql = 'WHERE ' . implode(' AND ', $where);

    $order_sql = 'ORDER BY p.id DESC';
    if ($sort === 'price-asc') $order_sql = 'ORDER BY p.price ASC';
    elseif ($sort === 'price-desc') $order_sql = 'ORDER BY p.price DESC';
    elseif ($sort === 'name') $order_sql = 'ORDER BY p.name ASC';

    $countSql = "SELECT COUNT(DISTINCT p.id) as total FROM products p {$where_sql}";
    $stmt = $pdo->prepare($countSql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();

    $hasShowPrice = false;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'show_price'")->fetchAll();
        if (count($cols) > 0) $hasShowPrice = true;
    } catch(Exception $e) {}
    $spCol = $hasShowPrice ? "p.show_price," : "1 AS show_price,";

    $sql = "
        SELECT
          p.id, p.name AS title, p.slug, p.sku, p.price, $spCol p.description, p.is_active, p.is_featured,
          (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url
        FROM products p
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

    $stockStmt = $pdo->prepare("SELECT SUM(quantity - IFNULL(reserved,0)) AS qty FROM inventory WHERE product_id = :pid");
    foreach ($items as &$it) {
        if (empty($it['image_url'])) {
            $it['image_url'] = 'https://via.placeholder.com/300x200?text=No+image';
        }
        $stockStmt->execute([':pid' => $it['id']]);
        $qty = (int)$stockStmt->fetchColumn();
        $it['in_stock'] = $qty > 0;
        
        if (isset($it['show_price']) && $it['show_price'] == 0) {
            $it['price'] = null;
        } else {
            $it['price'] = (float)$it['price'];
        }
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

// ---------------------------------------------------------
// ส่วนที่ 2: ดึงข้อมูลหมวดหมู่และแบรนด์สำหรับแถบด้านซ้าย
// ---------------------------------------------------------

// ดึง TAG ประเภทสินค้า
$catTagsStmt = $pdo->prepare("
    SELECT pt.tag_value AS name, COUNT(DISTINCT pt.product_id) AS cnt
    FROM product_tags pt 
    JOIN products p ON pt.product_id = p.id 
    WHERE pt.tag_group = 'category' AND p.is_active = 1
    GROUP BY pt.tag_value
    ORDER BY pt.tag_value ASC
");
$catTagsStmt->execute();
$allCategoryTags = $catTagsStmt->fetchAll(PDO::FETCH_ASSOC);

// ดึง TAG แบรนด์
$brandsStmt = $pdo->prepare("
    SELECT b.id, b.name, COALESCE(b.logo_url, '') AS logo_url,
         (
            SELECT COUNT(DISTINCT pt.product_id) 
            FROM product_tags pt 
            JOIN products p ON pt.product_id = p.id 
            WHERE pt.tag_group = 'brand' AND pt.tag_value = b.name AND p.is_active = 1
         ) AS cnt
    FROM brands b
    ORDER BY b.name ASC
");
$brandsStmt->execute();
$allBrandsSidebar = $brandsStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ร้านค้า - Shop</title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    .page-main .toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px; }
    .page-main .toolbar .left { display:flex; align-items:center; gap:8px; color: rgba(11,47,74,0.75); font-weight:600; }
    .page-main .toolbar .right { display:flex; align-items:center; gap:12px; justify-content:flex-end; }
    .page-main .toolbar .pricelist { background: #fff; border: 1px solid rgba(11,47,74,0.06); padding: 8px 14px; border-radius: 8px; font-weight:700; color: var(--navy); white-space:nowrap; box-shadow: 0 6px 20px rgba(9,30,45,0.03); }
    .page-main .toolbar .sort-by { display:flex; align-items:center; gap:8px; background:#fff; border:1px solid rgba(11,47,74,0.06); padding:8px 12px; border-radius:8px; box-shadow: 0 6px 20px rgba(9,30,45,0.03); font-weight:700; color: rgba(11,47,74,0.8); white-space:nowrap; }
    .page-main .toolbar .sort-by label { margin-right:6px; font-weight:700; color: rgba(11,47,74,0.7); }
    .page-main .toolbar .sort-by select { appearance:none; -webkit-appearance:none; border:0; background:transparent; font-weight:700; padding:4px 6px; cursor:pointer; }
    .page-main .toolbar .view-controls, .load-more-wrap { display:none !important; }

    /* Custom Slider */
    .page-main .sidebar .filter-section.price-section { padding-bottom: 20px !important; }
    .price-label-top-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .price-label-top-row span { font-size: 1.05rem; font-weight: 800; color: var(--navy); }
    .custom-price-slider { position: relative; width: 100%; height: 4px; background: rgba(0, 0, 0, 0.06); border-radius: 4px; margin: 8px 0 6px 0; }
    .custom-price-slider .slider-track { position: absolute; height: 100%; background: #222; border-radius: 4px; z-index: 1; left: 0%; right: 0%; }
    .custom-price-slider input[type="range"] { position: absolute; width: 100%; height: 4px; top: 0; background: none; pointer-events: none; -webkit-appearance: none; appearance: none; z-index: 2; margin: 0; outline: none; }
    .custom-price-slider input[type="range"]::-webkit-slider-thumb { height: 18px; width: 18px; border-radius: 50%; background: #fff; border: 3px solid #222; pointer-events: auto; -webkit-appearance: none; box-shadow: 0 2px 6px rgba(0,0,0,0.15); cursor: grab; position: relative; z-index: 3; transition: transform 0.1s ease; }
    .custom-price-slider input[type="range"]::-webkit-slider-thumb:active { cursor: grabbing; transform: scale(1.2); }
    .price-range-labels { display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; color: rgba(11, 47, 74, 0.5); margin-bottom: 14px; }
    .price-inputs { display: flex; align-items: center; gap: 8px; width: 100%; margin-bottom: 14px; }
    .price-inputs .price-input { flex: 1; width: 100%; min-width: 0; font-size: 0.95rem !important; font-weight: 600 !important; color: var(--navy) !important; padding: 8px 12px !important; border-radius: 6px; border: 1px solid rgba(0, 0, 0, 0.12); text-align: center; background: #fff; outline: none; }
    .price-inputs .dash { font-size: 1.1rem; font-weight: 700; color: rgba(11, 47, 74, 0.5); }
    .filter-btn-row { display: flex; justify-content: flex-end; width: 100%; }
    .btn-filter { position: static !important; height: 36px !important; padding: 0 16px !important; font-size: 0.85rem !important; border-radius: 8px !important; background: linear-gradient(90deg, var(--blue), var(--teal)) !important; color: #fff !important; border: 0 !important; font-weight: 800 !important; cursor: pointer !important; box-shadow: 0 6px 16px rgba(9,30,45,0.12) !important; transition: transform 0.15s ease, box-shadow 0.15s ease !important; }
    .btn-filter:hover { transform: translateY(-2px) !important; box-shadow: 0 12px 24px rgba(9,30,45,0.15) !important; }

    /* Custom Scrollbar */
    .custom-scroll::-webkit-scrollbar { width: 5px; }
    .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }
    .custom-scroll::-webkit-scrollbar-thumb:hover { background: #aaa; }
    
    .brand-grid-scroll { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; max-height: 280px; overflow-y: auto; padding-right: 5px; }
    .brand-grid-scroll::-webkit-scrollbar { width: 5px; }
    .brand-grid-scroll::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
    .brand-grid-scroll::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }

    /* =========================================
       สไตล์สำหรับปุ่มแบ่งหน้า (Pagination)
       ========================================= */
    .pagination-wrap { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-top: 40px; 
        padding-top: 20px; 
        border-top: 1px solid rgba(11,47,74,0.08); 
        width: 100%; 
    }
    .pagination-controls { 
        display: flex; 
        gap: 10px; 
    }
    .page-btn { 
        padding: 8px 18px; 
        border: 1px solid #d9d9d9; 
        background: #fff; 
        border-radius: 6px; 
        color: #1677ff; 
        cursor: pointer; 
        font-family: inherit; 
        font-size: 0.95rem; 
        font-weight: 600; 
        transition: all 0.2s ease; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
    }
    .page-btn:hover:not(:disabled) { 
        border-color: #1677ff; 
        color: #1677ff; 
        background: #f0f5ff; 
        transform: translateY(-1px); 
        box-shadow: 0 4px 8px rgba(22,119,255,0.1); 
    }
    .page-btn:disabled { 
        color: #aaa; 
        background: #f5f5f5; 
        border-color: #e8e8e8; 
        cursor: not-allowed; 
        box-shadow: none; 
        transform: none; 
    }
    .page-info { 
        color: #555; 
        font-weight: 600; 
        font-size: 0.95rem; 
    }

    /* =========================================
       บังคับ Layout สินค้าให้เป็น 3 คอลัมน์ (แถวละ 3 ชิ้น)
       ========================================= */
    .page-main .product-grid {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important; /* บังคับให้แบ่งเป็น 3 ช่องเท่าๆ กัน */
        gap: 20px !important; /* ระยะห่างระหว่างกล่องสินค้า */
        width: 100%;
    }

    /* สำหรับหน้าจอแท็บเล็ต (iPad) ให้ย่อเหลือแถวละ 2 ชิ้น จะได้ไม่บีบเกินไป */
    @media (max-width: 992px) {
        .page-main .product-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }

    /* สำหรับหน้าจอมือถือ ให้ย่อเหลือแถวละ 1 ชิ้น ดูง่ายๆ */
    @media (max-width: 576px) {
        .page-main .product-grid {
            grid-template-columns: 1fr !important;
        }
    }
    
  </style>
</head>
<body>
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="container page-main">
    <aside class="sidebar">
      <h3 style="margin-top: 0; margin-bottom: 15px; color: #0b2f4a; font-weight: 800; font-size: 1.2rem;">Categories</h3>

      <div class="filter-section" data-collapsible data-collapsed="false">
        <h4>ประเภทสินค้า <span class="chev" aria-hidden="true">›</span></h4>
        <div class="filter-body">
          <label style="margin-bottom: 12px; display: block; font-weight: 600; color: #0b2f4a; cursor: pointer;">
          </label>
          
          <hr style="border: 0; border-top: 1px solid #eee; margin: 0 0 10px 0;">
          
          <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;" class="custom-scroll">
              <?php foreach ($allCategoryTags as $c): ?>
                  <?php $isActiveCat = (isset($_GET['cat_tag']) && $_GET['cat_tag'] === $c['name']); ?>
                  <a href="shop.php<?php echo $isActiveCat ? '' : '?cat_tag='.urlencode($c['name']); ?>" 
                     style="display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; margin-bottom: 4px; border-radius: 6px; text-decoration: none; color: <?php echo $isActiveCat ? '#1677ff' : '#555'; ?>; background: <?php echo $isActiveCat ? '#e6f4ff' : '#f9f9f9'; ?>; font-weight: <?php echo $isActiveCat ? '700' : '500'; ?>; transition: 0.2s;">
                      <span><?php echo htmlspecialchars($c['name']); ?></span>
                      <span style="font-size: 0.75rem; background: <?php echo $isActiveCat ? '#1677ff' : '#e0e0e0'; ?>; color: <?php echo $isActiveCat ? '#fff' : '#666'; ?>; padding: 2px 8px; border-radius: 12px; font-weight: 700;"><?php echo $c['cnt']; ?></span>
                  </a>
              <?php endforeach; ?>
              
              <?php if(empty($allCategoryTags)): ?>
                  <div style="text-align: center; padding: 15px; color: #888; font-size: 0.9rem; border: 1px dashed #ccc; border-radius: 6px;">ไม่มีประเภทสินค้า</div>
              <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="filter-section price-section" data-collapsible data-collapsed="true">
        <h4>ราคา <span class="chev" aria-hidden="true">›</span></h4>
        <div class="filter-body">
          <div class="price-label-top-row"><span id="labelMin">฿0</span><span id="labelMax">฿3,000,000</span></div>
          <div class="custom-price-slider">
            <div class="slider-track"></div>
            <input type="range" min="0" max="3000000" value="0" id="sliderMin" step="100">
            <input type="range" min="0" max="3000000" value="3000000" id="sliderMax" step="100">
          </div>
          <div class="price-range-labels"><span>0</span><span>3,000,000</span></div>
          <div class="price-inputs">
            <input type="number" class="price-input" id="inputMin" placeholder="ต่ำสุด" name="min_price" value="0">
            <span class="dash">-</span>
            <input type="number" class="price-input" id="inputMax" placeholder="สูงสุด" name="max_price" value="3000000">
          </div>
          <div class="filter-btn-row"><button id="priceFilterBtn" class="btn-filter">FILTER</button></div>
        </div>
      </div>

      <div class="filter-section brand-section" data-collapsible data-collapsed="true">
        <h4>แบรนด์ <span class="chev" aria-hidden="true">›</span></h4>
        <div class="filter-body">
          <div class="brand-search-wrap" style="position: relative; margin-bottom: 15px;">
            <input id="brandSearchInput" type="search" placeholder="Search..." style="width: 100%; padding: 8px 30px 8px 12px; border: 1px solid #e8e8e8; border-radius: 4px; outline: none; font-family: inherit; font-size: 0.9rem;" />
            <svg style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #999; pointer-events: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          </div>
          
          <div class="brand-grid-scroll" id="brandGrid">
            <?php foreach ($allBrandsSidebar as $b): ?>
                <?php $isActiveBrand = (isset($_GET['brand']) && $_GET['brand'] === $b['name']); ?>
                
                <a href="shop.php<?php echo $isActiveBrand ? '' : '?brand='.urlencode($b['name']); ?>" 
                   class="brand-sidebar-item" 
                   data-name="<?php echo htmlspecialchars(strtolower($b['name'])); ?>"
                   title="<?php echo htmlspecialchars($b['name']); ?>" 
                   style="display: flex; align-items: center; justify-content: center; padding: 6px; height: 50px; border: 1px solid <?php echo $isActiveBrand ? '#1677ff' : '#e8e8e8'; ?>; border-radius: 4px; text-decoration: none; background: <?php echo $isActiveBrand ? '#e6f4ff' : '#fff'; ?>; transition: all 0.2s;">
                    
                    <?php if (!empty($b['logo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($b['logo_url']); ?>" alt="<?php echo htmlspecialchars($b['name']); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    <?php else: ?>
                        <span style="font-size: 0.7rem; color: <?php echo $isActiveBrand ? '#1677ff' : '#666'; ?>; font-weight: 700; text-align: center; word-break: break-all; line-height: 1.1;"><?php echo htmlspecialchars($b['name']); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
          </div>
          
          <?php if(empty($allBrandsSidebar)): ?>
            <div style="text-align: center; padding: 15px; color: #888; font-size: 0.9rem; border: 1px dashed #ccc; border-radius: 6px;">ไม่มีแบรนด์ในระบบ</div>
          <?php endif; ?>
        </div>
      </div>

      <button id="resetFilters" class="btn-reset" onclick="location.href='shop.php'">RESET</button>
    </aside>

    <section class="content">
      <div class="toolbar">
        <div class="left"><div id="productsCount">All Products: <span id="totalCount">0</span> items</div></div>
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
      <div id="productGrid" class="product-grid grid-view"></div>
    </section>
  </main>

  <div class="quick-contact">
    <button title="แชท"><i class="fa fa-comment-dots"></i></button>
    <button title="โทร"><i class="fa fa-phone"></i></button>
  </div>

  <?php if (file_exists(__DIR__ . '/footer.php')) include_once __DIR__ . '/footer.php'; ?>

  <script>window.SHOP_API = '<?php echo basename(__FILE__); ?>';</script>
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

      const gap = 10000; const maxLimit = parseInt(sliderMin.max);
      function formatNumber(num) { return num.toLocaleString('th-TH'); }
      function updateTrack() {
        const minVal = parseInt(sliderMin.value);
        const maxVal = parseInt(sliderMax.value);
        labelMin.textContent = `฿${formatNumber(minVal)}`;
        labelMax.textContent = `฿${formatNumber(maxVal)}`;
        const percentMin = (minVal / maxLimit) * 100;
        const percentMax = (maxVal / maxLimit) * 100;
        track.style.left = percentMin + '%';
        track.style.right = (100 - percentMax) + '%';
      }

      sliderMin.addEventListener('input', () => { if (parseInt(sliderMax.value) - parseInt(sliderMin.value) <= gap) { sliderMin.value = parseInt(sliderMax.value) - gap; } inputMin.value = sliderMin.value; updateTrack(); });
      sliderMax.addEventListener('input', () => { if (parseInt(sliderMax.value) - parseInt(sliderMin.value) <= gap) { sliderMax.value = parseInt(sliderMin.value) + gap; } inputMax.value = sliderMax.value; updateTrack(); });
      inputMin.addEventListener('input', () => { if(inputMin.value === '') return; sliderMin.value = inputMin.value; updateTrack(); });
      inputMax.addEventListener('input', () => { if(inputMax.value === '') return; sliderMax.value = inputMax.value; updateTrack(); });
      updateTrack(); 
    });

    // ค้นหาแบรนด์ใน Sidebar ด้วยการพิมพ์
    document.getElementById('brandSearchInput')?.addEventListener('input', function(e) {
        const val = e.target.value.toLowerCase();
        document.querySelectorAll('.brand-sidebar-item').forEach(item => {
            if (item.dataset.name.includes(val)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });

    // ดักจับการส่งค่าไปยัง script.js ทั้งแบรนด์และประเภท
    const urlParamsObj = new URLSearchParams(window.location.search);
    const activeBrand = urlParamsObj.get('brand');
    const activeCatTag = urlParamsObj.get('cat_tag');
    
    if (activeBrand || activeCatTag) {
        const originalFetch = window.fetch;
        window.fetch = function() {
            if (typeof arguments[0] === 'string' && arguments[0].includes('ajax=1')) {
                if (activeBrand) arguments[0] += '&brand=' + encodeURIComponent(activeBrand);
                if (activeCatTag) arguments[0] += '&cat_tag=' + encodeURIComponent(activeCatTag);
            }
            return originalFetch.apply(this, arguments);
        };
    }
  </script>
</body>
</html>