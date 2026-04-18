<?php
// wishlist.php
// - Displays the user's wishlist (server-session synced with client localStorage).
// - Supports:
//    GET  ?action=list    -> returns JSON list of product data for IDs stored in session (for AJAX).
//    POST ?action=sync    -> accepts JSON { ids: [...] } and stores in session, returns { ok: true }.
// - If a DB is available via config.php/getPDO(), product rows are fetched; otherwise placeholders are returned.

session_start();

header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Helper: load product rows by ids using DB if available.
 */
function fetchProductsByIds(array $ids): array {
    $out = [];

    // Normalize & dedupe
    $ids = array_values(array_unique(array_map('strval', $ids)));
    if (count($ids) === 0) return [];

    // Try to use config.php/getPDO()
    if (file_exists(__DIR__ . '/config.php')) {
        try {
            require_once __DIR__ . '/config.php';
            if (function_exists('getPDO')) {
                $pdo = getPDO();

                $numeric = array_values(array_filter($ids, function($i){ return ctype_digit((string)$i); }));
                $fetched = [];

                if (count($numeric) > 0) {
                    $pl = implode(',', array_fill(0, count($numeric), '?'));
                    $sql = "SELECT id, name, COALESCE(price, NULL) AS price, COALESCE(image_url, '') AS image_url FROM products WHERE id IN ($pl)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($numeric);
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $fetched[(string)$r['id']] = $r;
                    }
                }

                $nonNumeric = array_values(array_diff($ids, $numeric));
                if (count($nonNumeric) > 0) {
                    $candidates = ['sku', 'slug', 'code', 'external_id'];
                    foreach ($candidates as $col) {
                        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = :col");
                        $st->execute([':col' => $col]);
                        if ((int)$st->fetchColumn() > 0) {
                            $pl = implode(',', array_fill(0, count($nonNumeric), '?'));
                            $sql = "SELECT id, name, COALESCE(price, NULL) AS price, COALESCE(image_url, '') AS image_url, $col FROM products WHERE $col IN ($pl)";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($nonNumeric);
                            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $key = isset($r[$col]) ? (string)$r[$col] : (string)$r['id'];
                                $fetched[$key] = ['id'=>$r['id'],'name'=>$r['name'],'price'=>$r['price'],'image_url'=>$r['image_url']];
                            }
                            break;
                        }
                    }
                }

                foreach ($ids as $id) {
                    if (isset($fetched[$id])) {
                        $out[] = [
                            'id' => (string)$fetched[$id]['id'],
                            'name' => $fetched[$id]['name'],
                            'price' => $fetched[$id]['price'],
                            'image_url' => $fetched[$id]['image_url'],
                        ];
                    } else {
                        $out[] = [
                            'id' => (string)$id,
                            'name' => "สินค้า (ID: " . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . ")",
                            'price' => null,
                            'image_url' => '',
                        ];
                    }
                }

                return $out;
            }
        } catch (Throwable $e) {}
    }

    foreach ($ids as $id) {
        $out[] = [
            'id' => (string)$id,
            'name' => "สินค้า (ID: " . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . ")",
            'price' => null,
            'image_url' => '',
        ];
    }
    return $out;
}

/* ---------------------------
   API endpoints (AJAX)
   --------------------------- */
if ($method === 'POST' && ($action === 'sync')) {
    $input = file_get_contents('php://input');
    $ids = [];
    if ($input) {
        $decoded = json_decode($input, true);
        if (is_array($decoded) && isset($decoded['ids']) && is_array($decoded['ids'])) {
            $ids = array_values(array_map('strval', $decoded['ids']));
        } elseif (is_array($decoded)) {
            $ids = array_values(array_map('strval', $decoded));
        }
    }
    if (empty($ids) && isset($_POST['ids'])) {
        $ids = is_array($_POST['ids']) ? array_values(array_map('strval', $_POST['ids'])) : [strval($_POST['ids'])];
    }

    $_SESSION['wishlist_ids'] = array_values(array_unique($ids));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'count' => count($_SESSION['wishlist_ids'])]);
    exit;
}

if ($method === 'GET' && ($action === 'list')) {
    $ids = $_SESSION['wishlist_ids'] ?? [];
    $ids = array_values(array_unique(array_map('strval', $ids)));
    $products = fetchProductsByIds($ids);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'count' => count($products), 'items' => $products], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------------------
   Render HTML page
   --------------------------- */
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>รายการโปรด - Wishlist</title>

  <link rel="stylesheet" href="styles.css" />
  <style>
    /* Wishlist page specifics */
    .wishlist-hero { padding: 18px 0; margin-top: 18px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .wishlist-title { font-weight:800; font-size:1.25rem; color:var(--navy); }
    
    /* สไตล์กล่องเมื่อไม่มีรายการโปรด (ปรับให้สวยคล้ายหน้าตะกร้าว่าง) */
    .wishlist-empty { padding:48px 20px; text-align:center; color:var(--navy); background:#fff; border-radius:12px; box-shadow:0 12px 32px rgba(9,30,45,0.05); font-size:1.1rem; font-weight:700; }
    
    .btn-primary { display:inline-block; padding:10px 14px; border-radius:8px; border:0; cursor:pointer; font-weight:800; text-decoration:none !important; text-align:center; background: linear-gradient(90deg,var(--blue),var(--teal)); color:#fff; box-shadow:0 10px 28px rgba(9,30,45,0.06); }

    /* อิง Grid ตามหน้า Shop ทุกประการ */
    .wishlist-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); 
        gap: 32px; 
        margin-top: 12px; 
        align-items: start; 
    }
    
    /* Transition เวลาสินค้าหายไปให้ดูนุ่มนวล */
    .product-card {
        transition: opacity 0.3s ease, transform 0.3s ease;
    }

    @media (max-width: 760px) {
      .wishlist-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container">
    <div class="wishlist-hero">
      <div class="wishlist-title">รายการโปรดของฉัน (Wishlist)</div>
      <div style="color:var(--muted);">รายการโปรดจะซิงค์กับเบราว์เซอร์ของคุณ (localStorage)</div>
    </div>

    <div id="wishlistRoot">
      <div class="wishlist-empty" id="emptyMsg">กำลังโหลดรายการโปรด...</div>
      <div id="wishlistGrid" class="wishlist-grid" style="display:none;"></div>
    </div>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>

  <script>
    (function(){
      'use strict';

      const LOCAL_KEY = 'site_wishlist_v1';
      const root = document.getElementById('wishlistRoot');
      const grid = document.getElementById('wishlistGrid');
      const emptyMsg = document.getElementById('emptyMsg');

      function loadLocal() {
        try {
          const raw = localStorage.getItem(LOCAL_KEY);
          if (!raw) return [];
          const arr = JSON.parse(raw);
          if (!Array.isArray(arr)) return [];
          return arr.map(String);
        } catch (e) { return []; }
      }

      function postSync(ids) {
        try {
          navigator.sendBeacon && (() => {
            const url = location.pathname + '?action=sync';
            const payload = JSON.stringify({ ids: ids });
            if (navigator.sendBeacon) {
              const blob = new Blob([payload], { type: 'application/json' });
              navigator.sendBeacon(url, blob);
              return;
            }
          })();
        } catch (e) {}
        fetch(location.pathname + '?action=sync', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ ids: ids })
        }).catch(()=>{});
      }

      function fetchList() {
        return fetch(location.pathname + '?action=list', { credentials: 'same-origin' })
          .then(r => r.json())
          .then(json => {
            if (!json || !json.items) return [];
            return json.items;
          }).catch(()=>[]);
      }

      function formatPrice(p) {
        if (p === null || p === undefined || p === '') return 'สอบถามราคา';
        if (isNaN(Number(p))) return 'สอบถามราคา';
        const n = Number(p);
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ฿';
      }

      function renderEmpty(show, text) {
        if (show) {
          emptyMsg.style.display = '';
          grid.style.display = 'none';
          emptyMsg.innerHTML = `<div style="margin-bottom:16px;">${text || 'ยังไม่มีรายการโปรดของคุณ'} 🤍</div><a class="btn-primary" href="index.php">ไปเลือกดูสินค้ากันเลย</a>`;
        } else {
          emptyMsg.style.display = 'none';
          grid.style.display = '';
        }
      }

      function createCard(item, localIds) {
        const id = String(item.id);
        const div = document.createElement('article');
        div.className = 'product-card';
        div.dataset.productId = id;

        const thumb = document.createElement('div');
        thumb.className = 'product-thumb';
        if (item.image_url) {
          const img = document.createElement('img');
          img.src = item.image_url;
          img.alt = item.name || 'รูปสินค้า';
          thumb.appendChild(img);
        } else {
          const span = document.createElement('span');
          span.className = 'thumb-label';
          span.textContent = 'รูปสินค้า';
          thumb.appendChild(span);
        }

        // ปุ่มหัวใจ (สไตล์จาก styles.css โดยตรง)
        const fav = document.createElement('button');
        fav.type = 'button';
        fav.className = 'fav-btn';
        if (localIds.includes(id)) {
          fav.classList.add('active');
        }
        fav.setAttribute('aria-label', 'เพิ่มรายการโปรด');
        fav.dataset.pid = id; 
        fav.innerHTML = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12.1 21s-7.6-4.8-9.5-7.1C-0.6 11.5 2.2 6.6 6.6 6.6c2.3 0 3.9 1.5 4.9 2.6 1-1.1 2.6-2.6 4.9-2.6 4.4 0 7.2 4.9 3.9 7.3-1.9 2.3-9.5 7.1-9.5 7.1z" stroke="currentColor" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        
        // -------------------------------------------------------------
        // เวลากดปุ่มหัวใจ ให้สินค้าหายไปทันที
        // -------------------------------------------------------------
        fav.addEventListener('click', function () {
          // ใส่เอฟเฟกต์เฟดจางลงนิดนึงก่อนลบ เพื่อความนุ่มนวล
          div.style.opacity = '0';
          div.style.transform = 'scale(0.9)';
          
          setTimeout(() => {
             // ลบสินค้าออกจากหน้าจอ
             div.remove();
             
             // เช็คว่าถ้าถูกลบหมดแล้ว ให้โชว์หน้าจอว่างเปล่า
             if (grid.children.length === 0) {
               renderEmpty(true, 'ไม่มีสินค้าในรายการโปรดแล้ว');
             }

             // อัปเดตข้อมูลกับ LocalStorage และ Server
             const updated = loadLocal();
             postSync(updated);
          }, 150); // รอให้ script.js หลักทำงานเปลี่ยนสีหัวใจเสร็จก่อนค่อยลบ
        });

        thumb.appendChild(fav);

        const title = document.createElement('h3');
        title.className = 'prod-title';
        title.textContent = item.name || 'Unnamed';

        const price = document.createElement('div');
        price.className = 'product-price';
        price.textContent = formatPrice(item.price);

        // ปุ่มตะกร้าพร้อมไอคอน (สไตล์จาก styles.css โดยตรง)
        const actions = document.createElement('div');
        actions.className = 'card-actions';
        const btn = document.createElement('button');
        btn.className = 'add-cart btn-icon';
        btn.type = 'button';
        btn.dataset.id = id; 
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg> เพิ่มในตะกร้า';
        actions.appendChild(btn);

        // ประกอบร่าง
        div.appendChild(thumb);
        div.appendChild(title);
        div.appendChild(price);
        div.appendChild(actions);

        return div;
      }

      // INIT
      (function init() {
        const localIds = loadLocal();
        if (localIds.length) postSync(localIds);

        fetchList().then(items => {
          if (!items || items.length === 0) {
            renderEmpty(true, 'ยังไม่มีรายการโปรดของคุณ');
            return;
          }
          renderEmpty(false);
          grid.innerHTML = '';
          items.forEach(item => {
            const card = createCard(item, localIds);
            grid.appendChild(card);
          });
        }).catch(()=> {
          renderEmpty(true, 'เกิดข้อผิดพลาดขณะโหลดรายการโปรด');
        });
      })();

      // Reload if wishlist changes in another tab
      window.addEventListener('storage', function (e) {
        if (e.key === LOCAL_KEY) {
          setTimeout(()=> location.reload(), 150);
        }
      });
    })();
  </script>
  
  <script src="script.js"></script>
</body>
</html>