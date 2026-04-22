<?php
// admin-edit-product.php - หน้าจัดการแก้ไขและลบสินค้า
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php");
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

// จัดการลบสินค้า
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $pdo->beginTransaction();
        
        // 1. ดึง URL รูปภาพขึ้นมาเพื่อเตรียมลบไฟล์ออกจากเซิร์ฟเวอร์
        $stmtImg = $pdo->prepare("SELECT url FROM product_images WHERE product_id = ?");
        $stmtImg->execute([$id]);
        $images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. ลบความเชื่อมโยงในตาราง product_tags และ product_images
        $pdo->prepare("DELETE FROM product_tags WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
        
        // 3. ลบข้อมูลสินค้าหลักออกจากฐานข้อมูล
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);

        // 4. วนลูปรูปภาพจริงออกจากโฟลเดอร์
        foreach ($images as $img) {
            if (!empty($img['url']) && file_exists($img['url'])) {
                unlink($img['url']);
            }
        }

        $pdo->commit();
        $success_msg = "ลบสินค้าและข้อมูลที่เกี่ยวข้องเรียบร้อยแล้ว!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "ไม่สามารถลบสินค้าได้: " . $e->getMessage();
    }
}

// ดึงรายการสินค้าทั้งหมดมาแสดงในตาราง
try {
    $products = $pdo->query("
        SELECT p.id, p.sku, p.name, p.price, 
        (SELECT url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url 
        FROM products p 
        ORDER BY p.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
    $error_msg = "ไม่พบตาราง products: " . $e->getMessage();
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการสินค้า - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; padding-bottom: 40px; }
    .container { max-width: 1100px; margin: 40px auto; padding: 0 15px; }
    .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .card-title { font-size: 1.4rem; font-weight: 700; color: var(--navy); margin: 0; display: flex; align-items: center; gap: 10px; }
    
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 12px; border-bottom: 1px solid #f0f0f0; text-align: left; vertical-align: middle; }
    .table th { background: #fafafa; font-weight: 700; color: #333; }
    
    .btn-add { background: var(--primary); color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
    .btn-add:hover { background: #0958d9; }
    
    .btn-action { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: 0.2s; }
    .btn-edit { background: #f6ffed; color: #389e0d; border: 1px solid #b7eb8f; margin-right: 5px; }
    .btn-edit:hover { background: #d9f7be; }
    .btn-del { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }
    .btn-del:hover { background: #ffccc7; }
    
    .product-img { width: 50px; height: 50px; object-fit: contain; background: #f9f9f9; border: 1px solid #eee; border-radius: 4px; padding: 4px; }
    
    .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; display: flex; align-items: center; gap: 8px; }
    .alert-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  <div class="container">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 16.28V2c0-1.1-.9-2-2-2H6c-1.1 0-2 .9-2 2v14.28c-.59.34-1 .98-1 1.72 0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2 0-.74-.41-1.38-1-1.72z"></path></svg>
                รายการสินค้าทั้งหมดในระบบ
            </h2>
            <a href="admin-add-product.php" class="btn-add">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                เพิ่มสินค้าใหม่
            </a>
        </div>
        
        <?php if($success_msg): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?php echo h($success_msg); ?>
            </div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                <?php echo h($error_msg); ?>
            </div>
        <?php endif; ?>

        <table class="table">
            <thead>
                <tr>
                    <th style="width: 70px;">รูปภาพ</th>
                    <th style="width: 140px;">รหัสสินค้า (SKU)</th>
                    <th>ชื่อสินค้า</th>
                    <th style="width: 120px;">ราคา (บาท)</th>
                    <th style="width: 160px; text-align: center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="5" style="text-align:center; color:#999; padding:30px;">ไม่มีข้อมูลสินค้าในระบบ</td></tr>
                <?php else: ?>
                    <?php foreach($products as $p): ?>
                    <tr>
                        <td style="text-align: center;">
                            <?php if(!empty($p['image_url'])): ?>
                                <img src="<?php echo h($p['image_url']); ?>" class="product-img" alt="img">
                            <?php else: ?>
                                <div class="product-img" style="display:flex; align-items:center; justify-content:center; color:#ccc; font-size:0.7rem;">No Img</div>
                            <?php endif; ?>
                        </td>
                        <td style="color:#666; font-size: 0.9rem;"><?php echo h($p['sku']); ?></td>
                        <td style="font-weight: 700; color: var(--navy);"><?php echo h($p['name']); ?></td>
                        <td style="color: #d4380d; font-weight: 600;">฿<?php echo number_format($p['price'], 2); ?></td>
                        <td style="text-align: center;">
                            <a href="#" class="btn-action btn-edit" onclick="alert('ระบบแก้ไขกำลังอยู่ระหว่างพัฒนา'); return false;">แก้ไข</a>
                            <a href="?delete=<?php echo $p['id']; ?>" class="btn-action btn-del" onclick="return confirm('⚠️ คุณแน่ใจหรือไม่ที่จะลบสินค้า : <?php echo h($p['name']); ?> ?\n(รูปภาพและข้อมูลที่เกี่ยวข้องจะถูกลบทั้งหมด)');">ลบ</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
  </div>
</body>
</html>