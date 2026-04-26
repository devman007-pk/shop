<?php
// admin-member-manage.php - หน้าจัดการบัญชีลูกค้า (เพิ่ม Username และ Password)
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
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([(int)$_GET['delete']]);
    $success_msg = "ลบบัญชีลูกค้าสำเร็จ!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user_id'])) {
    $edit_id = (int)$_POST['edit_user_id'];
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // รหัสผ่านใหม่
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    try {
        if (!empty($password)) {
            // ถ้ามีการพิมพ์รหัสผ่านใหม่ ให้เข้ารหัสและอัปเดตด้วย
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, name=?, email=?, phone=?, address=? WHERE id=?");
            $stmt->execute([$username, $hashed, $name, $email, $phone, $address, $edit_id]);
        } else {
            // ถ้าไม่ได้พิมพ์รหัสผ่านใหม่ ก็อัปเดตแค่ข้อมูลอื่นๆ
            $stmt = $pdo->prepare("UPDATE users SET username=?, name=?, email=?, phone=?, address=? WHERE id=?");
            $stmt->execute([$username, $name, $email, $phone, $address, $edit_id]);
        }
        $success_msg = "อัปเดตข้อมูลลูกค้าสำเร็จ!";
    } catch (Exception $e) {
        $error_msg = "เกิดข้อผิดพลาด: ชื่อผู้ใช้อาจซ้ำกันกับคนอื่นในระบบ";
    }
}

$search_query = trim($_GET['q'] ?? '');
if ($search_query !== '') {
    $searchTerm = "%$search_query%";
    // เพิ่มการค้นหาจาก username ด้วย
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username LIKE ? OR name LIKE ? OR email LIKE ? OR phone LIKE ? OR address LIKE ? ORDER BY id DESC");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $users = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$edit_data = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการบัญชีลูกค้า - Admin Panel</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans Thai', sans-serif; background: #f0f2f5; margin: 0; padding-bottom: 50px; }
    .container { max-width: 1200px; margin: 30px auto; padding: 0 15px; }
    .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
    .search-box { display: flex; gap: 10px; margin-bottom: 20px; }
    .search-box input { flex: 1; padding: 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 1rem; }
    .search-box button { background: #1890ff; color: #fff; border: none; padding: 0 25px; border-radius: 6px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 6px; }
    .table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
    .table th, .table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; }
    .table th { background: #fafafa; font-weight: 600; color: #333; }
    .btn-action { padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-block; margin-right: 5px; margin-bottom: 5px;}
    .btn-edit { background: #f6ffed; color: #389e0d; border: 1px solid #b7eb8f; }
    .btn-del { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }
    .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; display: flex; align-items: center; gap: 8px; }
    .alert-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 10px; }
    .edit-form label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9rem;}
    .edit-form input, .edit-form textarea { width: 100%; padding: 10px; border: 1px solid #d9d9d9; border-radius: 4px; box-sizing: border-box;}
    .btn-save { background: #1890ff; color: #fff; border: none; padding: 10px 25px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; }
    .contact-info { display: flex; align-items: center; gap: 6px; }
    .contact-info svg { color: #888; flex-shrink: 0; }
    .user-badge { display: inline-block; background: #e6f4ff; color: #1677ff; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; border: 1px solid #91caff; margin-top: 4px; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  <div class="container">
    
    <?php if($success_msg): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <?php echo h($success_msg); ?>
        </div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            <?php echo h($error_msg); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin-top:0; display:flex; align-items:center; gap:8px; color:#0b2f4a;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            ค้นหาบัญชีลูกค้า
        </h3>
        <form method="get" action="admin-member-manage.php" class="search-box">
            <input type="text" name="q" value="<?php echo h($search_query); ?>" placeholder="พิมพ์ Username, ชื่อ, อีเมล, เบอร์โทร หรือ ที่อยู่ เพื่อค้นหา...">
            <button type="submit">ค้นหา</button>
            <?php if($search_query): ?>
                <a href="admin-member-manage.php" style="padding:12px 20px; background:#f5f5f5; border:1px solid #ddd; border-radius:6px; text-decoration:none; color:#333; font-weight:bold;">ล้าง</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if($edit_data): ?>
    <div class="card edit-form" style="border-left: 4px solid #389e0d;">
        <h3 style="margin-top:0; display:flex; align-items:center; gap:8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            แก้ไขข้อมูลลูกค้า: ลำดับที่ #<?php echo h($edit_data['id']); ?>
        </h3>
        <form method="post" action="admin-member-manage.php">
            <input type="hidden" name="edit_user_id" value="<?php echo h($edit_data['id']); ?>">
            
            <div style="background:#fafafa; padding:15px; border-radius:6px; border:1px solid #eee; margin-bottom:15px;">
                <h4 style="margin: 0 0 10px 0; color: #1890ff;">ข้อมูลการเข้าสู่ระบบ</h4>
                <div class="grid-2">
                    <div>
                        <label>ชื่อผู้ใช้ (Username)</label>
                        <input type="text" name="username" value="<?php echo h($edit_data['username'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label>รหัสผ่านใหม่ (เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</label>
                        <input type="password" name="password" placeholder="ตั้งรหัสผ่านใหม่...">
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div>
                    <label>ชื่อ-นามสกุล</label>
                    <input type="text" name="name" value="<?php echo h($edit_data['name'] ?? ''); ?>">
                </div>
                <div>
                    <label>เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" value="<?php echo h($edit_data['phone'] ?? ''); ?>">
                </div>
            </div>
            <div class="grid-2">
                <div>
                    <label>อีเมล (Email)</label>
                    <input type="text" name="email" value="<?php echo h($edit_data['email'] ?? ''); ?>">
                </div>
            </div>
            <div style="margin-bottom:15px;">
                <label>ที่อยู่จัดส่ง</label>
                <textarea name="address" rows="3"><?php echo h($edit_data['address'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="btn-save">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                บันทึกการแก้ไข
            </button>
            <a href="admin-member-manage.php" style="margin-left: 10px; color: #666; text-decoration: none;">ยกเลิก</a>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin-top:0; display:flex; align-items:center; gap:10px; color:#0b2f4a;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            รายชื่อลูกค้าในระบบ
        </h2>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th style="width: 250px;">ข้อมูลผู้ใช้</th>
                    <th style="width: 200px;">การติดต่อ</th>
                    <th>ที่อยู่</th>
                    <th style="width: 120px; text-align:center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($users)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">ไม่พบข้อมูลลูกค้า</td></tr>
                <?php else: ?>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td style="color:#888;">#<?php echo h($u['id']); ?></td>
                        <td>
                            <div style="font-weight:600; color:#0b2f4a;"><?php echo h($u['name'] ?? 'ไม่มีชื่อ'); ?></div>
                            <div class="user-badge">@<?php echo h($u['username'] ?? 'ไม่มีชื่อผู้ใช้'); ?></div>
                        </td>
                        <td>
                            <div class="contact-info">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                <?php echo h($u['phone'] ?? '-'); ?>
                            </div>
                            <div class="contact-info" style="color:#666; font-size:0.85rem; margin-top:4px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                <?php echo h($u['email'] ?? '-'); ?>
                            </div>
                        </td>
                        <td style="color:#555;"><?php echo h($u['address'] ?? '-'); ?></td>
                        <td style="text-align:center;">
                            <a href="?edit=<?php echo $u['id']; ?>" class="btn-action btn-edit">แก้ไข</a>
                            <a href="?delete=<?php echo $u['id']; ?>" class="btn-action btn-del" onclick="return confirm('ยืนยันการลบลูกค้า: @<?php echo h($u['username'] ?? ''); ?> ?');">ลบ</a>
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