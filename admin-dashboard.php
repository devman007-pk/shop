<?php
// admin-dashboard.php - จัดการโปรไฟล์แอดมินและข้อมูลระบบ
session_start();
require_once __DIR__ . '/config.php';

// 1. ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // ถ้าไม่ใช่แอดมิน หรือไม่ได้ล็อกอิน ให้ส่งกลับไปหน้าล็อกอินแอดมินทันที
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$adminId = $_SESSION['user_id'];
$msg = "";

// --- 2. จัดการการอัปเดตข้อมูล ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ก) อัปเดตโปรไฟล์ส่วนตัว (ชื่อผู้ใช้ / รหัสผ่าน)
        if (isset($_POST['save_profile'])) {
            $newUsername = trim($_POST['new_username']);
            $currPass = $_POST['current_password'];
            $newPass = $_POST['new_password'];
            $confPass = $_POST['confirm_password'];

            // อัปเดตเฉพาะชื่อผู้ใช้
            if (!empty($newUsername)) {
                $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->execute([$newUsername, $adminId]);
                $_SESSION['user_name'] = $newUsername; // อัปเดตชื่อในเซสชันด้วย
            }

            // ถ้ามีการกรอกรหัสผ่านใหม่
            if (!empty($newPass)) {
                // เปลี่ยนจาก SELECT password_hash FROM users เป็น admins
                $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?"); // ในตาราง admins ใช้คอลัมน์ password
                $stmt->execute([$adminId]);
                $user = $stmt->fetch();

                // และตอนอัปเดตรหัสผ่านใหม่ (ประมาณบรรทัดที่ 37)
                $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");

                if (password_verify($currPass, $user['password_hash'])) {
                    if ($newPass === $confPass) {
                        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $stmt->execute([$hashed, $adminId]);
                        $msg = "<div class='alert alert-success'>เปลี่ยนรหัสผ่านสำเร็จแล้ว!</div>";
                    } else {
                        $msg = "<div class='alert alert-danger'>รหัสผ่านใหม่ไม่ตรงกัน</div>";
                    }
                } else {
                    $msg = "<div class='alert alert-danger'>รหัสผ่านปัจจุบันไม่ถูกต้อง</div>";
                }
            } else {
                $msg = "<div class='alert alert-success'>บันทึกการเปลี่ยนแปลงชื่อผู้ใช้แล้ว</div>";
            }
        }

        // ข) อัปเดตข้อมูลบริษัท (จากส่วนเดิม)
        if (isset($_POST['save_company'])) {
            $stmt = $pdo->prepare("UPDATE company_info SET name=?, address=?, tax_id=?, email=?, phone=? WHERE id=1");
            $stmt->execute([$_POST['c_name'], $_POST['c_addr'], $_POST['c_tax'], $_POST['c_email'], $_POST['c_phone']]);
            $msg = "<div class='alert alert-success'>อัปเดตข้อมูลบริษัทสำเร็จ!</div>";
        }

    } catch (Exception $e) {
        $msg = "<div class='alert alert-danger'>ข้อผิดพลาด: " . $e->getMessage() . "</div>";
    }
}

// --- 3. ดึงข้อมูลปัจจุบันมาแสดงผล ---
$admin = $pdo->prepare("SELECT username FROM admins WHERE id = ?");
$admin->execute([$adminId]);
$admin = $admin->fetch();$orders = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$company = $pdo->query("SELECT * FROM company_info WHERE id = 1")->fetch();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>แผงควบคุมแอดมิน - OTM</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans Thai', sans-serif; background: #f4f7f9; margin: 0; color: #333; }
    .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
    .card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
    h2 { margin: 0 0 20px; font-size: 1.3rem; color: #0b2f4a; border-left: 4px solid #1890ff; padding-left: 12px; }
    
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; }
    .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: inherit; font-size: 1rem; }
    .form-control[readonly] { background: #f9f9f9; color: #888; cursor: not-allowed; }
    
    .btn-save { background: #1f8b50; color: #fff; border: none; padding: 14px; border-radius: 8px; width: 100%; font-size: 1rem; font-weight: 700; cursor: pointer; transition: 0.2s; }
    .btn-save:hover { background: #166d3e; }

    .badge-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 20px; }
    .badge { padding: 10px; border-radius: 8px; text-align: center; color: #fff; font-size: 0.85rem; font-weight: 600; }
    
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 600; }
    .alert-success { background: #e6f7ff; color: #1890ff; border: 1px solid #91d5ff; }
    .alert-danger { background: #fff1f0; color: #f5222d; border: 1px solid #ffa39e; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>

  <div class="container">
    <?php echo $msg; ?>

    <div class="card">
        <h2>โปรไฟล์ของฉัน</h2>
        <form method="POST">
            <div class="form-group">
                <label>ชื่อผู้ใช้ (ปัจจุบัน)</label>
                <input type="text" class="form-control" value="<?php echo h($admin['username']); ?>" readonly>
            </div>
            <div class="form-group">
                <label>เปลี่ยนชื่อผู้ใช้</label>
                <input type="text" name="new_username" class="form-control" placeholder="กรอกชื่อใหม่ที่นี่ (ถ้าต้องการเปลี่ยน)">
            </div>
            <hr style="border:0; border-top:1px solid #eee; margin:25px 0;">
            <div class="form-group">
                <label>รหัสผ่านปัจจุบัน (กรอกเมื่อจะเปลี่ยนรหัสผ่าน)</label>
                <input type="password" name="current_password" class="form-control">
            </div>
            <div class="form-group">
                <label>รหัสผ่านใหม่</label>
                <input type="password" name="new_password" class="form-control">
            </div>
            <div class="form-group">
                <label>ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_password" class="form-control">
            </div>
            <button type="submit" name="save_profile" class="btn-save">บันทึกการเปลี่ยนแปลง</button>
        </form>
    </div>

   
  </div>
</body>
</html>