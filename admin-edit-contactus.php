<?php
// admin-edit-contactus.php - หน้าจัดการข้อมูล "ติดต่อเรา"
session_start();
require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // ถ้าไม่ใช่แอดมิน หรือไม่ได้ล็อกอิน ให้ส่งกลับไปหน้าล็อกอินแอดมินทันที
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

// ตรวจสอบว่าตารางมีข้อมูลแถวแรก(id=1) หรือยัง
$checkStmt = $pdo->query("SELECT COUNT(*) FROM contact_us WHERE id = 1");
if ($checkStmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO contact_us (id, company_name) VALUES (1, 'กรุณาใส่ข้อมูล')");
}

// เมื่อกดปุ่มบันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact'])) {
    
    // รับค่าจากฟอร์ม
    $company_name = trim($_POST['company_name']);
    $address = trim($_POST['address']);
    $tax_id = trim($_POST['tax_id']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    $qr_code_url = '';

    // จัดการอัปโหลดรูปภาพ QR Code
    if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/contact/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

        $file_info = pathinfo($_FILES['qr_image']['name']);
        $ext = strtolower($file_info['extension']);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed_ext)) {
            $new_filename = 'qrcode_' . time() . '.' . $ext;
            $target_file = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['qr_image']['tmp_name'], $target_file)) {
                $qr_code_url = $target_file;
                
                // ลบรูปเก่า (ถ้ามี)
                $oldImgStmt = $pdo->query("SELECT qr_code_url FROM contact_us WHERE id = 1");
                $oldImg = $oldImgStmt->fetchColumn();
                if ($oldImg && file_exists($oldImg)) { unlink($oldImg); }
            } else {
                $error_msg = "อัปโหลด QR Code ไม่สำเร็จ";
            }
        } else {
            $error_msg = "รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WEBP)";
        }
    }

    // อัปเดตข้อมูลลง Database
    if (empty($error_msg)) {
        try {
            $sql = "UPDATE contact_us SET company_name=?, address=?, tax_id=?, email=?, phone=?";
            $params = [$company_name, $address, $tax_id, $email, $phone];

            if ($qr_code_url !== '') {
                $sql .= ", qr_code_url=?";
                $params[] = $qr_code_url;
            }
            
            $sql .= " WHERE id=1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $success_msg = "บันทึกข้อมูลหน้าติดต่อเราเรียบร้อยแล้ว!";
        } catch (Exception $e) {
            $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ดึงข้อมูลปัจจุบันมาแสดงในฟอร์ม
$contact = $pdo->query("SELECT * FROM contact_us WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการหน้าติดต่อเรา - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --primary-hover: #0958d9; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; padding-bottom: 50px; }
    .container { max-width: 900px; margin: 30px auto; padding: 0 15px; }
    
    .page-title { color: var(--navy); display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 1.5rem; font-weight: 700; }
    
    .section-card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 20px; border: 1px solid #e8e8e8; }
    
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    
    .form-group { margin-bottom: 15px; }
    label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; font-size: 0.95rem; }
    input[type="text"], input[type="file"], textarea { width: 100%; padding: 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 1rem; font-family: inherit; box-sizing: border-box; }
    input[type="text"]:focus, textarea:focus { outline: none; border-color: var(--primary); }
    textarea { resize: vertical; min-height: 80px; }
    
    .current-img { margin-top: 10px; padding: 15px; border: 1px dashed #ccc; border-radius: 6px; display: inline-block; background: #fafafa; text-align: center; }
    .current-img img { max-height: 150px; object-fit: contain; }
    
    .footer-actions { position: sticky; bottom: 20px; background: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 -4px 15px rgba(0,0,0,0.1); display: flex; justify-content: flex-end; border: 1px solid #e8e8e8; z-index: 100; }
    .btn-save { background: var(--primary); color: white; border: none; padding: 12px 30px; border-radius: 6px; font-size: 1.05rem; font-weight: 700; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 8px; }
    .btn-save:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(24, 144, 255, 0.3); }

    .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
    .alert-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }
  </style>
</head>
<body>

  <?php include __DIR__ . '/admin-navbar.php'; ?>

  <main class="container">
    <h1 class="page-title">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
        แก้ไขข้อมูลหน้า "ติดต่อเรา"
    </h1>

    <?php if($success_msg): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <?php echo h($success_msg); ?>
        </div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            <?php echo h($error_msg); ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="update_contact" value="1">

        <div class="section-card">
            <div class="form-group">
                <label>ชื่อบริษัท / สำนักงาน</label>
                <input type="text" name="company_name" value="<?php echo h($contact['company_name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>ที่อยู่</label>
                <textarea name="address"><?php echo h($contact['address'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>เลขประจำตัวผู้เสียภาษี</label>
                <input type="text" name="tax_id" value="<?php echo h($contact['tax_id'] ?? ''); ?>">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>อีเมล (Email)</label>
                    <input type="text" name="email" value="<?php echo h($contact['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" value="<?php echo h($contact['phone'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 15px; border-top: 1px solid #f0f0f0; padding-top: 15px;">
                <label>อัปโหลดรูป QR Code (เช่น LINE)</label>
                <input type="file" name="qr_image" accept="image/*">
                <?php if(!empty($contact['qr_code_url']) && file_exists($contact['qr_code_url'])): ?>
                    <div class="current-img">
                        <span style="display:block; font-size:0.85rem; color:#666; margin-bottom:10px;">QR Code ปัจจุบัน:</span>
                        <img src="<?php echo h($contact['qr_code_url']); ?>" alt="Current QR">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer-actions">
            <button type="submit" class="btn-save">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                บันทึกการเปลี่ยนแปลงทั้งหมด
            </button>
        </div>
    </form>
  </main>

</body>
</html>