<?php
// ourwork.php - improved visuals for simple list: show only company names (title)
// Place next to index.php and include navbar/footer as in other pages.

declare(strict_types=1);

$works = [];
$dbError = null;

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT id, title FROM works WHERE status = 'published' ORDER BY updated_at DESC, id DESC");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $works[] = [
                'id' => $r['id'],
                'title' => $r['title']
            ];
        }
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }
}

// fallback static list if DB empty
if (empty($works)) {
    $fallback = [
        'บริษัททีโอที จำกัด (มหาชน)',
        'บริษัท ทริปเปิลที อินเทอร์เน็ต จำกัด',
        'บริษัท แอดวานซ์ ไวร์เลส เน็ทเวอร์ค จำกัด',
        'บริษัท ซายส์ ล็อกซอินโฟ จำกัด',
        'บริษัท แอดวานซ์ ไวร์เลส เน็ทเวอร์ค จำกัด',
        'บริษัท แอดเดรสตัวอย่าง จำกัด',
        'และผลงานอื่นๆ อีกมากมาย'
    ];
    $works = array_map(function($t,$i){ return ['id'=>$i+1,'title'=>$t]; }, $fallback, array_keys($fallback));
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>ผลงานที่ผ่านมา</title>

<!-- Optional: Google font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="styles.css" />
<style>
  :root{
    --bg-1: #f6fbfc;
    --card: #ffffff;
    --muted: rgba(11,47,74,0.6);
    --navy: #0b2f4a;
    --accent-1: #1188ff;
    --accent-2: #2bb673;
    --glass: rgba(255,255,255,0.7);
    --radius: 12px;
    --maxw: 1180px;
  }

  html,body{height:100%;margin:0;background:linear-gradient(180deg,var(--bg-1),#f3faf6);font-family:Inter, "Noto Sans Thai", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;color:var(--navy);-webkit-font-smoothing:antialiased;}
  .container{width:94%;max-width:var(--maxw);margin:28px auto;padding:0;}

  /* Header / hero */
  .page-hero{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:18px;
    background:linear-gradient(90deg, rgba(17,136,255,0.06), rgba(43,182,115,0.03));
    padding:22px;
    border-radius:14px;
    box-shadow:0 14px 36px rgba(11,47,74,0.04);
    border:1px solid rgba(11,47,74,0.03);
    margin-bottom:18px;
  }
  .hero-left { min-width:0; }
  .page-title{font-size:1.35rem;margin:0;font-weight:800;}
  .page-sub{margin:6px 0 0;color:var(--muted);font-size:0.98rem;}

  /* Search box */
  .search-wrap { display:flex; gap:8px; align-items:center; }
  .search {
    display:flex;
    align-items:center;
    background:var(--card);
    border-radius:10px;
    padding:6px 8px;
    box-shadow:0 6px 20px rgba(9,30,45,0.04);
    border:1px solid rgba(11,47,74,0.04);
  }
  .search input {
    border:0; outline:none; font-size:0.95rem; padding:10px; width:320px; background:transparent;
  }
  .search button {
    background:linear-gradient(90deg,var(--accent-1),var(--accent-2));
    border:0; color:#fff; padding:8px 10px; border-radius:8px; cursor:pointer; font-weight:700;
  }
  @media (max-width:820px){
    .search input{width:180px;}
  }
  @media (max-width:520px){
    .page-hero{flex-direction:column;align-items:flex-start;}
    .search input{width:100%;}
    .search-wrap{width:100%;}
  }

  /* card + grid list */
  .card { background:var(--card); border-radius:var(--radius); padding:18px; box-shadow:0 10px 30px rgba(11,47,74,0.04); border:1px solid rgba(11,47,74,0.03); }
  .works-grid { margin-top:12px; display:grid; grid-template-columns: repeat(2, 1fr); gap:12px; }
  .work-item {
    display:flex; gap:12px; align-items:flex-start; padding:12px; border-radius:10px; background:linear-gradient(180deg, #fff, #fbfeff);
    transition: transform .12s ease, box-shadow .12s ease;
    border:1px solid rgba(11,47,74,0.03);
  }
  .work-item:hover { transform: translateY(-6px); box-shadow:0 20px 40px rgba(11,47,74,0.06); }
  .work-index {
    width:44px; height:44px; border-radius:10px; background:linear-gradient(90deg,var(--accent-1),var(--accent-2)); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; flex:0 0 44px;
  }
  .work-body { min-width:0; }
  .work-title { font-weight:800; margin:0 0 6px; font-size:1rem; color:var(--navy); word-break:break-word; }
  .work-sub { margin:0; color:var(--muted); font-size:0.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

  /* single column small screens */
  @media (max-width:900px){ .works-grid{grid-template-columns:repeat(1,1fr);} }

  /* footer services */
  .services-row{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:22px}
  .service{background:transparent;padding:6px 8px;}
  .service h4{margin:0 0 6px;font-weight:800;color:var(--navy)}
  .service p{margin:0;color:var(--muted);font-size:0.95rem}

  /* db error */
  .db-error { padding:12px;border-radius:10px;background:#fff7f7;border:1px solid rgba(176,0,32,0.08);color:#a20000;margin-bottom:12px; }

  /* no-results */
  .no-results{padding:18px;text-align:center;color:var(--muted);font-weight:600;}
</style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container">
    <div class="page-hero">
      <div class="hero-left">
        <h1 class="page-title">ผลงานที่ผ่านมา</h1>
        <div class="page-sub">รายชื่อลูกค้า / โครงการที่ผ่านมา — สามารถจัดการรายการผ่านระบบ admin</div>
      </div>

      <div class="search-wrap">
        <div class="search" role="search" aria-label="ค้นหาผลงาน">
          <input id="workSearch" type="search" placeholder="ค้นหาชื่อบริษัท..." aria-label="ค้นหาชื่อบริษัท">
          <button id="clearSearch" title="Clear">ล้าง</button>
        </div>
      </div>
    </div>

    <?php if ($dbError): ?>
      <div class="db-error">Database error: <?php echo htmlspecialchars($dbError); ?> — กำลังแสดงรายการสำรอง</div>
    <?php endif; ?>

    <div class="card" role="region" aria-label="List of past works">
      <?php if (count($works) === 0): ?>
        <div class="no-results">ยังไม่มีข้อมูลผลงาน</div>
      <?php else: ?>
        <div id="worksGrid" class="works-grid">
          <?php foreach ($works as $i => $w): ?>
            <article class="work-item" data-title="<?php echo htmlspecialchars(mb_strtolower($w['title'])); ?>">
              <div class="work-index"><?php echo $i + 1; ?></div>
              <div class="work-body">
                <h3 class="work-title"><?php echo htmlspecialchars($w['title']); ?></h3>
                <p class="work-sub">ลูกค้าโครงการ / บริษัท</p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="divider" style="height:1px;background:linear-gradient(90deg, rgba(11,47,74,0.03), rgba(11,47,74,0.09), rgba(11,47,74,0.03));margin:22px 0;border-radius:2px;"></div>

    <section class="services-row" aria-label="บริการ">
      <div class="service">
        <h4>Fiber Optic</h4>
        <p>บริการด้านสื่อสาร สายไฟเบอร์ออพติค</p>
      </div>
      <div class="service">
        <h4>CCTV</h4>
        <p>ระบบกล้องวงจรปิด</p>
      </div>
      <div class="service">
        <h4>ระบบเครือข่าย</h4>
        <p>ระบบเครือข่าย สายแลน</p>
      </div>
      <div class="service">
        <h4>WIFI Hotspot</h4>
        <p>ระบบไวไฟ โรงแรม รีสอร์ต</p>
      </div>
    </section>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>

  <script>
    // Simple client-side filtering for works
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
            grid.parentNode.insertBefore(el, grid.nextSibling);
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

      // allow pressing Enter on search to focus first visible item
      input.addEventListener('keydown', (e)=>{
        if (e.key === 'Enter') {
          const first = items.find(it => it.style.display !== 'none');
          if (first) first.scrollIntoView({behavior:'smooth', block:'center'});
        }
      });
    })();
  </script>
</body>
</html>