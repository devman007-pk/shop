<?php
// logout.php - simple logout page (clears session and redirects)
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = [];

// If session cookie exists, remove it
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );
}

// Destroy the session
session_destroy();

// Optional: show a confirmation then redirect to homepage
$redirectTo = 'index.php';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ออกจากระบบ — กำลังไปยังหน้าหลัก</title>
  <meta http-equiv="refresh" content="1.5;url=<?php echo htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8'); ?>" />
  <style>
    body{font-family:system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Thai", Arial; background:#f6fbfc; color:#0b2f4a; display:flex; align-items:center; justify-content:center; min-height:60vh; margin:0;}
    .box{background:#fff;padding:24px;border-radius:12px;box-shadow:0 12px 36px rgba(9,30,45,0.06);text-align:center;}
    a{color:#1e90ff;text-decoration:none;font-weight:700}
  </style>
</head>
<body>
  <div class="box" role="status" aria-live="polite">
    <p style="font-weight:800;margin:0 0 8px;">ออกจากระบบเรียบร้อยแล้ว</p>
    <p style="margin:0 0 12px;color:#66797f;">กำลังพาคุณไปยังหน้าหลัก…</p>
    <p><a href="<?php echo htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8'); ?>">หากไม่ไปอัตโนมัติ ให้คลิกที่นี่</a></p>
  </div>
</body>
</html>