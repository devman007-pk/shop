<?php
// admin-panel.php - หน้า Dashboard แอดมิน
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // ถ้าไม่ใช่แอดมิน หรือไม่ได้ล็อกอิน ให้ส่งกลับไปหน้าล็อกอินแอดมินทันที
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$adminName = $_SESSION['user_name'] ?? 'Admin';

try {
    $ordersTotal = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $ordersPending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'pending_payment')")->fetchColumn();
    $ordersProcessing = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
    $ordersShipped = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'shipped'")->fetchColumn();
    $ordersCompleted = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('completed', 'paid')")->fetchColumn();
    $ordersCancelled = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn();

    $productsTotal = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $brandsTotal = $pdo->query("SELECT COUNT(*) FROM brands")->fetchColumn();
    
    // นับจำนวนโค้ดส่วนลด (ถ้ามีตาราง discounts)
    $discountTotal = $pdo->query("SELECT COUNT(*) FROM discounts")->fetchColumn();
    
    // นับแอดมินจากตาราง admins และนับลูกค้าจากตาราง users
    $usersAdmin = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    $usersCustomer = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    $worksTotal = $pdo->query("SELECT COUNT(*) FROM works")->fetchColumn();
    $countTagBrand = $pdo->query("SELECT COUNT(*) FROM product_tags WHERE product_id = 0 AND tag_group = 'brand'")->fetchColumn();
    $countTagCategory = $pdo->query("SELECT COUNT(*) FROM product_tags WHERE product_id = 0 AND tag_group = 'category'")->fetchColumn();

    // ดึงสินค้าล่าสุดมาแค่ 5 ชิ้นเท่านั้น
    $latestProducts = $pdo->query("
        SELECT p.id, p.name, p.sku, 
        (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url 
        FROM products p ORDER BY p.id DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $ordersTotal = $ordersPending = $ordersProcessing = $ordersShipped = $ordersCompleted = $ordersCancelled = 0;
    $productsTotal = $brandsTotal = $usersAdmin = $usersCustomer = $worksTotal = $discountTotal = 0;
    $countTagBrand = $countTagCategory = 0;
    $latestProducts = [];
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
  <style>
    body { margin: 0; font-family: 'Noto Sans Thai', sans-serif; background-color: #f0f2f5; color: #333; }
    .admin-container { max-width: 1200px; margin: 20px auto; padding: 0 15px; display: flex; flex-direction: column; gap: 15px; }
    .panel-card { background: white; border: 1px solid #e8e8e8; border-radius: 6px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; gap: 15px; }
    .panel-header { display: flex; justify-content: space-between; align-items: flex-start; }
    .panel-title { font-size: 1.1rem; font-weight: 700; margin: 0; color: #222; }
    .panel-subtitle { font-size: 0.85rem; color: #666; margin-top: 5px; }
    
    /* Layouts */
    .grid-3-col { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
    .grid-2-col { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
    
    .badge-group { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; color: white; display: inline-block; white-space: nowrap; }
    .bg-gray { background-color: #8c8c8c; }
    .bg-yellow { background-color: #faad14; }
    .bg-blue { background-color: #1677ff; }
    .bg-red { background-color: #ff4d4f; }
    .bg-green { background-color: #52c41a; }
    .bg-darkgreen { background-color: #1f8b50; }
    
    .action-group { display: flex; gap: 10px; justify-content: flex-end; align-items: center; }
    .btn { padding: 6px 16px; border-radius: 4px; font-size: 0.9rem; font-weight: 500; cursor: pointer; text-decoration: none; text-align: center; white-space: nowrap; transition: opacity 0.2s; }
    .btn-solid-blue { background: #1677ff; color: white; border: 1px solid #1677ff; }
    .btn-solid-green { background: #1f8b50; color: white; border: 1px solid #1f8b50; }
    .btn-outline-blue { background: white; color: #1677ff; border: 1px solid #1677ff; }
    .btn:hover { opacity: 0.85; }
    
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; margin-top: 15px; }
    .item-card { border: 1px solid #e8e8e8; border-radius: 6px; background: #fff; overflow: hidden; }
    .item-img-box { background: #f5f5f5; height: 180px; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #e8e8e8; padding: 10px;}
    .item-img-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .item-info { padding: 15px; }
    .item-name { font-weight: 700; font-size: 0.95rem; margin-bottom: 5px; color: #222; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
    .item-code { font-size: 0.8rem; color: #888; }
    
    /* Responsive ให้มือถือแสดงเป็นแถวเดียว */
    @media (max-width: 768px) {
        .grid-2-col { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <?php include __DIR__ . '/admin-navbar.php'; ?>

  <div class="admin-container">
    
    <div class="grid-3-col">
        <div class="panel-card">
            <div class="panel-header" style="flex-direction: column; gap: 15px; height: 100%;">
                <div>
                    <h3 class="panel-title">ตั้งค่าหน้าเว็บหลัก</h3>
                    <div class="panel-subtitle" style="margin-top: 10px; line-height: 1.5;">เพิ่มสินค้าตรง สินค้าแนะนำ และ สินค้าใหม่</div>
                </div>
                <div class="action-group" style="width: 100%; margin-top: auto;">
                    <a href="admin-product.php" class="btn btn-solid-green" style="width: 100%; padding: 10px;">เลือกสินค้า</a>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">จัดการสินค้า</h3>
                    <div class="badge-group">
                        <span class="badge bg-blue">ประเภท: <?php echo $countTagCategory; ?></span>
                        <span class="badge bg-green">แบรนด์: <?php echo $countTagBrand; ?></span>
                    </div>
                </div>
                <div class="action-group">
                    <a href="admin-tag.php" class="btn btn-solid-blue">เพิ่ม TAG</a>
                    <a href="admin-edit-tag.php" class="btn btn-outline-blue">แก้ไข</a>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">จัดการแบรนด์</h3>
                    <div class="badge-group">
                        <span class="badge bg-gray">ทั้งหมด: <?php echo $brandsTotal; ?></span>
                    </div>
                </div>
                <div class="action-group">
                    <a href="admin-brand.php" class="btn btn-solid-blue">เพิ่มแบรนด์</a>
                    <a href="admin-edit-brand.php" class="btn btn-outline-blue">แก้ไข/ลบ</a>
                </div>
            </div>
        </div>
    </div> 

    <div class="grid-3-col">
        <div class="panel-card">
            <div class="panel-header" style="flex-direction: column; gap: 15px; height: 100%;">
                <div>
                    <h3 class="panel-title">ตั้งค่าหน้าเกี่ยวกับเรา</h3>
                    <div class="panel-subtitle" style="margin-top: 10px; line-height: 1.5;">แก้ไขข้อมูลบริษัท โลโก้ ประวัติ และข้อมูลติดต่อ</div>
                </div>
                <div class="action-group" style="width: 100%; margin-top: auto;">
                    <a href="admin-edit-aboutas.php" class="btn btn-solid-blue" style="width: 100%; padding: 10px;">แก้ไขข้อมูลบริษัท</a>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">จัดการผลงาน</h3>
                    <div class="badge-group">
                        <span class="badge bg-gray">ทั้งหมด: <?php echo $worksTotal; ?></span>
                    </div>
                </div>
                <div class="action-group">
                    <a href="admin-ourwork.php" class="btn btn-solid-blue">เพิ่มผลงาน</a>
                    <a href="admin-edit-ourwork.php" class="btn btn-outline-blue">แก้ไข/ลบ</a>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header" style="flex-direction: column; gap: 15px; height: 100%;">
                <div>
                    <h3 class="panel-title">ตั้งค่าหน้าติดต่อเรา</h3>
                    <div class="panel-subtitle" style="margin-top: 10px; line-height: 1.5;">แก้ไขที่อยู่ อีเมล เบอร์โทร และรูป QR Code</div>
                </div>
                <div class="action-group" style="width: 100%; margin-top: auto;">
                    <a href="admin-edit-contactus.php" class="btn btn-solid-blue" style="width: 100%; padding: 10px;">แก้ไขข้อมูลติดต่อเรา</a>
                </div>
            </div>
        </div>
    </div>

    <div class="grid-2-col">
        <div class="panel-card">
            <div class="panel-header" style="flex-direction: column; gap: 15px; height: 100%;">
                <div>
                    <h3 class="panel-title">จัดการข้อมูลสินค้า (Products)</h3>
                    <div class="badge-group" style="margin-bottom: 8px;">
                        <span class="badge bg-gray">ทั้งหมด: <?php echo $productsTotal; ?></span>
                    </div>
                    <div class="panel-subtitle">เพิ่มสินค้าใหม่ อัปโหลดรูปภาพ แก้ไขรายละเอียด หรือลบสินค้า</div>
                </div>
                <div class="action-group" style="width: 100%; margin-top: auto; justify-content: flex-start;">
                    <a href="admin-add-product.php" class="btn btn-solid-blue">เพิ่มสินค้า</a>
                    <a href="admin-edit-product.php" class="btn btn-outline-blue">แก้ไข/ลบ</a>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header" style="flex-direction: column; gap: 15px; height: 100%;">
                <div>
                    <h3 class="panel-title">จัดการโค้ดส่วนลด (Discount)</h3>
                    <div class="badge-group" style="margin-bottom: 8px;">
                        <span class="badge bg-green">รวม: <?php echo $discountTotal; ?></span>
                    </div>
                    <div class="panel-subtitle">สร้างและจัดการโค้ดส่วนลด โปรโมชั่นสำหรับลูกค้า</div>
                </div>
                <div class="action-group" style="width: 100%; margin-top: auto; justify-content: flex-start;">
                    <a href="admin-code-discount.php" class="btn btn-solid-blue">สร้างโค้ดส่วนลด</a>
                    <a href="admin-edit-code-discount.php" class="btn btn-outline-blue">จัดการโค้ด</a>
                </div>
            </div>
        </div>
    </div>

    <div class="panel-card">
        <div class="panel-header" style="align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h3 class="panel-title">จัดการผู้ใช้</h3>
                <div class="panel-subtitle" style="margin-top: 5px;">Admin: <?php echo $usersAdmin; ?> | Customer: <?php echo $usersCustomer; ?></div>
            </div>
            <div class="action-group">
                <a href="admin-user-add.php" class="btn btn-solid-blue">เพิ่มผู้ใช้</a>
                <a href="admin-manage.php" class="btn btn-outline-blue">จัดการบัญชีผู้ดูแล</a>
                <a href="admin-member-manage.php" class="btn btn-outline-blue">จัดการบัญชีลูกค้า</a>
            </div>
        </div>
    </div>

    <div class="panel-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
            <div>
                <h3 class="panel-title">ภาพรวมสถานะคำสั่งซื้อ</h3>
                <div class="badge-group" style="margin-top: 10px; margin-bottom: 15px;">
                    <span class="badge bg-gray">ทั้งหมด: <?php echo $ordersTotal; ?></span>
                    <span class="badge bg-yellow">รอชำระเงิน: <?php echo $ordersPending; ?></span>
                    <span class="badge bg-blue">กำลังจัดเตรียม: <?php echo $ordersProcessing; ?></span>
                    <span class="badge bg-red">ยกเลิก: <?php echo $ordersCancelled; ?></span>
                    <span class="badge bg-green">จัดส่งแล้ว: <?php echo $ordersShipped; ?></span>
                    <span class="badge bg-darkgreen">เสร็จสิ้นแล้ว: <?php echo $ordersCompleted; ?></span>
                </div>
            </div>
            <div class="action-group">
                <a href="admin-order.php" class="btn btn-solid-green">ไปหน้าจัดการคำสั่งซื้อ</a>
                <a href="admin-reports.php" class="btn btn-outline-blue">รายงานยอดขาย</a>
            </div>
        </div>
    </div>

    <div class="panel-card" style="background: transparent; border: none; padding: 0;">
        <div class="panel-header" style="background: white; padding: 15px 20px; border: 1px solid #e8e8e8; border-radius: 6px; align-items: center;">
            <h3 class="panel-title">สินค้าในระบบล่าสุด</h3>
            <a href="admin-view-products.php" class="btn btn-solid-blue">ดูทั้งหมด</a>
        </div>
        
        <div class="product-grid">
            <?php foreach($latestProducts as $item): ?>
            <div class="item-card">
                <div class="item-img-box">
                    <?php if(!empty($item['image_url'])): ?>
                        <img src="<?php echo h($item['image_url']); ?>" alt="<?php echo h($item['name']); ?>">
                    <?php else: ?>
                        <div style="width: 120px; height: 120px; background: #f5f5f5; display: flex; align-items: center; justify-content: center; color: #ccc;">No Img</div>
                    <?php endif; ?>
                </div>
                <div class="item-info">
                    <div class="item-name"><?php echo h($item['name']); ?></div>
                    <div class="item-code">รหัสสินค้า: <?php echo h($item['sku'] ?? '#PRD'.str_pad((string)$item['id'], 4, '0', STR_PAD_LEFT)); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($latestProducts)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: white; border-radius: 6px; border: 1px dashed #ccc; color: #888;">
                    ยังไม่มีสินค้าในระบบ
                </div>
            <?php endif; ?>
        </div>
    </div>

  </div>

</body>
</html>