<?php
declare(strict_types=1);

// brand.php - Brand listing page (robust version)
// - Shows brand tiles (logo + name + product count)
// - Counts only products that have at least one tag in product_tags
// - Detects whether 'active' columns exist in tables and adds conditions only if present
// Requires config.php with getPDO()

require_once __DIR__ . '/config.php';

try {
    $pdo = getPDO();
} catch (Exception $e) {
    http_response_code(500);
    echo "Database connection error";
    exit;
}

// helper: check if a column exists in the current database
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
    // If information_schema isn't accessible, fall back to "no active column"
    $productsHasActive = false;
    $brandsHasActive = false;
}

// Build dynamic WHERE for brands (if brands.active exists)
$brandWhere = [];
if ($brandsHasActive) {
    $brandWhere[] = "COALESCE(b.active, 1) = 1";
}
// If you want to filter out brands with no logo, add condition here (optional)

// Build SQL for fetching brands and counts
// The subquery counts products with the brand_id, optionally requiring p.active = 1 (if column exists),
// and requiring that the product has at least one row in product_tags.
$productsActiveCond = $productsHasActive ? "AND p.active = 1" : "";

$brandWhereSql = '';
if (!empty($brandWhere)) {
    $brandWhereSql = 'WHERE ' . implode(' AND ', $brandWhere);
}

$sql = "
  SELECT
    b.id,
    b.name,
    COALESCE(b.logo_url, '') AS logo_url,
    (
      SELECT COUNT(DISTINCT p.id)
      FROM products p
      WHERE p.brand_id = b.id
        {$productsActiveCond}
        AND EXISTS (SELECT 1 FROM product_tags pt WHERE pt.product_id = p.id)
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
    // If query fails, show a friendly message and placeholders
    $brands = [];
    $queryError = $e->getMessage();
}

// By default show only brands with count > 0 (you can change to show all)
$displayList = array_values(array_filter($brands, function($b){
    return (int)$b['cnt'] > 0;
}));

?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Brands</title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    /* Page-specific styles (same as previous) */
    .brands-page { padding: 28px 0; }
    .brands-header { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:18px; }
    .brands-title { font-weight:800; font-size:1.4rem; color:var(--navy); }
    .brand-search { display:flex; gap:8px; align-items:center; }
    .brand-search input[type="search"] { padding:8px 10px; border-radius:6px; border:1px solid rgba(11,47,74,0.06); width:260px; }
    .brand-list { display:grid; grid-template-columns: repeat(auto-fill, minmax(320px,1fr)); gap:18px; margin-top:12px; }
    .brand-tile {
      display:flex;
      gap:18px;
      align-items:center;
      padding:14px 18px;
      background:#fff;
      border-radius:8px;
      border:1px solid rgba(11,47,74,0.06);
      box-shadow:0 6px 18px rgba(9,30,45,0.03);
      min-height:82px;
    }
    .brand-tile .logo-wrap { flex:0 0 140px; display:flex; align-items:center; justify-content:center; }
    .brand-tile .logo-wrap img { max-width:120px; max-height:56px; object-fit:contain; display:block; }
    .brand-tile .meta { flex:1 1 auto; display:flex; flex-direction:column; gap:6px; }
    .brand-name { font-weight:800; color:var(--navy); }
    .brand-count { color: rgba(11,47,74,0.6); font-size:0.95rem; }
    .brand-action { flex:0 0 42px; display:flex; align-items:center; justify-content:center; }
    .brand-placeholder { height:72px; border:1px dashed rgba(11,47,74,0.06); border-radius:8px; display:flex; align-items:center; justify-content:center; color:rgba(11,47,74,0.4); background:#fff; }
    .waiting-note { color: rgba(11,47,74,0.6); margin-top:8px; font-size:0.95rem; }
    @media (max-width:760px){
      .brand-list { grid-template-columns: 1fr; }
      .brand-tile { padding:12px; gap:12px; }
      .brand-tile .logo-wrap { flex-basis:100px; }
    }
    .error-note { color: #b00020; font-weight:700; margin-bottom:12px; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/navbar.php'; ?>

  <main class="container brands-page">
    <div class="brands-header">
      <div>
        <nav aria-label="breadcrumb" style="font-size:0.9rem;color:rgba(11,47,74,0.6); margin-bottom:8px;">
          <a href="index.php">Home</a> &nbsp;/&nbsp; <span>Brands</span>
        </nav>
        <div class="brands-title">Brands</div>
      </div>

      <div class="brand-search" role="search" aria-label="Search brands">
        <input id="brandSearch" class="brand-search-input" type="search" placeholder="Search brands..." aria-label="Search brands" />
        <button id="brandSearchBtn" class="brand-search-btn" type="button" aria-label="ค้นหา">
          <!-- SVG magnifier (icon-only) -->
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
            <circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="1.6" fill="none"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
    </div>

    <?php if (!empty($queryError)): ?>
      <div class="error-note">Error loading brands: <?php echo htmlspecialchars($queryError); ?></div>
    <?php endif; ?>

    <?php if (count($displayList) === 0): ?>

      <div class="brand-list" id="brandList">
        <?php for ($i=0; $i<8; $i++): ?>
          <div class="brand-placeholder">Waiting for DB update</div>
        <?php endfor; ?>
      </div>

    <?php else: ?>

      <div class="brand-list" id="brandList">
        <?php foreach ($displayList as $b): ?>
          <label class="brand-tile" data-name="<?php echo htmlspecialchars((string)$b['name']); ?>">
            <div class="logo-wrap">
              <?php if (!empty($b['logo_url'])): ?>
                <img src="<?php echo htmlspecialchars($b['logo_url']); ?>" alt="<?php echo htmlspecialchars((string)$b['name']); ?> logo" />
              <?php else: ?>
                <div style="width:120px;height:56px;display:flex;align-items:center;justify-content:center;color:rgba(11,47,74,0.4);">
                  No logo
                </div>
              <?php endif; ?>
            </div>

            <div class="meta">
              <div class="brand-name"><?php echo htmlspecialchars((string)$b['name']); ?></div>
              <div class="brand-count"><?php echo number_format((int)$b['cnt']); ?> Products</div>
            </div>

            <div class="brand-action" aria-hidden="true">
              <button class="btn-cta" title="View brand" style="border:0;background:rgba(11,47,74,0.04);width:36px;height:36px;border-radius:18px;cursor:pointer;">›</button>
            </div>
          </label>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </main>

  <?php
  $footerPath = __DIR__ . '/footer.php';
  if (file_exists($footerPath)) {
      include_once $footerPath;
  }
  ?>

  <script>
    // simple client-side filtering for brand list
    (function(){
      const input = document.getElementById('brandSearch');
      const btn = document.getElementById('brandSearchBtn');
      const list = document.getElementById('brandList');
      if (!list) return;
      function doFilter(q){
        q = (q||'').trim().toLowerCase();
        Array.from(list.children).forEach(tile => {
          const name = (tile.dataset && tile.dataset.name) ? tile.dataset.name.toLowerCase() : '';
          if (!q) tile.style.display = '';
          else tile.style.display = name.indexOf(q) !== -1 ? '' : 'none';
        });
      }
      if (input){
        input.addEventListener('input', (e) => doFilter(e.target.value));
        if (btn) btn.addEventListener('click', ()=> doFilter(input.value));
      }
    })();
  </script>
</body>
</html>