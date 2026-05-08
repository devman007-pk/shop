<?php
// product-detail.php - หน้ารายละเอียดสินค้า
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

$pdo = getPDO();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. ดึงข้อมูลสินค้าหลัก
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("<div style='text-align:center; padding: 50px; font-family:sans-serif;'><h2>ไม่พบสินค้าที่คุณต้องการ</h2><a href='shop.php'>กลับหน้าร้านค้า</a></div>");
}

// 2. ดึงรูปภาพทั้งหมดของสินค้านี้
$stmtImg = $pdo->prepare("SELECT url FROM product_images WHERE product_id = ? ORDER BY position ASC");
$stmtImg->execute([$id]);
$images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
$main_img = !empty($images) ? $images[0]['url'] : 'placeholder.png';

// 3. ดึงข้อมูล TAG (แบรนด์ และ ประเภทสินค้า)
$stmtTags = $pdo->prepare("SELECT tag_group, tag_value FROM product_tags WHERE product_id = ?");
$stmtTags->execute([$id]);
$rawTags = $stmtTags->fetchAll(PDO::FETCH_ASSOC);

$p_brands = [];
$p_categories = [];
foreach ($rawTags as $t) {
    if ($t['tag_group'] === 'brand') $p_brands[] = $t['tag_value'];
    if ($t['tag_group'] === 'category') $p_categories[] = $t['tag_value'];
}

// 4. ดึงสินค้าแนะนำ/สินค้าอื่นๆ (สุ่มมา 4 ชิ้น)
$hasShowPrice = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'show_price'")->fetchAll();
    if (count($cols) > 0) $hasShowPrice = true;
} catch (Throwable $e) {}
$spCol = $hasShowPrice ? "p.show_price" : "1 AS show_price";

$stmtRel = $pdo->query("
    SELECT p.id, p.name, p.price, $spCol,
    (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url
    FROM products p 
    WHERE p.is_active = 1 AND p.id != $id
    ORDER BY RAND() LIMIT 4
");
$related = $stmtRel->fetchAll(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function formatPrice($p) {
    if ($p === null || $p === '') return null;
    if (!is_numeric($p)) return null;
    return number_format((float)$p, 2);
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo h($product['name']); ?> - รายละเอียดสินค้า</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .pd-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
    .pd-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 40px; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid rgba(11,47,74,0.03); }
    
    .pd-images .main-img { width: 100%; height: 400px; object-fit: contain; border: 1px solid #eee; border-radius: 8px; margin-bottom: 15px; padding: 10px; background: #fdfdfd; }
    .pd-images .thumbnails { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; }
    .pd-images .thumb { width: 80px; height: 80px; object-fit: contain; border: 1px solid #eee; border-radius: 6px; cursor: pointer; padding: 5px; transition: 0.2s; background: #fff; }
    .pd-images .thumb:hover { border-color: #1e90ff; }

    .pd-info h1 { font-size: 1.8rem; font-weight: 800; color: #0b2f4a; margin: 0 0 10px 0; line-height: 1.3; }
    
    /* สไตล์สำหรับป้าย Tags */
    .tags-container { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 15px; }
    .sku-tag { background: #f0f2f5; padding: 4px 12px; border-radius: 4px; font-size: 0.85rem; color: #555; font-weight: 600; border: 1px solid #e8e8e8; }
    .tag-badge { padding: 4px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
    .tag-brand { background: #f6ffed; color: #389e0d; border: 1px solid #b7eb8f; }
    .tag-category { background: #e6f4ff; color: #1677ff; border: 1px solid #91caff; }

    /* ราคาสินค้า (สีแดง #9B0F06) */
    .pd-info .price { font-size: 2rem; font-weight: 900; color: #9B0F06; margin: 20px 0; }
    .pd-info .desc { color: #555; line-height: 1.7; margin-bottom: 30px; font-size: 1rem; background: #fcfcfc; padding: 15px; border-radius: 8px; border: 1px dashed #eee; }
    
    .pd-actions { display: flex; gap: 15px; align-items: center; }
    
    /* =======================================================
       สไตล์ปุ่มตะกร้า (ฟ้า-เขียว) และ ปุ่มหัวใจ 
       ======================================================= */
    .btn-add-cart-large { 
        flex: 1; 
        background: linear-gradient(90deg, #1e90ff, #2bb673) !important; 
        color: #fff !important; 
        border: none !important; 
        padding: 16px 20px; 
        border-radius: 8px; 
        font-size: 1.1rem; 
        font-weight: 800; 
        cursor: pointer; 
        transition: 0.2s; 
        display: flex; 
        justify-content: center; 
        align-items: center; 
        gap: 10px; 
        box-shadow: 0 4px 12px rgba(43, 182, 115, 0.2); 
    }
    .btn-add-cart-large:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 6px 16px rgba(43, 182, 115, 0.4); 
    }

    .pd-actions .fav-btn {
        width: 60px; height: 60px; border-radius: 8px; background: #fff; border: 1px solid rgba(11,47,74,0.1);
        display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; padding: 0;
    }
    .pd-actions .fav-btn svg { width: 28px; height: 28px; display: block; }
    .pd-actions .fav-btn svg path { stroke: #0b2f4a; fill: transparent; transition: stroke 0.2s ease, fill 0.2s ease; }
    
    /* หัวใจแดงเต็มดวง */
    .pd-actions .fav-btn.active { border-color: #ff4d4f; }
    .pd-actions .fav-btn.active svg path, .product-card .fav-btn.active svg path { stroke: #ff4d4f !important; fill: #ff4d4f !important; }
    .product-card .fav-btn.active { border-color: #ff4d4f !important; color: #ff4d4f !important; }

    /* ปุ่มตะกร้าเล็กใน Card สินค้าด้านล่าง */
    .product-card .add-cart.btn-icon {
        background: linear-gradient(90deg, #1e90ff, #2bb673) !important;
        color: #fff !important;
        border: none !important;
    }
    .product-card .add-cart.btn-icon:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(43, 182, 115, 0.3) !important; }

    .section-title { font-size: 1.4rem; font-weight: 800; color: #0b2f4a; margin: 50px 0 20px 0; border-left: 4px solid #1e90ff; padding-left: 10px; }
    .related-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 50px; }
    
    .card-link-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 10; }
    .product-card { position: relative; cursor: pointer !important; }
    .product-card .fav-btn, .product-card .add-cart { position: relative; z-index: 20 !important; }

    @media (max-width: 900px) { .pd-grid { grid-template-columns: 1fr; } .related-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 500px) { .related-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="pd-container">
    <div class="pd-grid">
      <div class="pd-images">
        <img id="mainImage" src="<?php echo h($main_img); ?>" class="main-img" alt="<?php echo h($product['name']); ?>">
        <?php if(count($images) > 1): ?>
        <div class="thumbnails">
          <?php foreach($images as $img): ?>
            <img src="<?php echo h($img['url']); ?>" class="thumb" onclick="document.getElementById('mainImage').src=this.src;">
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="pd-info">
        
       <div class="tags-container">
            <span class="sku-tag">รหัสสินค้า: <?php echo h($product['sku']); ?></span>
            
            <?php if(!empty($p_brands)): ?>
                <span class="tag-badge tag-brand">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    แบรนด์: <?php echo h(implode(', ', $p_brands)); ?>
                </span>
            <?php endif; ?>

            <?php if(!empty($p_categories)): ?>
                <span class="tag-badge tag-category">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                    หมวดหมู่: <?php echo h(implode(', ', $p_categories)); ?>
                </span>
            <?php endif; ?>
        </div>

        <h1><?php echo h($product['name']); ?></h1>
        
        <?php 
            $fmt = formatPrice($product['price']); 
            $showPrice = !isset($product['show_price']) || $product['show_price'] == 1;
        ?>
        <div class="price">
            <?php echo ($showPrice && $fmt !== null) ? '฿' . h($fmt) : '<span style="font-size:1.4rem; color:#9B0F06;">สอบถามราคา</span>'; ?>
        </div>

        <div class="desc">
            <h4 style="margin-top:0; color:#0b2f4a;">รายละเอียดสินค้า:</h4>
            <?php echo nl2br(h($product['description'])); ?>
        </div>

        <div class="pd-actions">
            <button class="add-cart btn-add-cart-large" data-id="<?php echo h($product['id']); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                เพิ่มสินค้าลงตะกร้า
            </button>
            
            <button class="fav-btn" type="button" data-pid="<?php echo h($product['id']); ?>">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12.1 21s-7.6-4.8-9.5-7.1C-0.6 11.5 2.2 6.6 6.6 6.6c2.3 0 3.9 1.5 4.9 2.6 1-1.1 2.6-2.6 4.9-2.6 4.4 0 7.2 4.9 3.9 7.3-1.9 2.3-9.5 7.1-9.5 7.1z" stroke="currentColor" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
      </div>
    </div>

    <h2 class="section-title">สินค้าอื่นๆ ที่คุณอาจสนใจ</h2>
    <div class="related-grid">
      <?php foreach($related as $rp): ?>
        <article class="product-card" data-product-id="<?php echo h($rp['id']); ?>">
          <a href="product-detail.php?id=<?php echo h($rp['id']); ?>" class="card-link-overlay"></a>

          <div class="product-thumb">
            <?php if (!empty($rp['image_url'])): ?>
                <img src="<?php echo h($rp['image_url']); ?>" alt="<?php echo h($rp['name']); ?>">
            <?php else: ?>
                <span class="thumb-label">รูปสินค้า</span>
            <?php endif; ?>
            <button class="fav-btn" type="button" data-pid="<?php echo h($rp['id']); ?>" style="position: absolute;">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12.1 21s-7.6-4.8-9.5-7.1C-0.6 11.5 2.2 6.6 6.6 6.6c2.3 0 3.9 1.5 4.9 2.6 1-1.1 2.6-2.6 4.9-2.6 4.4 0 7.2 4.9 3.9 7.3-1.9 2.3-9.5 7.1-9.5 7.1z" stroke="currentColor" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </div>
          <h3 class="prod-title" style="color: var(--navy);"><?php echo h($rp['name']); ?></h3>
          <?php $fmtRec = formatPrice($rp['price']); ?>
          <div class="product-price" style="color: #9B0F06 !important; font-weight: 800; font-size: 1.1rem; margin-top: 8px;">
            <?php echo (!isset($rp['show_price']) || $rp['show_price'] == 1) && $fmtRec !== null ? '฿'.h($fmtRec) : 'สอบถามราคา'; ?>
          </div>
          <div class="card-actions">
            <button class="add-cart btn-icon" data-id="<?php echo h($rp['id']); ?>">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
              เพิ่มในตะกร้า
            </button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  <script src="script.js"></script>
</body>
</html>