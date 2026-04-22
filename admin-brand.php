<?php
// admin-brand.php - หน้าเพิ่มข้อมูลแบรนด์ (รองรับ logo_url และ slug)
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php");
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['brand_name'])) {
    $brand_name = trim($_POST['brand_name']);
    $logo_url = ''; // เปลี่ยนมาใช้ตัวแปร logo_url

    // จัดการอัปโหลดรูปภาพ
    if (isset($_FILES['brand_image']) && $_FILES['brand_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/brands/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_info = pathinfo($_FILES['brand_image']['name']);
        $ext = strtolower($file_info['extension']);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed_ext)) {
            $new_filename = uniqid('brand_') . '.' . $ext;
            $target_file = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['brand_image']['tmp_name'], $target_file)) {
                $logo_url = $target_file;
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ";
            }
        } else {
            $error_msg = "รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WEBP) เท่านั้น";
        }
    }

    if (empty($error_msg) && $brand_name !== '') {
        // สร้าง slug จากชื่อแบรนด์ (ตัวพิมพ์เล็ก เปลี่ยนช่องว่างเป็นขีด)
        $slug = strtolower(str_replace([' ', '/', '\\'], '-', $brand_name));
        
        try {
            // บันทึกลงตาราง brands โดยใช้ logo_url และ slug ตาม DB ของคุณ
            $stmt = $pdo->prepare("INSERT INTO brands (name, slug, logo_url) VALUES (?, ?, ?)");
            $stmt->execute([$brand_name, $slug, $logo_url]);
            $success_msg = "เพิ่มแบรนด์ '{$brand_name}' เรียบร้อยแล้ว!";
        } catch (Exception $e) {
            $error_msg = "เกิดข้อผิดพลาดจากฐานข้อมูล: " . $e->getMessage();
        }
    } elseif ($brand_name === '') {
        $error_msg = "กรุณากรอกชื่อแบรนด์";
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>เพิ่มแบรนด์ - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;700&display=swap" rel="stylesheet">
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
    .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .alert-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  
  <div class="container">
    <div class="card">
        <h2 style="margin-top:0; color:#0b2f4a; display:flex; align-items:center; gap:10px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
            เพิ่มแบรนด์สินค้า
        </h2>
        
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
            <div class="form-group">
                <label>รูปภาพโลโก้แบรนด์</label>
                <input type="file" name="brand_image" accept="image/*">
            </div>

            <div class="form-group">
                <label>ชื่อแบรนด์</label>
                <input type="text" name="brand_name" placeholder="เช่น TP-Link, Cisco, Hikvision..." required>
            </div>
            
            <button type="submit" class="btn-submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                บันทึกแบรนด์
            </button>
        </form>
    </div>
  </div>
</body>
</html>