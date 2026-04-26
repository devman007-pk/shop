<?php
// admin-navbar.php - แถบเมนูด้านบนสำหรับระบบหลังบ้าน (ฉบับปรับปรุงลิงก์ Account)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ดึงชื่อจาก Session ถ้าไม่มีให้ขึ้นว่า Admin
$adminNameNav = $_SESSION['user_name'] ?? 'Admin';

// ป้องกันการประกาศฟังก์ชัน h_nav ซ้ำ
if (!function_exists('h_nav')) {
    function h_nav($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
?>
<style>
  /* =========================================
     CSS สำหรับ Admin Navbar
     ========================================= */
  .admin-navbar {
      background-color: #1890ff; /* สีน้ำเงินสว่าง */
      height: 60px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 20px;
      color: white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      font-family: 'Noto Sans Thai', sans-serif;
      position: sticky;
      top: 0;
      z-index: 1000;
  }
  .admin-navbar * { box-sizing: border-box; }
  .admin-navbar-brand { font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
  
  .admin-navbar-actions { display: flex; gap: 10px; align-items: center; }
  .btn-nav {
      background: white;
      color: #333;
      border: 1px solid #d9d9d9;
      padding: 6px 15px;
      border-radius: 4px;
      font-size: 0.9rem;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      transition: all 0.2s;
  }
  .btn-nav-blue { background: #1677ff; color: white; border: 1px solid #1677ff; }
  .btn-nav-red { background: #ff4d4f; color: white; border: none; }
  
  .btn-nav:hover { opacity: 0.85; transform: translateY(-1px); }
</style>

<nav class="admin-navbar">
  <div class="admin-navbar-brand">ผู้ดูแลรวม - OTM Shop</div>
  <div class="admin-navbar-actions">
      <a href="admin-panel.php" class="btn-nav">กลับหน้าหลัก</a>
      
      <a href="admin-dashboard.php" class="btn-nav btn-nav-blue">
          สวัสดี, <?php echo h_nav($adminNameNav); ?>
      </a>
      
      <a href="admin-logout.php" class="btn-nav btn-nav-red">ออกจากระบบ</a>
  </div>
</nav>