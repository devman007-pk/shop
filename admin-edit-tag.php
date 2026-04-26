<?php
// admin-edit-tag.php - หน้าสำหรับแก้ไขและลบ TAG สินค้า (เพิ่มระบบแก้ไข)
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

// 1. จัดการการลบ
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM product_tags WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $success_msg = "ลบข้อมูลเรียบร้อยแล้ว!";
    } catch (Exception $e) {
        $error_msg = "ลบไม่ได้: " . $e->getMessage();
    }
}

// 2. จัดการการอัปเดต (แก้ไข)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tag'])) {
    try {
        $id = (int)$_POST['id'];
        $new_value = trim($_POST['tag_value']);
        $stmt = $pdo->prepare("UPDATE product_tags SET tag_value = ? WHERE id = ?");
        $stmt->execute([$new_value, $id]);
        $success_msg = "อัปเดตชื่อเรียบร้อยแล้ว!";
    } catch (Exception $e) {
        $error_msg = "อัปเดตไม่ได้: " . $e->getMessage();
    }
}

// 3. ดึงข้อมูลที่จะแก้ไข (ถ้ามีการกดปุ่มแก้ไข)
$editTag = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM product_tags WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editTag = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4. ดึงข้อมูล TAG ทั้งหมดและแยกหมวดหมู่
$categories = [];
$brands = [];
try {
    $tags = $pdo->query("SELECT * FROM product_tags WHERE product_id = 0 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach($tags as $t) {
        if ($t['tag_group'] === 'category') {
            $categories[] = $t;
        } elseif ($t['tag_group'] === 'brand') {
            $brands[] = $t;
        }
    }
} catch (Exception $e) {
    $error_msg = "ไม่สามารถดึงข้อมูลได้: " . $e->getMessage();
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการแก้ไข/ลบ TAG - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; padding-bottom: 40px; }
    .container { max-width: 1100px; margin: 40px auto; padding: 0 15px; }
    
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .page-title { font-size: 1.5rem; font-weight: 800; color: var(--navy); margin: 0; display: flex; align-items: center; gap: 10px; }
    
    .btn-add { background: var(--primary); color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 6px; transition: 0.2s; box-shadow: 0 2px 8px rgba(24,144,255,0.2); }
    .btn-add:hover { background: #0958d9; transform: translateY(-2px); }
    
    /* Layout แยก 2 ฝั่ง */
    .split-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; align-items: start; }
    
    .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
    .card-header { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
    
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 12px 10px; border-bottom: 1px solid #f0f0f0; text-align: left; }
    .table th { background: #fafafa; font-weight: 700; color: #555; }
    .table tr:hover { background: #fdfdfd; }
    
    /* ปุ่มจัดการ */
    .btn-edit { background: #fff; color: #1677ff; border: 1px solid #91d5ff; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; margin-right: 5px; }
    .btn-edit:hover { background: #e6f4ff; border-color: #1677ff; }
    
    .btn-del { background: #fff; color: #ff4d4f; border: 1px solid #ffa39e; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; }
    .btn-del:hover { background: #fff2f0; border-color: #ff4d4f; }
    
    /* แจ้งเตือน */
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
    .alert-success { background: #f6ffed; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }
    
    .badge-category { background: #e6f4ff; color: #1677ff; border: 1px solid #91caff; padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: 700; display: inline-block; }
    .badge-brand { background: #f6ffed; color: #389e0d; border: 1px solid #b7eb8f; padding: 6px 14px; border-radius: 20px; font-size: 0.9rem; font-weight: 700; display: inline-block; }

    /* ฟอร์มแก้ไข */
    .edit-form-card { background: #fffbe6; border: 1px solid #ffe58f; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: flex-end; gap: 15px; box-shadow: 0 4px 15px rgba(250,173,20,0.1); }
    .form-control { padding: 10px 15px; border-radius: 8px; border: 1px solid #d9d9d9; font-family: inherit; font-size: 1rem; width: 100%; box-sizing: border-box; }
    .btn-save { background: #1677ff; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: 0.2s; }
    .btn-save:hover { background: #0958d9; }
    .btn-cancel { color: #888; text-decoration: none; font-weight: 600; padding: 10px; }

    @media (max-width: 800px) {
        .split-grid { grid-template-columns: 1fr; }
        .edit-form-card { flex-direction: column; align-items: stretch; }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  <div class="container">
    
    <div class="page-header">
        <h2 class="page-title">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
            จัดการแก้ไข / ลบ TAG
        </h2>
        <a href="admin-tag.php" class="btn-add">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            เพิ่ม TAG ใหม่
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

    <?php if ($editTag): ?>
        <form method="POST" class="edit-form-card">
            <input type="hidden" name="id" value="<?php echo $editTag['id']; ?>">
            <div style="flex: 1;">
                <label style="display: block; font-weight: 700; color: #555; margin-bottom: 8px;">
                    กำลังแก้ไข <?php echo $editTag['tag_group'] == 'category' ? 'ประเภทสินค้า' : 'แบรนด์'; ?>:
                </label>
                <input type="text" name="tag_value" class="form-control" value="<?php echo h($editTag['tag_value']); ?>" required>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="submit" name="update_tag" class="btn-save">บันทึกการแก้ไข</button>
                <a href="admin-edit-tag.php" class="btn-cancel">ยกเลิก</a>
            </div>
        </form>
    <?php endif; ?>

    <div class="split-grid">
        <div class="card" style="border-top: 4px solid #1677ff;">
            <div class="card-header">
                <span class="badge-category">📂 ประเภทสินค้า (Category)</span>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>ชื่อประเภทสินค้า</th>
                        <th style="width: 140px; text-align: center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="3" style="text-align:center; color:#999; padding:20px;">ไม่มีข้อมูล</td></tr>
                    <?php else: ?>
                        <?php foreach($categories as $c): ?>
                        <tr>
                            <td style="color:#888;">#<?php echo h($c['id']); ?></td>
                            <td style="font-weight: 700; color: var(--navy);"><?php echo h($c['tag_value']); ?></td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="?edit=<?php echo $c['id']; ?>" class="btn-edit">แก้ไข</a>
                                <a href="?delete=<?php echo $c['id']; ?>" class="btn-del" onclick="return confirm('ลบประเภท: <?php echo h($c['tag_value']); ?> ใช่หรือไม่?');">ลบ</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card" style="border-top: 4px solid #52c41a;">
            <div class="card-header">
                <span class="badge-brand">🏷️ แบรนด์ (Brand)</span>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>ชื่อแบรนด์</th>
                        <th style="width: 140px; text-align: center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($brands)): ?>
                        <tr><td colspan="3" style="text-align:center; color:#999; padding:20px;">ไม่มีข้อมูล</td></tr>
                    <?php else: ?>
                        <?php foreach($brands as $b): ?>
                        <tr>
                            <td style="color:#888;">#<?php echo h($b['id']); ?></td>
                            <td style="font-weight: 700; color: var(--navy);"><?php echo h($b['tag_value']); ?></td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="?edit=<?php echo $b['id']; ?>" class="btn-edit">แก้ไข</a>
                                <a href="?delete=<?php echo $b['id']; ?>" class="btn-del" onclick="return confirm('ลบแบรนด์: <?php echo h($b['tag_value']); ?> ใช่หรือไม่?');">ลบ</a>
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