<?php
// process-order.php - บันทึกคำสั่งซื้อ ที่อยู่ และล้างตะกร้าสินค้า
session_start();
require_once __DIR__ . '/config.php';

// เช็คว่าล็อกอินและมีของในตะกร้าหรือไม่
if (!isset($_SESSION['user_id']) || empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getPDO();
    $userId = $_SESSION['user_id'];

    // 1. อัปเดตข้อมูลผู้ใช้ (จำที่อยู่จัดส่งพื้นฐานไว้ใช้รอบหน้า)
    $stmtUpdateUser = $pdo->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, phone = ?, address = ?, subdistrict = ?, district = ?, province = ?, zipcode = ?
        WHERE id = ?
    ");
    $stmtUpdateUser->execute([
        $_POST['first_name'] ?? '', 
        $_POST['last_name'] ?? '', 
        $_POST['phone'] ?? '',
        $_POST['address'] ?? '', 
        $_POST['subdistrict'] ?? '', 
        $_POST['district'] ?? '',
        $_POST['province'] ?? '', 
        $_POST['zipcode'] ?? '', 
        $userId
    ]);

    // 2. คำนวณราคาสินค้าในตะกร้า
    $subtotal = 0.0;
    $pids = array_keys($_SESSION['cart']);
    $products = [];
    
    if (!empty($pids)) {
        $placeholders = implode(',', array_fill(0, count($pids), '?'));
        $stmt = $pdo->prepare("SELECT id, price FROM products WHERE id IN ($placeholders)");
        $stmt->execute($pids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $p) {
            $qty = (int)$_SESSION['cart'][$p['id']];
            $subtotal += (float)$p['price'] * $qty;
        }
    }
    
    // คำนวณส่วนลด
    $discountAmount = 0.0;
    $discountCode = $_SESSION['discount_code'] ?? null;
    if ($discountCode === 'SAVE10') { 
        $discountAmount = $subtotal * 0.10; 
    } elseif ($discountCode === 'MINUS50') { 
        $discountAmount = 50.00; 
    }
    
    $grandTotal = $subtotal - $discountAmount;

    // 3. สร้างเลขรหัสคำสั่งซื้อ (Order Number)
    $orderNumber = 'ORD-' . date('YmdHis') . '-' . rand(10, 99);

    // 4. บันทึกข้อมูลลงตาราง orders (รวมข้อมูลใบกำกับภาษี)
    $reqTax = isset($_POST['req_tax_invoice']) && $_POST['req_tax_invoice'] == '1' ? 1 : 0;
    
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (
            order_number, user_id, status, payment_status, total_amount, payment_method, currency, notes, req_tax_invoice,
            tax_first_name, tax_last_name, tax_company_name, tax_id, tax_address, tax_subdistrict, tax_district, tax_province, tax_zipcode
        ) VALUES (?, ?, 'pending_payment', 'unpaid', ?, ?, 'THB', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmtOrder->execute([
        $orderNumber, 
        $userId, 
        $grandTotal, 
        $_POST['payment_method'] ?? 'bank_transfer', 
        $_POST['order_notes'] ?? '', 
        $reqTax,
        $reqTax ? ($_POST['tax_first_name'] ?? '') : '',
        $reqTax ? ($_POST['tax_last_name'] ?? '') : '',
        $reqTax ? ($_POST['tax_company_name'] ?? '') : '',
        $reqTax ? ($_POST['tax_id'] ?? '') : '',
        $reqTax ? ($_POST['tax_address'] ?? '') : '',
        $reqTax ? ($_POST['tax_subdistrict'] ?? '') : '',
        $reqTax ? ($_POST['tax_district'] ?? '') : '',
        $reqTax ? ($_POST['tax_province'] ?? '') : '',
        $reqTax ? ($_POST['tax_zipcode'] ?? '') : ''
    ]);

    $orderId = $pdo->lastInsertId();

    // 5. บันทึกรายการสินค้าลงตาราง order_items
    if ($orderId && !empty($products)) {
        $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        foreach ($products as $p) {
            $qty = (int)$_SESSION['cart'][$p['id']];
            $stmtItem->execute([$orderId, $p['id'], $qty, $p['price']]);
        }
    }

    // =========================================================
    // 6. ล้างตะกร้าสินค้า (ตรงนี้คือจุดที่ทำให้สินค้าหายไปจากตะกร้าครับ!)
    // =========================================================
    unset($_SESSION['cart']);
    unset($_SESSION['discount_code']);

    // 7. ส่งลูกค้าไปยังหน้า payment 
    header("Location: payment.php?id=" . $orderId);
    exit;
}
?>