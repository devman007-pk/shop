<?php
// admin-order-detail.php - หน้ารายละเอียดคำสั่งซื้อ (ปรับปุ่มบันทึกให้เหลือปุ่มเดียว)
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = "";

// 1. รับค่าการอัปเดตข้อมูลพัสดุ หรือ การกดปุ่มเสร็จสิ้น
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_shipping']) || isset($_POST['mark_completed'])) {
        
        $shipping_company = trim($_POST['shipping_company'] ?? '');
        $tracking_number = trim($_POST['tracking_number'] ?? '');
        
        // ถ้าแอดมินกดปุ่ม "เสร็จสิ้น" จะเปลี่ยนสถานะเป็น completed ด้วย
        $new_status = isset($_POST['mark_completed']) ? 'completed' : null;
        
        try {
            if ($new_status) {
                // บันทึกเลขพัสดุ + เปลี่ยนเป็นเสร็จสิ้น
                $stmt = $pdo->prepare("UPDATE orders SET shipping_company = ?, tracking_number = ?, status = ? WHERE id = ?");
                $stmt->execute([$shipping_company, $tracking_number, $new_status, $order_id]);
                $msg = "<div class='alert alert-success'>อัปเดตข้อมูลพัสดุและเปลี่ยนสถานะเป็น <b>เสร็จสิ้นคำสั่งซื้อ</b> เรียบร้อย!</div>";
            } else {
                // บันทึกแค่เลขพัสดุ
                $stmt = $pdo->prepare("UPDATE orders SET shipping_company = ?, tracking_number = ? WHERE id = ?");
                $stmt->execute([$shipping_company, $tracking_number, $order_id]);
                $msg = "<div class='alert alert-success'>บันทึกข้อมูลการจัดส่งพัสดุสำเร็จ!</div>";
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), "Unknown column") !== false) {
                 $msg = "<div class='alert alert-danger'><b>เกิดข้อผิดพลาด:</b> ไม่พบคอลัมน์เก็บข้อมูลขนส่ง กรุณาเพิ่มคอลัมน์ shipping_company และ tracking_number ในฐานข้อมูล</div>";
            } else {
                 $msg = "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// 2. ดึงข้อมูล Order + ข้อมูล User
$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email as u_email, u.phone as u_phone 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("ไม่พบคำสั่งซื้อนี้ในระบบ หรือรหัสไม่ถูกต้อง");
}

// 3. ดึงรายการสินค้าในบิลนี้
$stmtItems = $pdo->prepare("
    SELECT oi.*, p.name, p.id as p_id,
    (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function formatSKU($id) {
    if (is_numeric($id)) return sprintf("PROD-%04d", (int)$id);
    return $id;
}

function getStatusDisplay($status) {
    switch ($status) {
        case 'pending': case 'pending_payment': return ['text' => 'รอชำระเงิน', 'color' => '#faad14', 'bg' => '#fffbe6'];
        case 'payment_review': return ['text' => 'ตรวจสอบการชำระเงิน', 'color' => '#722ed1', 'bg' => '#f9f0ff'];
        case 'processing': return ['text' => 'กำลังจัดเตรียม', 'color' => '#1677ff', 'bg' => '#e6f4ff'];
        case 'shipped': return ['text' => 'จัดส่งแล้ว', 'color' => '#52c41a', 'bg' => '#f6ffed'];
        case 'completed': case 'paid': return ['text' => 'เสร็จสิ้นแล้ว', 'color' => '#1f8b50', 'bg' => '#e6f7eb'];
        case 'cancelled': return ['text' => 'ยกเลิก', 'color' => '#ff4d4f', 'bg' => '#fff2f0'];
        default: return ['text' => 'ไม่ทราบสถานะ', 'color' => '#8c8c8c', 'bg' => '#f5f5f5'];
    }
}
$display = getStatusDisplay($order['status']);
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>รายละเอียดคำสั่งซื้อ #<?php echo str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans Thai', sans-serif; background: #f0f2f5; margin: 0; color: #333; }
    .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
    .btn-back { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; color: #1677ff; text-decoration: none; font-weight: 600; padding: 8px 16px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: 0.2s; }
    .btn-back:hover { background: #f0f7ff; transform: translateX(-4px); }
    
    .grid-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start; }
    .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); margin-bottom: 24px; border: 1px solid rgba(11,47,74,0.03); }
    .card-header { font-size: 1.2rem; font-weight: 800; color: #0b2f4a; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px; margin-top: 0; display: flex; justify-content: space-between; align-items: center; }

    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 15px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; }
    th { background: #fafafa; font-weight: 700; color: #555; }
    
    .status-chip { padding: 12px 16px; border-radius: 8px; font-size: 1.1rem; font-weight: 800; display: inline-block; text-align: center; width: 100%; box-sizing: border-box; }
    
    .info-box { background: #f8fafc; padding: 18px; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 0.95rem; line-height: 1.6; }
    .info-box h4 { margin: 0 0 10px 0; color: #0b2f4a; font-weight: 800; font-size: 1.05rem; }

    .form-update { display: flex; flex-direction: column; gap: 16px; margin-top: 15px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-weight: 700; color: #444; font-size: 0.9rem; }
    .form-group input { padding: 12px; border-radius: 8px; border: 1px solid #d9d9d9; font-family: inherit; font-size: 1rem; background: #fff; transition: 0.2s; }
    .form-group input:focus { border-color: #1677ff; outline: none; box-shadow: 0 0 0 3px rgba(22,119,255,0.1); }
    
    .btn-save { width: 100%; background: #1677ff; color: #fff; padding: 14px; border: none; border-radius: 8px; font-size: 1.05rem; font-weight: 800; cursor: pointer; transition: 0.2s; margin-top: 10px; box-shadow: 0 4px 12px rgba(22,119,255,0.2); }
    .btn-save:hover { background: #0958d9; transform: translateY(-2px); }

    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center; border: 1px solid transparent; }
    .alert-success { background: #f6ffed; color: #127a3b; border-color: #b7eb8f; }
    .alert-danger { background: #fff2f0; color: #ff4d4f; border-color: #ffccc7; }
    
    .slip-img { width: 100%; border-radius: 10px; border: 1px solid #eee; transition: 0.3s; cursor: pointer; }
    .slip-img:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.12); }

    @media (max-width: 900px) { .grid-layout { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>

  <div class="container">
    <a href="admin-order.php" class="btn-back">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        กลับไปหน้าภาพรวมคำสั่งซื้อ
    </a>
    
    <?php echo $msg; ?>

    <div class="grid-layout">
        <div>
            <div class="card">
                <div class="card-header">
                    <span>รายละเอียดคำสั่งซื้อ #<?php echo str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    <span style="font-size: 0.9rem; color: #888; font-weight: normal;">
                        วันที่สั่งซื้อ: <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                    </span>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div class="info-box">
                        <h4>ข้อมูลลูกค้า</h4>
                        <?php 
                            $cName = trim(($order['tax_first_name'] ?? $order['username'] ?? '') . ' ' . ($order['tax_last_name'] ?? ''));
                            if(!$cName) $cName = 'ลูกค้าทั่วไป';
                        ?>
                        <b>ชื่อ:</b> <?php echo h($cName); ?><br>
                        <b>โทร:</b> <?php echo h($order['u_phone'] ?? '-'); ?><br>
                        <b>อีเมล:</b> <?php echo h($order['u_email'] ?? '-'); ?>
                    </div>
                    
                    <div class="info-box">
                        <h4>ที่อยู่จัดส่ง / ออกใบกำกับภาษี</h4>
                        <?php 
                            $addr = [];
                            if (!empty($order['tax_company_name'])) $addr[] = '<b>บจก. '.h($order['tax_company_name']).'</b><br>';
                            if (!empty($order['tax_address'])) $addr[] = $order['tax_address'];
                            if (!empty($order['tax_subdistrict'])) $addr[] = $order['tax_subdistrict'];
                            if (!empty($order['tax_district'])) $addr[] = $order['tax_district'];
                            if (!empty($order['tax_province'])) $addr[] = 'จ.'.$order['tax_province'];
                            if (!empty($order['tax_zipcode'])) $addr[] = $order['tax_zipcode'];
                            echo empty($addr) ? '<span style="color:#999;">(ไม่ได้ระบุที่อยู่)</span>' : implode(' ', $addr);
                        ?>
                    </div>
                </div>

                <div class="card-header">รายการสินค้า</div>
                <table>
                    <thead>
                        <tr>
                            <th>รูป</th>
                            <th>สินค้า</th>
                            <th>ราคา</th>
                            <th style="text-align:center;">จำนวน</th>
                            <th style="text-align:right;">รวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $it): ?>
                        <tr>
                            <td>
                                <img src="<?php echo h($it['image_url'] ?? 'placeholder.png'); ?>" style="width: 55px; height: 55px; object-fit: contain; border-radius: 8px; border: 1px solid #eee;">
                            </td>
                            <td>
                                <div style="font-weight: 700; color:#0b2f4a;"><?php echo h($it['name']); ?></div>
                                <div style="font-size: 0.8rem; color: #888; margin-top:4px;">รหัส: <?php echo h(formatSKU($it['p_id'])); ?></div>
                            </td>
                            <td>฿<?php echo number_format($it['unit_price'], 2); ?></td>
                            <td style="text-align:center; font-weight:700;"><?php echo $it['quantity']; ?></td>
                            <td style="font-weight: 800; text-align:right;">฿<?php echo number_format($it['unit_price'] * $it['quantity'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <tr>
                            <td colspan="4" style="text-align: right; border-bottom: none; padding-top: 25px; font-size:1.1rem;"><strong>ยอดสุทธิทั้งหมด:</strong></td>
                            <td style="color: #ff4d4f; font-size: 1.4rem; font-weight: 900; border-bottom: none; padding-top: 25px; text-align:right;">
                                ฿<?php echo number_format($order['total_amount'], 2); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="card" style="border-top: 6px solid <?php echo $display['color']; ?>;">
                <div class="card-header" style="border-bottom: none; padding-bottom: 0;">สถานะคำสั่งซื้อ</div>
                
                <div style="margin-top: 15px; margin-bottom: 20px;">
                    <div class="status-chip" style="background: <?php echo $display['bg']; ?>; color: <?php echo $display['color']; ?>; border: 1px solid <?php echo $display['color']; ?>;">
                        สถานะปัจจุบัน: <?php echo $display['text']; ?>
                    </div>
                </div>

                <form method="POST" class="form-update">
                    <div class="info-box" style="background: #f0f7ff; border-color: #91d5ff; margin-top: 10px;">
                        <h4 style="color: #1677ff; display:flex; align-items:center; gap:8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                            ข้อมูลการจัดส่งพัสดุ
                        </h4>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label>บริษัทขนส่ง (เช่น Kerry, Flash, J&T)</label>
                            <input type="text" name="shipping_company" value="<?php echo h($order['shipping_company'] ?? ''); ?>" placeholder="ระบุชื่อบริษัทขนส่ง">
                        </div>
                        <div class="form-group">
                            <label>หมายเลขพัสดุ (Tracking Number)</label>
                            <input type="text" name="tracking_number" value="<?php echo h($order['tracking_number'] ?? ''); ?>" placeholder="ระบุเลขพัสดุ">
                        </div>
                    </div>

                    <?php if ($order['status'] === 'shipped'): ?>
                        <button type="submit" name="mark_completed" class="btn-save" style="background: #1f8b50; box-shadow: 0 4px 12px rgba(31,139,80,0.2);" onclick="return confirm('ยืนยันบันทึกเลขพัสดุ และเปลี่ยนสถานะเป็น เสร็จสิ้น ใช่หรือไม่?');">
                            ✔ บันทึก & เสร็จสิ้นคำสั่งซื้อ
                        </button>
                    <?php else: ?>
                        <button type="submit" name="update_shipping" class="btn-save">
                            บันทึกข้อมูล
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card">
                <div class="card-header">หลักฐานการโอนเงิน</div>
                <?php if(!empty($order['payment_slip'])): ?>
                    <a href="uploads/slips/<?php echo h($order['payment_slip']); ?>" target="_blank">
                        <img src="uploads/slips/<?php echo h($order['payment_slip']); ?>" class="slip-img" alt="สลิปโอนเงิน">
                    </a>
                    <p style="text-align: center; font-size: 0.85rem; color: #888; margin-top: 12px;">คลิกที่รูปเพื่อดูขนาดเต็ม</p>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 10px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1; color: #888;">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg><br>
                        ยังไม่มีการแนบสลิปโอนเงิน
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </div>
</body>
</html>