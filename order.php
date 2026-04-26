<?php
// order.php - หน้าแสดงประวัติการสั่งซื้อของฉัน
session_start();

// ==========================================
// 1. สั่งห้ามเบราว์เซอร์จำหน้าเว็บ (Anti-Cache)
// ป้องกันการกดย้อนกลับมาดูข้อมูลหลังจาก Logout ไปแล้ว
// ==========================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/config.php';

// ==========================================
// 2. ตรวจสอบการล็อกอิน (บังคับว่าต้องเป็น "ลูกค้า" เท่านั้น)
// ==========================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

$pdo = getPDO();
$userId = $_SESSION['user_id'];

// ดึงประวัติการสั่งซื้อของผู้ใช้คนนี้ เรียงจากล่าสุดไปเก่าสุด
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ฟังก์ชันแปลง Status ภาษาอังกฤษเป็นข้อความภาษาไทยและสีป้าย (Badge)
function getOrderStatus($status) {
    switch ($status) {
        case 'pending':
        case 'pending_payment':
            return ['text' => 'รอชำระเงิน', 'class' => 'status-warning'];
        case 'payment_review':
            return ['text' => 'รอตรวจสอบยอด', 'class' => 'status-info'];
        case 'processing':
            return ['text' => 'กำลังจัดเตรียมสินค้า', 'class' => 'status-primary'];
        case 'shipped':
            return ['text' => 'จัดส่งแล้ว', 'class' => 'status-success'];
        case 'completed':
        case 'paid':
            return ['text' => 'เสร็จสิ้น', 'class' => 'status-success'];
        case 'cancelled':
            return ['text' => 'ยกเลิกคำสั่งซื้อ', 'class' => 'status-danger'];
        default:
            return ['text' => $status, 'class' => 'status-secondary'];
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ประวัติการสั่งซื้อของฉัน</title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    /* ซ่อน Body Background ของเดิมเพื่อไม่ให้ตีกับ Global CSS */
    .page-title { font-size: 1.8rem; font-weight: 800; margin-bottom: 30px; color: #112a46; }
    
    /* สไตล์กล่องแจ้งเตือนความสำเร็จ */
    .alert-success { background: #f6fff6; color: #127a3b; padding: 15px; border-radius: 8px; border: 1px solid #b7eb8f; margin-bottom: 25px; font-weight: 700; text-align: center; box-shadow: 0 4px 12px rgba(18,122,59,0.05); }
    
    /* สไตล์การ์ดออเดอร์ */
    .order-card { background: #fff; border: 1px solid #eaeaea; border-radius: 12px; padding: 20px 25px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px; transition: transform 0.2s; }
    .order-card:hover { transform: translateY(-2px); box-shadow: 0 6px 25px rgba(0,0,0,0.05); }
    
    .order-info { flex: 1; min-width: 250px; }
    .order-number { font-size: 1.15rem; font-weight: 800; color: #222; margin-bottom: 5px; }
    .order-date { font-size: 0.9rem; color: #777; }
    
    .order-amount { font-size: 1.25rem; font-weight: 800; color: #1ba2b4; }
    
    /* สไตล์ป้ายสถานะ */
    .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; display: inline-block; white-space: nowrap; }
    .status-warning { background: #fffbe6; color: #faad14; border: 1px solid #ffe58f; }
    .status-info { background: #e6f7ff; color: #1677ff; border: 1px solid #91caff; }
    .status-primary { background: #f0f5ff; color: #2f54eb; border: 1px solid #adc6ff; }
    .status-success { background: #f6fff6; color: #127a3b; border: 1px solid #b7eb8f; }
    .status-danger { background: #fff2f0; color: #ff4d4f; border: 1px solid #ffccc7; }
    .status-secondary { background: #f5f5f5; color: #595959; border: 1px solid #d9d9d9; }

    /* ปุ่มดูรายละเอียด */
    .btn-view { background: #fff; color: #1ba2b4; border: 1px solid #1ba2b4; padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: 0.95rem; text-decoration: none; display: inline-block; transition: all 0.2s; white-space: nowrap; }
    .btn-view:hover { background: #1ba2b4; color: #fff; }
    
    /* สไตล์กรณีไม่มีออเดอร์ */
    .empty-state { text-align: center; padding: 60px 20px; background: #fff; border-radius: 12px; border: 1px dashed #ccc; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
    .empty-state h3 { color: #555; margin-bottom: 10px; }
    .empty-state p { color: #888; margin-bottom: 20px; }
    .btn-shopping { display: inline-block; background: #1677ff; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; transition: background 0.2s; }
    .btn-shopping:hover { background: #0958d9; }

    /* ส่วนแสดงเลขพัสดุ */
    .shipping-info-box {
        flex-basis: 100%;
        background: #f8fafc;
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px dashed #cbd5e1;
        margin-top: 5px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
    .shipping-info-title {
        color: #0b2f4a;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .shipping-details {
        font-size: 0.95rem;
        color: #444;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .tracking-number-badge {
        background: #fff;
        padding: 4px 10px;
        border-radius: 4px;
        border: 1px solid #cbd5e1;
        font-family: monospace;
        font-weight: 800;
        color: #1677ff;
        font-size: 1.05rem;
        letter-spacing: 0.5px;
    }

    @media (max-width: 768px) {
        .order-card { flex-direction: column; align-items: flex-start; }
        .order-info { width: 100%; }
        .btn-view { width: 100%; text-align: center; margin-top: 10px; }
    }
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container" style="padding: 48px 15px; min-height: 60vh;">
    <h1 class="page-title">ประวัติการสั่งซื้อของฉัน</h1>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert-success">
            <?php echo h($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:15px;"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
            <h3>ยังไม่มีประวัติการสั่งซื้อ</h3>
            <p>คุณยังไม่ได้ทำการสั่งซื้อสินค้าใดๆ ไปเลือกสินค้าลงตะกร้ากันเลย!</p>
            <a href="index.php" class="btn-shopping">เลือกซื้อสินค้า</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $o): 
            $statusObj = getOrderStatus($o['status']);
            // จัดฟอร์แมตวันที่ให้เป็นแบบไทย
            $dateTimestamp = strtotime($o['created_at']);
            $dateThai = date('d/m/', $dateTimestamp) . (date('Y', $dateTimestamp) + 543) . ' เวลา ' . date('H:i', $dateTimestamp) . ' น.';
        ?>
        <div class="order-card">
            <div class="order-info">
                <div class="order-number"><?php echo h($o['order_number']); ?></div>
                <div class="order-date">สั่งซื้อเมื่อ: <?php echo $dateThai; ?></div>
            </div>
            
            <div>
                <div class="order-amount">฿<?php echo number_format($o['total_amount'], 2); ?></div>
            </div>
            
            <div>
                <span class="status-badge <?php echo $statusObj['class']; ?>">
                    <?php echo $statusObj['text']; ?>
                </span>
            </div>
            
            <div>
                <a href="payment.php?id=<?php echo $o['id']; ?>" class="btn-view">ดูรายละเอียดบิล</a>
            </div>

            <?php if (!empty($o['shipping_company']) || !empty($o['tracking_number'])): ?>
            <div class="shipping-info-box">
                <div class="shipping-info-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1677ff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    ข้อมูลการจัดส่ง:
                </div>
                <div class="shipping-details">
                    <span><b>ขนส่ง:</b> <span style="color:#1677ff; font-weight: 700;"><?php echo h($o['shipping_company'] ?: 'ไม่ระบุ'); ?></span></span>
                    <span style="color: #cbd5e1;">|</span>
                    <span>
                        <b>เลขพัสดุ:</b> 
                        <span class="tracking-number-badge">
                            <?php echo h($o['tracking_number'] ?: '-'); ?>
                        </span>
                    </span>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    <?php endif; ?>

  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  <script src="script.js"></script>
</body>
</html>