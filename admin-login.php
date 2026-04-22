<?php
// admin-login.php - หน้าล็อกอินแอดมิน (เปลี่ยนอิโมจิเป็น SVG)
session_start();
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin') {
    header("Location: admin-panel.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        if (password_verify($password, $admin['password']) || $password === $admin['password']) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['user_name'] = $admin['username']; 
            $_SESSION['user_role'] = 'admin';
            header("Location: admin-panel.php");
            exit;
        } else {
            $error = 'รหัสผ่านไม่ถูกต้อง';
        }
    } else {
        $error = 'ไม่พบชื่อผู้ใช้นี้ในระบบผู้ดูแล';
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>เข้าสู่ระบบแอดมิน</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans Thai', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
    .login-box { background: #fff; padding: 35px; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); width: 100%; max-width: 380px; }
    .login-box h2 { margin-top: 0; display: flex; align-items: center; justify-content: center; gap: 8px; color: #0b2f4a; margin-bottom: 25px; }
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #333; }
    .form-group input { width: 100%; padding: 12px; border: 1px solid #d9d9d9; border-radius: 6px; box-sizing: border-box; font-size: 1rem; }
    .form-group input:focus { outline: none; border-color: #1890ff; }
    .btn-login { width: 100%; padding: 12px; background: #1890ff; color: #fff; border: none; border-radius: 6px; font-size: 1.05rem; cursor: pointer; font-weight: 600; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-login:hover { background: #0958d9; transform: translateY(-2px); }
    .error { color: #d9363e; text-align: center; margin-bottom: 20px; font-size: 0.95rem; background: #fff1f0; padding: 12px; border-radius: 6px; border: 1px solid #ffccc7;}
  </style>
</head>
<body>
  <div class="login-box">
    <h2>
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        เข้าสู่ระบบผู้ดูแล
    </h2>
    <?php if($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label>ชื่อผู้ใช้ (Username)</label>
        <input type="text" name="username" placeholder="เช่น admin" required autofocus>
      </div>
      <div class="form-group">
        <label>รหัสผ่าน (Password)</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
    </form>
  </div>
</body>
</html>