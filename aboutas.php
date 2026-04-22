<?php
session_start();
// aboutas.php - Company profile page (เชื่อมต่อฐานข้อมูลแล้ว)

$about = null;
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    try {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT * FROM about_us WHERE id = 1");
        $about = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // กรณีมี Error ให้เงียบไว้แล้วไปใช้ Fallback ด้านล่าง
    }
}

// ข้อมูลสำรอง (Fallback) กรณีฐานข้อมูลไม่มีข้อมูล
if (!$about) {
    $about = [
        'company_name' => 'บริษัท ออน ไทม์ แมนเนจเม้นท์ จำกัด',
        'company_desc' => 'ผู้ให้บริการด้านสื่อสารและระบบเครือข่าย — ให้บริการในพื้นที่จังหวัดระยองและชลบุรี ด้วยทีมงานมืออาชีพที่พร้อมดูแลทั้งระบบ Fiber, CCTV, Network และ Wi‑Fi',
        'logo_url' => '',
        'slogan' => 'ความพึงพอใจของท่านคือบริการของเรา',
        'founded_info' => '4 กันยายน พ.ศ. 2562 ทุนจดทะเบียน 1,000,000 บาท',
        'executive_name' => 'คุณนวภัทร นาครัตน์',
        'employee_count' => 'ประจำ 6 คน ; ช่างรับเหมาช่วงประจำ 20 คน (4 ทีม)',
        'service_area' => 'จังหวัดระยอง, ชลบุรี และภาคตะวันออก',
        'address' => '50/123 หมู่7 ตำบลเนินพระ อำเภอเมืองระยอง จังหวัดระยอง 21000',
        'phone' => '033-013-917, 081-649-2504',
        'email' => 'navapat@otm.co.th',
        'portfolio_link' => 'ourwork.php'
    ];
}

// ฟังก์ชันสำหรับป้องกันตัวอักษรพิเศษ
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ประวัติบริษัท - <?php echo h($about['company_name']); ?></title>

  <link rel="stylesheet" href="styles.css" />
  <style>
    /* Namespaced styles for About page only */
    body.about-page {
      --bg-start: #f4fbff;
      --bg-end: #eef8f3;
      --card: #ffffff;
      --muted: rgba(11,47,74,0.6);
      --navy: #0b2f4a;
      --accent-1: #1188ff;
      --accent-2: #2bb673;
      --glass: rgba(255,255,255,0.65);
      --radius-lg: 18px;
      --radius-md: 10px;
      --container-w: 1180px;
      color: var(--navy);
      -webkit-font-smoothing:antialiased;
    }

    body.about-page .site-header + main.container,
    body.about-page > main.container {
      padding-top: 48px !important;
      padding-bottom: 96px !important;
      box-sizing: border-box !important;
      width: 94% !important;
      max-width: var(--container-w) !important;
      margin: 0 auto !important;
    }

    body.about-page .container{
      width:94%; max-width:var(--container-w); margin:0 auto; box-sizing:border-box;
    }
    body.about-page .hero-wrap { margin-top: 0 !important; position: relative; margin: 8px auto 6px; padding:36px 0 12px; }
    
    body.about-page .hero {
      background: linear-gradient(90deg, rgba(17,136,255,0.08), rgba(43,182,115,0.05));
      border-radius: 14px; padding:30px; display:flex; gap:20px; align-items:center;
      box-shadow: 0 18px 40px rgba(11,47,74,0.06); border: 1px solid rgba(11,47,74,0.03);
    }
    body.about-page .hero .left { flex:1; min-width:0; }
    body.about-page .hero h1 { margin:0 0 8px; font-size:1.6rem; font-weight:900; letter-spacing: -0.3px; color:var(--navy); }
    body.about-page .hero p { margin:0; color:var(--muted); font-size:1rem; line-height:1.6; white-space:pre-line;}

    body.about-page .hero .logo {
      width:120px;height:80px;border-radius:12px;background:linear-gradient(180deg,#fff,#f3fbff);display:flex;align-items:center;justify-content:center;border:1px solid rgba(11,47,74,0.04);
      box-shadow:0 8px 18px rgba(11,47,74,0.04); flex:0 0 120px; padding:8px;
    }
    body.about-page .hero .logo img{ max-width:100%; max-height:100%; object-fit:contain; display:block; }

    body.about-page .profile-grid { display:grid; grid-template-columns: 1fr 360px; gap:28px; margin-top:18px; align-items:start; }
    body.about-page .card { background:var(--card); border-radius:var(--radius-md); padding:20px; box-shadow:0 10px 30px rgba(11,47,74,0.04); border:1px solid rgba(11,47,74,0.03); }
    body.about-page .company-title { font-weight:800; font-size:1.05rem; margin-bottom:12px; color:var(--navy); }
    body.about-page .company-text { color:var(--muted); line-height:1.85; white-space:pre-line; font-size:0.98rem; }

    body.about-page .contact-card h4 { margin:0 0 10px; font-size:1rem; font-weight:800; color:var(--navy); }
    body.about-page .contact-item { display:flex; gap:12px; align-items:flex-start; margin:12px 0; color:var(--muted); font-size:0.95rem; }
    body.about-page .contact-item svg{ flex:0 0 20px; margin-top:3px; color:var(--accent-1); }

    body.about-page .cta {
      display:inline-block; margin-top:16px; padding:10px 14px; border-radius:10px;
      background:linear-gradient(90deg,var(--accent-1),var(--accent-2)); color:#fff;
      text-decoration:none; font-weight:700; box-shadow:0 8px 20px rgba(43,182,115,0.12);
    }

    body.about-page .services-area { margin-top:34px; }
    body.about-page .services-row { display:grid; grid-template-columns: repeat(4, 1fr); gap:18px; }
    body.about-page .service {
      background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(250,252,253,0.98));
      border-radius:12px; padding:16px; display:flex; gap:14px; align-items:flex-start;
      border:1px solid rgba(11,47,74,0.03); transition: transform .16s ease, box-shadow .16s ease;
    }
    body.about-page .service:hover { transform:translateY(-6px); box-shadow:0 22px 40px rgba(11,47,74,0.06); }
    body.about-page .service .icon { width:56px; height:56px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:linear-gradient(90deg,var(--accent-1),var(--accent-2)); color:#fff; font-weight:800; font-size:1.05rem; }
    body.about-page .service h3{ margin:0; font-size:1.02rem; color:var(--navy); font-weight:800; }
    body.about-page .service p{ margin:6px 0 0; color:var(--muted); font-size:0.95rem; line-height:1.6; }

    body.about-page .divider { height:1px; background: linear-gradient(90deg, rgba(11,47,74,0.03), rgba(11,47,74,0.09), rgba(11,47,74,0.03)); margin:22px 0; border-radius:2px; }
    body.about-page .page-accent {
      margin-top:40px; padding:34px 0 80px;
      background: radial-gradient( circle at 10% 20%, rgba(17,136,255,0.04), transparent 8%), radial-gradient( circle at 90% 80%, rgba(43,182,115,0.03), transparent 10% );
    }

    @media (max-width:1100px){ body.about-page .profile-grid{grid-template-columns:1fr 320px;} body.about-page .services-row{grid-template-columns:repeat(2,1fr);} }
    @media (max-width:760px){
      body.about-page .hero{flex-direction:column;align-items:flex-start;}
      body.about-page .profile-grid{grid-template-columns:1fr;}
      body.about-page .services-row{grid-template-columns:1fr;}
      body.about-page .hero .logo{width:96px;height:64px;}
      body.about-page .site-header + main.container, body.about-page > main.container { padding-top: 18px !important; padding-bottom: 48px !important; }
    }
    body.about-page a { color:var(--accent-1); text-decoration:none; }
    body.about-page a:hover { text-decoration:underline; }
  </style>
</head>
<body class="about-page">
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container">
    <div class="hero-wrap">
      <div class="hero">
        <div class="left">
          <nav aria-label="breadcrumb" style="font-size:0.9rem;color:var(--muted);margin-bottom:8px;">
            <a href="index.php" style="color:var(--muted);">หน้าแรก</a> &nbsp;/&nbsp; <span style="color:var(--navy);font-weight:700">เกี่ยวกับเรา</span>
          </nav>

          <h1><?php echo h($about['company_name'] ?? ''); ?></h1>
          <p><?php echo h($about['company_desc'] ?? ''); ?></p>
        </div>

        <div class="logo" aria-hidden="true">
          <?php if (!empty($about['logo_url']) && file_exists(__DIR__ . '/' . $about['logo_url'])): ?>
            <img src="<?php echo h($about['logo_url']); ?>" alt="Company logo">
          <?php elseif (file_exists(__DIR__ . '/uploads/logo_main.png')): ?>
            <img src="uploads/logo_main.png" alt="Company logo">
          <?php else: ?>
            <img src="logo/logo.jpg" alt="Company logo">
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="profile-grid">
      <section class="card" aria-labelledby="companyProfileHeading">
        <div id="companyProfileHeading" class="company-title">ประวัติบริษัท (Company Profile)</div>

        <div class="company-text">
สโลแกนบริษัท : <?php echo h($about['slogan'] ?? '-'); ?> 
วันที่ก่อตั้ง / ทุนจดทะเบียน : <?php echo h($about['founded_info'] ?? '-'); ?> 
ผู้บริหาร : <?php echo h($about['executive_name'] ?? '-'); ?> 

จำนวนพนักงาน : <?php echo h($about['employee_count'] ?? '-'); ?> 

พื้นที่ให้บริการ: <?php echo h($about['service_area'] ?? '-'); ?>
        </div>

        <div class="divider" role="separator" aria-hidden="true"></div>

        <div class="company-text" style="font-weight:800;margin-top:8px;">
          <?php echo h($about['company_name'] ?? 'บริษัท ออน ไทม์ แมนเนจเม้นท์ จำกัด'); ?>
        </div>
      </section>

      <aside class="card contact-card" aria-labelledby="contactHeading">
        <div id="contactHeading" class="company-title">ข้อมูลติดต่อ</div>

        <div class="contact-item">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><path d="M3 6h18" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
          <div>
            <div style="font-weight:800;color:var(--navy)">ที่ตั้ง</div>
            <div style="color:var(--muted);margin-top:6px;"><?php echo h($about['address'] ?? '-'); ?></div>
          </div>
        </div>

        <div class="contact-item">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><path d="M3 5v14a2 2 0 002 2h14" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <div>
            <div style="font-weight:800;color:var(--navy)">โทรศัพท์</div>
            <div style="color:var(--muted);margin-top:6px;"><?php echo h($about['phone'] ?? '-'); ?></div>
          </div>
        </div>

        <div class="contact-item">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true"><path d="M3 8l9 6 9-6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <div>
            <div style="font-weight:800;color:var(--navy)">E-mail</div>
            <div style="color:var(--muted);margin-top:6px;"><?php echo h($about['email'] ?? '-'); ?></div>
          </div>
        </div>

        <?php $link = !empty($about['portfolio_link']) ? $about['portfolio_link'] : 'ourwork.php'; ?>
        <a href="<?php echo h($link); ?>" class="cta" role="button">ดูผลงานของเรา</a>
      </aside>
    </div>

    <section class="services-area">
      <h2 style="margin:18px 0 14px;font-size:1.15rem;font-weight:800;color:var(--navy)">บริการของเรา</h2>

      <div class="services-row">
        <div class="service">
          <div class="icon" aria-hidden="true">F</div>
          <div>
            <h3>Fiber Optic</h3>
            <p>ติดตั้งและดูแลสื่อสารด้วยสายไฟเบอร์ออพติค ทั้งงานระยะไกลและภายในอาคาร</p>
          </div>
        </div>

        <div class="service">
          <div class="icon" aria-hidden="true">C</div>
          <div>
            <h3>CCTV</h3>
            <p>ออกแบบ ติดตั้ง และบำรุงรักษาระบบกล้องวงจรปิด</p>
          </div>
        </div>

        <div class="service">
          <div class="icon" aria-hidden="true">N</div>
          <div>
            <h3>ระบบเครือข่าย</h3>
            <p>ติดตั้งระบบเครือข่าย LAN, VLAN, และออกแบบโครงสร้างเครือข่าย</p>
          </div>
        </div>

        <div class="service">
          <div class="icon" aria-hidden="true">W</div>
          <div>
            <h3>WIFI Hotspot</h3>
            <p>ระบบไวไฟสำหรับโรงแรม รีสอร์ต และหน่วยงานที่ต้องการระบบบริหารผู้ใช้งาน</p>
          </div>
        </div>
      </div>
    </section>

    <div class="page-accent"></div>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
</body>
</html>