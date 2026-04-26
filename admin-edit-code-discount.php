<?php
// admin-edit-code-discount.php - จัดการและแก้ไขโค้ดส่วนลด (เวอร์ชันปรับปรุงดีไซน์)
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();
$msg = "";

// 1. ระบบลบข้อมูล
if (isset($_GET['delete'])) {
    try {
        $delId = (int)$_GET['delete'];
        $pdo->prepare("DELETE FROM discounts WHERE id = ?")->execute([$delId]);
        $msg = "<div class='alert alert-success'>ลบโค้ดส่วนลดเรียบร้อยแล้ว</div>";
    } catch (Exception $e) {
        $msg = "<div class='alert alert-danger'>ไม่สามารถลบได้: " . $e->getMessage() . "</div>";
    }
}

// 2. ระบบอัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_discount'])) {
    $id = (int)$_POST['id'];
    $code = strtoupper(trim($_POST['code']));
    $type = $_POST['discount_type'];
    $value = (float)$_POST['discount_value'];
    $min_order = (float)$_POST['min_order_value'] ?: 0;
    $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
    $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("UPDATE discounts SET code=?, discount_type=?, discount_value=?, min_order_value=?, usage_limit=?, valid_until=?, status=? WHERE id=?");
        $stmt->execute([$code, $type, $value, $min_order, $usage_limit, $valid_until, $status, $id]);
        $msg = "<div class='alert alert-success'>อัปเดตโค้ด <b>{$code}</b> สำเร็จ!</div>";
    } catch (PDOException $e) {
        $msg = "<div class='alert alert-danger'>เกิดข้อผิดพลาด: โค้ดนี้อาจซ้ำหรือข้อมูลไม่ถูกต้อง</div>";
    }
}

// 3. ดึงข้อมูลที่จะแก้ไข
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM discounts WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4. ดึงข้อมูลโค้ดทั้งหมด
$discounts = $pdo->query("SELECT * FROM discounts ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>จัดการโค้ดส่วนลด - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #1677ff; --success: #1f8b50; --warning: #faad14; --danger: #ff4d4f; --bg: #f0f2f5; --navy: #0b2f4a; }
    body { font-family: 'Noto Sans Thai', sans-serif; background: var(--bg); margin: 0; color: #333; }
    .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
    
    .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; border: 1px solid rgba(0,0,0,0.02); }
    .card-title { font-size: 1.3rem; font-weight: 800; color: var(--navy); margin: 0 0 20px 0; display: flex; align-items: center; gap: 10px; }
    
    /* สไตล์ฟอร์มแก้ไข */
    .edit-form-card { background: #fffbe6; border: 2px solid var(--warning); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-weight: 700; color: #555; font-size: 0.9rem; }
    .form-control { padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-family: inherit; font-size: 0.95rem; }
    .form-control:focus { border-color: var(--warning); outline: none; box-shadow: 0 0 0 3px rgba(250, 173, 20, 0.1); }
    
    .btn-save { background: var(--success); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 800; transition: 0.2s; box-shadow: 0 4px 10px rgba(31,139,80,0.2); }
    .btn-save:hover { transform: translateY(-2px); background: #156d3e; }
    .btn-cancel { color: #666; text-decoration: none; font-weight: 600; padding: 10px; }

    /* สไตล์ตาราง */
    .action-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .btn-add-new { background: var(--primary); color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; font-weight: 800; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
    .btn-add-new:hover { background: #0958d9; transform: translateY(-2px); }

    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 14px; text-align: left; border-bottom: 1px solid #f0f0f0; }
    th { background: #fafafa; font-weight: 700; color: #555; font-size: 0.9rem; }
    
    .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; color: #fff; display: inline-block; }
    .bg-green { background: var(--success); } .bg-red { background: var(--danger); }
    
    .btn-action { padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 700; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px; }
    .btn-edit { background: #e6f7ff; color: var(--primary); border: 1px solid #91caff; margin-right: 5px; }
    .btn-edit:hover { background: #bae0ff; }
    .btn-delete { background: #fff1f0; color: var(--danger); border: 1px solid #ffa39e; }
    .btn-delete:hover { background: #ffccc7; }

    .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; text-align: center; font-weight: 700; }
    .alert-success { background: #f6ffed; color: var(--success); border: 1px solid #b7eb8f; }
    .alert-danger { background: #fff1f0; color: var(--danger); border: 1px solid #ffa39e; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>
  <div class="container">
    <?php echo $msg; ?>

    <?php if ($editData): ?>
    <div class="card edit-form-card">
        <h2 class="card-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            แก้ไขโค้ดส่วนลด: <?php echo h($editData['code']); ?>
        </h2>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $editData['id']; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>ชื่อโค้ด (Code)</label>
                    <input type="text" name="code" class="form-control" value="<?php echo h($editData['code']); ?>" required>
                </div>
                <div class="form-group">
                    <label>ประเภทส่วนลด</label>
                    <select name="discount_type" class="form-control">
                        <option value="percentage" <?php echo $editData['discount_type']=='percentage'?'selected':''; ?>>เปอร์เซ็นต์ (%)</option>
                        <option value="fixed" <?php echo $editData['discount_type']=='fixed'?'selected':''; ?>>จำนวนเงิน (บาท)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>มูลค่าส่วนลด</label>
                    <input type="number" step="0.01" name="discount_value" class="form-control" value="<?php echo h($editData['discount_value']); ?>" required>
                </div>
                <div class="form-group">
                    <label>ยอดสั่งซื้อขั้นต่ำ (บาท)</label>
                    <input type="number" step="0.01" name="min_order_value" class="form-control" value="<?php echo h($editData['min_order_value']); ?>">
                </div>
                <div class="form-group">
                    <label>จำกัดจำนวนการใช้ (ครั้ง)</label>
                    <input type="number" name="usage_limit" class="form-control" value="<?php echo h($editData['usage_limit'] ?? ''); ?>" placeholder="ไม่ระบุ = ไม่จำกัด">
                </div>
                <div class="form-group">
                    <label>วันหมดอายุ</label>
                    <input type="datetime-local" name="valid_until" class="form-control" value="<?php echo $editData['valid_until'] ? date('Y-m-d\TH:i', strtotime($editData['valid_until'])) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>สถานะ</label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo $editData['status']=='active'?'selected':''; ?>>เปิดใช้งาน (Active)</option>
                        <option value="inactive" <?php echo $editData['status']=='inactive'?'selected':''; ?>>ปิดใช้งาน (Inactive)</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <button type="submit" name="update_discount" class="btn-save">บันทึกการแก้ไข</button>
                <a href="admin-edit-code-discount.php" class="btn-cancel">ยกเลิก</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="action-header">
            <h2 class="card-title" style="margin-bottom:0;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                รายการโค้ดส่วนลดทั้งหมด
            </h2>
            <a href="admin-code-discount.php" class="btn-add-new">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                สร้างโค้ดใหม่
            </a>
        </div>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>โค้ด</th>
                        <th>ส่วนลด</th>
                        <th>ขั้นต่ำ</th>
                        <th>ใช้ไป / สิทธิ์</th>
                        <th>วันหมดอายุ</th>
                        <th>สถานะ</th>
                        <th style="text-align: center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($discounts as $d): ?>
                    <tr>
                        <td><strong style="color:var(--navy); font-size:1.1rem; letter-spacing:1px;"><?php echo h($d['code']); ?></strong></td>
                        <td style="font-weight:700; color:var(--primary);">
                            <?php echo floatval($d['discount_value']); ?><?php echo $d['discount_type'] === 'percentage' ? '%' : ' ฿'; ?>
                        </td>
                        <td><?php echo number_format($d['min_order_value'], 2); ?> ฿</td>
                        <td style="font-weight:600;">
                            <?php echo $d['used_count']; ?> / <span style="color:#888;"><?php echo $d['usage_limit'] ? $d['usage_limit'] : '∞'; ?></span>
                        </td>
                        <td>
                            <?php 
                                if($d['valid_until']) {
                                    $isExpired = strtotime($d['valid_until']) < time();
                                    echo $isExpired ? "<span style='color:var(--danger); font-weight:700;'>หมดอายุแล้ว</span>" : date('d/m/Y H:i', strtotime($d['valid_until']));
                                } else {
                                    echo "<span style='color:#999;'>ไม่มีกำหนด</span>";
                                }
                            ?>
                        </td>
                        <td>
                            <?php if ($d['status'] === 'active'): ?>
                                <span class="badge bg-green">เปิดใช้งาน</span>
                            <?php else: ?>
                                <span class="badge bg-red">ปิดใช้งาน</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            <a href="?edit=<?php echo $d['id']; ?>" class="btn-action btn-edit">แก้ไข</a>
                            <a href="?delete=<?php echo $d['id']; ?>" class="btn-action btn-delete" onclick="return confirm('ยืนยันการลบโค้ด [<?php echo h($d['code']); ?>] หรือไม่?');">ลบ</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($discounts)): ?>
                    <tr><td colspan="7" style="text-align:center; padding: 50px; color:#888;">ยังไม่มีการสร้างโค้ดส่วนลดในระบบ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
  </div>
</body>
</html>