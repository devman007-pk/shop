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

function columnExists(PDO $pdo, string $table, string $column): bool {
    $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column";
    $st = $pdo->prepare($sql);
    $st->execute([':table' => $table, ':column' => $column]);
    return (int)$st->fetchColumn() > 0;
}

try {
    $productsHasActive = columnExists($pdo, 'products', 'active');
    $brandsHasActive = columnExists($pdo, 'brands', 'active');
} catch (Exception $e) {
    $productsHasActive = false;
    $brandsHasActive = false;
}

$brandWhere = [];
if ($brandsHasActive) {
    $brandWhere[] = "COALESCE(b.active, 1) = 1";
}

$productsActiveCond = $productsHasActive ? "AND p.active = 1" : "";
$brandWhereSql = !empty($brandWhere) ? 'WHERE ' . implode(' AND ', $brandWhere) : '';

// แก้ไข SQL ให้นับจำนวนสินค้าจากตาราง product_tags แทน
$sql = "
  SELECT
    b.id,
    b.name,
    COALESCE(b.logo_url, '') AS logo_url,
    (
      SELECT COUNT(DISTINCT pt.product_id)
      FROM product_tags pt
      JOIN products p ON pt.product_id = p.id
      WHERE pt.tag_group = 'brand' 
        AND pt.tag_value = b.name
        {$productsActiveCond}
    ) AS cnt
  FROM brands b
  {$brandWhereSql}
  ORDER BY b.name ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $brands = [];
    $queryError = $e->getMessage();
}

// แสดงทุกแบรนด์ แม้จะยังไม่มีสินค้าก็ตาม
$displayList = $brands;

?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>แบรนด์สินค้าทั้งหมด - Brands</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css" />
  <style>
    .brands-page { padding: 40px 15px; min-height: 60vh; font-family: 'Noto Sans Thai', sans-serif; }
    .brands-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
    .brands-title { font-weight: 800; font-size: 1.8rem; color: #0b2f4a; margin-top: 5px; }
    .brand-search { position: relative; width: 100%; max-width: 380px; }
    .brand-search input[type="search"] { width: 100%; padding: 12px 20px; padding-right: 50px; border-radius: 30px; border: 1px solid #d9d9d9; font-family: inherit; font-size: 1rem; outline: none; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
    .brand-search input[type="search"]:focus { border-color: #1677ff; box-shadow: 0 0 0 3px rgba(22, 119, 255, 0.1); }
    .brand-search-btn { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: #888; cursor: pointer; padding: 8px; display: flex; align-items: center; justify-content: center; }
    .brand-list { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
    .brand-tile { display: flex; align-items: center; background: #fff; border: 1px solid #e8e8e8; border-radius: 4px; text-decoration: none; color: inherit; transition: all 0.2s ease; overflow: hidden; height: 85px; }
    .brand-tile:hover { border-color: #1677ff; box-shadow: 0 4px 15px rgba(0,0,0,0.06); transform: translateY(-2px); }
    .brand-tile .logo-wrap { width: 120px; height: 100%; display: flex; align-items: center; justify-content: center; border-right: 1px solid #e8e8e8; padding: 10px; background: #fff; }
    .brand-tile .logo-wrap img { max-width: 100%; max-height: 45px; object-fit: contain; }
    .brand-tile .meta { flex: 1; padding: 0 15px; display: flex; flex-direction: column; justify-content: center; }
    .brand-name { font-weight: 700; color: #222; font-size: 1rem; margin-bottom: 2px; }
    .brand-count { color: #666; font-size: 0.85rem; }
    .brand-action { width: 50px; display: flex; align-items: center; justify-content: center; }
    .brand-action-icon { width: 28px; height: 28px; border-radius: 50%; background: #f0f2f5; color: #555; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; transition: 0.2s; }
    .brand-tile:hover .brand-action-icon { background: #1677ff; color: #fff; }
    .empty-state { grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: #fff; border-radius: 8px; border: 1px dashed #ccc; color: #888; }
    @media (max-width: 1024px) { .brand-list { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) { .brand-list { grid-template-columns: repeat(2, 1fr); } .brands-header { flex-direction: column; align-items: flex-start; } .brand-search { max-width: 100%; } }
    @media (max-width: 480px) { .brand-list { grid-template-columns: 1fr; } .brand-tile { height: 75px; } .brand-tile .logo-wrap { width: 100px; } }
  </style>
</head>
<body>
  <?php include __DIR__ . '/navbar.php'; ?>
  <main class="container brands-page">
    <div class="brands-header">
      <div>
        <nav aria-label="breadcrumb" style="font-size:0.9rem; color:#888; margin-bottom:5px;">
          <a href="index.php" style="color:#1677ff; text-decoration:none; font-weight:600;">Home</a> &nbsp;/&nbsp; <span>Brands</span>
        </nav>
        <div class="brands-title">แบรนด์สินค้า (Brands)</div>
      </div>
      <div class="brand-search" role="search">
        <input id="brandSearch" type="search" placeholder="ค้นหาแบรนด์ที่ต้องการ..." aria-label="Search brands" />
        <button id="brandSearchBtn" class="brand-search-btn" type="button" aria-label="ค้นหา">
          <svg viewBox="0 0 24 24" width="20" height="20"><circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="2" fill="none"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
      </div>
    </div>

    <div class="brand-list" id="brandList">
      <?php if (count($displayList) === 0): ?>
        <div class="empty-state">ไม่พบข้อมูลแบรนด์สินค้าในขณะนี้</div>
      <?php else: ?>
        <?php foreach ($displayList as $b): ?>
          <a href="shop.php?brand=<?php echo urlencode($b['name']); ?>" class="brand-tile" data-name="<?php echo htmlspecialchars((string)$b['name']); ?>">
            <div class="logo-wrap">
              <?php if (!empty($b['logo_url'])): ?>
                <img src="<?php echo htmlspecialchars($b['logo_url']); ?>" alt="<?php echo htmlspecialchars((string)$b['name']); ?> logo" />
              <?php else: ?>
                <div style="font-size:0.8rem; color:#ccc; font-weight:600;">No Logo</div>
              <?php endif; ?>
            </div>
            <div class="meta">
              <div class="brand-name"><?php echo htmlspecialchars((string)$b['name']); ?></div>
              <div class="brand-count"><?php echo number_format((int)$b['cnt']); ?> Products</div>
            </div>
            <div class="brand-action">
              <div class="brand-action-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include_once __DIR__ . '/footer.php'; ?>

  <script>
    (function(){
      const input = document.getElementById('brandSearch');
      const list = document.getElementById('brandList');
      if (!list || !input) return;
      function doFilter(q){
        q = q.trim().toLowerCase();
        Array.from(list.children).forEach(tile => {
          if(tile.classList.contains('empty-state')) return;
          const name = tile.dataset.name ? tile.dataset.name.toLowerCase() : '';
          tile.style.display = (!q || name.indexOf(q) !== -1) ? '' : 'none';
        });
      }
      input.addEventListener('input', (e) => doFilter(e.target.value));
    })();
  </script>
</body>
</html>