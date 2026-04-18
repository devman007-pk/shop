<?php
// register.php - เพิ่มการบันทึก Username ลงฐานข้อมูล พร้อม CSS ฉบับเต็ม
session_start();
require_once __DIR__ . '/config.php';
$pdo = null;
if (function_exists('getPDO')) { try { $pdo = getPDO(); } catch (Throwable $e) {} }

if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf_token = $_SESSION['csrf_token'];

$errors = [];
$success = null;

if (isset($_SESSION['user_name'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo) {
        $errors[] = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');

        if ($username === '' || $email === '' || $password === '') {
            $errors[] = 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง';
        } elseif ($password !== $confirm) {
            $errors[] = 'รหัสผ่านไม่ตรงกัน';
        } elseif (mb_strlen($password) < 6) {
            $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
        } else {
            // เช็ค Username หรือ Email ซ้ำ
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้งานแล้ว';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                // บันทึก username ลงในคอลัมน์ใหม่ที่เพิ่งเพิ่ม
                $sql = "INSERT INTO users (uuid, username, email, password_hash, role, status) VALUES (UUID(), ?, ?, ?, 'customer', 'active')";
                $insertStmt = $pdo->prepare($sql);
                
                if ($insertStmt->execute([$username, $email, $hash])) {
                    $_SESSION['user_name'] = $username;
                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $success = 'ลงทะเบียนสำเร็จ! กำลังเข้าสู่ระบบ...';
                    echo "<script>setTimeout(() => { window.location.replace('index.php'); }, 1500);</script>";
                } else {
                    $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
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
  <title>ลงทะเบียน - ร้านค้าออนไลน์</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* พื้นหลังของหน้า */
    body { background-color: #f9fbff; }
    
    .auth-page { 
      padding: 60px 20px; 
      min-height: 80vh; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
    }
    
    .auth-card { 
      width: 100%; 
      max-width: 440px; 
      background: #ffffff; 
      border-radius: 16px; 
      padding: 40px 32px; 
      box-shadow: 0 12px 40px rgba(9,30,45,0.08); 
      border: 1px solid rgba(11,47,74,0.05); 
    }
    
    .auth-card h1 { margin: 0 0 8px 0; font-size: 1.6rem; font-weight: 800; color: var(--navy); text-align: center; }
    .auth-card .subtitle { text-align: center; color: var(--muted); font-size: 0.95rem; margin-bottom: 28px; }
    .auth-card label { display: block; margin-top: 16px; margin-bottom: 8px; font-weight: 700; font-size: 0.95rem; color: var(--navy); }
    
    .auth-card input { 
      width: 100%; 
      padding: 14px 16px; 
      font-size: 1rem; 
      border-radius: 10px; 
      border: 1px solid rgba(11,47,74,0.15); 
      box-sizing: border-box; 
      transition: all 0.3s ease; 
      background: #fcfcfd; 
      font-family: inherit; 
    }
    .auth-card input:focus { outline: none; border-color: var(--blue); background: #ffffff; box-shadow: 0 0 0 4px rgba(30,144,255,0.1); }
    
    .btn-register { 
      display: block; width: 100%; background: linear-gradient(135deg, var(--blue), var(--teal)); 
      color: #fff; border: 0; padding: 14px; border-radius: 10px; cursor: pointer; 
      font-weight: 800; font-size: 1.05rem; margin-top: 28px; 
      transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 8px 24px rgba(30,144,255,0.25); 
    }
    .btn-register:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(30,144,255,0.35); }
    
    .error { background: #fff7f7; border: 1px solid rgba(255,77,79,0.3); color: #ff4d4f; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 600; text-align: center; }
    .success { background: #f6fff6; border: 1px solid rgba(43,182,115,0.2); color: #127a3b; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 700; text-align: center; }
    
    /* ตรงนี้แหละครับ! สไตล์ของลิงก์ที่ทำให้สวยขึ้น */
    .links { margin-top: 32px; font-size: 0.95rem; text-align: center; color: var(--muted); font-weight: 500; }
    .links a { color: var(--blue); text-decoration: none; font-weight: 700; transition: color 0.2s; }
    .links a:hover { color: var(--navy); text-decoration: underline; }
    
    #matchInfo { font-size: 0.85rem; margin-top: 6px; text-align: right; height: 16px; }
  </style>
  <script>
    function checkMatch() {
      const p = document.getElementById('password').value;
      const c = document.getElementById('confirm').value;
      const info = document.getElementById('matchInfo');
      if (!c) { info.textContent = ''; return; }
      if (p === c) { info.textContent = '✓ รหัสผ่านตรงกัน'; info.style.color = '#127a3b'; } 
      else { info.textContent = '✗ รหัสผ่านไม่ตรงกัน'; info.style.color = '#ff4d4f'; }
    }
  </script>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container auth-page">
    <div class="auth-card">
      <h1>สร้างบัญชีใหม่</h1>

      <?php if (!empty($errors)): ?>
        <div class="error"><?php echo implode('<br>', $errors); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="post" action="register.php" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        
        <label for="username">ชื่อผู้ใช้ (Username)</label>
        <input id="username" name="username" type="text" placeholder="ตั้งชื่อผู้ใช้อย่างน้อย 3 ตัวอักษร" required autofocus>

        <label for="email">อีเมล (Email)</label>
        <input id="email" name="email" type="email" placeholder="example@mail.com" required>

        <label for="password">รหัสผ่าน (Password)</label>
        <input id="password" name="password" type="password" placeholder="ตั้งรหัสผ่านอย่างน้อย 6 ตัวอักษร" required oninput="checkMatch()">

        <label for="confirm">ยืนยันรหัสผ่าน (Confirm Password)</label>
        <input id="confirm" name="confirm" type="password" placeholder="กรอกรหัสผ่านอีกครั้ง" required oninput="checkMatch()">
        <div id="matchInfo"></div>

        <button class="btn-register" type="submit">ลงทะเบียนบัญชีใหม่</button>
      </form>

      <div class="links">
        มีบัญชีผู้ใช้งานอยู่แล้ว? <a href="login.php">เข้าสู่ระบบที่นี่</a>
      </div>
    </div>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
</body>
</html>