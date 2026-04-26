<?php
// admin-edit-product.php - หน้าจัดการแก้ไขและลบสินค้า (เพิ่มระบบแก้ไข TAG แบรนด์และประเภท)
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$success_msg = '';
$error_msg = '';

// 1. จัดการลบสินค้า
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $pdo->beginTransaction();
        
        $stmtImg = $pdo->prepare("SELECT url FROM product_images WHERE product_id = ?");
        $stmtImg->execute([$id]);
        $images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
        
        $pdo->prepare("DELETE FROM product_tags WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);

        foreach ($images as $img) {
            if (!empty($img['url']) && file_exists($img['url'])) {
                unlink($img['url']);
            }
        }

        $pdo->commit();
        $success_msg = "ลบสินค้าและข้อมูลที่เกี่ยวข้องเรียบร้อยแล้ว!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "ไม่สามารถลบสินค้าได้: " . $e->getMessage();
    }
}

// 2. จัดการอัปเดตข้อมูลสินค้าและ TAG (แก้ไข)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    try {
        $pdo->beginTransaction();

        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $sku = trim($_POST['sku']);
        $show_price = isset($_POST['show_price']) ? (int)$_POST['show_price'] : 1;
        
        // ถ้าเลือกไม่แสดงราคา ให้บันทึกราคาเป็น 0 ไปเลย
        $price = ($show_price === 1) ? (float)$_POST['price'] : 0;
        $description = trim($_POST['description']);

        // 2.1 อัปเดตข้อมูลหลัก
        $stmt = $pdo->prepare("UPDATE products SET name = ?, sku = ?, price = ?, show_price = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $sku, $price, $show_price, $description, $id]);
        
        // 2.2 จัดการอัปเดต TAG (ลบของเก่า แล้วเพิ่มของใหม่เข้าไป)
        $selected_brands = $_POST['brands'] ?? [];
        $selected_categories = $_POST['categories'] ?? [];

        // ลบ TAG เดิมเฉพาะของสินค้านี้
        $pdo->prepare("DELETE FROM product_tags WHERE product_id = ?")->execute([$id]);

        // เพิ่ม TAG ใหม่
        $stmtTag = $pdo->prepare("INSERT INTO product_tags (product_id, tag_group, tag_value) VALUES (?, ?, ?)");
        foreach ($selected_brands as $b) {
            $stmtTag->execute([$id, 'brand', $b]);
        }
        foreach ($selected_categories as $c) {
            $stmtTag->execute([$id, 'category', $c]);
        }

        $pdo->commit();
        $success_msg = "อัปเดตข้อมูลสินค้า '{$name}' เรียบร้อยแล้ว!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            $error_msg = "ไม่สามารถบันทึกได้: รหัส SKU นี้มีซ้ำในระบบแล้ว";
        } elseif (strpos($e->getMessage(), 'Unknown column \'show_price\'') !== false) {
            $error_msg = "เกิดข้อผิดพลาด: คุณลืมรันคำสั่ง SQL เพื่อเพิ่มคอลัมน์ show_price ในฐานข้อมูลครับ";
        } else {
            $error_msg = "อัปเดตไม่ได้: " . $e->getMessage();
        }
    }
}

// 3. ดึงข้อมูลสินค้าที่ต้องการแก้ไข รวมถึง TAG ทั้งหมด
$editProduct = null;
$masterBrands = [];
$masterCategories = [];
$currentProductTags = ['brand' => [], 'category' => []];

if (isset($_GET['edit'])) {
    try {
        $editId = (int)$_GET['edit'];
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$editId]);
        $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($editProduct) {
            // ดึง TAG กลางทั้งหมดมาโชว์เป็นตัวเลือก
            $stmtTags = $pdo->query("SELECT tag_group, tag_value FROM product_tags WHERE product_id = 0 ORDER BY tag_value ASC");
            $allTags = $stmtTags->fetchAll(PDO::FETCH_ASSOC);
            foreach ($allTags as $t) {
                if ($t['tag_group'] === 'brand') $masterBrands[] = $t['tag_value'];
                if ($t['tag_group'] === 'category') $masterCategories[] = $t['tag_value'];
            }

            // ดึง TAG ปัจจุบันที่สินค้านี้ใช้อยู่ เพื่อติ๊กถูก
            $stmtCurrTags = $pdo->prepare("SELECT tag_group, tag_value FROM product_tags WHERE product_id = ?");
            $stmtCurrTags->execute([$editId]);
            $currTags = $stmtCurrTags->fetchAll(PDO::FETCH_ASSOC);
            foreach ($currTags as $ct) {
                if ($ct['tag_group'] === 'brand') $currentProductTags['brand'][] = $ct['tag_value'];
                if ($ct['tag_group'] === 'category') $currentProductTags['category'][] = $ct['tag_value'];
            }
        }
    } catch (Exception $e) {
        // ข้ามไป
    }
}

// 4. ดึงรายการสินค้าทั้งหมดมาแสดงในตาราง
try {
    $hasShowPriceCol = false;
    $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'show_price'")->fetchAll();
    if (count($cols) > 0) { $hasShowPriceCol = true; }

    $sqlSelect = $hasShowPriceCol ? "p.id, p.sku, p.name, p.price, p.show_price," : "p.id, p.sku, p.name, p.price, 1 as show_price,";
    
    $products = $pdo->query("
        SELECT $sqlSelect 
        (SELECT url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url 
        FROM products p 
        ORDER BY p.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
    $error_msg = "ไม่พบตาราง products: " . $e->getMessage();
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการสินค้า - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; padding-bottom: 40px; }
    .container { max-width: 1100px; margin: 40px auto; padding: 0 15px; }
    .card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .card-title { font-size: 1.4rem; font-weight: 700; color: var(--navy); margin: 0; display: flex; align-items: center; gap: 10px; }
    
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 12px; border-bottom: 1px solid #f0f0f0; text-align: left; vertical-align: middle; }
    .table th { background: #fafafa; font-weight: 700; color: #333; }
    
    .btn-add { background: var(--primary); color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
    .btn-add:hover { background: #0958d9; }
    
    .btn-action { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: 0.2s; }
    .btn-edit { background: #e6f4ff; color: #1677ff; border: 1px solid #91caff; margin-right: 5px; }
    .btn-edit:hover { background: #bae0ff; }
    .btn-del { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }
    .btn-del:hover { background: #ffccc7; }
    
    .product-img { width: 50px; height: 50px; object-fit: contain; background: #f9f9f9; border: 1px solid #eee; border-radius: 4px; padding: 4px; }
    
    .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; display: flex; align-items: center; gap: 8px; }
    .alert-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .alert-error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffccc7; }

    /* ฟอร์มแก้ไข */
    .edit-form-card { background: #e6f4ff; border: 1px solid #91caff; padding: 25px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(22,119,255,0.1); }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
    .form-group { display: flex; flex-direction: column; gap: 8px; }
    .form-group label { font-weight: 700; color: #333; font-size: 0.95rem; }
    .form-control { padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-family: inherit; }
    .form-control:disabled { background: #f5f5f5; cursor: not-allowed; }
    .btn-save { background: #1677ff; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 1rem;}
    .btn-save:hover { background: #0958d9; }
    .btn-cancel { color: #555; text-decoration: none; font-weight: 600; padding: 10px; margin-left: 10px; }
    
    .radio-group { display: flex; gap: 20px; align-items: center; padding-top: 8px; }
    .radio-group label { display: flex; align-items: center; gap: 5px; font-weight: 500; cursor: pointer; }

    /* สไตล์กล่อง Checkbox (เอามาจากหน้าเพิ่มสินค้า) */
    .checkbox-container { display: flex; flex-wrap: wrap; gap: 10px; padding: 15px; background: #fff; border: 1px solid #ccc; border-radius: 6px; max-height: 150px; overflow-y: auto; }
    .checkbox-label { display: flex; align-items: center; gap: 6px; background: #f9f9f9; padding: 6px 12px; border: 1px solid #ddd; border-radius: 20px; font-size: 0.9rem; cursor: pointer; user-select: none; transition: 0.2s; font-weight: normal; margin-bottom: 0; }
    .checkbox-label:hover { border-color: var(--primary); }
    .checkbox-label input[type="checkbox"] { cursor: pointer; }
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

    <?php if ($editProduct): ?>
    <div class="edit-form-card">
        <h2 style="margin-top: 0; color: #0b2f4a; margin-bottom: 20px; border-bottom: 2px solid #bae0ff; padding-bottom: 10px;">
            กำลังแก้ไขข้อมูล: <?php echo h($editProduct['name']); ?>
        </h2>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $editProduct['id']; ?>">
            
            <div class="form-grid">
                <div class="form-group">
                    <label>ชื่อสินค้า</label>
                    <input type="text" name="name" class="form-control" value="<?php echo h($editProduct['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>รหัส SKU</label>
                    <input type="text" name="sku" class="form-control" value="<?php echo h($editProduct['sku']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>การแสดงราคาหน้าเว็บ</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="show_price" value="1" onchange="togglePriceInput(true)" 
                            <?php echo (!isset($editProduct['show_price']) || $editProduct['show_price'] == 1) ? 'checked' : ''; ?>>
                            แสดงราคา
                        </label>
                        <label>
                            <input type="radio" name="show_price" value="0" onchange="togglePriceInput(false)"
                            <?php echo (isset($editProduct['show_price']) && $editProduct['show_price'] == 0) ? 'checked' : ''; ?>>
                            ไม่แสดงราคา (สอบถามราคา)
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>ราคา (บาท)</label>
                    <input type="number" step="0.01" name="price" id="price_input" class="form-control" value="<?php echo h($editProduct['price']); ?>" required>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 20px;">
                <div class="form-group">
                    <label style="color: #1f8b50;">
                        <svg width="18" height="18" style="vertical-align: middle;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        แบรนด์สินค้า (เลือกได้มากกว่า 1)
                    </label>
                    <div class="checkbox-container">
                        <?php if(empty($masterBrands)): ?>
                            <span style="color:#999;">ไม่มีข้อมูลแบรนด์</span>
                        <?php else: ?>
                            <?php foreach($masterBrands as $b): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="brands[]" value="<?php echo h($b); ?>" <?php echo in_array($b, $currentProductTags['brand']) ? 'checked' : ''; ?>>
                                    <?php echo h($b); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label style="color: #1677ff;">
                        <svg width="18" height="18" style="vertical-align: middle;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                        ประเภทสินค้า (เลือกได้มากกว่า 1)
                    </label>
                    <div class="checkbox-container">
                        <?php if(empty($masterCategories)): ?>
                            <span style="color:#999;">ไม่มีข้อมูลประเภท</span>
                        <?php else: ?>
                            <?php foreach($masterCategories as $c): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="categories[]" value="<?php echo h($c); ?>" <?php echo in_array($c, $currentProductTags['category']) ? 'checked' : ''; ?>>
                                    <?php echo h($c); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 20px; margin-bottom: 25px;">
                <label>รายละเอียดสินค้า</label>
                <textarea name="description" class="form-control" rows="4"><?php echo h($editProduct['description'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" name="update_product" class="btn-save">บันทึกการแก้ไขทั้งหมด</button>
            <a href="admin-edit-product.php" class="btn-cancel">ยกเลิก</a>
        </form>

        <script>
            function togglePriceInput(isShowingPrice) {
                const priceInput = document.getElementById('price_input');
                if (isShowingPrice) {
                    priceInput.disabled = false;
                    priceInput.required = true;
                } else {
                    priceInput.disabled = true;
                    priceInput.required = false;
                    priceInput.value = '0'; 
                }
            }
            window.onload = function() {
                const showPriceChecked = document.querySelector('input[name="show_price"]:checked');
                if(showPriceChecked) togglePriceInput(showPriceChecked.value === '1');
            }
        </script>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 16.28V2c0-1.1-.9-2-2-2H6c-1.1 0-2 .9-2 2v14.28c-.59.34-1 .98-1 1.72 0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2 0-.74-.41-1.38-1-1.72z"></path></svg>
                รายการสินค้าทั้งหมดในระบบ
            </h2>
            <a href="admin-add-product.php" class="btn-add">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                เพิ่มสินค้าใหม่
            </a>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 70px;">รูปภาพ</th>
                    <th style="width: 140px;">รหัสสินค้า (SKU)</th>
                    <th>ชื่อสินค้า</th>
                    <th style="width: 140px;">ราคา (บาท)</th>
                    <th style="width: 160px; text-align: center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="5" style="text-align:center; color:#999; padding:30px;">ไม่มีข้อมูลสินค้าในระบบ</td></tr>
                <?php else: ?>
                    <?php foreach($products as $p): ?>
                    <tr>
                        <td style="text-align: center;">
                            <?php if(!empty($p['image_url'])): ?>
                                <img src="<?php echo h($p['image_url']); ?>" class="product-img" alt="img">
                            <?php else: ?>
                                <div class="product-img" style="display:flex; align-items:center; justify-content:center; color:#ccc; font-size:0.7rem;">No Img</div>
                            <?php endif; ?>
                        </td>
                        <td style="color:#666; font-size: 0.9rem;"><?php echo h($p['sku']); ?></td>
                        <td style="font-weight: 700; color: var(--navy);"><?php echo h($p['name']); ?></td>
                        <td style="color: #d4380d; font-weight: 600;">
                            <?php 
                                if (isset($p['show_price']) && $p['show_price'] == 0) {
                                    echo '<span style="color:#888; font-weight:normal;">สอบถามราคา</span>';
                                } else {
                                    echo '฿' . number_format($p['price'], 2);
                                }
                            ?>
                        </td>
                        <td style="text-align: center;">
                            <a href="?edit=<?php echo $p['id']; ?>" class="btn-action btn-edit">แก้ไข</a>
                            <a href="?delete=<?php echo $p['id']; ?>" class="btn-action btn-del" onclick="return confirm('⚠️ คุณแน่ใจหรือไม่ที่จะลบสินค้า : <?php echo h($p['name']); ?> ?\n(รูปภาพและข้อมูลที่เกี่ยวข้องจะถูกลบทั้งหมด)');">ลบ</a>
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