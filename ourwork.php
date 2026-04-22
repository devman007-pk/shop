<?php
// ourwork.php - หน้าแสดงผลงานที่ผ่านมา (กลับมาใช้ 2 คอลัมน์แบบเดิม โลโก้ซ้าย-ชื่อขวา)
declare(strict_types=1);

$works = [];
$dbError = null;

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT id, title, logo_url FROM works WHERE status = 'published' ORDER BY id DESC");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $works[] = [
                'id' => $r['id'],
                'title' => $r['title'],
                'logo_url' => $r['logo_url'] ?? ''
            ];
        }
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }
}

// Fallback กรณีฐานข้อมูลว่าง
if (empty($works)) {
    $fallback = [
        'บริษัททีโอที จำกัด (มหาชน)',
        'บริษัท ทริปเปิลที อินเทอร์เน็ต จำกัด',
        'บริษัท แอดวานซ์ ไวร์เลส เน็ทเวอร์ค จำกัด',
        'บริษัท ซายส์ ล็อกซอินโฟ จำกัด',
        'บริษัท แอดวานซ์ ไวร์เลส เน็ทเวอร์ค จำกัด',
        'บริษัท แอดเดรสตัวอย่าง จำกัด'
    ];
    $works = array_map(function($t,$i){ return ['id'=>$i+1,'title'=>$t, 'logo_url'=>'']; }, $fallback, array_keys($fallback));
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>ผลงานที่ผ่านมา - OTM</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&family=Noto+Sans+Thai:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg-1: #f6fbfc; --card: #ffffff; --muted: rgba(11,47,74,0.6); --navy: #0b2f4a;
    --accent-1: #1188ff; --accent-2: #2bb673; --radius: 12px; --maxw: 1180px;
  }

  html,body{height:100%;margin:0;background:linear-gradient(180deg,var(--bg-1),#f3faf6);font-family:'Noto Sans Thai', sans-serif;color:var(--navy);-webkit-font-smoothing:antialiased;}
  .container{width:94%;max-width:var(--maxw);margin:28px auto;padding:0;}

  /* Header / hero */
  .page-hero{
    display:flex; align-items:center; justify-content:space-between; gap:18px;
    background:linear-gradient(90deg, rgba(17,136,255,0.06), rgba(43,182,115,0.03));
    padding:22px; border-radius:14px; box-shadow:0 14px 36px rgba(11,47,74,0.04);
    border:1px solid rgba(11,47,74,0.03); margin-bottom:18px;
  }
  .page-title{font-size:1.35rem;margin:0;font-weight:800;}
  .page-sub{margin:6px 0 0;color:var(--muted);font-size:0.98rem;}

  /* Search box */
  .search-wrap { display:flex; gap:8px; align-items:center; }
  .search {
    display:flex; align-items:center; background:var(--card); border-radius:10px;
    padding:6px 8px; box-shadow:0 6px 20px rgba(9,30,45,0.04); border:1px solid rgba(11,47,74,0.04);
  }
  .search input { border:0; outline:none; font-size:0.95rem; padding:10px; width:320px; background:transparent; font-family:inherit;}
  .search button { background:linear-gradient(90deg,var(--accent-1),var(--accent-2)); border:0; color:#fff; padding:8px 10px; border-radius:8px; cursor:pointer; font-weight:700; font-family:inherit;}
  
  @media (max-width:820px){ .search input{width:180px;} }
  @media (max-width:520px){ .page-hero{flex-direction:column;align-items:flex-start;} .search input{width:100%;} .search-wrap{width:100%;} }

  /* Card + Grid list (กลับมาใช้แบบ 2 คอลัมน์) */
  .card { background:var(--card); border-radius:var(--radius); padding:20px; box-shadow:0 10px 30px rgba(11,47,74,0.04); border:1px solid rgba(11,47,74,0.03); }
  
  .works-grid { margin-top:12px; display:grid; grid-template-columns: repeat(2, 1fr); gap:15px; }
  
  /* ปรับแต่งไอเทมให้แสดงแนวนอน โลโก้ซ้าย ชื่อขวา */
  .work-item {
    display:flex; gap:15px; align-items:center; padding:15px; border-radius:10px; background:linear-gradient(180deg, #fff, #fbfeff);
    transition: transform .12s ease, box-shadow .12s ease;
    border:1px solid rgba(11,47,74,0.05);
  }
  .work-item:hover { transform: translateY(-6px); box-shadow:0 20px 40px rgba(11,47,74,0.06); }
  
  /* กรอบโลโก้ทางซ้าย (ขนาดพอๆ กับกล่องตัวเลขเดิม) */
  .work-logo {
    width: 60px; height: 60px; flex: 0 0 60px; display: flex; align-items: center; justify-content: center;
    background: #fff; border-radius: 8px; padding: 4px; border: 1px solid #f0f0f0;
  }
  .work-logo img {
    max-width: 100%; max-height: 100%; object-fit: contain;
  }
  .work-logo .no-logo {
    width: 100%; height: 100%; background: #f0f2f5; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 0.7rem; font-weight: bold; text-align: center; line-height: 1.1;
  }
  
  /* ส่วนข้อความทางขวา */
  .work-body { min-width:0; text-align: left; }
  .work-title { font-weight:800; margin:0 0 4px; font-size:1rem; color:var(--navy); word-break:break-word; }
  .work-sub { margin:0; color:var(--muted); font-size:0.9rem; }

  /* 1 คอลัมน์บนจอมือถือ */
  @media (max-width:900px){ .works-grid{grid-template-columns:repeat(1,1fr);} }

  /* DB Error & No Results */
  .db-error { padding:12px;border-radius:10px;background:#fff7f7;border:1px solid rgba(176,0,32,0.08);color:#a20000;margin-bottom:12px; }
  .no-results { padding:30px;text-align:center;color:var(--muted);font-weight:600;grid-column: 1 / -1; }
</style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container">
    <div class="page-hero">
      <div class="hero-left">
        <h1 class="page-title">ผลงานที่ผ่านมา</h1>
        <div class="page-sub">รายชื่อลูกค้า / โครงการที่ไว้วางใจใช้บริการจากเรา</div>
      </div>

      <div class="search-wrap">
        <div class="search">
          <input id="workSearch" type="search" placeholder="ค้นหาชื่อหน่วยงาน/บริษัท...">
          <button id="clearSearch">ล้าง</button>
        </div>
      </div>
    </div>

    <?php if ($dbError): ?>
      <div class="db-error">Database error: <?php echo htmlspecialchars($dbError); ?></div>
    <?php endif; ?>

    <div class="card">
      <?php if (count($works) === 0): ?>
        <div class="no-results">ยังไม่มีข้อมูลผลงาน</div>
      <?php else: ?>
        <div id="worksGrid" class="works-grid">
          <?php foreach ($works as $w): ?>
            <article class="work-item" data-title="<?php echo htmlspecialchars(mb_strtolower($w['title'])); ?>">
              <div class="work-logo">
                  <?php if (!empty($w['logo_url']) && file_exists($w['logo_url'])): ?>
                      <img src="<?php echo htmlspecialchars($w['logo_url']); ?>" alt="<?php echo htmlspecialchars($w['title']); ?>">
                  <?php else: ?>
                      <div class="no-logo">ไม่มีรูป</div>
                  <?php endif; ?>
              </div>
              <div class="work-body">
                <h3 class="work-title"><?php echo htmlspecialchars($w['title']); ?></h3>
                <p class="work-sub">ลูกค้าโครงการ / บริษัท</p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>

  <script>
    (function(){
      const input = document.getElementById('workSearch');
      const clearBtn = document.getElementById('clearSearch');
      const grid = document.getElementById('worksGrid');
      if (!input || !grid) return;
      const items = Array.from(grid.children);

      function doFilter(q){
        q = (q||'').trim().toLowerCase();
        let visible = 0;
        items.forEach(item=>{
          const title = item.dataset.title || '';
          if (!q || title.indexOf(q) !== -1) {
            item.style.display = '';
            visible++;
          } else {
            item.style.display = 'none';
          }
        });
        if (visible === 0) {
          if (!document.getElementById('noResultsMsg')) {
            const el = document.createElement('div');
            el.id = 'noResultsMsg';
            el.className = 'no-results';
            el.textContent = 'ไม่พบผลลัพธ์ที่ตรงกับการค้นหา';
            grid.appendChild(el);
          }
        } else {
          const exist = document.getElementById('noResultsMsg');
          if (exist) exist.remove();
        }
      }

      input.addEventListener('input', (e)=> doFilter(e.target.value));
      clearBtn.addEventListener('click', ()=>{
        input.value = '';
        doFilter('');
        input.focus();
      });
    })();
  </script>
</body>
</html>