<?php
// process-order.php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getPDO();
    $userId = $_SESSION['user_id'];

    // 1. อัปเดตข้อมูลผู้ใช้
    $stmtUpdateUser = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, address=?, subdistrict=?, district=?, province=?, zipcode=? WHERE id=?");
    $stmtUpdateUser->execute([
        $_POST['first_name'] ?? '', $_POST['last_name'] ?? '', $_POST['phone'] ?? '',
        $_POST['address'] ?? '', $_POST['subdistrict'] ?? '', $_POST['district'] ?? '',
        $_POST['province'] ?? '', $_POST['zipcode'] ?? '', $userId
    ]);

    // 2. คำนวณยอดรวม
    $subtotal = 0.0;
    $pids = array_keys($_SESSION['cart']);
    if (!empty($pids)) {
        $placeholders = implode(',', array_fill(0, count($pids), '?'));
        $stmt = $pdo->prepare("SELECT id, price FROM products WHERE id IN ($placeholders)");
        $stmt->execute($pids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as $p) {
            $subtotal += (float)$p['price'] * (int)$_SESSION['cart'][$p['id']];
        }
    }
    
    $orderNumber = 'ORD-' . date('YmdHis') . '-' . rand(10, 99);
    $reqTax = isset($_POST['req_tax_invoice']) ? 1 : 0;

    // 3. บันทึก Order
    $stmtOrder = $pdo->prepare("INSERT INTO orders (order_number, user_id, status, total_amount, req_tax_invoice, tax_first_name, tax_last_name, tax_company_name, tax_id, tax_address, tax_subdistrict, tax_district, tax_province, tax_zipcode) VALUES (?, ?, 'pending_payment', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtOrder->execute([
        $orderNumber, $userId, $subtotal, $reqTax,
        $_POST['tax_first_name'] ?? '', $_POST['tax_last_name'] ?? '', $_POST['tax_company_name'] ?? '',
        $_POST['tax_id'] ?? '', $_POST['tax_address'] ?? '', $_POST['tax_subdistrict'] ?? '',
        $_POST['tax_district'] ?? '', $_POST['tax_province'] ?? '', $_POST['tax_zipcode'] ?? ''
    ]);
    $orderId = $pdo->lastInsertId();

    // 4. บันทึกรายการสินค้า
    if ($orderId && !empty($products)) {
        $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        foreach ($products as $p) {
            $stmtItem->execute([$orderId, $p['id'], (int)$_SESSION['cart'][$p['id']], $p['price']]);
        }
    }

    // 🏆 5. ล้างตะกร้าสินค้าออกจากระบบ (ทั้ง Session และ Database)
    unset($_SESSION['cart']);
    unset($_SESSION['discount_code']);
    
    // ลบสินค้าในตาราง cart_items ของ User คนนี้ทิ้ง
    $pdo->prepare("DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id = ?)")->execute([$userId]);
    // ลบตะกร้าในตาราง carts ของ User คนนี้ทิ้ง
    $pdo->prepare("DELETE FROM carts WHERE user_id = ?")->execute([$userId]);

    header("Location: payment.php?id=" . $orderId);
    exit;
}