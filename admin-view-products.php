<?php
// admin-view-products.php - หน้าดูข้อมูลสินค้าและประวัติผู้ซื้อ (เพิ่มระบบ 4 คอลัมน์ + แบ่งหน้าแบบใหม่)
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$search = $_GET['search'] ?? '';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ==========================================
// ส่วนที่ 1: แสดงรายละเอียดสินค้า (ถ้ามีการส่ง ID มา)
// ==========================================
if ($product_id > 0) {
    // ดึงข้อมูลสินค้าหลัก
    $stmt = $pdo->prepare("
        SELECT p.*, b.name as brand_name 
        FROM products p 
        LEFT JOIN brands b ON p.brand_id = b.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) die("ไม่พบสินค้านี้");

    // ดึงรูปภาพทั้งหมด
    $images = $pdo->prepare("SELECT url FROM product_images WHERE product_id = ? ORDER BY position ASC");
    $images->execute([$product_id]);
    $product_images = $images->fetchAll(PDO::FETCH_ASSOC);

    // ดึง TAG แบรนด์และประเภท จากตาราง product_tags
    $stmtTags = $pdo->prepare("SELECT tag_group, tag_value FROM product_tags WHERE product_id = ?");
    $stmtTags->execute([$product_id]);
    $rawTags = $stmtTags->fetchAll(PDO::FETCH_ASSOC);

    $p_brands = [];
    $p_categories = [];
    foreach ($rawTags as $t) {
        if ($t['tag_group'] === 'brand') $p_brands[] = $t['tag_value'];
        if ($t['tag_group'] === 'category') $p_categories[] = $t['tag_value'];
    }
    // Fallback: ถ้าไม่มี TAG แบรนด์ใหม่ ให้ลองใช้ชื่อแบรนด์ระบบเก่าเผื่อไว้
    if (empty($p_brands) && !empty($product['brand_name'])) {
        $p_brands[] = $product['brand_name'];
    }

    // ดึงประวัติผู้ซื้อ
    $history = $pdo->prepare("
        SELECT u.username, u.email, o.created_at, oi.quantity, o.id as order_id
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN users u ON o.user_id = u.id
        WHERE oi.product_id = ? AND o.status IN ('paid', 'completed', 'shipped', 'processing')
        ORDER BY o.created_at DESC
    ");
    $history->execute([$product_id]);
    $buyers = $history->fetchAll(PDO::FETCH_ASSOC);
} 
// ==========================================
// ส่วนที่ 2: แสดงรายการค้นหาสินค้า (เพิ่มระบบแบ่งหน้า)
// ==========================================
else {
    // ตั้งค่าการแบ่งหน้า (Pagination)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    
    // กำหนดจำนวนสินค้าต่อหน้า (4 คอลัมน์ x 3 แถว = 12 ชิ้น)
    $limit = 12; 
    $offset = ($page - 1) * $limit;

    $countQuery = "SELECT COUNT(*) FROM products p";
    $query = "SELECT p.*, 
              (SELECT url FROM product_images WHERE product_id = p.id ORDER BY position ASC LIMIT 1) as main_img 
              FROM products p";
              
    if ($search) {
        $countQuery .= " WHERE p.name LIKE ? OR p.sku LIKE ?";
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute(["%$search%", "%$search%"]);
        $total_items = $stmtCount->fetchColumn();

        $query .= " WHERE p.name LIKE ? OR p.sku LIKE ? ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($query);
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $total_items = $pdo->query($countQuery)->fetchColumn();

        $query .= " ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->query($query);
    }
    
    $all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_pages = ceil($total_items / $limit);
}
?>

<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการข้อมูลสินค้า - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans Thai', sans-serif; background: #f0f2f5; margin: 0; color: #333; }
    .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
    .card { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
    
    /* Search Bar */
    .search-section { display: flex; gap: 10px; margin-bottom: 25px; }
    .search-input { flex: 1; padding: 12px 20px; border-radius: 8px; border: 1px solid #d9d9d9; font-size: 1rem; font-family: inherit; }
    .btn-search { background: #1677ff; color: #fff; border: none; padding: 0 25px; border-radius: 8px; cursor: pointer; font-weight: 700; }

    /* Product List Layout (4 คอลัมน์เป๊ะๆ) */
    .product-list-grid { 
        display: grid; 
        grid-template-columns: repeat(4, 1fr); /* บังคับ 4 คอลัมน์ */
        gap: 20px; 
    }
    
    .product-item { background: #fff; border: 1px solid #eee; border-radius: 10px; overflow: hidden; transition: 0.3s; text-decoration: none; color: inherit; display: flex; flex-direction: column; }
    .product-item:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); border-color: #1677ff; }
    .item-img { width: 100%; height: 180px; object-fit: contain; background: #f9f9f9; padding: 15px; box-sizing: border-box; border-bottom: 1px solid #f0f0f0; }
    .item-content { padding: 15px; flex: 1; display: flex; flex-direction: column; }
    .item-name { font-weight: 700; color: #0b2f4a; margin-bottom: 5px; font-size: 0.95rem; line-height: 1.4; display: -webkit-box; -webkitlineclamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .item-sku { font-size: 0.8rem; color: #888; margin-bottom: auto; }
    .item-price { margin-top: 12px; font-weight: 800; color: #ff4d4f; font-size: 1.1rem; }

    /* Responsive Grid */
    @media (max-width: 1024px) { .product-list-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) { .product-list-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .product-list-grid { grid-template-columns: 1fr; } }

    /* =========================================
       Pagination Styles (แบบใหม่เหมือนในรูป)
       ========================================= */
    .pagination-wrap { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-top: 35px; 
        padding-top: 20px; 
        border-top: 1px solid #f0f0f0; 
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
        text-decoration: none;
        transition: all 0.2s ease; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
    }
    .page-btn:hover:not(:disabled) { 
        border-color: #1677ff; 
        background: #f0f5ff; 
    }
    .page-btn[disabled] { 
        color: #aaa; 
        background: #f5f5f5; 
        border-color: #e8e8e8; 
        cursor: not-allowed; 
        box-shadow: none; 
    }
    .page-info { 
        color: #555; 
        font-weight: 600; 
        font-size: 0.95rem; 
    }

    /* Detail View Styles */
    .detail-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; }
    .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 15px; }
    .gallery-img { width: 100%; border-radius: 6px; border: 1px solid #eee; }
    .price-tag { font-size: 1.8rem; font-weight: 800; color: #ff4d4f; margin: 15px 0; }
    
    /* History Table */
    .table-history { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .table-history th, .table-history td { padding: 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
    .table-history th { background: #fafafa; font-weight: 700; color: #555; }
    .badge-qty { background: #e6f4ff; color: #1677ff; padding: 4px 10px; border-radius: 20px; font-weight: 700; }
    
    .btn-back { display: inline-block; margin-bottom: 20px; color: #1677ff; text-decoration: none; font-weight: 600; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>

  <div class="container">
    <?php if ($product_id > 0): ?>
      <a href="admin-view-products.php" class="btn-back">← กลับไปหน้ารายการสินค้า</a>
      
      <div class="card detail-grid">
        <div>
          <img src="<?php echo h($product_images[0]['url'] ?? 'placeholder.png'); ?>" style="width:100%; border-radius:12px; border:1px solid #eee;">
          <div class="gallery-grid">
            <?php foreach($product_images as $img): ?>
              <img src="<?php echo h($img['url']); ?>" class="gallery-img">
            <?php endforeach; ?>
          </div>
        </div>
        <div>
          <h1 style="margin:0; font-weight:800; color:#0b2f4a;"><?php echo h($product['name']); ?></h1>
          
          <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
            <span style="background:#f5f5f5; padding:5px 12px; border-radius:6px; font-size:0.9rem; border:1px solid #e8e8e8;">
                รหัส: <?php echo h($product['sku']); ?>
            </span>
            
            <?php if(!empty($p_brands)): ?>
                <span style="background:#f6ffed; color:#389e0d; border:1px solid #b7eb8f; padding:5px 12px; border-radius:6px; font-size:0.9rem; font-weight:600;">
                    🏷️ แบรนด์: <?php echo h(implode(', ', $p_brands)); ?>
                </span>
            <?php endif; ?>

            <?php if(!empty($p_categories)): ?>
                <span style="background:#e6f4ff; color:#1677ff; border:1px solid #91caff; padding:5px 12px; border-radius:6px; font-size:0.9rem; font-weight:600;">
                    📂 ประเภท: <?php echo h(implode(', ', $p_categories)); ?>
                </span>
            <?php endif; ?>
          </div>

          <div class="price-tag">
            <?php 
                if (isset($product['show_price']) && $product['show_price'] == 0) {
                    echo '<span style="color:#888; font-size:1.2rem;">สอบถามราคา</span>';
                } else {
                    echo '฿' . number_format($product['price'], 2);
                }
            ?>
          </div>
          
          <div style="line-height:1.6; color:#666;">
            <h4 style="color:#333; margin-bottom:10px;">รายละเอียดสินค้า</h4>
            <?php echo nl2br(h($product['description'])); ?>
          </div>
        </div>
      </div>

      <div class="card">
        <h3 style="margin:0 0 20px 0; font-weight:800;">🛒 ประวัติการสั่งซื้อสินค้านี้</h3>
        <table class="table-history">
          <thead>
            <tr>
                <th>วันที่สั่งซื้อ</th>
                <th>ชื่อลูกค้า</th>
                <th>อีเมล</th>
                <th>จำนวน</th>
                <th>รหัสออเดอร์</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($buyers as $b): ?>
            <tr>
              <td><?php echo date('d/m/Y H:i', strtotime($b['created_at'])); ?></td>
              <td><b><?php echo h($b['username']); ?></b></td>
              <td><?php echo h($b['email']); ?></td>
              <td><span class="badge-qty"><?php echo $b['quantity']; ?> ชิ้น</span></td>
              <td><a href="admin-order-detail.php?id=<?php echo $b['order_id']; ?>" style="color:#1677ff;">#<?php echo str_pad($b['order_id'], 5, '0', STR_PAD_LEFT); ?></a></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($buyers)): ?>
              <tr><td colspan="5" style="text-align:center; padding:40px; color:#888;">ยังไม่มีประวัติการซื้อสินค้านี้</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php else: ?>
      <h2 style="font-weight:800; color:#0b2f4a; margin-bottom:20px;">คลังสินค้าทั้งหมด</h2>
      
      <form class="search-section" method="GET">
        <input type="text" name="search" class="search-input" placeholder="ค้นหาด้วยชื่อสินค้า หรือ รหัสสินค้า (SKU)..." value="<?php echo h($search); ?>">
        <button type="submit" class="btn-search">ค้นหา</button>
      </form>

      <div class="product-list-grid">
        <?php foreach($all_products as $p): ?>
        <a href="admin-view-products.php?id=<?php echo $p['id']; ?>" class="product-item">
          <img src="<?php echo h($p['main_img'] ?? 'placeholder.png'); ?>" class="item-img">
          <div class="item-content">
            <div class="item-name"><?php echo h($p['name']); ?></div>
            <div class="item-sku">SKU: <?php echo h($p['sku']); ?></div>
            <div class="item-price">
                <?php 
                    if (isset($p['show_price']) && $p['show_price'] == 0) {
                        echo '<span style="color:#888; font-size:1rem; font-weight:normal;">สอบถามราคา</span>';
                    } else {
                        echo '฿' . number_format($p['price'], 2);
                    }
                ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
        
        <?php if(empty($all_products)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 50px; background: #fff; border-radius: 10px; border: 1px dashed #ccc; color: #888;">ไม่พบสินค้าที่คุณค้นหา</div>
        <?php endif; ?>
      </div>

      <?php if (isset($total_pages) && $total_pages > 1): ?>
      <div class="pagination-wrap">
          <div class="pagination-controls">
              <?php
              $qs = "";
              if ($search) $qs .= "&search=" . urlencode($search);
              
              // ปุ่ม ก่อนหน้า
              if ($page > 1) {
                  echo '<a href="?page='.($page-1).$qs.'" class="page-btn">« ก่อนหน้า</a>';
              } else {
                  echo '<button class="page-btn" disabled>« ก่อนหน้า</button>';
              }
              
              // ปุ่ม ถัดไป
              if ($page < $total_pages) {
                  echo '<a href="?page='.($page+1).$qs.'" class="page-btn">ถัดไป »</a>';
              } else {
                  echo '<button class="page-btn" disabled>ถัดไป »</button>';
              }
              ?>
          </div>
          <div class="page-info">หน้า <?php echo $page; ?> จาก <?php echo $total_pages; ?></div>
      </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</body>
</html>