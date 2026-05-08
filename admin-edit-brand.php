<?php
// admin-edit-brand.php - หน้าจัดการ ลบ และแก้ไขแบรนด์
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

// 1. จัดการลบแบรนด์ (พร้อมลบไฟล์รูปภาพออกจาก Server)
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        
        $stmtImg = $pdo->prepare("SELECT logo_url FROM brands WHERE id = ?");
        $stmtImg->execute([$id]);
        $brand = $stmtImg->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
        $stmt->execute([$id]);

        if ($brand && !empty($brand['logo_url']) && file_exists($brand['logo_url'])) {
            unlink($brand['logo_url']);
        }

        $success_msg = "ลบแบรนด์เรียบร้อยแล้ว!";
    } catch (Exception $e) {
        $error_msg = "ไม่สามารถลบได้: " . $e->getMessage();
    }
}

// 2. จัดการอัปเดตข้อมูลแบรนด์ (แก้ไข)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_brand'])) {
    try {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        
        // ตรวจสอบว่ามีการอัปโหลดรูปภาพใหม่หรือไม่
        if (isset($_FILES['brand_image']) && $_FILES['brand_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/'; // โฟลเดอร์สำหรับเก็บรูป (แก้ไขให้ตรงกับระบบคุณถ้าจำเป็น)
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            
            $file_ext = strtolower(pathinfo($_FILES['brand_image']['name'], PATHINFO_EXTENSION));
            $new_filename = $upload_dir . 'brand_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            
            if (move_uploaded_file($_FILES['brand_image']['tmp_name'], $new_filename)) {
                // ดึงรูปเก่ามาลบทิ้ง
                $stmtOld = $pdo->prepare("SELECT logo_url FROM brands WHERE id = ?");
                $stmtOld->execute([$id]);
                $oldBrand = $stmtOld->fetch(PDO::FETCH_ASSOC);
                
                if ($oldBrand && !empty($oldBrand['logo_url']) && file_exists($oldBrand['logo_url'])) {
                    unlink($oldBrand['logo_url']);
                }
                
                // อัปเดตทั้งชื่อและรูป
                $stmt = $pdo->prepare("UPDATE brands SET name = ?, logo_url = ? WHERE id = ?");
                $stmt->execute([$name, $new_filename, $id]);
            } else {
                throw new Exception("อัปโหลดรูปภาพไม่สำเร็จ");
            }
        } else {
            // อัปเดตแค่ชื่ออย่างเดียว
            $stmt = $pdo->prepare("UPDATE brands SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
        }
        
        $success_msg = "อัปเดตข้อมูลแบรนด์เรียบร้อยแล้ว!";
    } catch (Exception $e) {
        $error_msg = "อัปเดตไม่ได้: " . $e->getMessage();
    }
}

// 3. ดึงข้อมูลแบรนด์ที่ต้องการแก้ไข (ถ้ามีการกดปุ่มแก้ไข)
$editBrand = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editBrand = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4. ดึงรายการแบรนด์ทั้งหมดมาแสดง
try {
    $brands = $pdo->query("SELECT * FROM brands ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $brands = [];
    $error_msg = "ไม่พบตาราง brands: " . $e->getMessage();
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการแบรนด์ - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; padding-bottom: 40px; }
    .container { max-width: 900px; margin: 40px auto; padding: 0 15px; }
    .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px;}
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .card-title { font-size: 1.4rem; font-weight: 700; color: var(--navy); margin: 0; display: flex; align-items: center; gap: 10px; }
    
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 12px; border-bottom: 1px solid #f0f0f0; text-align: left; vertical-align: middle; }
    .table th { background: #fafafa; font-weight: 700; color: #333; }
    
    .btn-add { background: var(--primary); color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
    .btn-add:hover { background: #0958d9; }
    
    /* ปุ่มจัดการ */
    .btn-edit { background: #e6f4ff; color: #1677ff; border: 1px solid #91caff; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; margin-right: 5px; }
    .btn-edit:hover { background: #bae0ff; }
    
    .btn-del { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; }
    .btn-del:hover { background: #ffccc7; }
    
    .brand-img { width: 60px; height: 60px; object-fit: contain; background: #f9f9f9; border: 1px solid #eee; border-radius: 4px; padding: 4px; }
    
    .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; display: flex; align-items: center; gap: 8px; }
    .alert-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }

    /* ฟอร์มแก้ไข */
    .edit-form-card { background: #f6ffed; border: 1px solid #b7eb8f; padding: 20px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(82,196,26,0.1); }
    .form-group { margin-bottom: 15px; }
    .form-control { padding: 10px 15px; border-radius: 8px; border: 1px solid #d9d9d9; font-family: inherit; font-size: 1rem; width: 100%; box-sizing: border-box; }
    .btn-save { background: #52c41a; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: 0.2s; }
    .btn-save:hover { background: #389e0d; }
    .btn-cancel { color: #888; text-decoration: none; font-weight: 600; padding: 10px; margin-left: 10px; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  <div class="container">
      
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

    <?php if ($editBrand): ?>
        <div class="edit-form-card">
            <h3 style="margin-top:0; color:#389e0d;">กำลังแก้ไขแบรนด์: <?php echo h($editBrand['name']); ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $editBrand['id']; ?>">
                
                <div class="form-group">
                    <label style="display: block; font-weight: 700; margin-bottom: 5px;">ชื่อแบรนด์:</label>
                    <input type="text" name="name" class="form-control" value="<?php echo h($editBrand['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label style="display: block; font-weight: 700; margin-bottom: 5px;">รูปโลโก้ใหม่ (ปล่อยว่างไว้ถ้าไม่ต้องการเปลี่ยน):</label>
                    <input type="file" name="brand_image" class="form-control" accept="image/*">
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="update_brand" class="btn-save">บันทึกการแก้ไข</button>
                    <a href="admin-edit-brand.php" class="btn-cancel">ยกเลิก</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                รายการแบรนด์ทั้งหมด
            </h2>
            <a href="admin-brand.php" class="btn-add">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                เพิ่มแบรนด์
            </a>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th style="width: 90px; text-align: center;">รูปภาพ</th>
                    <th>ชื่อแบรนด์</th>
                    <th style="width: 150px; text-align: center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($brands)): ?>
                    <tr><td colspan="4" style="text-align:center; color:#999; padding:30px;">ไม่มีข้อมูลแบรนด์ในระบบ</td></tr>
                <?php else: ?>
                    <?php foreach($brands as $b): ?>
                    <tr>
                        <td style="color:#888;">#<?php echo h($b['id']); ?></td>
                        <td style="text-align: center;">
                            <?php if(!empty($b['logo_url'])): ?>
                                <img src="<?php echo h($b['logo_url']); ?>" class="brand-img" alt="logo">
                            <?php else: ?>
                                <div class="brand-img" style="display:flex; align-items:center; justify-content:center; color:#ccc; font-size:0.8rem;">No Img</div>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 700; color: var(--navy);"><?php echo h($b['name']); ?></td>
                        <td style="text-align: center; white-space: nowrap;">
                            <a href="?edit=<?php echo $b['id']; ?>" class="btn-edit">แก้ไข</a>
                            <a href="?delete=<?php echo $b['id']; ?>" class="btn-del" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบแบรนด์ : <?php echo h($b['name']); ?> ?');">ลบ</a>
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