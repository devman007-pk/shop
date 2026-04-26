<?php
// admin-code-discount.php - หน้าเพิ่มโค้ดส่วนลด
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // ถ้าไม่ใช่แอดมิน หรือไม่ได้ล็อกอิน ให้ส่งกลับไปหน้าล็อกอินแอดมินทันที
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_discount'])) {
    $code = strtoupper(trim($_POST['code']));
    $type = $_POST['discount_type'];
    $value = $_POST['discount_value'];
    $min_order = $_POST['min_order_value'] ?: 0;
    $usage_limit = !empty($_POST['usage_limit']) ? $_POST['usage_limit'] : null;
    $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("INSERT INTO discounts (code, discount_type, discount_value, min_order_value, usage_limit, valid_until, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $type, $value, $min_order, $usage_limit, $valid_until, $status]);
        $msg = "<div class='alert alert-success'>เพิ่มโค้ดส่วนลด <b>{$code}</b> สำเร็จ!</div>";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Error Code 23000 คือข้อมูลซ้ำ (Duplicate)
            $msg = "<div class='alert alert-danger'>เกิดข้อผิดพลาด: โค้ด <b>{$code}</b> มีอยู่ในระบบแล้ว!</div>";
        } else {
            $msg = "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
        }
    }
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>เพิ่มโค้ดส่วนลด - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans Thai', sans-serif; background: #f0f2f5; margin: 0; color: #333; }
    .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
    .card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    h2 { margin: 0 0 20px; color: #0b2f4a; border-left: 4px solid #1677ff; padding-left: 12px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 5px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .btn-save { background: #1677ff; color: white; border: none; padding: 12px; border-radius: 6px; width: 100%; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: 10px; }
    .btn-save:hover { background: #0958d9; }
    .btn-back { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-weight: 600; }
    .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
    .alert-success { background: #f6ffed; color: #52c41a; border: 1px solid #b7eb8f; }
    .alert-danger { background: #fff1f0; color: #f5222d; border: 1px solid #ffa39e; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  <div class="container">
    <a href="admin-edit-code-discount.php" class="btn-back">← กลับไปหน้าจัดการโค้ด</a>
    <?php echo $msg; ?>
    <div class="card">
        <h2>สร้างโค้ดส่วนลดใหม่</h2>
        <form method="POST">
            <div class="form-group">
                <label>ชื่อโค้ดส่วนลด (ตัวพิมพ์ใหญ่และตัวเลข เช่น SUMMER2026)</label>
                <input type="text" name="code" class="form-control" required style="text-transform: uppercase;">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>ประเภทส่วนลด</label>
                    <select name="discount_type" class="form-control" required>
                        <option value="percentage">เปอร์เซ็นต์ (%)</option>
                        <option value="fixed">ลดเป็นจำนวนเงิน (บาท)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>มูลค่าส่วนลด (ตัวเลข)</label>
                    <input type="number" step="0.01" name="discount_value" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>ยอดสั่งซื้อขั้นต่ำ (บาท)</label>
                    <input type="number" step="0.01" name="min_order_value" class="form-control" value="0">
                </div>
                <div class="form-group">
                    <label>จำกัดจำนวนการใช้ (ปล่อยว่างถ้าไม่จำกัด)</label>
                    <input type="number" name="usage_limit" class="form-control">
                </div>
                <div class="form-group">
                    <label>วันหมดอายุ (ปล่อยว่างถ้าไม่มีวันหมดอายุ)</label>
                    <input type="datetime-local" name="valid_until" class="form-control">
                </div>
                <div class="form-group">
                    <label>สถานะ</label>
                    <select name="status" class="form-control">
                        <option value="active">เปิดใช้งาน</option>
                        <option value="inactive">ปิดใช้งาน</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_discount" class="btn-save">+ เพิ่มโค้ดส่วนลด</button>
        </form>
    </div>
  </div>
</body>
</html>