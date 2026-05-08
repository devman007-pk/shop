<?php
// navbar.php - header / nav with site-wide font loading
// Place next to index.php and include it as: include __DIR__ . '/navbar.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$cartCount = isset($_SESSION['cart_count']) ? (int)$_SESSION['cart_count'] : 0;
$wishCount = isset($_SESSION['wish_count']) ? (int)$_SESSION['wish_count'] : 0;

// --- โค้ดที่แก้ไขใหม่: ให้รับรู้เฉพาะลูกค้าเท่านั้น ---
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? '';
$rawUsername = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') : 'Guest';

// เช็คว่าเป็นลูกค้า (customer) เท่านั้นถึงจะโชว์ชื่อ
if ($isLoggedIn && $userRole === 'customer') {
    $accountLink = 'dashboard.php';
    $displayName = "สวัสดี, " . $rawUsername;
} else {
    // ถ้ายังไม่ล็อกอิน หรือ "เป็นแอดมินแอบเข้ามาดูหน้าร้าน" ให้โชว์เป็น Guest
    $accountLink = 'login.php';
    $displayName = "Guest";
}
// ------------------------------------------

function nav_active(string $file): bool {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $current = basename($path);
    if ($current === '') $current = 'index.php';

    if (stripos($file, '.php') !== false) {
        return strcasecmp($current, $file) === 0;
    }
    return (stripos($current, $file) !== false) || (stripos($path, $file) !== false);
}

function nav_class(string $file, string $extra = ''): string {
    $active = nav_active($file) ? ' active' : '';
    $classes = trim($extra . $active);
    return $classes ? ' class="' . $classes . '"' : '';
}

// =========================================
//  ดึงข้อมูล Tags (หมวดหมู่ และ แบรนด์) สำหรับ Search Dropdown
// =========================================
$navTags = ['category' => [], 'brand' => []];
if (function_exists('getPDO')) {
    try {
        $pdoTemp = getPDO();
        $stmtTags = $pdoTemp->query("SELECT tag_group, tag_value FROM product_tags WHERE tag_group IN ('category', 'brand') GROUP BY tag_group, tag_value ORDER BY tag_value ASC");
        while ($row = $stmtTags->fetch(PDO::FETCH_ASSOC)) {
            $navTags[$row['tag_group']][] = $row['tag_value'];
        }
    } catch (Exception $e) {}
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;700;900&family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">

<style>
/* Set site font loaded above as the global font (will apply to all pages that include this navbar) */
:root {
  --site-font: "Noto Sans Thai", "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
  --navy: #0b2f4a;
  --blue: #1e90ff;
  --teal: #2bb673;
  --card: #ffffff;
  --muted: rgba(11,47,74,0.6);
  --accent-gradient: linear-gradient(180deg, rgba(30,144,255,0.06), rgba(43,182,115,0.03));
  --radius: 12px;
  --transition: 180ms cubic-bezier(.2,.9,.2,1);
}

/* Force the site font globally so header/nav keep consistent across pages */
html, body {
  font-family: var(--site-font) !important;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* Topbar / header */
.site-header { position: relative; z-index: 200; }
.top-bar {
  background: rgba(255,255,255,0.98);
  border-bottom: 1px solid rgba(11,47,74,0.04);
  box-shadow: 0 6px 18px rgba(9,30,45,0.03);
}
.top-inner {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  padding:10px 0;
}

/* Container helper - safe minimal width when used here */
.container { width:94%; max-width:1300px; margin:0 auto; box-sizing:border-box; }

/* Logo */
.logo { display:inline-block; }
.logo-img { height:48px; width:auto; display:block; object-fit:contain; max-width:220px; }

/* Search box */
.search-form{
  flex:1 1 560px;
  min-width:220px;
  max-width:720px;
  display:flex;
  align-items:center;
  gap:10px;
  padding:10px 12px;
  background:#f6fbff;
  border-radius:24px;
  border:1px solid rgba(11,47,74,0.06);
  box-shadow: 0 6px 20px rgba(9,30,45,0.03);
  transition: box-shadow var(--transition), border-color var(--transition);
}
.search-form input[type="search"]{
  flex:1;
  border:0;
  background:transparent;
  padding:8px 10px;
  font-size:0.95rem;
  color:var(--navy);
  outline:none;
}
.search-form button{
  width:44px;height:44px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;
  border:1px solid rgba(11,47,74,0.06);background:#ffffff;cursor:pointer;
  box-shadow: 0 6px 16px rgba(9,30,45,0.04);padding:0;transition: transform 140ms ease, box-shadow 140ms ease;
}

/* Action items (cart, wish, account) */
.actions-box{ display:flex; gap:12px; align-items:center; white-space:nowrap; }
.actions-box.boxed { background: rgba(255,255,255,0.98); border-radius:12px; padding:6px; box-shadow: 0 8px 28px rgba(9,30,45,0.04); border:1px solid rgba(11,47,74,0.06); }
.action-item{ background:#ffffff; padding:10px 14px; border-radius:12px; display:inline-flex; align-items:center; gap:12px; min-width:140px; height:60px; box-shadow: 0 10px 30px rgba(9,30,45,0.06); border:0; cursor:pointer; position:relative; }
.action-item .icon{ width:44px; height:44px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; background:linear-gradient(180deg,#ffffff,#f7fbff); color:var(--navy); }
.action-item .meta{ display:flex; flex-direction:column; line-height:1; }
.action-item .meta .small{ font-weight:800; font-size:0.92rem; color:var(--navy); }
.action-item .meta .price, .action-item .meta .muted{ font-size:0.78rem; color:#66797f; }
.count-badge{ position:absolute; top:8px; left:8px; min-width:18px; height:18px; padding:0 6px; border-radius:9px; background:var(--navy); color:#fff; font-size:0.72rem; display:inline-flex; align-items:center; justify-content:center; box-shadow:0 6px 16px rgba(9,30,45,0.12); border:2px solid #fff; }

/* Navigation */
.nav-bar{
  position:relative;
  z-index:30;
  background:var(--card);
  box-shadow:0 6px 18px rgba(9,30,45,0.06);
  border-top-left-radius:10px;
  border-top-right-radius:10px;
  margin-top:10px;
}
.nav-inner{ padding:10px 0; display:flex; justify-content:center; }
.nav-list{ list-style:none; display:flex; gap:26px; align-items:center; margin:0; padding:0; }
.nav-list li{ position:relative; }
.nav-list a{
  color:var(--navy);
  text-decoration:none;
  padding:8px 12px;
  border-radius:8px;
  font-weight:700;
  transition: all var(--transition);
  display:inline-flex;
  align-items:center;
  gap:8px;
}
/* active / hover */
.nav-list a:hover, .nav-list a:focus, .nav-list a.active {
  background: var(--accent-gradient);
  padding:8px 16px;
  box-shadow: 0 6px 18px rgba(9,30,45,0.03);
  outline: none;
}

/* Force consistent look: higher specificity to avoid overrides */
.site-header .nav-bar .nav-list a,
.site-header .nav-bar .nav-list a.active,
.site-header .nav-bar .nav-list a:hover {
  color: var(--navy) !important;
  border-radius:8px !important;
  font-weight:700 !important;
}

/* Accessibility focus */
.nav-list a:focus { outline: 3px solid rgba(30,144,255,0.12); outline-offset:2px; }

/* Responsive tweaks */
@media (max-width:900px){
  .nav-list { gap:14px; font-size:0.95rem; }
  .search-form{ max-width:55%; }
}
@media (max-width:600px){
  .nav-list { gap:10px; font-size:0.92rem; }
  .search-form{ display:block; width:100%; }
  .actions-box{ display:flex; gap:8px; flex-wrap:wrap; }
}

/* Small safety resets so pages with other styles won't push nav weirdly */
.site-header .top-bar, .site-header .nav-bar { margin-bottom:0 !important; }

/* =========================================
   FIX: คืนชีพปุ่มเมนู และ บังคับแสดงหัวใจที่รูปสินค้า
   ========================================= */

/* 1. คืนชีพปุ่ม My Wishlist บนแถบเมนู (Navbar) */
.site-header .actions-box .wish-btn {
  display: inline-flex !important;
  visibility: visible !important;
  opacity: 1 !important;
  position: relative !important; 
}

/* 2. จัดตำแหน่งปุ่มหัวใจให้อยู่มุมขวาบนของรูปสินค้า */
.product-card .product-thumb {
  position: relative !important;
}

.product-card .fav-btn {
  position: absolute !important;
  top: 10px !important;
  right: 10px !important;
  width: 36px !important;
  height: 36px !important;
  background-color: #ffffff !important;
  border: 1px solid rgba(0, 0, 0, 0.08) !important;
  border-radius: 50% !important;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1) !important;
  display: flex !important; /* บังคับให้ปุ่มแสดงผล */
  visibility: visible !important;
  align-items: center !important;
  justify-content: center !important;
  cursor: pointer !important;
  z-index: 99 !important;
  color: #999 !important;
  padding: 0 !important;
  margin: 0 !important;
  font-size: 18px !important; /* สำหรับแสดง ♡ รูปแบบข้อความ */
  transition: all 0.2s ease !important;
}

/* เอฟเฟกต์ตอนเอาเมาส์ชี้หัวใจ */
.product-card .fav-btn:hover {
  transform: scale(1.1) !important;
}

/* บังคับขนาดให้ SVG ที่ JS สร้างขึ้น */
.product-card .fav-btn svg {
  width: 18px !important;
  height: 18px !important;
  display: block !important;
}

/* =========================================
   FIX 3: ล็อกตำแหน่งตัวเลขและขนาดปุ่มให้เท่ากัน 100% ทุกปุ่ม
   ========================================= */

/* ล็อกระยะห่างด้านในของปุ่ม (กล่องสีขาว) ให้เท่ากันเป๊ะ */
.site-header .actions-box .action-item {
  position: relative !important;
  padding: 10px 14px !important;
  display: inline-flex !important;
  align-items: center !important;
}

/* ล็อกตำแหน่งตัวเลขแจ้งเตือน ให้อยู่มุมซ้ายบนของไอคอนพอดี */
.site-header .actions-box .action-item .count-badge {
  position: absolute !important;
  top: 8px !important;         /* ระยะจากขอบบน */
  left: 16px !important;       /* ระยะจากขอบซ้าย (ให้เกาะขอบไอคอนพอดี) */
  min-width: 20px !important;
  height: 20px !important;
  padding: 0 6px !important;
  border-radius: 10px !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  font-family: inherit !important;
  line-height: 1 !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  box-sizing: border-box !important;
  background: var(--navy) !important;
  color: #ffffff !important;
  border: 2px solid #ffffff !important;
  margin: 0 !important;
  transform: none !important;
  z-index: 10 !important;
}

/* =========================================
   สไตล์สำหรับ Dropdown ของช่องค้นหา
   ========================================= */
.search-tags-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    width: 100%;
    min-width: 320px;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    padding: 20px;
    z-index: 9999;
    display: none; /* ซ่อนไว้ก่อน */
    max-height: 400px;
    overflow-y: auto;
    text-align: left;
}
.search-tags-dropdown.active {
    display: block;
    animation: fadeInDown 0.2s ease-out;
}
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.tags-section { margin-bottom: 20px; }
.tags-section:last-child { margin-bottom: 0; }
.tags-section-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0b2f4a;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px dashed #eee;
    display: flex;
    align-items: center;
    gap: 8px;
}
.tags-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.tag-label { cursor: pointer; user-select: none; margin: 0; }
.tag-label input[type="checkbox"] { display: none; }
.tag-btn {
    display: inline-flex;
    padding: 6px 14px;
    background: #f0f2f5;
    color: #555;
    border-radius: 20px;
    font-size: 0.85rem;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}
.tag-label input[type="checkbox"]:checked + .tag-btn {
    background: #e6f4ff;
    color: #1677ff;
    border-color: #91caff;
    font-weight: 700;
    transform: scale(1.05);
}
</style>

<header class="site-header">
  <div class="top-bar">
    <div class="container top-inner">
      <a class="logo" href="index.php" aria-label="ไปยังหน้าแรก">
        <img src="logo/logo.jpg" alt="BrandName" class="logo-img" />
      </a>

      <form class="search-form" action="shop.php" method="get" role="search" aria-label="ค้นหา" style="position:relative;">
        <input id="searchInput" name="q" type="search" placeholder="ค้นหา..." aria-label="ค้นหา" autocomplete="off" />
        <button id="searchBtn" type="submit" aria-label="ค้นหา">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="M21 21l-4.35-4.35" stroke="#0b2f4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="11" cy="11" r="6" stroke="#0b2f4a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </form>

      <div class="actions-box boxed" role="region" aria-label="Header actions">
        <button class="action-item cart-btn" aria-label="ตะกร้าสินค้า" type="button" onclick="location.href='cart.php'">
          <span class="count-badge cart-count"><?php echo $cartCount; ?></span>
          <span class="icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M6 6h14l-1.5 9h-11L6 6z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/> 
              <circle cx="10" cy="20" r="1.25" fill="currentColor"/>
              <circle cx="18" cy="20" r="1.25" fill="currentColor"/>
            </svg>
          </span>
          <span class="meta">
            <span class="small">My Cart</span>
            <span class="price">0.00 ฿</span>
          </span>
        </button>

        <button class="action-item wish-btn" aria-label="รายการโปรด" type="button" onclick="location.href='wishlist.php'">
          <span class="count-badge wish-count"><?php echo $wishCount; ?></span>
          <span class="icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M20.8 7.6c0 5.1-8.8 10.8-8.8 10.8S3.2 12.7 3.2 7.6a4.4 4.4 0 018.8-1 4.4 4.4 0 018.8 1z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span class="meta">
            <span class="small">My Wishlist</span>
            <span class="muted">View</span>
          </span>
        </button>

        <button class="action-item account-btn" aria-label="บัญชีผู้ใช้" type="button" onclick="location.href='<?php echo $accountLink; ?>'">
          <span class="icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="12" cy="7" r="3.2" stroke="currentColor" stroke-width="1.2"/>
            </svg>
          </span>
          <span class="meta">
            <span class="small"><?php echo $displayName; ?></span>
            <span class="muted">My Account</span>
          </span>
        </button>
      </div>
    </div>
  </div>

  <nav class="nav-bar" id="main-nav" aria-label="Primary">
    <div class="container nav-inner">
      <ul class="nav-list" role="menubar">
        <li role="none"><a role="menuitem" href="index.php"<?php echo nav_class('index.php','home'); ?>>หน้าแรก</a></li>
        <li role="none"><a role="menuitem" href="shop.php"<?php echo nav_class('shop.php'); ?>>ร้านค้า</a></li>
        <li role="none"><a role="menuitem" href="brand.php"<?php echo nav_class('brand.php'); ?>>แบรนด์</a></li>
        <li role="none"><a role="menuitem" href="aboutas.php"<?php echo nav_class('aboutas.php'); ?>>เกี่ยวกับเรา</a></li>
        <li role="none"><a role="menuitem" href="ourwork.php"<?php echo nav_class('ourwork.php'); ?>>ผลงานของเรา</a></li>
        <li role="none"><a role="menuitem" href="contactus.php"<?php echo nav_class('contactus.php'); ?>>ติดต่อเรา</a></li>
      </ul>
    </div>
  </nav>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchForm = document.querySelector('.search-form');
    if (!searchInput || !searchForm) return;

    // นำข้อมูล Tags จาก PHP มาใช้ใน JS
    const tagData = <?php echo json_encode($navTags); ?>;
    
    // สร้างกล่อง Dropdown
    const dropdown = document.createElement('div');
    dropdown.className = 'search-tags-dropdown';
    
    let html = '';
    
    // วนลูปสร้างปุ่ม: ประเภทสินค้า
    if (tagData.category && tagData.category.length > 0) {
        html += `<div class="tags-section">
                    <div class="tags-section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1677ff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                        เลือกหมวดหมู่สินค้า
                    </div>
                    <div class="tags-wrapper">`;
        tagData.category.forEach(cat => {
            html += `<label class="tag-label">
                        <input type="checkbox" name="category[]" value="${cat}">
                        <span class="tag-btn">${cat}</span>
                     </label>`;
        });
        html += `</div></div>`;
    }
    
    // วนลูปสร้างปุ่ม: แบรนด์
    if (tagData.brand && tagData.brand.length > 0) {
        html += `<div class="tags-section">
                    <div class="tags-section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#389e0d" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        เลือกแบรนด์
                    </div>
                    <div class="tags-wrapper">`;
        tagData.brand.forEach(brand => {
            html += `<label class="tag-label">
                        <input type="checkbox" name="brand[]" value="${brand}">
                        <span class="tag-btn">${brand}</span>
                     </label>`;
        });
        html += `</div></div>`;
    }
    
    if (html === '') {
        html = '<div style="color:#999; font-size:0.85rem; text-align:center;">ยังไม่มีแท็กข้อมูลในระบบ</div>';
    }
    
    dropdown.innerHTML = html;
    
    // นำ Dropdown ไปซ่อนไว้ในฟอร์ม
    searchForm.appendChild(dropdown);

    // เปิด Dropdown เมื่อคลิกที่ช่องค้นหา
    searchInput.addEventListener('focus', () => {
        dropdown.classList.add('active');
    });

    // ปิด Dropdown เมื่อคลิกที่พื้นที่อื่นนอกจากฟอร์มค้นหา
    document.addEventListener('click', (e) => {
        if (!searchForm.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });
});
</script>