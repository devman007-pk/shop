<?php
session_start();
require_once __DIR__ . '/config.php';
if (!isset($_SESSION['user_name'])) { header("Location: login.php"); exit; }

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!password_verify($old_pass, $user['password_hash'])) {
        $errors[] = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
    } elseif ($new_pass !== $confirm_pass) {
        $errors[] = "รหัสผ่านใหม่ไม่ตรงกัน";
    } elseif (strlen($new_pass) < 6) {
        $errors[] = "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        if ($update->execute([$new_hash, $_SESSION['user_id']])) {
            $success = "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว";
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" /><title>เปลี่ยนรหัสผ่าน</title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    .input-row { position: relative; margin-bottom: 20px; }
    /* ปรับปรุงสไตล์ไอคอนรูปตาให้สวยขึ้น */
    .toggle-eye { position: absolute; right: 15px; top: 35px; cursor: pointer; color: #bfbfbf; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; transition: color 0.2s; }
    .toggle-eye:hover { color: var(--navy); }
    /* เว้นที่ด้านขวาไม่ให้ตัวหนังสือทับไอคอน */
    input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; padding-right: 45px; box-sizing: border-box; }
  </style>
  <script>
    function togglePass(id) {
        const x = document.getElementById(id);
        x.type = x.type === "password" ? "text" : "password";
    }
  </script>
</head>
<body class="about-page">
  <?php include __DIR__ . '/navbar.php'; ?>
  <main class="container" style="padding: 48px 0; min-height: 60vh;">
    <div style="max-width: 500px; margin: 0 auto; background:#fff; padding:40px; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.05);">
      <h2 style="font-weight:900; text-align:center; margin-top:0;">เปลี่ยนรหัสผ่าน</h2>
      
      <?php if(!empty($errors)) echo "<div style='color:#ff4d4f; margin-bottom:20px; background: #fff1f0; padding: 12px; border-radius: 8px;'>".implode("<br>", $errors)."</div>"; ?>
      <?php if($success) echo "<div style='color:#127a3b; margin-bottom:20px; background: #f6fff6; padding: 12px; border-radius: 8px;'>$success</div>"; ?>

      <form method="post">
        <div class="input-row">
          <label>รหัสผ่านปัจจุบัน</label>
          <input type="password" name="old_password" id="old_p" required>
          <span class="toggle-eye" onclick="togglePass('old_p')" title="แสดง/ซ่อนรหัสผ่าน">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </span>
        </div>
        <div class="input-row">
          <label>รหัสผ่านใหม่</label>
          <input type="password" name="new_password" id="new_p" required>
          <span class="toggle-eye" onclick="togglePass('new_p')" title="แสดง/ซ่อนรหัสผ่าน">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </span>
        </div>
        <div class="input-row">
          <label>ยืนยันรหัสผ่านใหม่</label>
          <input type="password" name="confirm_password" id="conf_p" required>
          <span class="toggle-eye" onclick="togglePass('conf_p')" title="แสดง/ซ่อนรหัสผ่าน">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          </span>
        </div>
        <button type="submit" class="btn-primary" style="width:100%; padding:14px; border-radius:10px; border:0; background:var(--navy); color:#fff; font-weight:800; cursor:pointer;">อัปเดตรหัสผ่าน</button>
      </form>
    </div>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  <script src="script.js"></script>
</body>
</html>