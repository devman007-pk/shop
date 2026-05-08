<?php
// admin-tag.php - หน้าสำหรับเพิ่ม TAG สินค้า (เพิ่มระบบป้องกันข้อมูลซ้ำ)
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tag_value'])) {
    $tag_value = trim($_POST['tag_value']);
    $tag_group = $_POST['tag_group'];

    if ($tag_value !== '') {
        // 🛠️ เช็คก่อนว่ามี TAG ชื่อนี้ในกลุ่มที่เลือกอยู่แล้วหรือไม่
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM product_tags WHERE tag_group = ? AND tag_value = ?");
        $stmtCheck->execute([$tag_group, $tag_value]);
        
        if ($stmtCheck->fetchColumn() > 0) {
            $error_msg = "มีชื่อ TAG '{$tag_value}' ในระบบอยู่แล้วครับ";
        } else {
            // ถ้ายังไม่มี ค่อยบันทึกข้อมูล
            try {
                $stmt = $pdo->prepare("INSERT INTO product_tags (product_id, tag_group, tag_value) VALUES (0, ?, ?)");
                $stmt->execute([$tag_group, $tag_value]);
                $success_msg = "เพิ่ม TAG '{$tag_value}' เรียบร้อยแล้ว!";
            } catch (Exception $e) {
                $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    } else {
        $error_msg = "กรุณากรอกชื่อ TAG";
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>เพิ่ม TAG สินค้า - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --primary-hover: #0958d9; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; padding-bottom: 40px; }
    .container { max-width: 600px; margin: 40px auto; padding: 0 15px; }
    .card { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .form-group { margin-bottom: 20px; }
    label { display: block; font-weight: 700; margin-bottom: 8px; color: var(--navy); }
    input[type="text"], select { width: 100%; padding: 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 1rem; box-sizing: border-box; font-family: inherit; }
    input[type="text"]:focus, select:focus { outline: none; border-color: var(--primary); }
    .btn-submit { background: var(--primary); color: #fff; border: none; padding: 14px 20px; border-radius: 6px; font-weight: 700; font-size: 1.05rem; width: 100%; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(24, 144, 255, 0.3); }
    
    /* ปุ่มจัดการ */
    .btn-manage { background: #e6f4ff; color: #1677ff; border: 1px solid #91caff; padding: 8px 14px; border-radius: 6px; text-decoration: none; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
    .btn-manage:hover { background: #bae0ff; }

    .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .alert-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  
  <div class="container">
    <div class="card">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <h2 style="margin:0; color:#0b2f4a; display:flex; align-items:center; gap:10px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                เพิ่ม TAG ข้อมูลสินค้า
            </h2>
            <a href="admin-edit-tag.php" class="btn-manage">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="14 2 18 6 7 17 3 17 3 13 14 2"></polygon><line x1="3" y1="22" x2="21" y2="22"></line></svg>
                จัดการแก้ไข/ลบ
            </a>
        </div>
        
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
        
        <form method="post">
            <div class="form-group">
                <label>กลุ่มของ TAG (Tag Group)</label>
                <select name="tag_group" required>
                    <option value="category">ประเภทสินค้า (เช่น เราเตอร์, กล้องวงจรปิด)</option>
                    <option value="brand">แบรนด์สินค้า (เช่น TP-Link, Hikvision)</option>
                </select>
            </div>

            <div class="form-group">
                <label>ชื่อ TAG (Tag Value)</label>
                <input type="text" name="tag_value" placeholder="ระบุชื่อที่ต้องการ..." required autofocus>
            </div>
            
            <button type="submit" class="btn-submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                บันทึก TAG ใหม่
            </button>
        </form>
    </div>
  </div>
</body>
</html>