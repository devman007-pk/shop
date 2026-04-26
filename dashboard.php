<?php
// dashboard.php - หน้าจัดการบัญชีลูกค้า (User Dashboard)
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

// เช็คว่าล็อกอินหรือยัง ถ้ายังไม่ได้ล็อกอิน ให้เด้งไปหน้า login.php
if (!isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['user_name'];

// ฟังก์ชันจำลองการดึงประวัติการสั่งซื้อ (ในอนาคตคุณสามารถเชื่อม SQL ดึงข้อมูลจากตาราง orders ได้)
$orderStatus = "ยังไม่มีคำสั่งซื้อที่อยู่ระหว่างดำเนินการ";
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Dashboard - <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    .dashboard-page { padding: 48px 0; min-height: 60vh; background: #f9fbff; }
    .dashboard-layout { display: grid; grid-template-columns: 280px 1fr; gap: 32px; align-items: start; }
    
    /* เมนูด้านซ้าย */
    .dashboard-menu { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 12px 32px rgba(9,30,45,0.04); border: 1px solid rgba(11,47,74,0.03); }
    .dashboard-menu h3 { margin-top: 0; margin-bottom: 20px; font-weight: 800; color: var(--navy); font-size: 1.1rem; display: flex; align-items: center; gap: 8px; }
    .menu-list { list-style: none; padding: 0; margin: 0; }
    .menu-list li { margin-bottom: 10px; }
    .menu-list a { display: flex; align-items: center; padding: 12px 16px; text-decoration: none; color: var(--navy); font-weight: 700; border-radius: 10px; transition: all 0.2s ease; }
    .menu-list a:hover, .menu-list a.active { background: #f0f7ff; color: var(--blue); }
    .menu-list a.logout { color: #ff4d4f; margin-top: 20px; border-top: 1px dashed #eee; border-radius: 0; padding-top: 20px; }
    
    /* เนื้อหาด้านขวา */
    .dashboard-content { background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 12px 32px rgba(9,30,45,0.04); border: 1px solid rgba(11,47,74,0.03); }
    .dashboard-content h2 { margin-top: 0; font-weight: 900; color: var(--navy); border-bottom: 2px solid #f0f4f8; padding-bottom: 16px; margin-bottom: 28px; }
    
    .card-stat { background: linear-gradient(145deg, #ffffff, #f6faff); padding: 24px; border-radius: 14px; border: 1px solid rgba(30,144,255,0.08); }
    .card-stat h4 { margin: 0 0 12px 0; color: var(--navy); font-weight: 800; display: flex; align-items: center; gap: 8px; }
    .card-stat p { margin: 0; color: var(--muted); font-weight: 600; line-height: 1.5; }

    /* ปรับแต่งไอคอนให้สีเข้ากับหัวข้อ */
    .icon-primary { color: var(--blue); }
    .icon-warning { color: #d97706; }

    @media (max-width: 900px) {
        .dashboard-layout { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container dashboard-page">
    <div class="dashboard-layout">
      
      <aside class="dashboard-menu">
        <h3>
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-primary"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          สวัสดี, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
        </h3>
        <ul class="menu-list">
          <li><a href="dashboard.php" class="active">หน้าจัดการบัญชี</a></li>
          <li><a href="edit-profile.php">แก้ไขข้อมูลส่วนตัว</a></li>
          <li><a href="order.php">ประวัติการสั่งซื้อ</a></li>
          <li><a href="change-password.php">เปลี่ยนรหัสผ่าน</a></li>
          <li><a href="logout.php" class="logout">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            ออกจากระบบ
          </a></li>
        </ul>
      </aside>

      <div class="dashboard-content">
        <h2>ภาพรวมบัญชี</h2>
        <p style="font-size: 1.05rem; color: #555; margin-bottom: 30px;">ยินดีต้อนรับกลับมา! คุณสามารถติดตามสถานะการจัดส่งและจัดการความปลอดภัยของบัญชีได้จากหน้านี้</p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
            <div class="card-stat">
                <h4>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-primary"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                  สถานะคำสั่งซื้อล่าสุด
                </h4>
                <p><?php echo $orderStatus; ?></p>
                <a href="order.php" style="display:inline-block; margin-top:12px; font-size:0.9rem; color:var(--blue); text-decoration:none; font-weight:700;">ดูประวัติทั้งหมด →</a>
            </div>

            <div class="card-stat">
                <h4>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-primary"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                  ข้อมูลการจัดส่ง
                </h4>
                <p>คุณยังไม่ได้ระบุที่อยู่เริ่มต้นสำหรับจัดส่งสินค้า</p>
                <a href="edit-profile.php" style="display:inline-block; margin-top:12px; font-size:0.9rem; color:var(--blue); text-decoration:none; font-weight:700;">เพิ่มที่อยู่ตอนนี้ →</a>
            </div>
        </div>

        <div style="margin-top: 40px; padding: 24px; background: #fff9f0; border-radius: 14px; border: 1px solid #ffe8cc;">
            <h4 style="margin:0 0 10px 0; color: #855d10; display: flex; align-items: center; gap: 8px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-warning"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
              ความปลอดภัยของบัญชี
            </h4>
            <p style="margin:0; color: #916a1c; font-size: 0.95rem; line-height: 1.6;">รหัสผ่านของคุณไม่ได้ถูกเปลี่ยนมานานแล้ว เพื่อความปลอดภัยสูงสุดแนะนำให้เปลี่ยนทุก 3 เดือน <br> <a href="change-password.php" style="color: #c27803; font-weight:800; text-decoration:underline;">เปลี่ยนรหัสผ่านที่นี่</a></p>
        </div>
      </div>

    </div>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  <script src="script.js"></script>
</body>
</html>