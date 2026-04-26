<?php
// admin-product.php - หน้าจัดการการแสดงผลสินค้าหน้าแรก (สินค้าใหม่ & สินค้าแนะนำ)
session_start();
require_once __DIR__ . '/config.php';

// 1. ตรวจสอบสิทธิ์ (ต้อง Login และเป็น Admin)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

// 2. จัดการบันทึกข้อมูล (เมื่อกดปุ่มบันทึก)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $pdo->beginTransaction();
        
        // อัปเดตเฉพาะสินค้าที่กำลังแสดงอยู่บนหน้าจอ (แก้บัคค้นหาแล้วข้อมูลหาย)
        if (!empty($_POST['displayed_products'])) {
            $displayed = array_map('intval', $_POST['displayed_products']);
            $placeholders = implode(',', array_fill(0, count($displayed), '?'));
            
            // รีเซ็ตค่าให้เป็น 0 ก่อนเฉพาะตัวที่แสดงบนหน้าจอ
            $resetStmt = $pdo->prepare("UPDATE products SET is_new_product = 0, is_recommended = 0 WHERE id IN ($placeholders)");
            $resetStmt->execute($displayed);
            
            // อัปเดต สินค้าใหม่
            if (!empty($_POST['is_new'])) {
                $stmtNew = $pdo->prepare("UPDATE products SET is_new_product = 1 WHERE id = ?");
                foreach ($_POST['is_new'] as $id) { $stmtNew->execute([$id]); }
            }

            // อัปเดต สินค้าแนะนำ
            if (!empty($_POST['is_rec'])) {
                $stmtRec = $pdo->prepare("UPDATE products SET is_recommended = 1 WHERE id = ?");
                foreach ($_POST['is_rec'] as $id) { $stmtRec->execute([$id]); }
            }
        }

        $pdo->commit();
        $success_msg = "บันทึกการตั้งค่าหน้าแรกเรียบร้อยแล้ว!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// 3. ดึงข้อมูลสินค้าทั้งหมด
$search = $_GET['search'] ?? '';
$sql = "SELECT id, name, is_new_product, is_recommended FROM products WHERE is_active = 1";
if ($search) { $sql .= " AND name LIKE :search"; }
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
if ($search) { $stmt->execute(['search' => "%$search%"]); } 
else { $stmt->execute(); }
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ตั้งค่าการแสดงผลสินค้า - OTM Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --primary-hover: #0958d9; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background-color: var(--bg); margin: 0; padding-bottom: 50px; }
    
    .container { max-width: 1000px; margin: 30px auto; padding: 0 15px; }
    
    .card { background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 25px; border: 1px solid #e8e8e8; }
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .card-title { font-size: 1.4rem; font-weight: 700; color: var(--navy); margin: 0; display: flex; align-items: center; gap: 10px; }

    .btn-back { background: #f0f2f5; color: #333; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 5px; border: 1px solid #d9d9d9; transition: all 0.2s; }
    .btn-back:hover { background: #e6e8eb; border-color: #ccc; }

    .search-box { margin-bottom: 20px; display: flex; gap: 10px; }
    .search-box input { flex: 1; padding: 10px; border: 1px solid #d9d9d9; border-radius: 4px; font-size: 1rem; }
    .btn-search { background: var(--navy); color: white; border: none; padding: 0 20px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
    .btn-search:hover { opacity: 0.9; }

    .product-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .product-table th { background: #fafafa; padding: 12px; text-align: left; border-bottom: 2px solid #f0f0f0; font-weight: 700; color: #333; font-size: 0.95rem; }
    .product-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    .product-table tr:hover { background-color: #fdfdfd; }
    .th-icon { display: inline-flex; align-items: center; gap: 6px; justify-content: center; }

    .checkbox-container { display: flex; align-items: center; justify-content: center; gap: 5px; cursor: pointer; }
    .checkbox-container input { width: 18px; height: 18px; cursor: pointer; }

    .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
    .alert-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }

    .footer-actions { position: sticky; bottom: 20px; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 -4px 15px rgba(0,0,0,0.1); display: flex; justify-content: flex-end; border: 1px solid #e8e8e8; }
    .btn-save { background: var(--primary); color: white; border: none; padding: 12px 40px; border-radius: 6px; font-size: 1.1rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
    .btn-save:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(24, 144, 255, 0.3); }
  </style>
</head>
<body>

  <?php include __DIR__ . '/admin-navbar.php'; ?>

  <main class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                เลือกสินค้าแสดงหน้าหลัก (index.php)
            </h1>
            <a href="admin-panel.php" class="btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                กลับ Dashboard
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

        <form method="get" class="search-box">
            <input type="text" name="search" placeholder="พิมพ์ชื่อสินค้าเพื่อค้นหา..." value="<?php echo h($search); ?>">
            <button type="submit" class="btn-search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                ค้นหา
            </button>
            <?php if($search): ?>
                <a href="admin-product.php" style="line-height: 40px; text-decoration: none; color: #666; font-size: 0.9rem;">ล้างการค้นหา</a>
            <?php endif; ?>
        </form>

        <form method="post">
            <table class="product-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>ชื่อสินค้า</th>
                        <th style="text-align: center; width: 140px;">
                            <span class="th-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                สินค้าใหม่
                            </span>
                        </th>
                        <th style="text-align: center; width: 140px;">
                            <span class="th-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 5h5l-4 4 1 5-5-3-5 3 1-5-4-4h5z"></path></svg>
                                สินค้าแนะนำ
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($products)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #999; padding: 30px;">ไม่พบสินค้าในระบบ</td></tr>
                    <?php else: ?>
                        <?php foreach($products as $p): ?>
                        <tr>
                            <input type="hidden" name="displayed_products[]" value="<?php echo $p['id']; ?>">
                            
                            <td style="color: #666;">#<?php echo h($p['id']); ?></td>
                            <td style="font-weight: 500; color: var(--navy);"><?php echo h($p['name']); ?></td>
                            
                            <td style="text-align: center;">
                                <label class="checkbox-container">
                                    <input type="checkbox" name="is_new[]" value="<?php echo $p['id']; ?>" <?php echo $p['is_new_product'] ? 'checked' : ''; ?>>
                                </label>
                            </td>
                            
                            <td style="text-align: center;">
                                <label class="checkbox-container">
                                    <input type="checkbox" name="is_rec[]" value="<?php echo $p['id']; ?>" <?php echo $p['is_recommended'] ? 'checked' : ''; ?>>
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="footer-actions">
                <button type="submit" name="save_settings" class="btn-save">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    บันทึกการตั้งค่าทั้งหมด
                </button>
            </div>
        </form>
    </div>
  </main>

</body>
</html>