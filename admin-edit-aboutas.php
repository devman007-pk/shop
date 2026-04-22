<?php
// admin-edit-aboutas.php - หน้าจัดการข้อมูล "เกี่ยวกับเรา"
session_start();
require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php");
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

// ตรวจสอบว่าตาราง about_us มีข้อมูลแถวแรก(id=1) หรือยัง ถ้ายังให้สร้างก่อนกัน Error
$checkStmt = $pdo->query("SELECT COUNT(*) FROM about_us WHERE id = 1");
if ($checkStmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO about_us (id, company_name) VALUES (1, 'กรุณาใส่ชื่อบริษัท')");
}

// เมื่อกดปุ่มบันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_about'])) {
    
    // รับค่าจากฟอร์ม
    $company_name = trim($_POST['company_name']);
    $company_desc = trim($_POST['company_desc']);
    $slogan = trim($_POST['slogan']);
    $founded_info = trim($_POST['founded_info']);
    $executive_name = trim($_POST['executive_name']);
    $employee_count = trim($_POST['employee_count']);
    $service_area = trim($_POST['service_area']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $portfolio_link = trim($_POST['portfolio_link']);
    
    $logo_url = '';

    // จัดการอัปโหลดรูปภาพโลโก้
    if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/about/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

        $file_info = pathinfo($_FILES['logo_image']['name']);
        $ext = strtolower($file_info['extension']);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed_ext)) {
            $new_filename = 'company_logo_' . time() . '.' . $ext;
            $target_file = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['logo_image']['tmp_name'], $target_file)) {
                $logo_url = $target_file;
                
                // ลบรูปเก่า (ถ้ามี) เพื่อไม่ให้เปลืองพื้นที่
                $oldImgStmt = $pdo->query("SELECT logo_url FROM about_us WHERE id = 1");
                $oldImg = $oldImgStmt->fetchColumn();
                if ($oldImg && file_exists($oldImg)) { unlink($oldImg); }
            } else {
                $error_msg = "อัปโหลดโลโก้ไม่สำเร็จ";
            }
        } else {
            $error_msg = "รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WEBP)";
        }
    }

    // อัปเดตข้อมูลลง Database
    if (empty($error_msg)) {
        try {
            $sql = "UPDATE about_us SET 
                    company_name=?, company_desc=?, slogan=?, founded_info=?, 
                    executive_name=?, employee_count=?, service_area=?, 
                    address=?, phone=?, email=?, portfolio_link=?";
            $params = [$company_name, $company_desc, $slogan, $founded_info, $executive_name, $employee_count, $service_area, $address, $phone, $email, $portfolio_link];

            // ถ้ามีการอัปโหลดโลโก้ใหม่ ให้เอาไปอัปเดตด้วย
            if ($logo_url !== '') {
                $sql .= ", logo_url=?";
                $params[] = $logo_url;
            }
            
            $sql .= " WHERE id=1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $success_msg = "บันทึกข้อมูลหน้าเกี่ยวกับเราเรียบร้อยแล้ว!";
        } catch (Exception $e) {
            $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// ดึงข้อมูลปัจจุบันมาแสดงในฟอร์ม
$about = $pdo->query("SELECT * FROM about_us WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการหน้าเกี่ยวกับเรา - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --primary-hover: #0958d9; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; padding-bottom: 50px; }
    .container { max-width: 1000px; margin: 30px auto; padding: 0 15px; }
    
    .page-title { color: var(--navy); display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 1.5rem; font-weight: 700; }
    
    .section-card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 20px; border: 1px solid #e8e8e8; }
    .section-header { font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; display: flex; align-items: center; gap: 8px; }
    
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    
    .form-group { margin-bottom: 15px; }
    label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; font-size: 0.9rem; }
    input[type="text"], input[type="file"], textarea { width: 100%; padding: 10px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 0.95rem; font-family: inherit; box-sizing: border-box; }
    input[type="text"]:focus, textarea:focus { outline: none; border-color: var(--primary); }
    textarea { resize: vertical; min-height: 80px; }
    
    .current-logo { margin-top: 10px; padding: 10px; border: 1px dashed #ccc; border-radius: 6px; display: inline-block; background: #fafafa; }
    .current-logo img { max-height: 80px; object-fit: contain; }
    
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
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        แก้ไขข้อมูลหน้า "เกี่ยวกับเรา"
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
        <input type="hidden" name="update_about" value="1">

        <div class="section-card">
            <div class="section-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                ส่วนที่ 1 : ข้อมูลบริษัทและโลโก้
            </div>
            
            <div class="form-group">
                <label>ชื่อบริษัท (บรรทัดตัวหนา)</label>
                <input type="text" name="company_name" value="<?php echo h($about['company_name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>คำอธิบายบริษัท (ใต้ชื่อบริษัท)</label>
                <textarea name="company_desc"><?php echo h($about['company_desc'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>เปลี่ยนรูปโลโก้บริษัท</label>
                <input type="file" name="logo_image" accept="image/*">
                <?php if(!empty($about['logo_url']) && file_exists($about['logo_url'])): ?>
                    <div class="current-logo">
                        <span style="display:block; font-size:0.8rem; color:#666; margin-bottom:5px;">โลโก้ปัจจุบัน:</span>
                        <img src="<?php echo h($about['logo_url']); ?>" alt="Current Logo">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-card">
            <div class="section-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                ส่วนที่ 2 : ประวัติบริษัท (Company Profile)
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label>สโลแกนบริษัท</label>
                    <input type="text" name="slogan" value="<?php echo h($about['slogan'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>วันที่ก่อตั้ง & ทุนจดทะเบียน</label>
                    <input type="text" name="founded_info" value="<?php echo h($about['founded_info'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>ผู้บริหาร</label>
                    <input type="text" name="executive_name" value="<?php echo h($about['executive_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>จำนวนพนักงาน</label>
                    <input type="text" name="employee_count" value="<?php echo h($about['employee_count'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>พื้นที่ให้บริการ</label>
                <input type="text" name="service_area" value="<?php echo h($about['service_area'] ?? ''); ?>">
            </div>
        </div>

        <div class="section-card">
            <div class="section-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                ส่วนที่ 3 : ข้อมูลติดต่อ (ช่องขวาสุด)
            </div>
            
            <div class="form-group">
                <label>ที่ตั้งบริษัท</label>
                <textarea name="address"><?php echo h($about['address'] ?? ''); ?></textarea>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label>เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" value="<?php echo h($about['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="text" name="email" value="<?php echo h($about['email'] ?? ''); ?>">
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