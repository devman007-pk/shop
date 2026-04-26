<?php
// admin-manage.php - หน้าจัดการบัญชีแอดมิน (เปลี่ยนอิโมจิเป็น SVG)
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // ถ้าไม่ใช่แอดมิน หรือไม่ได้ล็อกอิน ให้ส่งกลับไปหน้าล็อกอินแอดมินทันที
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

if (isset($_GET['delete'])) {
    $id_to_delete = (int)$_GET['delete'];
    if ($id_to_delete === $_SESSION['user_id']) {
        $error_msg = "ไม่อนุญาตให้ลบบัญชีของตัวคุณเองที่กำลังใช้งานอยู่";
    } else {
        $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$id_to_delete]);
        $success_msg = "ลบบัญชีผู้ดูแลระบบสำเร็จ!";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin_id'])) {
    $edit_id = (int)$_POST['edit_admin_id'];
    $new_username = trim($_POST['username']);
    $new_password = $_POST['password'];

    try {
        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET username = ?, password = ? WHERE id = ?");
            $stmt->execute([$new_username, $hashed, $edit_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE admins SET username = ? WHERE id = ?");
            $stmt->execute([$new_username, $edit_id]);
        }
        $success_msg = "อัปเดตข้อมูลบัญชีสำเร็จ!";
    } catch (Exception $e) {
        $error_msg = "เกิดข้อผิดพลาด: ชื่อผู้ใช้อาจซ้ำกัน";
    }
}

$admins = $pdo->query("SELECT id, username FROM admins ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT id, username FROM admins WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการผู้ดูแล - Admin Panel</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans Thai', sans-serif; background: #f0f2f5; margin: 0; padding-bottom: 50px; }
    .container { max-width: 900px; margin: 30px auto; padding: 0 15px; }
    .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
    .table th { background: #fafafa; }
    .btn-action { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-block; margin-right: 5px; }
    .btn-edit { background: #f6ffed; color: #389e0d; border: 1px solid #b7eb8f; }
    .btn-del { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }
    .role-badge { background: #e6f7ff; color: #0958d9; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; border: 1px solid #91caff; }
    .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
    .alert-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }
    .edit-form input { width: 100%; padding: 10px; border: 1px solid #d9d9d9; border-radius: 4px; margin-bottom: 10px; box-sizing: border-box; }
    .btn-save { background: #1890ff; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-weight: bold; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  <div class="container">
    <?php if($success_msg): ?><div class="alert alert-success"><?php echo h($success_msg); ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-error"><?php echo h($error_msg); ?></div><?php endif; ?>

    <?php if($edit_data): ?>
    <div class="card edit-form" style="border-left: 4px solid #1890ff;">
        <h3 style="margin-top:0; display:flex; align-items:center; gap:8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            แก้ไขบัญชีผู้ดูแล: ID #<?php echo h($edit_data['id']); ?>
        </h3>
        <form method="post" action="admin-manage.php">
            <input type="hidden" name="edit_admin_id" value="<?php echo h($edit_data['id']); ?>">
            <label style="display:block; margin-bottom:6px; font-weight:600;">ชื่อผู้ใช้ (Username)</label>
            <input type="text" name="username" value="<?php echo h($edit_data['username']); ?>" required>
            <label style="display:block; margin-bottom:6px; font-weight:600;">รหัสผ่านใหม่ (ปล่อยว่างไว้หากไม่ต้องการเปลี่ยน)</label>
            <input type="password" name="password" placeholder="ตั้งรหัสผ่านใหม่...">
            <button type="submit" class="btn-save">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                บันทึกการแก้ไข
            </button>
            <a href="admin-manage.php" style="margin-left: 10px; color: #666; text-decoration: none;">ยกเลิก</a>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin-top:0; display:flex; align-items:center; gap:10px; color:#0b2f4a;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            จัดการบัญชีผู้ดูแลระบบ (Admins)
        </h2>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Username (ชื่อผู้ใช้)</th>
                    <th style="width: 120px;">Role (สถานะ)</th>
                    <th style="width: 150px; text-align:center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($admins as $a): ?>
                <tr>
                    <td style="color:#888;">#<?php echo h($a['id']); ?></td>
                    <td style="font-weight:600;"><?php echo h($a['username']); ?></td>
                    <td><span class="role-badge">Admin</span></td>
                    <td style="text-align:center;">
                        <a href="?edit=<?php echo $a['id']; ?>" class="btn-action btn-edit">แก้ไข</a>
                        <a href="?delete=<?php echo $a['id']; ?>" class="btn-action btn-del" onclick="return confirm('ลบบัญชีแอดมินนี้?');">ลบ</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
  </div>
</body>
</html>