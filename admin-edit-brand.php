<?php
// admin-edit-brand.php - หน้าจัดการลบและแสดงรายการแบรนด์
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php");
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

// จัดการลบแบรนด์ (พร้อมลบไฟล์รูปภาพออกจาก Server)
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        
        // 1. ดึงชื่อไฟล์รูปภาพขึ้นมาก่อนเพื่อจะลบไฟล์จริง
        $stmtImg = $pdo->prepare("SELECT image_url FROM brands WHERE id = ?");
        $stmtImg->execute([$id]);
        $brand = $stmtImg->fetch(PDO::FETCH_ASSOC);
        
        // 2. ลบข้อมูลจาก Database
        $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
        $stmt->execute([$id]);

        // 3. ลบไฟล์ออกจากโฟลเดอร์ (ถ้ามี)
        if ($brand && !empty($brand['image_url']) && file_exists($brand['image_url'])) {
            unlink($brand['image_url']);
        }

        $success_msg = "ลบแบรนด์เรียบร้อยแล้ว!";
    } catch (Exception $e) {
        $error_msg = "ไม่สามารถลบได้: " . $e->getMessage();
    }
}

// ดึงรายการแบรนด์ทั้งหมดมาแสดง
try {
    $brands = $pdo->query("SELECT * FROM brands ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $brands = [];
    $error_msg = "ไม่พบตาราง brands: " . $e->getMessage();
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการแบรนด์ - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; padding-bottom: 40px; }
    .container { max-width: 900px; margin: 40px auto; padding: 0 15px; }
    .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .card-title { font-size: 1.4rem; font-weight: 700; color: var(--navy); margin: 0; display: flex; align-items: center; gap: 10px; }
    
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 12px; border-bottom: 1px solid #f0f0f0; text-align: left; vertical-align: middle; }
    .table th { background: #fafafa; font-weight: 700; color: #333; }
    .btn-add { background: var(--primary); color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
    .btn-add:hover { background: #0958d9; }
    .btn-del { background: #ff4d4f; color: #fff; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; }
    .btn-del:hover { background: #d9363e; }
    
    .brand-img { width: 60px; height: 60px; object-fit: contain; background: #f9f9f9; border: 1px solid #eee; border-radius: 4px; padding: 4px; }
    
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
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                รายการแบรนด์ทั้งหมด
            </h2>
            <a href="admin-brand.php" class="btn-add">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                เพิ่มแบรนด์
            </a>
        </div>
        
        <?php if($success_msg): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?php echo h($success_msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if($error_msg): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                <?php echo h($error_msg); ?>
            </div>
        <?php endif; ?>

        <table class="table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th style="width: 90px; text-align: center;">รูปภาพ</th>
                    <th>ชื่อแบรนด์</th>
                    <th style="width: 120px; text-align: center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($brands)): ?>
                    <tr><td colspan="4" style="text-align:center; color:#999; padding:30px;">ไม่มีข้อมูลแบรนด์ในระบบ</td></tr>
                <?php else: ?>
                    <?php foreach($brands as $b): ?>
                    <tr>
                        <td style="color:#888;">#<?php echo h($b['id']); ?></td>
                        <td style="text-align: center;">
                            <?php if(!empty($b['image_url'])): ?>
                                <img src="<?php echo h($b['image_url']); ?>" class="brand-img" alt="logo">
                            <?php else: ?>
                                <div class="brand-img" style="display:flex; align-items:center; justify-content:center; color:#ccc; font-size:0.8rem;">No Img</div>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 700; color: var(--navy);"><?php echo h($b['name']); ?></td>
                        <td style="text-align: center;">
                            <a href="?delete=<?php echo $b['id']; ?>" class="btn-del" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบแบรนด์ : <?php echo h($b['name']); ?> ?');">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                ลบ
                            </a>
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