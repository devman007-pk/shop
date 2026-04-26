<?php
// admin-order.php - หน้าจัดการและอัปเดตสถานะคำสั่งซื้อ (เปลี่ยนปุ่มรายละเอียดเป็นกรอกเลขพัสดุในหน้าจัดส่ง)
session_start();
require_once __DIR__ . '/config.php';

// ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        $msg = "<div class='alert alert-success'>อัปเดตสถานะคำสั่งซื้อ <b>#".str_pad((string)$order_id, 5, '0', STR_PAD_LEFT)."</b> สำเร็จ!</div>";
    } catch (Exception $e) {
        $msg = "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
    }
}

try {
    $counts = [
        'total'          => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'pending'        => $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'pending_payment')")->fetchColumn(),
        'payment_review' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'payment_review'")->fetchColumn(),
        'processing'     => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn(),
        'shipped'        => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'shipped'")->fetchColumn(),
        'completed'      => $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('completed', 'paid')")->fetchColumn(),
        'cancelled'      => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn()
    ];
} catch (Exception $e) {
    $counts = ['total'=>0, 'pending'=>0, 'payment_review'=>0, 'processing'=>0, 'shipped'=>0, 'completed'=>0, 'cancelled'=>0];
}

$filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sql = "SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id";

if ($filter === 'pending') {
    $sql .= " WHERE o.status IN ('pending', 'pending_payment')";
} elseif ($filter === 'payment_review') {
    $sql .= " WHERE o.status = 'payment_review'";
} elseif ($filter === 'processing') {
    $sql .= " WHERE o.status = 'processing'";
} elseif ($filter === 'shipped') {
    $sql .= " WHERE o.status = 'shipped'";
} elseif ($filter === 'completed') {
    $sql .= " WHERE o.status IN ('completed', 'paid')";
} elseif ($filter === 'cancelled') {
    $sql .= " WHERE o.status = 'cancelled'";
}
$sql .= " ORDER BY o.id DESC";

try {
    $orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders = [];
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการคำสั่งซื้อ - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans Thai', sans-serif; background: #f0f2f5; margin: 0; color: #333; }
    .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
    .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; }
    
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 15px; }
    h2 { margin: 0; color: #0b2f4a; }
    
    .badge-group { display: flex; gap: 10px; flex-wrap: wrap; }
    .filter-badge { padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; color: #fff; text-decoration: none; transition: transform 0.2s, opacity 0.2s; display: inline-block; }
    .filter-badge:hover { transform: translateY(-2px); opacity: 0.9; }
    
    .bg-gray { background: #8c8c8c; } .bg-yellow { background: #faad14; } .bg-purple { background: #722ed1; } .bg-blue { background: #1677ff; } .bg-red { background: #ff4d4f; } .bg-green { background: #52c41a; } .bg-dark-green { background: #1f8b50; }
    .filter-badge.active { box-shadow: 0 0 0 3px rgba(0,0,0,0.15); border: 2px solid #fff; }

    table.custom-orders-table { width: 100% !important; border-collapse: separate !important; border-spacing: 0 14px !important; margin-top: -10px; }
    table.custom-orders-table th { padding: 10px 15px; font-weight: 600; color: #555; text-align: left; border: none !important; background: transparent !important; }
    .status-chip { padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; display: inline-block; border: 1px solid rgba(0,0,0,0.05); }

    .action-group { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .btn-action { 
        padding: 8px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; 
        border: none; cursor: pointer; transition: all 0.2s; text-decoration: none; 
        display: inline-flex; align-items: center; justify-content: center; font-family: inherit;
    }
    
    .btn-next { background: #1677ff; color: #fff; box-shadow: 0 2px 4px rgba(22,119,255,0.2); }
    .btn-next:hover { background: #0958d9; transform: translateY(-1px); }
    
    .btn-cancel { background: #fff; color: #ff4d4f; border: 1px solid rgba(255,77,79,0.5); }
    .btn-cancel:hover { background: #fff1f0; border-color: #ff4d4f; }
    
    .btn-view { background: #fff; color: #555; border: 1px solid #d9d9d9; }
    .btn-view:hover { border-color: #1677ff; color: #1677ff; }

    .btn-tracking { background: #fff; color: #722ed1; border: 1px solid #d3adf7; }
    .btn-tracking:hover { background: #f9f0ff; border-color: #722ed1; }

    .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; text-align: center; }
    .alert-success { background: #f6ffed; color: #52c41a; border: 1px solid #b7eb8f; }
    .alert-danger { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }
    .empty-state { text-align: center; padding: 40px 20px; color: #888; background: #fff; border-radius: 12px; border: 1px dashed #ccc; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>

  <div class="container">
    <?php echo $msg; ?>

    <div class="card">
        <div class="page-header">
            <h2>ภาพรวมสถานะคำสั่งซื้อ</h2>
            <a href="admin-reports.php" class="filter-badge" style="background: white; color: #1677ff; border: 1px solid #1677ff;">รายงานยอดขาย</a>
        </div>
        <div class="badge-group">
            <a href="admin-order.php?status=all" class="filter-badge bg-gray <?php echo $filter=='all'?'active':''; ?>">ทั้งหมด: <?php echo $counts['total']; ?></a>
            <a href="admin-order.php?status=pending" class="filter-badge bg-yellow <?php echo $filter=='pending'?'active':''; ?>">รอชำระเงิน: <?php echo $counts['pending']; ?></a>
            <a href="admin-order.php?status=payment_review" class="filter-badge bg-purple <?php echo $filter=='payment_review'?'active':''; ?>">ตรวจสอบการชำระเงิน: <?php echo $counts['payment_review']; ?></a>
            <a href="admin-order.php?status=processing" class="filter-badge bg-blue <?php echo $filter=='processing'?'active':''; ?>">กำลังจัดเตรียม: <?php echo $counts['processing']; ?></a>
            <a href="admin-order.php?status=shipped" class="filter-badge bg-green <?php echo $filter=='shipped'?'active':''; ?>">จัดส่งแล้ว: <?php echo $counts['shipped']; ?></a>
            <a href="admin-order.php?status=completed" class="filter-badge bg-dark-green <?php echo $filter=='completed'?'active':''; ?>">เสร็จสิ้นแล้ว: <?php echo $counts['completed']; ?></a>
            <a href="admin-order.php?status=cancelled" class="filter-badge bg-red <?php echo $filter=='cancelled'?'active':''; ?>">ยกเลิก: <?php echo $counts['cancelled']; ?></a>
        </div>
    </div>

    <div style="overflow-x: auto; padding-bottom: 20px;">
        <table class="custom-orders-table">
            <thead>
                <tr>
                    <th>รหัสสั่งซื้อ</th>
                    <th>วันที่สั่งซื้อ</th>
                    <th>ชื่อลูกค้า</th>
                    <th>ยอดรวม</th>
                    <th>สถานะปัจจุบัน</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): 
                    $display = getStatusDisplay($o['status']);
                    $borderColor = $display['color'];
                    $bgColor = $display['bg'];
                    
                    // --- ตรรกะกำหนดปุ่มสถานะถัดไป ---
                    $nextStatus = ''; $nextText = '';
                    switch ($o['status']) {
                        case 'pending': case 'pending_payment': $nextStatus = 'processing'; $nextText = 'ชำระเงินแล้ว'; break;
                        case 'payment_review': $nextStatus = 'processing'; $nextText = 'ตรวจสอบผ่าน'; break;
                        case 'processing': $nextStatus = 'shipped'; $nextText = 'จัดส่งแล้ว'; break;
                        case 'shipped': $nextStatus = ''; $nextText = ''; break;
                    }
                ?>
                <tr style="box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
                    <td style="padding: 16px 15px; background-color: <?php echo $bgColor; ?> !important; border-top: 2px solid <?php echo $borderColor; ?> !important; border-bottom: 2px solid <?php echo $borderColor; ?> !important; border-left: 6px solid <?php echo $borderColor; ?> !important; border-top-left-radius: 8px !important; border-bottom-left-radius: 8px !important; border-right: none !important;">
                        <strong>#<?php echo str_pad((string)$o['id'], 5, '0', STR_PAD_LEFT); ?></strong>
                    </td>
                    
                    <td style="padding: 16px 15px; background-color: <?php echo $bgColor; ?> !important; border-top: 2px solid <?php echo $borderColor; ?> !important; border-bottom: 2px solid <?php echo $borderColor; ?> !important; border-left: none !important; border-right: none !important;">
                        <?php echo date('d/m/Y H:i', strtotime($o['created_at'] ?? 'now')); ?>
                    </td>
                    
                    <td style="padding: 16px 15px; background-color: <?php echo $bgColor; ?> !important; border-top: 2px solid <?php echo $borderColor; ?> !important; border-bottom: 2px solid <?php echo $borderColor; ?> !important; border-left: none !important; border-right: none !important;">
                        <?php echo h($o['username'] ?? 'ลูกค้าทั่วไป'); ?>
                    </td>
                    
                    <td style="padding: 16px 15px; background-color: <?php echo $bgColor; ?> !important; border-top: 2px solid <?php echo $borderColor; ?> !important; border-bottom: 2px solid <?php echo $borderColor; ?> !important; border-left: none !important; border-right: none !important;">
                        <strong><?php echo number_format($o['total_amount'] ?? 0, 2); ?> ฿</strong>
                    </td>
                    
                    <td style="padding: 16px 15px; background-color: <?php echo $bgColor; ?> !important; border-top: 2px solid <?php echo $borderColor; ?> !important; border-bottom: 2px solid <?php echo $borderColor; ?> !important; border-left: none !important; border-right: none !important;">
                        <span class="status-chip" style="background: <?php echo $borderColor; ?>; color: #fff;">
                            <?php echo $display['text']; ?>
                        </span>
                    </td>
                    
                    <td style="padding: 16px 15px; background-color: <?php echo $bgColor; ?> !important; border-top: 2px solid <?php echo $borderColor; ?> !important; border-bottom: 2px solid <?php echo $borderColor; ?> !important; border-right: 2px solid <?php echo $borderColor; ?> !important; border-top-right-radius: 8px !important; border-bottom-right-radius: 8px !important; border-left: none !important;">
                        
                        <div class="action-group">
                            <?php if ($filter !== 'all'): ?>
                                <?php if ($nextStatus): ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <input type="hidden" name="status" value="<?php echo $nextStatus; ?>">
                                    <button type="submit" name="update_status" class="btn-action btn-next"><?php echo $nextText; ?></button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($o['status'] === 'shipped'): ?>
                                <a href="admin-order-detail.php?id=<?php echo $o['id']; ?>" class="btn-action btn-tracking">กรอกเลขพัสดุ</a>
                            <?php else: ?>
                                <a href="admin-order-detail.php?id=<?php echo $o['id']; ?>" class="btn-action btn-view">รายละเอียด</a>
                            <?php endif; ?>

                            <?php if ($filter !== 'all'): ?>
                                <?php if (!in_array($o['status'], ['completed', 'paid', 'cancelled'])): ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" name="update_status" class="btn-action btn-cancel" onclick="return confirm('ยืนยันการยกเลิกคำสั่งซื้อ #<?php echo str_pad((string)$o['id'], 5, '0', STR_PAD_LEFT); ?> หรือไม่?');">ยกเลิก</button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                        </div>

                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="6" style="border: none !important; background: transparent !important; padding: 0;">
                        <div class="empty-state">ไม่พบข้อมูลคำสั่งซื้อในสถานะนี้</div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
  </div>
</body>
</html>