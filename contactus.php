<?php
// contactus.php - Contact page with QR (image/line.png)
// Updated: align company name with other lines, make email/phone same color and remove underline.
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ติดต่อเรา - บริษัท ออน ไทม์ แมนเนจเม้นท์ จำกัด</title>

  <link rel="stylesheet" href="styles.css" />
  <style>
    /* Page-scoped styling to avoid interfering with global CSS */
    .contact-page { --container-w:1180px; color: #0b2f4a; font-family: inherit; }

    /* Ensure main has spacing from navbar and footer */
    .contact-page main.container {
      padding-top: 48px;
      padding-bottom: 80px;
      box-sizing: border-box;
      width:94%;
      max-width:var(--container-w);
      margin:0 auto;
    }

    /* Header / contact block */
    .contact-hero {
      display:flex;
      gap:28px;
      align-items:flex-start;
      justify-content:center;
      text-align:left;
      margin-bottom:28px;
      flex-wrap:wrap;
    }

    /* Info column */
    .contact-hero .info {
      max-width:640px;
      line-height:1.9;
      text-align:left; /* ensure left alignment */
    }

    /* Company name: align with left edge of the info lines */
    .contact-hero .company-name {
      text-align:left;
      font-weight:800;
      margin:0 0 6px 0;
      font-size:1.12rem;
    }

    .contact-hero p {
      margin:0 0 8px;
      color:rgba(11,47,74,0.75);
    }

    /* Link styling: make email/phone same color as regular text and remove underline */
    .contact-hero .info a {
      color: rgba(11,47,74,0.75) !important; /* same muted color as other lines */
      text-decoration: none !important;
      border-bottom: none !important;
      font-weight: 600; /* slightly bolder so it's still readable as a link */
    }
    /* keep a subtle hover (no underline) */
    .contact-hero .info a:hover {
      color: var(--navy);
      text-decoration: none;
    }

    /* QR box center */
    .qr-wrap {
      display:flex;
      justify-content:center;
      align-items:center;
      margin:18px 0 28px;
      flex: 0 0 260px;
    }
    .qr-box {
      width:220px;
      height:220px;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:18px;
      border:6px solid #111;
      background:#fff;
      box-shadow: 0 8px 22px rgba(11,47,74,0.06);
    }
    .qr-box img { max-width:100%; max-height:100%; display:block; }

    /* thin divider */
    .contact-divider {
      border-top:1px solid rgba(11,47,74,0.06);
      margin:34px 0;
    }

    /* feature list under */
    .contact-features {
      display:grid;
      grid-template-columns:repeat(4, 1fr);
      gap:28px;
      margin-top:18px;
      align-items:start;
    }
    .feature {
      background:transparent;
      padding:8px 6px;
      text-align:left;
    }
    .feature h3 { margin:0 0 8px; font-size:1rem; font-weight:800; color:#0b2f4a; }
    .feature p { margin:0; color:rgba(11,47,74,0.65); font-size:0.95rem; line-height:1.6; }

    /* responsive */
    @media (max-width:1000px){
      .contact-hero { flex-direction:column; align-items:center; text-align:center; }
      .contact-hero .info { max-width:100%; text-align:center; }
      .contact-hero .company-name { text-align:center; }
      .qr-wrap { order: -1; } /* put QR above on small screens if preferred */
      .contact-features { grid-template-columns:repeat(2,1fr); }
      .qr-wrap { flex: 0 0 auto; }
    }
    @media (max-width:560px){
      .contact-features { grid-template-columns:1fr; }
      .qr-box { width:190px; height:190px; padding:14px; }
      .contact-hero h1 { font-size:1.1rem; }
      .contact-hero .company-name { font-size:1.02rem; }
      .contact-page main.container { padding-top:26px; padding-bottom:40px; }
    }
  </style>
</head>
<body class="contact-page">
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container">
    <div class="contact-hero" role="region" aria-label="ข้อมูลการติดต่อ">
      <div class="info">
        <div class="company-name">บริษัท ออน ไทม์ แมนเนจเม้นท์ จำกัด (สำนักงานใหญ่)</div>

        <p>เลขที่ 50/123 หมู่ที่ 7 ตำบล เนินพระ อำเภอ/เขต เมืองระยอง จังหวัด ระยอง 21000</p>
        <p>เลขที่ผู้เสียภาษี: 0215562008121</p>
        <p>Email: <a href="mailto:navapat@otm.co.th">navapat@otm.co.th</a></p>
        <p>โทร: <a href="tel:033013917">033-013917</a></p>
      </div>

      <div class="qr-wrap" aria-hidden="false">
        <div class="qr-box" role="img" aria-label="QR LINE: สแกนเพื่อติดต่อ">
          <?php if (file_exists(__DIR__ . '/image/line.png')): ?>
            <img src="image/line.png" alt="QR LINE">
          <?php else: ?>
            <!-- fallback placeholder -->
            <img src="https://via.placeholder.com/200?text=QR" alt="QR placeholder">
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="contact-divider" aria-hidden="true"></div>

    <section class="contact-features" aria-label="บริการของเรา">
      <div class="feature">
        <h3>Fiber Optic</h3>
        <p>บริการด้านสื่อสาร สายไฟเบอร์ออพติค</p>
      </div>

      <div class="feature">
        <h3>CCTV</h3>
        <p>ระบบกล้องวงจรปิด ออกแบบและติดตั้ง</p>
      </div>

      <div class="feature">
        <h3>ระบบเครือข่าย</h3>
        <p>ระบบเครือข่าย LAN, VLAN และออกแบบโครงสร้าง</p>
      </div>

      <div class="feature">
        <h3>WIFI Hotspot</h3>
        <p>ระบบไวไฟ สำหรับโรงแรม รีสอร์ต และหน่วยงาน</p>
      </div>
    </section>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
</body>
</html>