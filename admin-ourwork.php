<?php
// admin-ourwork.php - หน้าสำหรับเพิ่มผลงานใหม่ (แปลงรูปเป็น Base64 เก็บลง Database โดยตรง)
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $title = trim($_POST['title']);
    $logo_url = ''; // เราจะใช้เก็บข้อมูลรูปภาพแบบ Base64 แทน

    // จัดการรูปภาพ (อ่านไฟล์แล้วแปลงเป็น Base64)
    if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
        $file_info = pathinfo($_FILES['logo_image']['name']);
        $ext = strtolower($file_info['extension']);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed_ext)) {
            // อ่านไฟล์เป็น Binary และแปลงเป็น Base64
            $image_data = file_get_contents($_FILES['logo_image']['tmp_name']);
            $base64 = base64_encode($image_data);
            $mime = mime_content_type($_FILES['logo_image']['tmp_name']);
            
            // สร้างเป็น Data URI เพื่อเก็บลง Database (แสดงผลใน <img> ได้เลย)
            $logo_url = 'data:' . $mime . ';base64,' . $base64;
        } else {
            $error_msg = "รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WEBP) เท่านั้น";
        }
    }

    if (empty($error_msg) && $title !== '') {
        try {
            // สร้าง slug อัตโนมัติ
            $slug = 'work-' . time() . '-' . rand(1000, 9999);
            
            // บันทึกลงฐานข้อมูล
            $stmt = $pdo->prepare("INSERT INTO works (title, logo_url, status, slug) VALUES (?, ?, 'published', ?)");
            $stmt->execute([$title, $logo_url, $slug]);
            $success_msg = "เพิ่มผลงาน '{$title}' พร้อมบันทึกรูปภาพลงฐานข้อมูลเรียบร้อยแล้ว!";
        } catch (Exception $e) {
            $error_msg = "เกิดข้อผิดพลาดจากฐานข้อมูล (อย่าลืมเปลี่ยน logo_url เป็น LONGTEXT): " . $e->getMessage();
        }
    } elseif ($title === '') {
        $error_msg = "กรุณากรอกชื่อหน่วยงาน/บริษัท";
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>เพิ่มผลงานใหม่ - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --primary-hover: #0958d9; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; padding-bottom: 40px; }
    .container { max-width: 600px; margin: 40px auto; padding: 0 15px; }
    .card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .form-group { margin-bottom: 20px; }
    label { display: block; font-weight: 700; margin-bottom: 8px; color: var(--navy); }
    input[type="text"], input[type="file"] { width: 100%; padding: 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 1rem; box-sizing: border-box; font-family: inherit; }
    input[type="text"]:focus { outline: none; border-color: var(--primary); }
    .btn-submit { background: var(--primary); color: #fff; border: none; padding: 14px 20px; border-radius: 6px; font-weight: 700; font-size: 1.05rem; width: 100%; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(24, 144, 255, 0.3); }
    .btn-manage { background: #e6f7ff; color: var(--primary); border: 1px solid #91caff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
    .btn-manage:hover { background: #bae0ff; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(24,144,255,0.15); }
    .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .alert-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  
  <div class="container">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px; border-bottom: 1px solid #eee; margin-bottom: 20px;">
            <h2 style="margin:0; color:#0b2f4a; display:flex; align-items:center; gap:10px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                เพิ่มผลงานใหม่
            </h2>
            <a href="admin-edit-ourwork.php" class="btn-manage">
                จัดการ/แก้ไขผลงาน
            </a>
        </div>
        
        <?php if($success_msg): ?>
            <div class="alert alert-success">
                <?php echo h($success_msg); ?>
            </div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-error">
                <?php echo h($error_msg); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>อัปโหลดโลโก้บริษัท/หน่วยงาน (ไฟล์จะถูกเก็บลง Database)</label>
                <input type="file" name="logo_image" accept="image/*">
            </div>
            <div class="form-group">
                <label>ชื่อบริษัท/หน่วยงาน (ข้อความหลัก)</label>
                <input type="text" name="title" placeholder="เช่น บริษัททีโอที จำกัด (มหาชน)" required autofocus>
            </div>
            
            <button type="submit" class="btn-submit">
                บันทึกผลงาน
            </button>
        </form>
    </div>
  </div>
</body>
</html>