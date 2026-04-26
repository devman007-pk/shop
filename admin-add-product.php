<?php
// admin-add-product.php - หน้าเพิ่มสินค้า (เพิ่มระบบ เปิด/ปิด แสดงราคา)
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

// เช็คว่ามีคอลัมน์ show_price ในฐานข้อมูลหรือยัง (กัน Error)
$hasShowPrice = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'show_price'")->fetchAll();
    if (count($cols) > 0) { $hasShowPrice = true; }
} catch (Exception $e) {}

// ฟังก์ชันสร้าง UUID อัตโนมัติ (เพราะฐานข้อมูลบังคับใส่)
function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

// สร้างตัวเลขสุ่ม 6 หลักสำหรับ SKU
$random_suffix = strtoupper(substr(uniqid(), -6));
$auto_generated_sku = 'PRD-' . $random_suffix;

// ดึงรายการ TAG กลางจากฐานข้อมูลมาให้ติ๊กเลือก
$brands = [];
$categories = [];
try {
    $stmtTags = $pdo->query("SELECT tag_group, tag_value FROM product_tags WHERE product_id = 0 ORDER BY tag_value ASC");
    $masterTags = $stmtTags->fetchAll(PDO::FETCH_ASSOC);
    foreach ($masterTags as $tag) {
        if ($tag['tag_group'] === 'brand') $brands[] = $tag['tag_value'];
        if ($tag['tag_group'] === 'category') $categories[] = $tag['tag_value'];
    }
} catch (Exception $e) {
    $error_msg = "คำเตือน: ดึงข้อมูล TAG ไม่ได้ (ยังสามารถเพิ่มสินค้าได้)";
}

// เมื่อกดบันทึกสินค้า
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_name'])) {
    $product_name = trim($_POST['product_name']);
    $product_sku = trim($_POST['product_sku']);
    $product_desc = trim($_POST['product_desc']);
    
    // จัดการเรื่องการแสดงราคา
    $show_price = isset($_POST['show_price']) ? (int)$_POST['show_price'] : 1;
    // ถ้าเลือกไม่แสดงราคา ให้บันทึกราคาเป็น 0 ไปเลยเพื่อป้องกันความผิดพลาด
    $product_price = ($show_price === 1) ? (float)$_POST['product_price'] : 0;
    
    // สร้าง UUID และ Slug อัตโนมัติ
    $uuid = generate_uuid();
    $slug = strtolower(str_replace([' ', '/', '\\'], '-', $product_name)) . '-' . rand(1000,9999);
    
    // รับค่า Array ของ Tag ที่ถูกติ๊ก
    $selected_brands = $_POST['brands'] ?? [];
    $selected_categories = $_POST['categories'] ?? [];

    $image_url = '';

    // 1. จัดการอัปโหลดรูปภาพ
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/products/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

        $file_info = pathinfo($_FILES['product_image']['name']);
        $ext = strtolower($file_info['extension']);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed_ext)) {
            $new_filename = 'prd_' . time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                $image_url = $target_file;
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ";
            }
        } else {
            $error_msg = "รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WEBP) เท่านั้น";
        }
    } else {
        $error_msg = "กรุณาอัปโหลดรูปภาพสินค้าอย่างน้อย 1 รูป";
    }

    // 2. บันทึกข้อมูลลงฐานข้อมูล
    if (empty($error_msg) && $product_name !== '') {
        try {
            $pdo->beginTransaction();

            // 2.1 บันทึกข้อมูลสินค้าหลัก
            if ($hasShowPrice) {
                $stmt = $pdo->prepare("INSERT INTO products (uuid, sku, name, slug, price, show_price, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$uuid, $product_sku, $product_name, $slug, $product_price, $show_price, $product_desc]);
            } else {
                // กรณีลืมเพิ่มคอลัมน์ใน DB
                $stmt = $pdo->prepare("INSERT INTO products (uuid, sku, name, slug, price, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$uuid, $product_sku, $product_name, $slug, $product_price, $product_desc]);
            }
            $new_product_id = $pdo->lastInsertId();

            // 2.2 บันทึกรูปภาพ
            if ($image_url !== '') {
                $stmtImg = $pdo->prepare("INSERT INTO product_images (product_id, url, position) VALUES (?, ?, 1)");
                $stmtImg->execute([$new_product_id, $image_url]);
            }

            // 2.3 บันทึกการเชื่อมโยง TAG
            $stmtTag = $pdo->prepare("INSERT INTO product_tags (product_id, tag_group, tag_value) VALUES (?, ?, ?)");
            foreach ($selected_brands as $b) {
                $stmtTag->execute([$new_product_id, 'brand', $b]);
            }
            foreach ($selected_categories as $c) {
                $stmtTag->execute([$new_product_id, 'category', $c]);
            }

            $pdo->commit();
            $success_msg = "เพิ่มสินค้า '{$product_name}' เข้าระบบเรียบร้อยแล้ว!";
            
            // รีเซ็ตรหัสอัตโนมัติใหม่
            $random_suffix = strtoupper(substr(uniqid(), -6));
            $auto_generated_sku = 'PRD-' . $random_suffix;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "เกิดข้อผิดพลาดจากฐานข้อมูล: " . $e->getMessage();
        }
    } elseif ($product_name === '') {
        $error_msg = "กรุณากรอกชื่อสินค้า";
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>เพิ่มสินค้า - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;700&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1890ff; --primary-hover: #0958d9; --navy: #0b2f4a; --bg: #f0f2f5; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; padding-bottom: 50px; }
    .container { max-width: 900px; margin: 30px auto; padding: 0 15px; }
    
    .page-title { color: var(--navy); display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 1.5rem; font-weight: 700; }
    
    .card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 25px; margin-bottom: 20px; border: 1px solid #e8e8e8; }
    .card-header { font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; display: flex; align-items: center; gap: 8px; }
    
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    
    .form-group { margin-bottom: 15px; }
    label { display: flex; align-items: center; gap: 6px; font-weight: 600; margin-bottom: 8px; color: #333; font-size: 0.95rem; }
    input[type="text"], input[type="number"], input[type="file"], textarea { width: 100%; padding: 12px; border: 1px solid #d9d9d9; border-radius: 6px; font-size: 1rem; font-family: inherit; box-sizing: border-box; }
    input[type="text"]:focus, input[type="number"]:focus, textarea:focus { outline: none; border-color: var(--primary); }
    input:disabled { background: #f5f5f5; color: #888; cursor: not-allowed; }
    textarea { resize: vertical; min-height: 100px; }
    
    .checkbox-container { display: flex; flex-wrap: wrap; gap: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #eee; border-radius: 6px; max-height: 150px; overflow-y: auto; }
    .checkbox-label { display: flex; align-items: center; gap: 6px; background: #fff; padding: 6px 12px; border: 1px solid #ddd; border-radius: 20px; font-size: 0.9rem; cursor: pointer; user-select: none; transition: 0.2s; font-weight: normal; margin-bottom: 0; }
    .checkbox-label:hover { border-color: var(--primary); }
    .checkbox-label input[type="checkbox"] { cursor: pointer; }
    
    /* สไตล์ Radio ปุ่มเลือกราคา */
    .radio-group { display: flex; gap: 20px; align-items: center; margin-top: 10px; background: #f9f9f9; padding: 10px 15px; border-radius: 6px; border: 1px solid #eee; }
    .radio-group label { margin-bottom: 0; cursor: pointer; font-weight: 500; font-size: 0.95rem; }

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
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 16.28V2c0-1.1-.9-2-2-2H6c-1.1 0-2 .9-2 2v14.28c-.59.34-1 .98-1 1.72 0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2 0-.74-.41-1.38-1-1.72z"></path></svg>
        เพิ่มสินค้าเข้าระบบ
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
        
        <div class="card">
            <div class="card-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                ข้อมูลสินค้าหลัก
            </div>
            <div class="form-group">
                <label>รูปภาพสินค้า (อัปโหลด 1 รูป)</label>
                <input type="file" name="product_image" accept="image/*" required>
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label>รหัสสินค้า / SKU (ระบบจะแก้ตามแบรนด์ให้อัตโนมัติ)</label>
                    <input type="text" name="product_sku" id="product_sku" value="<?php echo h($auto_generated_sku); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>การแสดงราคาหน้าเว็บ</label>
                    <div class="radio-group">
                        <label><input type="radio" name="show_price" value="1" onchange="togglePriceInput(true)" checked> แสดงราคา</label>
                        <label><input type="radio" name="show_price" value="0" onchange="togglePriceInput(false)"> ไม่แสดงราคา (สอบถามราคา)</label>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>ชื่อสินค้า</label>
                    <input type="text" name="product_name" placeholder="ระบุชื่อสินค้า..." required>
                </div>
                <div class="form-group">
                    <label>ราคา (บาท)</label>
                    <input type="number" name="product_price" id="product_price" placeholder="0.00" step="0.01" min="0" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>คำอธิบายสินค้า</label>
                <textarea name="product_desc" placeholder="พิมพ์รายละเอียดสินค้า..."></textarea>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 17 22 12"></polyline></svg>
                การจัดหมวดหมู่ (ดึงข้อมูลจาก TAG ส่วนกลาง)
            </div>
            
            <div class="grid-2">
                <div class="form-group">
                    <label style="color: #389e0d;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        แบรนด์สินค้า (ติ๊กเลือกได้มากกว่า 1)
                    </label>
                    <div class="checkbox-container">
                        <?php if(empty($brands)): ?>
                            <span style="color:#999; font-size:0.9rem;">ยังไม่มีแบรนด์ในระบบ (ไปเพิ่มที่หน้าจัดการ TAG)</span>
                        <?php else: ?>
                            <?php foreach($brands as $b): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="brands[]" class="brand-checkbox" value="<?php echo h($b); ?>">
                                    <?php echo h($b); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label style="color: #1677ff;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                        ประเภทสินค้า (ติ๊กเลือกได้มากกว่า 1)
                    </label>
                    <div class="checkbox-container">
                        <?php if(empty($categories)): ?>
                            <span style="color:#999; font-size:0.9rem;">ยังไม่มีประเภทในระบบ (ไปเพิ่มที่หน้าจัดการ TAG)</span>
                        <?php else: ?>
                            <?php foreach($categories as $c): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="categories[]" value="<?php echo h($c); ?>">
                                    <?php echo h($c); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-actions">
            <button type="submit" class="btn-save">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                บันทึกสินค้าใหม่
            </button>
        </div>
    </form>
  </main>

  <script>
    // สคริปต์สำหรับจัดการเปิด/ปิดช่องกรอกราคา
    function togglePriceInput(isShowingPrice) {
        const priceInput = document.getElementById('product_price');
        if (isShowingPrice) {
            priceInput.disabled = false;
            priceInput.required = true;
            // ถ้าค่าเดิมเป็น 0 ให้เคลียร์ทิ้งเพื่อให้กรอกง่ายขึ้น
            if (priceInput.value == '0') priceInput.value = '';
        } else {
            priceInput.disabled = true;
            priceInput.required = false;
            priceInput.value = '0';
        }
    }

    // สคริปต์เปลี่ยน SKU อัตโนมัติตามแบรนด์
    document.addEventListener('DOMContentLoaded', function() {
        const skuInput = document.getElementById('product_sku');
        const brandCheckboxes = document.querySelectorAll('.brand-checkbox');
        
        let initialSku = skuInput.value;
        let randomSuffix = initialSku.includes('-') ? initialSku.split('-')[1] : initialSku;

        function updateSku() {
            const checkedBrand = document.querySelector('.brand-checkbox:checked');
            let prefix = 'PRD'; 
            
            if (checkedBrand) {
                let brandName = checkedBrand.value;
                let cleanBrand = brandName.replace(/[^A-Za-z0-9]/g, '');
                
                if (cleanBrand.length >= 3) {
                    prefix = cleanBrand.substring(0, 3).toUpperCase();
                } else if (cleanBrand.length > 0) {
                    prefix = cleanBrand.toUpperCase();
                }
            }
            skuInput.value = prefix + '-' + randomSuffix;
        }

        brandCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateSku);
        });
    });
  </script>

</body>
</html>