<?php
// admin-edit-ourwork.php - หน้าจัดการ ลบ และแก้ไขผลงาน (รองรับ Base64)
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

// 1. จัดการลบผลงาน (ไม่ต้องลบไฟล์รูปแล้ว ลบแค่ใน Database)
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM works WHERE id = ?");
        $stmt->execute([$id]);
        $success_msg = "ลบผลงานเรียบร้อยแล้ว!";
    } catch (Exception $e) {
        $error_msg = "ไม่สามารถลบได้: " . $e->getMessage();
    }
}

// 2. จัดการอัปเดตข้อมูลผลงาน (แก้ไข)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_work'])) {
    try {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title']);
        
        // ถ้ามีการอัปโหลดรูปใหม่เข้ามา (แปลงเป็น Base64)
        if (isset($_FILES['work_logo']) && $_FILES['work_logo']['error'] === UPLOAD_ERR_OK) {
            $file_info = pathinfo($_FILES['work_logo']['name']);
            $ext = strtolower($file_info['extension']);
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($ext, $allowed_ext)) {
                $image_data = file_get_contents($_FILES['work_logo']['tmp_name']);
                $base64 = base64_encode($image_data);
                $mime = mime_content_type($_FILES['work_logo']['tmp_name']);
                $new_logo_url = 'data:' . $mime . ';base64,' . $base64;

                $stmt = $pdo->prepare("UPDATE works SET title = ?, logo_url = ? WHERE id = ?");
                $stmt->execute([$title, $new_logo_url, $id]);
            } else {
                 throw new Exception("รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WEBP) เท่านั้น");
            }
        } else {
            // ไม่อัปรูปใหม่ อัปเดตแค่ชื่อ
            $stmt = $pdo->prepare("UPDATE works SET title = ? WHERE id = ?");
            $stmt->execute([$title, $id]);
        }
        
        $success_msg = "อัปเดตข้อมูลผลงานเรียบร้อยแล้ว!";
    } catch (Exception $e) {
        $error_msg = "อัปเดตไม่ได้: " . $e->getMessage();
    }
}

// 3. ดึงข้อมูลผลงานที่ต้องการแก้ไข
$editWork = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM works WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editWork = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4. ดึงรายการผลงานทั้งหมด
try {
    $works = $pdo->query("SELECT * FROM works ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $works = [];
    $error_msg = "ไม่พบตาราง works: " . $e->getMessage();
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการผลงาน - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; padding-bottom: 40px; }
    .container { max-width: 1000px; margin: 40px auto; padding: 0 15px; }
    .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; border: 1px solid rgba(0,0,0,0.02); }
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; }
    .card-title { font-size: 1.4rem; font-weight: 800; color: var(--navy); margin: 0; display: flex; align-items: center; gap: 10px; }
    
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 14px; border-bottom: 1px solid #f0f0f0; text-align: left; vertical-align: middle; }
    .table th { background: #fafafa; font-weight: 700; color: #555; font-size: 0.9rem; }
    
    .btn-add { background: var(--primary); color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; box-shadow: 0 4px 10px rgba(24,144,255,0.2); }
    .btn-add:hover { background: #0958d9; transform: translateY(-2px); }
    
    /* ปุ่มจัดการ */
    .btn-action { padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 700; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px; }
    .btn-edit { background: #e6f7ff; color: var(--primary); border: 1px solid #91caff; margin-right: 5px; }
    .btn-edit:hover { background: #bae0ff; }
    .btn-del { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }
    .btn-del:hover { background: #ffccc7; }
    
    .work-img { width: 60px; height: 60px; object-fit: contain; background: #f9f9f9; border: 1px solid #eee; border-radius: 6px; padding: 4px; }
    
    .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; text-align: center; font-weight: 700; }
    .alert-success { background: #f6ffed; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }

    /* ฟอร์มแก้ไข */
    .edit-form-card { background: #f0f5ff; border: 2px solid #adc6ff; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 700; margin-bottom: 8px; color: #444; font-size: 0.9rem; }
    .form-control { padding: 12px; border-radius: 8px; border: 1px solid #d9d9d9; font-family: inherit; font-size: 1rem; width: 100%; box-sizing: border-box; }
    .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(24, 144, 255, 0.1); }
    .btn-save { background: var(--primary); color: #fff; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(24,144,255,0.2); }
    .btn-save:hover { background: #0958d9; transform: translateY(-2px); }
    .btn-cancel { color: #666; text-decoration: none; font-weight: 600; padding: 12px; margin-left: 10px; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  <div class="container">
      
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

    <?php if ($editWork): ?>
        <div class="card edit-form-card">
            <h3 style="margin-top:0; color:var(--navy); font-weight:800;">🛠️ กำลังแก้ไขผลงาน: <?php echo h($editWork['title']); ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $editWork['id']; ?>">
                
                <div class="form-group">
                    <label>ชื่อผลงาน / ชื่อบริษัท:</label>
                    <input type="text" name="title" class="form-control" value="<?php echo h($editWork['title']); ?>" required placeholder="เช่น ผลงานติดตั้งระบบอาคาร A">
                </div>
                
                <div class="form-group">
                    <label>รูปภาพโลโก้ใหม่ (ปล่อยว่างไว้ถ้าไม่เปลี่ยน):</label>
                    <input type="file" name="work_logo" class="form-control" accept="image/*">
                </div>
                
                <div style="margin-top: 20px; display: flex; align-items: center;">
                    <button type="submit" name="update_work" class="btn-save">บันทึกการแก้ไข</button>
                    <a href="admin-edit-ourwork.php" class="btn-cancel">ยกเลิก</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">รายการผลงานทั้งหมด</h2>
            <a href="admin-ourwork.php" class="btn-add">เพิ่มผลงาน</a>
        </div>

        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th style="width: 100px; text-align: center;">โลโก้</th>
                        <th>ชื่อผลงาน / บริษัท</th>
                        <th style="width: 180px; text-align: center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($works)): ?>
                        <tr><td colspan="4" style="text-align:center; color:#999; padding:40px;">ยังไม่มีข้อมูลผลงานในระบบ</td></tr>
                    <?php else: ?>
                        <?php foreach($works as $w): ?>
                        <tr>
                            <td style="color:#888;">#<?php echo h($w['id']); ?></td>
                            <td style="text-align: center;">
                                <?php if(!empty($w['logo_url'])): ?>
                                    <img src="<?php echo h($w['logo_url']); ?>" class="work-img" alt="logo">
                                <?php else: ?>
                                    <div class="work-img" style="display:flex; align-items:center; justify-content:center; color:#ccc; font-size:0.7rem;">No Img</div>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 700; color: var(--navy); font-size: 1rem;"><?php echo h($w['title']); ?></td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="?edit=<?php echo $w['id']; ?>" class="btn-action btn-edit">แก้ไข</a>
                                <a href="?delete=<?php echo $w['id']; ?>" class="btn-action btn-del" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบผลงาน : <?php echo h($w['title']); ?> ?');">ลบ</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
  </div>
</body>
</html>