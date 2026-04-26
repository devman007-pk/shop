<?php
// admin-user-add.php - หน้าเพิ่มบัญชีผู้ดูแลระบบ (Admin)
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (empty($username) || empty($password)) {
        $error_msg = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } elseif ($password !== $confirm) {
        $error_msg = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน";
    } else {
        try {
            // เช็คว่ามี username นี้แล้วหรือยัง
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
            $stmtCheck->execute([$username]);
            if ($stmtCheck->fetchColumn() > 0) {
                $error_msg = "ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น";
            } else {
                // เข้ารหัสผ่านเพื่อความปลอดภัย
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                $stmt->execute([$username, $hashed_password]);
                $success_msg = "เพิ่มบัญชีผู้ดูแลระบบ '{$username}' สำเร็จ!";
            }
        } catch (Exception $e) {
            $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>เพิ่มผู้ใช้ (Admin) - Admin Panel</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans Thai', sans-serif; background: #f0f2f5; margin: 0; padding-bottom: 50px; }
    .container { max-width: 600px; margin: 40px auto; padding: 0 15px; }
    .card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .card-title { margin-top: 0; color: #0b2f4a; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .form-group { margin-bottom: 20px; }
    label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
    input[type="text"], input[type="password"] { width: 100%; padding: 12px; border: 1px solid #d9d9d9; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
    input:focus { outline: none; border-color: #1890ff; }
    .btn-submit { background: #1890ff; color: #fff; border: none; padding: 12px; border-radius: 6px; font-weight: 600; font-size: 1rem; width: 100%; cursor: pointer; transition: 0.2s; }
    .btn-submit:hover { background: #0958d9; }
    .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
    .alert-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  <div class="container">
    <div class="card">
        <h2 class="card-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
            เพิ่มบัญชีผู้ดูแลระบบ (Admin)
        </h2>
        <?php if($success_msg): ?><div class="alert alert-success"><?php echo h($success_msg); ?></div><?php endif; ?>
        <?php if($error_msg): ?><div class="alert alert-error"><?php echo h($error_msg); ?></div><?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label>ชื่อผู้ใช้ (Username)</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>รหัสผ่าน (Password)</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>ยืนยันรหัสผ่าน (Confirm Password)</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn-submit">เพิ่มผู้ดูแลระบบ</button>
        </form>
    </div>
  </div>
</body>
</html>