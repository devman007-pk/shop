<?php
// login.php - simple login page storing users in users.json (demo use only)
session_start();

$USERS_FILE = __DIR__ . '/users.json';

// helper: read users (assoc array username -> ['id'=>..., 'username'=>..., 'password'=>hash])
function read_users($file) {
    if (!file_exists($file)) return [];
    $json = @file_get_contents($file);
    if (!$json) return [];
    $arr = json_decode($json, true);
    if (!is_array($arr)) return [];
    return $arr;
}
function write_users($file, $users) {
    @file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// CSRF token helpers
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

$errors = [];
$success = null;

// If already logged in, redirect to index
if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $post_token)) {
        $errors[] = 'Invalid request (CSRF).';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $errors[] = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
        } else {
            $users = read_users($USERS_FILE);
            if (!isset($users[$username])) {
                $errors[] = 'ไม่พบชื่อผู้ใช้นี้';
            } else {
                $user = $users[$username];
                if (password_verify($password, $user['password'])) {
                    // login success
                    session_regenerate_id(true);
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['user_id'] = $user['id'];
                    $success = 'เข้าสู่ระบบสำเร็จ กำลังเปลี่ยนหน้า...';
                    // redirect to index or intended page
                    header('Location: index.php');
                    exit;
                } else {
                    $errors[] = 'รหัสผ่านไม่ถูกต้อง';
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
    .auth-page { padding:48px 0; min-height:70vh; }
    .auth-card { max-width:420px; margin:28px auto; background:#fff; border-radius:12px; padding:20px; box-shadow:0 10px 30px rgba(9,30,45,0.06); border:1px solid rgba(11,47,74,0.04); }
    .auth-card h1{ margin:0 0 12px; font-size:1.25rem; color:var(--navy); }
    .auth-card label{ display:block; margin-top:12px; font-weight:700; color:var(--navy); }
    .auth-card input[type="text"], .auth-card input[type="password"]{ width:100%; padding:10px 12px; margin-top:6px; border-radius:8px; border:1px solid rgba(11,47,74,0.08); box-sizing:border-box; }
    
    /* แก้ไขให้ปุ่มชิดขวา */
    .btn-primary { display:block; margin-left:auto; margin-right:0; background:linear-gradient(90deg,var(--blue),var(--teal)); color:#fff; border:0; padding:10px 14px; border-radius:10px; cursor:pointer; font-weight:800; margin-top:14px; }
    
    .muted { color: rgba(11,47,74,0.6); font-size:0.95rem; }
    .error { background:#fff7f7; border:1px solid rgba(176,0,32,0.08); color:#a20000; padding:10px; border-radius:8px; margin-bottom:10px; }
    .success { background:#f6fff6; border:1px solid rgba(43,182,115,0.12); color:#127a3b; padding:10px; border-radius:8px; margin-bottom:10px; }
    
    /* แก้ไขให้ลิงก์อยู่ตรงกลาง */
    .links { margin-top:24px; font-size:0.95rem; text-align:center; }
    
    .links a { color:var(--blue); text-decoration:underline; }
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container auth-page">
    <div class="auth-card">
      <h1>เข้าสู่ระบบ</h1>

      <?php if (!empty($errors)): ?>
        <div class="error"><?php echo htmlspecialchars(implode('<br>', $errors)); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <label for="username">ชื่อผู้ใช้</label>
        <input id="username" name="username" type="text" required autofocus>

        <label for="password">รหัสผ่าน</label>
        <input id="password" name="password" type="password" required>

        <button class="btn-primary" type="submit">เข้าสู่ระบบ</button>
      </form>

      <div class="links">
        ยังไม่มีบัญชี? <a href="register.php">ลงทะเบียน</a>
      </div>
    </div>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
</body>
</html>