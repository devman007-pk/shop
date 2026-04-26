<?php
// payment.php
session_start();

// 1. สั่งห้ามเบราว์เซอร์จำหน้าเว็บ (Anti-Cache) เพื่อแก้ปัญหากดย้อนกลับหลัง Logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 2. ตรวจสอบการล็อกอิน (บังคับว่าต้องล็อกอินแล้ว และต้องเป็น "ลูกค้า" เท่านั้น)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/config.php';
// ... (โค้ดอื่นๆ ของหน้านั้นๆ ตามปกติ) ...
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$pdo = getPDO();
$orderId = $_GET['id'];
$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// =========================================================================
// ล้างตะกร้าฝั่งเซิร์ฟเวอร์ (PHP Session & Database)
// =========================================================================
if (isset($_SESSION['cart'])) unset($_SESSION['cart']);
if (isset($_SESSION['discount_code'])) unset($_SESSION['discount_code']);

try {
    $stmtCart = $pdo->prepare("SELECT id FROM carts WHERE user_id = ?");
    $stmtCart->execute([$userId]);
    $cartRows = $stmtCart->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cartRows as $cRow) {
        $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cRow['id']]);
        $pdo->prepare("DELETE FROM carts WHERE id = ?")->execute([$cRow['id']]);
    }
} catch (Exception $e) {}
// =========================================================================

// ดึงข้อมูลคำสั่งซื้อ
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("ไม่พบคำสั่งซื้อนี้");
}

// ดึงข้อมูลผู้ใช้
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

$customerName = trim(($order['tax_first_name'] ?? $userData['first_name'] ?? '') . ' ' . ($order['tax_last_name'] ?? $userData['last_name'] ?? ''));
if(!$customerName) $customerName = $userData['username'] ?? 'ลูกค้าทั่วไป';

$customerCompany = $order['tax_company_name'] ?? '';

// ดึงข้อมูลที่อยู่
$cAddress = !empty($order['tax_address']) ? $order['tax_address'] : ($userData['address'] ?? '');
$cSubdist = !empty($order['tax_subdistrict']) ? $order['tax_subdistrict'] : ($userData['subdistrict'] ?? '');
$cDist = !empty($order['tax_district']) ? $order['tax_district'] : ($userData['district'] ?? '');
$cProv = !empty($order['tax_province']) ? $order['tax_province'] : ($userData['province'] ?? '');
$cZip = !empty($order['tax_zipcode']) ? $order['tax_zipcode'] : ($userData['zipcode'] ?? '');

$fullAddress = trim(implode(' ', array_filter([$cAddress, $cSubdist, $cDist, $cProv, $cZip, 'ประเทศไทย'])));
if(empty($fullAddress)) $fullAddress = "ไม่ระบุที่อยู่";

// ดึงรายการสินค้า
$stmtItems = $pdo->prepare("
    SELECT oi.*, p.name, p.id as p_id,
    (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmtItems->execute([$orderId]);
$orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Fallback
if (empty($orderItems)) {
    $mockPrice = (float)$order['total_amount'] > 0 ? (float)$order['total_amount'] : 9800.00;
    $orderItems[] = [
        'product_id' => '1', 'p_id' => '1', 'name' => 'Alfa Base ODF Rack Mount 19"/21" Swing Type',
        'quantity' => 1, 'unit_price' => $mockPrice, 'image_url' => null
    ];
    if ((float)$order['total_amount'] == 0) { $order['total_amount'] = 9800.00; }
}

$itemCount = 0;
foreach($orderItems as $it) { $itemCount += $it['quantity']; }
if (!$itemCount) $itemCount = 1;

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if (!function_exists('formatSKU')) {
    function formatSKU($id) { return is_numeric($id) ? sprintf("PROD-%04d", (int)$id) : $id; }
}

// คำนวณยอดเงินและวันที่
$orderDateTimestamp = strtotime($order['created_at'] ?? date('Y-m-d H:i:s'));
$dateThai = date('d/m/', $orderDateTimestamp) . (date('Y', $orderDateTimestamp) + 543);
$dateEn = date('d/m/Y', $orderDateTimestamp);

$totalAmount = (float)$order['total_amount'];
$vat = $totalAmount * 7 / 107;
$subtotal = $totalAmount * 100 / 107;

// จัดการอัปโหลดสลิปและส่งอีเมล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_slip'])) {
    $file = $_FILES['payment_slip'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/slips/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
        $newFileName = 'slip_' . $orderId . '_' . time() . '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
            $pdo->prepare("UPDATE orders SET status = 'payment_review', payment_slip = ? WHERE id = ?")->execute([$newFileName, $orderId]);

            // --- ส่งอีเมล ---
            $toEmail = $userData['email'];
            $subject = "ใบเสนอราคาและยืนยันการสั่งซื้อ เลขที่ #" . $order['order_number'];
            
            $itemsHtml = '';
            $i = 1;
            foreach($orderItems as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $itemsHtml .= "<tr>
                    <td align='center' style='padding:8px; border-bottom:1px solid #eee;'>".$i++."</td>
                    <td style='padding:8px; border-bottom:1px solid #eee;'>".h($item['name'])."</td>
                    <td align='right' style='padding:8px; border-bottom:1px solid #eee;'>".number_format($item['quantity'], 2)."</td>
                    <td align='right' style='padding:8px; border-bottom:1px solid #eee;'>".number_format($item['unit_price'], 2)."</td>
                    <td align='right' style='padding:8px; border-bottom:1px solid #eee;'>".number_format($itemTotal, 2)." ฿</td>
                </tr>";
            }
            
            $customerAddressHtml = '';
            if ($cAddress) $customerAddressHtml .= h($cAddress) . '<br>';
            if ($cSubdist || $cDist) $customerAddressHtml .= h(trim($cSubdist . ' ' . $cDist)) . '<br>';
            if ($cProv) $customerAddressHtml .= 'จ. ' . h($cProv) . ' ' . h($cZip);

            $htmlContent = "
            <html>
            <body style='font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;'>
                <div style='max-width: 700px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px;'>
                    <table width='100%' cellspacing='0' cellpadding='0' style='margin-bottom: 20px; border-bottom: 2px solid #1ba2b4; padding-bottom: 10px;'>
                        <tr>
                            <td width='50%' valign='top'>
                                <h2 style='color: #1ba2b4; margin: 0;'>ใบเสนอราคา / คำสั่งซื้อ</h2>
                            </td>
                            <td width='50%' valign='top' align='left' style='font-size: 13px; line-height: 1.5;'>
                                <strong>บริษัท ออน ไทม์ แมนเนจเม้นท์ จำกัด</strong><br>
                                50/123 หมู่7 ต.เนินพระ อ.เมืองระยอง จ.ระยอง 21000<br>
                                เลขผู้เสียภาษี: 0215562008121
                            </td>
                        </tr>
                    </table>
                    
                    <div style='background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 4px;'>
                        <strong>ข้อมูลลูกค้า:</strong><br>
                        ".h($customerCompany ?: $customerName)."<br>
                        {$customerAddressHtml}
                    </div>
                    
                    <p><strong>หมายเลขคำสั่งซื้อ:</strong> ".h($order['order_number'])."<br>
                    <strong>วันที่:</strong> {$dateThai}</p>
                    
                    <table width='100%' cellspacing='0' cellpadding='0' style='margin-top: 20px; font-size: 14px;'>
                        <tr style='background: #eee;'>
                            <th align='center' style='padding:10px;'>ลำดับ</th>
                            <th align='left' style='padding:10px;'>รายการสินค้า</th>
                            <th align='right' style='padding:10px;'>จำนวน</th>
                            <th align='right' style='padding:10px;'>ราคาต่อหน่วย</th>
                            <th align='right' style='padding:10px;'>จำนวนเงิน</th>
                        </tr>
                        {$itemsHtml}
                    </table>
                    
                    <table width='100%' style='margin-top: 20px;'>
                        <tr>
                            <td width='50%'></td>
                            <td width='50%'>
                                <table width='100%'>
                                    <tr>
                                        <td>ราคาก่อนรวมภาษี</td>
                                        <td align='right'>".number_format($subtotal, 2)." ฿</td>
                                    </tr>
                                    <tr>
                                        <td>ภาษีมูลค่าเพิ่ม 7%</td>
                                        <td align='right'>".number_format($vat, 2)." ฿</td>
                                    </tr>
                                    <tr>
                                        <td><strong>ทั้งหมด</strong></td>
                                        <td align='right'><strong>".number_format($totalAmount, 2)." ฿</strong></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                    
                    <div style='text-align: center; margin-top: 40px; font-size: 12px; color: #777;'>
                        081-649-2504 | navapat@otm.co.th | http://www.otm.co.th
                    </div>
                </div>
            </body>
            </html>";

            $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: OTM Shop <navapat@otm.co.th>";
            @mail($toEmail, $subject, $htmlContent, $headers);

            $_SESSION['success_msg'] = "อัปโหลดสลิปสำเร็จ! และส่งใบเสนอราคาไปยังอีเมลของคุณเรียบร้อยแล้ว";
            
            // ส่งค่า clear_cart=1 กลับไปที่หน้า order.php ด้วย เพื่อบอกให้ล้าง Local Storage
            header("Location: order.php?clear_cart=1"); 
            exit;

        } else {
            $error = "เกิดข้อผิดพลาดในการบันทึกไฟล์สลิป";
        }
    } else {
        $error = "กรุณาเลือกไฟล์สลิปโอนเงิน";
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>แจ้งชำระเงิน - หมายเลข <?php echo h($order['order_number']); ?></title>
  <link rel="stylesheet" href="styles.css" />
  
  <script>
    try {
        localStorage.removeItem('site_cart_v1'); // ลบ key หลักของตะกร้า
        sessionStorage.removeItem('cart');
        
        // ถ้า Navbar มีฟังก์ชันอัปเดตตัวเลขตะกร้า ให้เคลียร์เป็น 0 ทันที
        window.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.cart-count').forEach(el => el.textContent = '0');
        });
    } catch(e) { console.log('Clear cart error:', e); }
  </script>
  
  <style>
    body { background: #fdfdfd; color: #333; }
    .payment-container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
    .payment-grid { display: grid; grid-template-columns: 1fr 350px; gap: 40px; align-items: start; }
    
    .header-section { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
    .page-title { font-size: 1.6rem; font-weight: 800; margin: 0 0 5px 0; color: #222; }
    .order-sub { font-size: 1rem; color: #666; font-style: italic; }
    
    .btn-print { background: #222; color: #fff; padding: 8px 16px; font-size: 0.85rem; border: none; cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 600; border-radius: 4px; }
    .btn-print:hover { background: #000; }

    .payment-info-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-top: 30px; margin-bottom: 20px; }
    .payment-info-header h3 { margin: 0; font-size: 1.2rem; font-weight: 800; }
    .payment-info-header .total { font-weight: 900; font-size: 1.1rem; }

    .payment-method-title { font-weight: 700; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; font-size: 1.1rem; color: #222; }

    .bank-box { border: 1px solid #1ba2b4; background: #e8f8f9; margin-bottom: 25px; border-radius: 6px; overflow: hidden; }
    .bank-box-header { background: #1ba2b4; color: #fff; padding: 12px 15px; font-size: 0.9rem; font-weight: 600; line-height: 1.5; }
    .bank-box-body { background: #fff; }
    
    .bank-item { padding: 16px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .bank-item:last-child { border-bottom: none; }
    .bank-info h4 { margin: 0 0 5px 0; font-size: 1.05rem; display: flex; align-items: center; gap: 8px; }
    .bank-info p { margin: 3px 0; color: #555; font-size: 0.9rem; }
    .bank-info .acc-num { font-size: 1.3rem; font-weight: 900; color: #222; letter-spacing: 0.5px; }
    
    .btn-copy { background: #f0f2f5; border: 1px solid #d9d9d9; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: #444; transition: all 0.2s; white-space: nowrap; display: flex; align-items: center; gap: 6px; }
    .btn-copy:hover { background: #e2e6ea; }

    .ref-code { padding: 15px 0; font-weight: 700; color: #444; font-size: 0.95rem; }
    .address-footer { border: 1px solid #eee; padding: 15px; background: #fdfdfd; margin-top: 30px; font-size: 0.9rem; color: #555; border-radius: 6px; }

    .upload-container { background: #fff; border: 1px solid #eaeaea; border-radius: 12px; padding: 25px; margin-top: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
    .upload-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
    .upload-header h4 { margin: 0; font-size: 1.15rem; font-weight: 800; color: #222; }
    .upload-header svg { color: #1677ff; }
    
    .file-drop-area { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 35px 20px; border: 2px dashed #c2c9d6; border-radius: 10px; background: #f8fafc; transition: all 0.3s ease; cursor: pointer; margin-bottom: 20px; }
    .file-drop-area:hover, .file-drop-area.dragover { border-color: #1677ff; background: #f0f7ff; }
    .file-drop-area svg.icon-upload { width: 48px; height: 48px; color: #a0aec0; margin-bottom: 12px; transition: color 0.3s; }
    .file-drop-area:hover svg.icon-upload { color: #1677ff; }
    .file-msg { font-size: 1rem; font-weight: 700; color: #334155; margin-bottom: 5px; text-align: center; }
    .file-sub-msg { font-size: 0.85rem; color: #94a3b8; }
    .file-drop-area input[type="file"] { position: absolute; left: 0; top: 0; height: 100%; width: 100%; opacity: 0; cursor: pointer; }

    .btn-submit-modern { width: 100%; background: #1ba2b4; color: #fff; border: none; padding: 15px; font-size: 1.05rem; font-weight: 800; border-radius: 8px; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 15px rgba(27,162,180,0.25); }
    .btn-submit-modern:hover { background: #158b9b; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(27,162,180,0.35); }

    .summary-card { border: 1px solid #eaeaea; background: #fafafa; border-radius: 6px; overflow: hidden; }
    .summary-header { padding: 20px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: background 0.2s; }
    .summary-header:hover { background: #f0f0f0; }
    .summary-header h4 { margin: 0; font-size: 1.1rem; font-weight: 800; }
    .summary-header .item-count { font-size: 0.85rem; color: #666; font-weight: normal; display: block; margin-top: 5px; }
    
    .chevron-icon { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .chevron-icon.open { transform: rotate(180deg); }

    .summary-items-container { border-bottom: 1px solid #eaeaea; background: #fff; display: none; max-height: 350px; overflow-y: auto; }
    .summary-item { display: flex; gap: 12px; padding: 15px 20px; border-bottom: 1px solid #f0f4f8; }
    .summary-item:last-child { border-bottom: none; }
    .summary-item img { width: 50px; height: 50px; object-fit: contain; border-radius: 6px; border: 1px solid #eee; background: #fff; }
    .summary-item-info { flex: 1; display: flex; flex-direction: column; justify-content: center; }
    .summary-item-sku { font-size: 0.75rem; color: #888; font-weight: 700; margin-bottom: 2px; }
    .summary-item-name { font-size: 0.85rem; font-weight: 700; color: #112a46; line-height: 1.3; }
    .summary-item-price { text-align: right; display: flex; flex-direction: column; justify-content: center; min-width: 60px; }
    .summary-item-price-val { font-weight: 800; font-size: 0.9rem; color: #222; }
    .summary-item-qty { font-size: 0.8rem; color: #888; font-weight: 600; margin-top: 2px; }

    .summary-body { padding: 20px; border-top: 1px solid #eaeaea; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 0.9rem; color: #555; }
    .summary-row.total { font-weight: 800; color: #000; border-top: 1px solid #ddd; padding-top: 15px; margin-top: 5px; font-size: 1.05rem; }
    
    .alert { padding: 15px; margin-bottom: 20px; font-weight: 700; text-align: center; border: 1px solid transparent; border-radius: 6px; }
    .alert-success { background: #f6fff6; color: #127a3b; border-color: #b7eb8f; }
    .alert-error { background: #fff2f0; color: #ff4d4f; border-color: #ffccc7; }

    .print-invoice { display: none; }
    .text-right { text-align: right; }

    @media print {
        @page { size: A4; margin: 10mm 10mm 5mm 10mm; } 
        body { background: #fff; margin: 0; padding: 0; font-family: 'Sarabun', 'Tahoma', sans-serif; color: #000; }
        body > * { display: none !important; }
        body > .print-invoice { display: flex !important; flex-direction: column; width: 100%; height: 99vh; padding: 10px 20px 0 20px; box-sizing: border-box; }
        .print-content { flex: 1 1 auto; }
        .print-header { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .print-logo-box { width: 150px; }
        
        /* ข้อมูลบริษัทชิดซ้าย */
        .print-company-info { text-align: left !important; width: 450px !important; font-size: 0.9rem; line-height: 1.5; }
        
        .print-title { font-size: 1.8rem; font-weight: bold; margin: 0 0 20px 0; }
        .print-dates { display: flex; margin-bottom: 20px; gap: 100px; font-size: 0.9rem; }
        .print-dates strong { font-weight: bold; }
        .print-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 0.9rem; }
        .print-table th { border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 8px 5px; text-align: left; }
        .print-table td { padding: 8px 5px; }
        .print-table tr.last-item td { border-bottom: 2px solid #000; padding-bottom: 15px; }
        .print-table th.text-right, .print-table td.text-right { text-align: right; }
        .print-summary-wrapper { display: flex; justify-content: flex-end; width: 100%; margin-top: 5px; }
        .summary-table { width: 300px; border-collapse: collapse; font-size: 0.9rem; }
        .summary-table td { padding: 6px 0; }
        .summary-total td { font-weight: bold; border-top: 1px solid #ccc; border-bottom: 3px double #000; padding-top: 10px; padding-bottom: 10px; margin-top: 5px; }
        .print-footer { flex: 0 0 auto; border-top: 1px solid #000; padding-top: 10px; padding-bottom: 5px; display: flex; justify-content: space-between; font-size: 0.8rem; background: #fff; margin-top: auto; }
    }
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <div class="payment-container">
    
    <?php if($error): ?>
      <div class="alert alert-error">❌ <?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="payment-grid">
      <div>
        <div class="header-section">
          <div>
            <h1 class="page-title">ขอบคุณสำหรับการสั่งซื้อของคุณ</h1>
            <div class="order-sub">คำสั่ง <?php echo h($order['order_number']); ?></div>
          </div>
          <button class="btn-print" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            พิมพ์
          </button>
        </div>

        <div class="payment-info-header">
          <h3>ข้อมูลการชำระเงิน</h3>
          <div class="total">
            <span style="font-weight:normal; margin-right:15px; font-size:1rem;">ทั้งหมด:</span>
            <?php echo number_format($order['total_amount'], 2); ?> ฿
          </div>
        </div>
        
        <div class="payment-method-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1677ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <circle cx="12" cy="12" r="4" fill="#1677ff"></circle>
            </svg>
            โอนเงิน
        </div>

        <?php if ($order['status'] === 'pending_payment'): ?>
            <div class="bank-box">
                <div class="bank-box-header">
                    กรุณาโอนเงินตามรายละเอียดบัญชีด้านล่าง แนบสลิปการโอนเงินและเลขที่คำสั่งซื้อทางหน้าเว็บนี้
                </div>
                
                <div class="bank-box-body">
                    <div class="bank-item">
                        <div class="bank-info">
                            <h4 style="color:#00a35b;">
                                <img src="logo/ks.png" alt="KBank Logo" style="height: 28px; width: 28px; object-fit: contain;">
                                ธนาคารกสิกรไทย (KBank)
                            </h4>
                            <p>ชื่อบัญชี: บจก. ออน ไทม์ เมเนจเม้นท์</p>
                            <p class="acc-num">189-1-14962-6</p>
                        </div>
                        <button type="button" class="btn-copy" onclick="copyToClipboard('1891149626', this)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            คัดลอก
                        </button>
                    </div>

                    <div class="bank-item">
                        <div class="bank-info">
                            <h4 style="color:#00aae4;">
                                <img src="logo/kt.png" alt="KTB Logo" style="height: 28px; width: 28px; object-fit: contain; transform: scale(1.3);">
                                ธนาคารกรุงไทย (KTB)
                            </h4>
                            <p>ชื่อบัญชี: บริษัท ออน ไทม์ แมนเนจเม้นท์ จำกัด</p>
                            <p class="acc-num">678-5-95273-5</p>
                        </div>
                        <button type="button" class="btn-copy" onclick="copyToClipboard('6785952735', this)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            คัดลอก
                        </button>
                    </div>
                </div>
            </div>

            <div class="ref-code">การสื่อสาร: <?php echo h($order['order_number']); ?></div>

            <div class="upload-container">
                <div class="upload-header">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    <h4>แนบสลิปโอนเงิน</h4>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="file-drop-area" id="drop-area">
                        <img id="preview-img" src="" style="display: none; max-height: 250px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); object-fit: contain; width: 100%;">
                        
                        <svg class="icon-upload" id="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        
                        <span class="file-msg" id="file-msg">คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวางที่นี่</span>
                        <span class="file-sub-msg" id="file-sub-msg">รองรับเฉพาะไฟล์ JPG, PNG</span>
                        <input type="file" name="payment_slip" id="payment_slip" accept="image/jpeg, image/png" required>
                    </div>
                    <button type="submit" class="btn-submit-modern">ยืนยันการชำระเงิน</button>
                </form>
            </div>
            
        <?php else: ?>
            <div style="background:#f6fff6; border:1px solid #b7eb8f; padding:30px; text-align:center; color:#127a3b; margin: 40px 0; border-radius: 6px;">
                <h3 style="margin-top:0; color:#127a3b; font-weight:800; font-size:1.4rem;">ได้รับหลักฐานการชำระเงินแล้ว</h3>
                <p style="margin-bottom:0; font-size:1.05rem;">รอการตรวจสอบยอดเงินจากทางร้านครับ</p>
                
                <?php if(!empty($order['payment_slip'])): ?>
                    <div style="margin-top: 25px; padding-top: 25px; border-top: 1px dashed #b7eb8f;">
                        <p style="margin-top:0; font-size: 0.9rem; color: #555;">รูปภาพสลิปที่แนบ:</p>
                        <img src="uploads/slips/<?php echo h($order['payment_slip']); ?>" alt="Payment Slip" style="max-width: 100%; max-height: 400px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; object-fit: contain;">
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

      </div>

      <aside>
        <div class="summary-card">
          <div class="summary-header" id="summary-toggle">
            <div>
              <h4>สรุปการสั่งซื้อ</h4>
              <span class="item-count"><?php echo $itemCount; ?> item(s) - <?php echo number_format($totalAmount, 2); ?> ฿</span>
            </div>
            <svg class="chevron-icon" id="summary-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </div>
          
          <div class="summary-items-container" id="summary-items">
            <?php foreach ($orderItems as $it): ?>
              <div class="summary-item">
                <img src="<?php echo h($it['image_url'] ?? 'placeholder.png'); ?>" alt="">
                <div class="summary-item-info">
                  <div class="summary-item-sku">รหัส: <?php echo h(formatSKU($it['p_id'] ?? $it['product_id'])); ?></div>
                  <div class="summary-item-name"><?php echo h($it['name']); ?></div>
                </div>
                <div class="summary-item-price">
                  <div class="summary-item-price-val">฿<?php echo number_format((float)$it['unit_price'] * (int)$it['quantity'], 2); ?></div>
                  <div class="summary-item-qty">x <?php echo $it['quantity']; ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="summary-body">
            <div class="summary-row">
              <span>ยอดรวมย่อย</span>
              <span><?php echo number_format($subtotal, 2); ?> ฿</span>
            </div>
            <div class="summary-row">
              <span>การจัดส่ง</span>
              <span style="color: #127a3b; font-weight: 700;">จัดส่งฟรี</span>
            </div>
            <div class="summary-row">
              <span>ภาษี (7%)</span>
              <span><?php echo number_format($vat, 2); ?> ฿</span>
            </div>
            <div class="summary-row total">
              <span>ทั้งหมด</span>
              <span><?php echo number_format($totalAmount, 2); ?> ฿</span>
            </div>
          </div>
        </div>
      </aside>

    </div>
  </div>

  <div class="print-invoice">
      <div class="print-content">
          <div class="print-header">
              <div class="print-logo-box">
                  <img src="logo/logo.jpg" alt="OTM Logo" style="max-width: 100%; height: auto;">
              </div>
              <div class="print-company-info">
                  <strong>บริษัท ออน ไทม์ แมนเนจเม้นท์ จำกัด</strong><br>
                  50/123 หมู่7 ตำบลเนินพระ อำเภอเมืองระยอง<br>
                  จังหวัดระยอง 21000<br>
                  เลขที่ประจำตัวผู้เสียภาษี : 0215562008121<br><br>
                  
                  <strong><?php echo h($customerCompany ?: $customerName); ?></strong><br>
                  <?php if($customerCompany && $customerName) echo h($customerName) . '<br>'; ?>
                  <?php if($cAddress) echo h($cAddress) . '<br>'; ?>
                  <?php if($cSubdist || $cDist) echo h(trim($cSubdist . ' ' . $cDist)) . '<br>'; ?>
                  <?php if($cProv) echo 'จ. ' . h($cProv) . ' ' . h($cZip); ?>
              </div>
          </div>
          
          <h2 class="print-title">ใบเสนอราคา # <?php echo h($order['order_number']); ?></h2>
          
          <div class="print-dates">
              <div class="date-col">
                  <strong>วันที่เสนอราคา</strong><br>
                  <?php echo $dateThai; ?>
              </div>
              <div class="date-col">
                  <strong>Expiration</strong><br>
                  <?php echo $dateEn; ?>
              </div>
          </div>
          
          <table class="print-table">
              <thead>
                  <tr>
                      <th>ลำดับ</th>
                      <th>รายการสินค้า</th>
                      <th class="text-right">จำนวน</th>
                      <th class="text-right">ราคาต่อหน่วย</th>
                      <th class="text-right">จำนวนเงิน</th>
                  </tr>
              </thead>
              <tbody>
                  <?php 
                  $i = 1;
                  foreach($orderItems as $item): 
                      $itemTotal = $item['quantity'] * $item['unit_price'];
                  ?>
                  <tr>
                      <td style="text-align: center;"><?php echo $i++; ?></td>
                      <td><?php echo h($item['name'] ?? 'สินค้ารหัส #'.$item['product_id']); ?></td>
                      <td class="text-right"><?php echo number_format((float)$item['quantity'], 2); ?></td>
                      <td class="text-right"><?php echo number_format((float)$item['unit_price'], 2); ?></td>
                      <td class="text-right"><?php echo number_format((float)$itemTotal, 2); ?> ฿</td>
                  </tr>
                  <?php endforeach; ?>
                  
                  <tr class="last-item">
                      <td style="text-align: center;"><?php echo $i++; ?></td>
                      <td>การจัดส่งมาตรฐาน (ฟรี)</td>
                      <td class="text-right">1.00</td>
                      <td class="text-right">0.00</td>
                      <td class="text-right">0.00 ฿</td>
                  </tr>
              </tbody>
          </table>
          
          <div class="print-summary-wrapper">
              <table class="summary-table">
                  <tr>
                      <td>ราคาก่อนรวมภาษีมูลค่าเพิ่ม</td>
                      <td class="text-right"><?php echo number_format($subtotal, 2); ?> ฿</td>
                  </tr>
                  <tr>
                      <td>ภาษีมูลค่าเพิ่ม 7%</td>
                      <td class="text-right"><?php echo number_format($vat, 2); ?> ฿</td>
                  </tr>
                  <tr class="summary-total">
                      <td>ทั้งหมด</td>
                      <td class="text-right"><?php echo number_format($totalAmount, 2); ?> ฿</td>
                  </tr>
              </table>
          </div>
      </div>
      
      <div class="print-footer">
          <span>081-649-2504</span>
          <span>navapat@otm.co.th</span>
          <span>http://www.otm.co.th</span>
          <span>Page 1 / 1</span>
      </div>
  </div>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  
  <script>
    function copyToClipboard(text, btnElement) {
        navigator.clipboard.writeText(text).then(function() {
            const originalHTML = btnElement.innerHTML;
            btnElement.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> คัดลอกแล้ว';
            btnElement.style.backgroundColor = '#f6fff6';
            btnElement.style.color = '#127a3b';
            btnElement.style.borderColor = '#b7eb8f';
            
            setTimeout(function() {
                btnElement.innerHTML = originalHTML;
                btnElement.style.backgroundColor = '';
                btnElement.style.color = '';
                btnElement.style.borderColor = '';
            }, 2000);
        }).catch(function(err) {
            alert("ไม่สามารถคัดลอกได้ กรุณาคัดลอกด้วยตนเอง");
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('summary-toggle');
        const itemsContainer = document.getElementById('summary-items');
        const chevron = document.getElementById('summary-chevron');

        if (toggleBtn && itemsContainer && chevron) {
            toggleBtn.addEventListener('click', function() {
                if (itemsContainer.style.display === 'none' || itemsContainer.style.display === '') {
                    itemsContainer.style.display = 'block';
                    chevron.classList.add('open');
                } else {
                    itemsContainer.style.display = 'none';
                    chevron.classList.remove('open');
                }
            });
        }

        const fileInput = document.getElementById('payment_slip');
        const fileMsg = document.getElementById('file-msg');
        const dropArea = document.getElementById('drop-area');
        
        const previewImg = document.getElementById('preview-img');
        const uploadIcon = document.getElementById('upload-icon');
        const fileSubMsg = document.getElementById('file-sub-msg');

        if (fileInput && fileMsg && dropArea) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    const fileName = file.name;
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        previewImg.style.display = 'block';
                        if(uploadIcon) uploadIcon.style.display = 'none';
                        if(fileSubMsg) fileSubMsg.textContent = 'คลิกหรือลากไฟล์ใหม่เพื่อเปลี่ยนรูป';
                    }
                    reader.readAsDataURL(file);

                    fileMsg.textContent = "เลือกไฟล์: " + fileName;
                    fileMsg.style.color = '#127a3b';
                    dropArea.style.borderColor = '#127a3b';
                    dropArea.style.backgroundColor = '#f6fff6';
                } else {
                    if(previewImg) previewImg.style.display = 'none';
                    if(uploadIcon) uploadIcon.style.display = 'block';
                    if(fileSubMsg) fileSubMsg.textContent = 'รองรับเฉพาะไฟล์ JPG, PNG';
                    
                    fileMsg.textContent = "คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวางที่นี่";
                    fileMsg.style.color = '#334155';
                    dropArea.style.borderColor = '#c2c9d6';
                    dropArea.style.backgroundColor = '#f8fafc';
                }
            });

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => dropArea.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => dropArea.classList.remove('dragover'), false);
            });
        }
    });
  </script>

  <script src="script.js"></script>
</body>
</html>