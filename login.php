<?php
// login.php - สำหรับลูกค้า (ปลดล็อคให้แอดมินเห็นหน้านี้ได้)
session_start();
require_once __DIR__ . '/config.php';

// เชื่อมต่อ Database
$pdo = null;
if (function_exists('getPDO')) {
    try { $pdo = getPDO(); } catch (Throwable $e) { }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

$errors = [];
$success = null;

// ==================================================
// --- ระบบป้องกันการเข้าผิดหน้า (อัปเดตใหม่) ---
// ==================================================
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'customer') {
        // ถ้า "ลูกค้า" ล็อกอินอยู่แล้ว ให้ไปหน้าแรก
        header('Location: index.php');
        exit;
    }
    // *** หมายเหตุ: ถ้าเป็น "admin" ระบบจะปล่อยผ่านให้ลงไปเห็นฟอร์มล็อกอินด้านล่างได้เลย ***
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo) {
        $errors[] = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
    } else {
        $post_token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($csrf_token, $post_token)) {
            $errors[] = 'Invalid request (CSRF).';
        } else {
            // รับค่าจากช่องเดียว (เป็นได้ทั้ง email หรือ username)
            $login_identity = trim((string)($_POST['login_identity'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($login_identity === '' || $password === '') {
                $errors[] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
            } else {
                // ค้นหาเฉพาะในตาราง users (ลูกค้า) เท่านั้น
                $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute([$login_identity, $login_identity]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password_hash'])) {
                    // ล็อกอินสำเร็จ (จะเขียนทับ Session เดิมของแอดมินทันที)
                    session_regenerate_id(true);
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = 'customer'; 
                    
                    $success = 'เข้าสู่ระบบสำเร็จ! กำลังพาท่านไปยังหน้าหลัก...';
                    echo "<script>window.location.replace('index.php');</script>";
                    exit;
                } else {
                    $errors[] = 'ชื่อผู้ใช้/อีเมล หรือรหัสผ่านไม่ถูกต้อง';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>เข้าสู่ระบบ</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    body { background-color: #f9fbff; }
    .auth-page { padding: 60px 20px; min-height: 80vh; display: flex; align-items: center; justify-content: center; }
    .auth-card { width: 100%; max-width: 400px; background: #fff; border-radius: 16px; padding: 40px 32px; box-shadow: 0 12px 40px rgba(9,30,45,0.08); border: 1px solid rgba(11,47,74,0.05); }
    .auth-card h1 { margin: 0 0 8px; font-size: 1.6rem; font-weight: 800; color: var(--navy); text-align: center; }
    .subtitle { text-align: center; color: var(--muted); font-size: 0.95rem; margin-bottom: 28px; }
    label { display: block; margin-top: 16px; margin-bottom: 8px; font-weight: 700; color: var(--navy); }
    
    .input-wrapper { position: relative; display: block; }
    .input-wrapper input { width: 100%; padding: 14px; padding-right: 48px; border-radius: 10px; border: 1px solid rgba(11,47,74,0.15); box-sizing: border-box; background: #fcfcfd; font-family: inherit; font-size: 1rem; transition: all 0.3s ease; }
    .input-wrapper input:focus { outline: none; border-color: var(--blue); background: #fff; box-shadow: 0 0 0 4px rgba(30,144,255,0.1); }
    
    .toggle-password { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--muted); padding: 0; display: flex; align-items: center; justify-content: center; transition: color 0.2s; }
    .toggle-password:hover { color: var(--navy); }
    .toggle-password svg { width: 20px; height: 20px; }

    .btn-login { width: 100%; background: linear-gradient(135deg, var(--blue), var(--teal)); color: #fff; border: 0; padding: 14px; border-radius: 10px; cursor: pointer; font-weight: 800; margin-top: 28px; box-shadow: 0 8px 24px rgba(30,144,255,0.25); font-size: 1.05rem; transition: transform 0.2s, box-shadow 0.2s; }
    .btn-login:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(30,144,255,0.35); }
    .error { background: #fff7f7; color: #ff4d4f; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; font-weight: 600; border: 1px solid rgba(255,77,79,0.2); font-size: 0.95rem; }
    .success { background: #f6fff6; border: 1px solid rgba(43,182,115,0.2); color: #127a3b; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 700; text-align: center; }
    .links { margin-top: 32px; text-align: center; color: var(--muted); font-size: 0.95rem; }
    .links a { color: var(--blue); text-decoration: none; font-weight: 700; transition: color 0.2s; }
    .links a:hover { color: var(--navy); text-decoration: underline; }
  </style>

  <script>
    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        }
    }
  </script>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>
  <main class="auth-page">
    <div class="auth-card">
      <h1>เข้าสู่ระบบ</h1>

      <?php if (!empty($errors)) echo '<div class="error">'.implode('<br>', $errors).'</div>'; ?>
      <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        
        <label for="login_identity">ชื่อผู้ใช้ หรือ อีเมล</label>
        <div class="input-wrapper">
          <input id="login_identity" name="login_identity" type="text" placeholder="Username หรือ Email" required autofocus>
        </div>

        <label for="password">รหัสผ่าน</label>
        <div class="input-wrapper">
          <input id="password" name="password" type="password" placeholder="กรอกรหัสผ่าน" required>
          <button type="button" class="toggle-password" onclick="togglePassword('password', this)" aria-label="แสดงรหัสผ่าน">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </button>
        </div>

        <button class="btn-login" type="submit">เข้าสู่ระบบ</button>
      </form>

      <div class="links">
        ยังไม่มีบัญชีใช่หรือไม่? <a href="register.php">ลงทะเบียนที่นี่</a>
      </div>
    </div>
  </main>
  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
</body>
</html>