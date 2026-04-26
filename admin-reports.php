<?php
// admin-reports.php - หน้ารายงานสรุปยอดขาย (อัปเดตระบบกรองช่วงเวลา เดือน-ปี)
session_start();
require_once __DIR__ . '/config.php';

// 1. ตรวจสอบสิทธิ์แอดมิน
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: admin-login.php"); 
    exit;
}

$pdo = getPDO();

$months_th = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];

// 2. รับค่าการกรองวันที่ (แบบช่วงเวลา)
// ถ้าไม่มีการระบุมา ให้ตั้งค่าเริ่มต้นเป็น "เดือนปัจจุบันถึงเดือนปัจจุบัน"
$start_month = str_pad($_GET['start_month'] ?? date('m'), 2, '0', STR_PAD_LEFT);
$start_year = $_GET['start_year'] ?? date('Y');

$end_month = str_pad($_GET['end_month'] ?? $start_month, 2, '0', STR_PAD_LEFT);
$end_year = $_GET['end_year'] ?? $start_year;

// สร้างวันที่สำหรับ SQL (เริ่มตั้งแต่วันที่ 1 ของเดือนเริ่มต้น จนถึงวันสุดท้ายของเดือนสิ้นสุด)
$start_date = "{$start_year}-{$start_month}-01";
// หาวันสุดท้ายของเดือนสิ้นสุด
$end_date = date('Y-m-t', strtotime("{$end_year}-{$end_month}-01"));

// สร้างข้อความแสดงช่วงเวลา
$period_text = "{$months_th[(int)$start_month]} " . ($start_year + 543);
if ($start_month != $end_month || $start_year != $end_year) {
    $period_text .= " - {$months_th[(int)$end_month]} " . ($end_year + 543);
}

// ---------------------------------------------------------
// 3. ระบบดึงข้อมูล (เตรียมไว้สำหรับทั้งหน้าเว็บและส่งออกไฟล์)
// ---------------------------------------------------------
try {
    // ดึงข้อมูลยอดขายแยกตามวัน ในช่วงเวลาที่เลือก (เฉพาะสถานะที่จ่ายเงินแล้ว)
    $stmtDaily = $pdo->prepare("
        SELECT DATE(created_at) as sale_date, SUM(total_amount) as daily_total, COUNT(*) as order_count 
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ? 
          AND status IN ('completed', 'paid', 'shipped', 'processing')
        GROUP BY DATE(created_at)
        ORDER BY sale_date DESC
    ");
    $stmtDaily->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $dailySales = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

    // ยอดสรุปภาพรวมทั้งหมดที่เคยขายได้
    $totalSales = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status IN ('completed', 'paid', 'shipped', 'processing')")->fetchColumn() ?: 0;
    
    // ยอดสรุปในช่วงเวลาที่เลือก
    $rangeSalesStmt = $pdo->prepare("
        SELECT SUM(total_amount) 
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ? 
          AND status IN ('completed', 'paid', 'shipped', 'processing')
    ");
    $rangeSalesStmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $rangeTotal = $rangeSalesStmt->fetchColumn() ?: 0;
    
    // ยอดที่รอตรวจสอบทั้งหมด
    $pendingReview = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'payment_review'")->fetchColumn() ?: 0;

} catch (Exception $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}

// ---------------------------------------------------------
// 4. ระบบส่งออกไฟล์ (Export to CSV)
// ---------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename_period = ($start_month == $end_month && $start_year == $end_year) 
                        ? $months_th[(int)$start_month] . "_" . ($start_year + 543)
                        : $months_th[(int)$start_month] . ($start_year + 543) . "_to_" . $months_th[(int)$end_month] . ($end_year + 543);
    
    $filename = "sales_report_" . $filename_period . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '";');
    
    $output = fopen('php://output', 'w');
    // ใส่ BOM เพื่อให้ Excel เปิดภาษาไทยได้ถูกต้อง
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // หัวตาราง
    fputcsv($output, ['รายงานยอดขายประจำช่วงเวลา', $period_text]);
    fputcsv($output, []); // บรรทัดว่าง
    fputcsv($output, ['วันที่', 'จำนวนออเดอร์', 'ยอดขายรวม (บาท)']);
    
    // ข้อมูลรายวัน
    foreach ($dailySales as $row) {
        fputcsv($output, [
            date('d/m/Y', strtotime($row['sale_date'])),
            $row['order_count'],
            number_format((float)$row['daily_total'], 2, '.', '')
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['ยอดขายรวมตลอดช่วงเวลา', '', number_format((float)$rangeTotal, 2, '.', '')]);
    
    fclose($output);
    exit;
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>รายงานยอดขาย - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans Thai', sans-serif; background: #f0f2f5; margin: 0; color: #333; }
    .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
    
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    h2 { margin: 0; color: #0b2f4a; font-weight: 800; }

    .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: #fff; padding: 25px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border-left: 6px solid #1677ff; }
    .stat-card.sales { border-left-color: #52c41a; }
    .stat-card.pending { border-left-color: #722ed1; }
    
    .stat-label { color: #8c8c8c; font-size: 0.9rem; font-weight: 600; margin-bottom: 8px; }
    .stat-value { font-size: 1.6rem; font-weight: 800; color: #0b2f4a; }
    .stat-unit { font-size: 1rem; color: #8c8c8c; margin-left: 5px; }

    /* ฟอร์มตัวกรองและปุ่มส่งออก */
    .action-bar { display: flex; justify-content: space-between; align-items: stretch; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
    .filter-card { background: #fff; padding: 20px; border-radius: 12px; display: flex; gap: 25px; align-items: flex-end; flex-wrap: wrap; box-shadow: 0 4px 12px rgba(0,0,0,0.03); flex: 1; }
    
    .filter-group-wrap { display: flex; gap: 15px; align-items: center; padding: 10px; background: #f9f9f9; border-radius: 8px; border: 1px solid #eee; }
    .filter-group-wrap .group-label { font-weight: 700; color: #0b2f4a; font-size: 0.9rem; white-space: nowrap; }
    
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label { font-size: 0.8rem; font-weight: 600; color: #666; }
    .form-control { padding: 8px 12px; border-radius: 6px; border: 1px solid #d9d9d9; font-family: inherit; min-width: 120px; }
    
    .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 0.95rem; height: 42px; box-sizing: border-box; }
    .btn-filter { background: #1677ff; color: #fff; }
    .btn-filter:hover { background: #0958d9; }
    
    .btn-export { background: #1f8b50; color: #fff; box-shadow: 0 4px 12px rgba(31,139,80,0.2); align-self: flex-end; }
    .btn-export:hover { background: #156d3e; transform: translateY(-2px); }

    .table-card { background: #fff; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }
    .table-title { font-size: 1.15rem; font-weight: 800; color: #0b2f4a; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; }
    th { background: #fafafa; font-weight: 700; color: #555; }
    tr:hover { background: #fdfdfd; }
    
    .btn-back { color: #1677ff; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 20px; }
    
    @media (max-width: 900px) { 
        .filter-card { flex-direction: column; align-items: stretch; gap: 15px; } 
        .filter-group-wrap { flex-direction: column; align-items: stretch; }
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/admin-navbar.php'; ?>

  <div class="container">
    <a href="admin-order.php" class="btn-back">← กลับไปหน้าจัดการคำสั่งซื้อ</a>
    
    <div class="page-header">
        <h2>รายงานยอดขายและการเติบโต</h2>
    </div>

    <div class="report-grid">
        <div class="stat-card sales">
            <div class="stat-label">ยอดขายรวมช่วงเวลาที่เลือก</div>
            <div class="stat-value" style="color:#52c41a;"><?php echo number_format($rangeTotal, 2); ?><span class="stat-unit">฿</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">ยอดขายรวมทั้งหมดตั้งแต่เปิดร้าน</div>
            <div class="stat-value"><?php echo number_format($totalSales, 2); ?><span class="stat-unit">฿</span></div>
        </div>
        <div class="stat-card pending">
            <div class="stat-label">ยอดเงินรอการตรวจสอบสลิป</div>
            <div class="stat-value" style="color:#722ed1;"><?php echo number_format($pendingReview, 2); ?><span class="stat-unit">฿</span></div>
        </div>
    </div>

    <div class="action-bar">
        <form method="GET" class="filter-card">
            
            <div class="filter-group-wrap">
                <div class="group-label">ตั้งแต่ :</div>
                <div class="form-group">
                    <label>เดือน</label>
                    <select name="start_month" class="form-control">
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?php echo sprintf("%02d", $m); ?>" <?php echo ($m == $start_month) ? "selected" : ""; ?>>
                                <?php echo $months_th[$m]; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>ปี (พ.ศ.)</label>
                    <select name="start_year" class="form-control">
                        <?php
                        $currentYear = (int)date('Y');
                        for($y = $currentYear + 1; $y >= 2026; $y--) {
                            $sel = ($y == $start_year) ? "selected" : "";
                            echo "<option value='$y' $sel>".($y+543)."</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="filter-group-wrap">
                <div class="group-label">ถึง :</div>
                <div class="form-group">
                    <label>เดือน</label>
                    <select name="end_month" class="form-control">
                        <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?php echo sprintf("%02d", $m); ?>" <?php echo ($m == $end_month) ? "selected" : ""; ?>>
                                <?php echo $months_th[$m]; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>ปี (พ.ศ.)</label>
                    <select name="end_year" class="form-control">
                        <?php
                        for($y = $currentYear + 1; $y >= 2026; $y--) {
                            $sel = ($y == $end_year) ? "selected" : "";
                            echo "<option value='$y' $sel>".($y+543)."</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-filter">ค้นหาข้อมูล</button>
        </form>

        <a href="admin-reports.php?start_month=<?php echo $start_month; ?>&start_year=<?php echo $start_year; ?>&end_month=<?php echo $end_month; ?>&end_year=<?php echo $end_year; ?>&export=csv" class="btn btn-export">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            ส่งออกไฟล์ CSV
        </a>
    </div>

    <div class="table-card">
        <div class="table-title">สรุปยอดขายรายวัน ประจำช่วงเวลา: <span style="color:#1677ff;"><?php echo $period_text; ?></span></div>
        <table>
            <thead>
                <tr>
                    <th>วันที่</th>
                    <th>จำนวนคำสั่งซื้อ</th>
                    <th style="text-align:right;">ยอดขายรวม (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($dailySales as $day): ?>
                <tr>
                    <td><b><?php echo date('d/m/Y', strtotime($day['sale_date'])); ?></b></td>
                    <td><?php echo number_format($day['order_count']); ?> รายการ</td>
                    <td style="text-align:right; font-weight:800; color:#52c41a;">
                        ฿<?php echo number_format($day['daily_total'], 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($dailySales)): ?>
                <tr>
                    <td colspan="3" style="text-align:center; padding:50px; color:#888;">
                        ไม่มีข้อมูลการขายในช่วงเวลาที่เลือก
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
  </div>
</body>
</html>