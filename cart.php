<?php
// cart.php - "ตะกร้าของฉัน" (My Cart) page
declare(strict_types=1);

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ใช้ Javascript เปลี่ยนหน้า 100% หมดปัญหา Header ตีกัน
function redirect_clean(string $url) {
    echo "<script>window.location.replace('$url');</script>";
    exit;
}

// ---------------------------------------------------------
// 1. ระบบจัดการ Sync ตะกร้าระหว่าง Javascript และ PHP Session
// ---------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'sync' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['ids']) && is_array($input['ids'])) {
        $newCart = [];
        foreach ($input['ids'] as $pid) {
            $pid = (string)$pid;
            $newCart[$pid] = isset($_SESSION['cart'][$pid]) ? $_SESSION['cart'][$pid] : 1;
        }
        $_SESSION['cart'] = $newCart;
        ob_clean();
        echo json_encode(['ok' => true]);
    }
    exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// เมื่อมีการกด "เพิ่มลงตะกร้า"
if (isset($_GET['add'])) {
    $pid = (string)$_GET['add'];
    if ($pid !== '') {
        if (!isset($_SESSION['cart'][$pid])) $_SESSION['cart'][$pid] = 0;
        $_SESSION['cart'][$pid] += 1;
    }
    $_SESSION['cart_mutated_by_php'] = true;
    redirect_clean('cart.php');
}

// ---------------------------------------------------------
// 2. จัดการคำสั่งจาก Form (อัปเดตจำนวน / ลบสินค้า)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_GET['action'])) {
    
    // ระบบอัปเดตจำนวนสินค้าแบบใหม่
    if (isset($_POST['update_qty']) && isset($_POST['pid']) && isset($_POST['qty'])) {
        $pid = (string)$_POST['pid'];
        $q = (int)$_POST['qty'];
        if ($q <= 0) {
            unset($_SESSION['cart'][$pid]);
        } else {
            $_SESSION['cart'][$pid] = $q;
        }
    }

    // ลบสินค้าทีละชิ้น
    if (isset($_POST['remove']) && is_string($_POST['remove'])) {
        $rid = (string)$_POST['remove'];
        unset($_SESSION['cart'][$rid]);
    }

    // ล้างตะกร้าทั้งหมด
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
    }

    $_SESSION['cart_mutated_by_php'] = true;
    redirect_clean('cart.php');
}

$forceLocalUpdate = false;
if (isset($_SESSION['cart_mutated_by_php'])) {
    $forceLocalUpdate = true;
    unset($_SESSION['cart_mutated_by_php']);
}

// ---------------------------------------------------------
// 3. ดึงข้อมูลสินค้าจาก Database
// ---------------------------------------------------------
$cartItems = [];
$subtotal = 0.0;
$dbAvailable = false;

if (!empty($_SESSION['cart'])) {
    $pids = array_keys($_SESSION['cart']);

    if (file_exists(__DIR__ . '/config.php')) {
        try {
            require_once __DIR__ . '/config.php';
            $pdo = getPDO();
            $dbAvailable = true;

            $numericIds = array_values(array_filter($pids, function($v){ return preg_match('/^\d+$/', (string)$v); }));
            $productsById = [];

            if (!empty($numericIds)) {
                $placeholders = implode(',', array_fill(0, count($numericIds), '?'));
                $sql = "
                    SELECT p.id, p.name, p.price,
                    (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url
                    FROM products p 
                    WHERE p.id IN ($placeholders)
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($numericIds);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $productsById[(string)$r['id']] = $r;
                }
            }

            foreach ($pids as $pid) {
                $qty = max(0, (int)($_SESSION['cart'][$pid] ?? 0));
                $item = [
                    'id' => $pid,
                    'name' => null,
                    'price' => null,
                    'image_url' => null,
                    'qty' => $qty,
                    'line_total' => 0.0
                ];

                if (isset($productsById[$pid])) {
                    $item['name'] = (string)$productsById[$pid]['name'];
                    $item['price'] = is_null($productsById[$pid]['price']) ? null : (float)$productsById[$pid]['price'];
                    $item['image_url'] = $productsById[$pid]['image_url'];
                } else {
                    $item['name'] = 'สินค้ารหัส #' . $pid;
                    $item['price'] = null;
                }

                if (is_numeric($item['price'])) {
                    $item['line_total'] = $item['price'] * $item['qty'];
                    $subtotal += $item['line_total'];
                } else {
                    $item['line_total'] = null;
                }
                $cartItems[] = $item;
            }
        } catch (Throwable $e) {
            $dbAvailable = false;
        }
    }

    if (!$dbAvailable) {
        foreach ($pids as $pid) {
            $qty = max(0, (int)($_SESSION['cart'][$pid] ?? 0));
            $price = null;
            if (preg_match('/(\d+)/', $pid, $m)) {
                $seed = (int)$m[1];
                $price = ($seed % 5 + 1) * 499.0;
            }
            $lineTotal = is_numeric($price) ? $price * $qty : null;
            if (is_numeric($lineTotal)) $subtotal += $lineTotal;

            $cartItems[] = [
                'id' => $pid,
                'name' => 'สินค้าตัวอย่าง',
                'price' => $price,
                'image_url' => null,
                'qty' => $qty,
                'line_total' => $lineTotal
            ];
        }
    }
}

function fmtPrice($p) {
    if ($p === null || $p === '') return null;
    return number_format((float)$p, 2);
}

// ==========================================
// ฟังก์ชันแปลงตัวเลข ID เป็นรหัส SKU สวยๆ (วิธีที่ 2)
// ==========================================
function formatSKU($id) {
    // ถ้า ID เป็นตัวเลข (มาจาก Database) ให้แปลงเป็นรูปแบบ PROD-0001
    if (is_numeric($id)) {
        return sprintf("PROD-%04d", (int)$id);
    }
    // ถ้าไม่ใช่ (เช่น p2-c5 ที่เป็นตัวอย่าง) ให้แสดงแบบเดิมไปก่อน
    return $id;
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ตะกร้าของฉัน - ร้านค้า</title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    .cart-page { padding: 48px 0; min-height: 60vh; }
    .cart-grid { display: grid; grid-template-columns: 1fr 380px; gap: 32px; align-items:start; }
    .cart-items { display:flex; flex-direction:column; gap:16px; }
    
    .cart-item { 
      display:flex; gap:20px; background:#fff; border-radius:12px; padding:18px; 
      align-items:center; border:1px solid rgba(11,47,74,0.04); 
      box-shadow:0 12px 32px rgba(9,30,45,0.05); min-height: 160px;
    }
    .cart-item .thumb { 
      width:140px; height:140px; border-radius:10px; 
      background:linear-gradient(180deg,#fff,#fbfeff); display:flex; 
      align-items:center; justify-content:center; border:1px solid rgba(11,47,74,0.02); 
    }
    
    .cart-item .meta { flex:1; display:flex; flex-direction:column; gap:4px; }
    .cart-item .meta .sku { font-size:0.85rem; color:var(--muted); font-weight:700; }
    .cart-item .meta .title { font-weight:800; font-size:1.1rem; color:var(--navy); }
    .cart-item .meta .price { font-weight:900; font-size:1.15rem; color:#9B0F06; }
    .cart-item .actions { display:flex; flex-direction:column; gap:12px; align-items:flex-end; }
    
    .cart-summary { background:#fff; border-radius:12px; padding:20px; border:1px solid rgba(11,47,74,0.04); box-shadow:0 12px 32px rgba(9,30,45,0.05); }
    
    .btn { display:inline-flex; align-items:center; justify-content:center; padding:10px 14px; border-radius:8px; border:0; cursor:pointer; font-weight:800; text-decoration:none !important; text-align:center; }
    .btn-primary { background: linear-gradient(90deg,var(--blue),var(--teal)); color:#fff; box-shadow:0 10px 28px rgba(9,30,45,0.06); }
    .btn-outline { border:1px solid rgba(11,47,74,0.06); background:#fff; color:var(--navy); }
    
    .btn-danger { background: #fff; border: 1px solid rgba(255,77,79,0.3); color: #ff4d4f; transition: all 0.2s ease; }
    .btn-danger:hover { background: #ff4d4f; color: #fff; box-shadow: 0 6px 16px rgba(255,77,79,0.2); border-color: #ff4d4f; }

    .btn-clear { background: #4a5568; color: #fff; width: auto; padding: 10px 20px; border-radius: 8px; box-shadow: 0 8px 20px rgba(74,85,104,0.15); transition: background 0.2s ease; font-size:0.95rem; }
    .btn-clear:hover { background: #2d3748; }

    .qty-control { display:flex; align-items:center; background:#fff; border:1px solid rgba(11,47,74,0.1); border-radius:8px; overflow:hidden; }
    .qty-btn { width:32px; height:32px; background:#f7f9fb; border:none; display:flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; color:var(--navy); cursor:pointer; transition:background 0.2s; }
    .qty-btn:hover { background:#e2e8f0; }
    .qty-input-box { width:44px; height:32px; border:none; border-left:1px solid rgba(11,47,74,0.1); border-right:1px solid rgba(11,47,74,0.1); text-align:center; font-weight:700; color:var(--navy); font-size:1rem; outline:none; -moz-appearance: textfield; }
    .qty-input-box::-webkit-outer-spin-button, .qty-input-box::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    .empty-cart { padding:48px 20px; text-align:center; color:var(--navy); background:#fff; border-radius:12px; box-shadow:0 12px 32px rgba(9,30,45,0.05); font-size:1.1rem; font-weight:700; }
    
    @media (max-width:900px){ .cart-grid { grid-template-columns: 1fr; } .cart-item { flex-direction:column; align-items:flex-start; } .cart-item .actions { align-items:flex-start; width:100%; flex-direction:row; justify-content:space-between; } }
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container cart-page">

    <?php if (empty($cartItems)): ?>
      <h1 style="margin-bottom:24px; font-weight:800; font-size:1.8rem;">ตะกร้าของฉัน</h1>
      <div class="empty-cart card">
        <div style="margin-bottom:16px;">ตะกร้าของคุณยังไม่มีสินค้า 🛒</div>
        <a class="btn btn-primary" href="index.php">ไปเลือกซื้อสินค้ากันเลย</a>
      </div>
    <?php else: ?>
      <div class="cart-grid">
        <div>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1 style="margin:0; font-weight:800; font-size:1.8rem;">ตะกร้าของฉัน</h1>
            <form method="post" action="cart.php" style="margin:0;">
              <button type="submit" name="clear_cart" class="btn btn-clear" onclick="return confirm('ต้องการล้างตะกร้าทั้งหมดหรือไม่?')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                ล้างตะกร้าทั้งหมด
              </button>
            </form>
          </div>

          <div class="cart-items">
            <?php foreach ($cartItems as $it): ?>
              <div class="cart-item" data-product-id="<?php echo h($it['id']); ?>">
                <div class="thumb" aria-hidden="true">
                  <?php if (!empty($it['image_url'])): ?>
                    <img src="<?php echo h($it['image_url']); ?>" alt="<?php echo h($it['name']); ?>" style="max-width:85%;max-height:85%;display:block;object-fit:contain;">
                  <?php else: ?>
                    <div style="color:rgba(11,47,74,0.4);font-size:0.95rem;font-weight:600;">ไม่มีรูปภาพ</div>
                  <?php endif; ?>
                </div>

                <div class="meta">
                  <div class="sku">รหัสสินค้า: <?php echo h(formatSKU($it['id'])); ?></div>
                  <div class="title"><?php echo h($it['name']); ?></div>
                  <div class="price"><?php echo ($it['price'] !== null) ? h(fmtPrice($it['price'])) . ' ฿' : 'สอบถามราคา'; ?></div>
                  <?php if ($it['line_total'] !== null): ?>
                    <div class="muted" style="font-weight:600; margin-top:4px; font-size:0.9rem;">ราคารวม: <span style="color:var(--navy);"><?php echo h(fmtPrice($it['line_total'])); ?> ฿</span></div>
                  <?php endif; ?>
                </div>

                <div class="actions">
                  
                  <div class="qty-control">
                    <form method="post" action="cart.php" style="display:flex;">
                      <input type="hidden" name="update_qty" value="1">
                      <input type="hidden" name="pid" value="<?php echo h($it['id']); ?>">
                      <input type="hidden" name="qty" value="<?php echo (int)$it['qty'] - 1; ?>">
                      <button type="submit" class="qty-btn" aria-label="ลดจำนวน">−</button>
                    </form>

                    <input type="number" value="<?php echo (int)$it['qty']; ?>" class="qty-input-box" readonly />

                    <form method="post" action="cart.php" style="display:flex;">
                      <input type="hidden" name="update_qty" value="1">
                      <input type="hidden" name="pid" value="<?php echo h($it['id']); ?>">
                      <input type="hidden" name="qty" value="<?php echo (int)$it['qty'] + 1; ?>">
                      <button type="submit" class="qty-btn" aria-label="เพิ่มจำนวน">+</button>
                    </form>
                  </div>

                  <form method="post" action="cart.php" style="margin:0;">
                    <button type="submit" name="remove" value="<?php echo h($it['id']); ?>" class="btn btn-danger" style="padding:6px 12px;font-size:0.85rem;">ลบ</button>
                  </form>

                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <aside>
          <div class="cart-summary">
            <h3 style="margin:0 0 16px 0; font-weight:800; font-size:1.2rem;">สรุปคำสั่งซื้อ</h3>
            <div style="display:flex;justify-content:space-between;margin:12px 0;font-weight:600;">
              <div>ยอดรวม (สินค้า)</div>
              <div style="color:var(--navy);"><?php echo h(fmtPrice($subtotal)); ?> ฿</div>
            </div>

            <?php
              $shipping = 0.00;
              $tax = 0.00;
              $grandTotal = $subtotal + $shipping + $tax;
            ?>
            <div style="display:flex;justify-content:space-between;margin:12px 0;font-weight:600;">
              <div>ค่าจัดส่ง</div>
              <div><?php echo h(fmtPrice($shipping)); ?> ฿</div>
            </div>
            <div style="display:flex;justify-content:space-between;margin:12px 0 16px 0;font-weight:600;">
              <div>ภาษี</div>
              <div><?php echo h(fmtPrice($tax)); ?> ฿</div>
            </div>

            <div style="display:flex;justify-content:space-between;border-top:2px dashed rgba(11,47,74,0.1);padding-top:16px;margin-top:12px;">
              <div style="font-weight:900; font-size:1.15rem;">ยอดที่ต้องชำระ</div>
              <div style="font-weight:900; font-size:1.2rem; color:#ff4d4f;"><?php echo h(fmtPrice($grandTotal)); ?> ฿</div>
            </div>

            <div style="margin-top:20px; display:flex; flex-direction:column; gap:10px;">
              <a class="btn btn-primary" href="checkout.php" style="width:100%; font-size:1rem;">ดำเนินการชำระเงิน</a>
              <a class="btn btn-outline" href="index.php" style="width:100%;">เลือกสินค้าเพิ่มเติม</a>
            </div>
          </div>
        </aside>
      </div>
    <?php endif; ?>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>

  <script>
    (function(){
        const LOCAL_CART_KEY = 'site_cart_v1';
        let sessionIds = <?php echo json_encode(array_keys($_SESSION['cart'] ?? [])); ?>;

        <?php if ($forceLocalUpdate): ?>
        localStorage.setItem(LOCAL_CART_KEY, JSON.stringify(sessionIds));
        <?php endif; ?>

        let localCartRaw = [];
        try {
            localCartRaw = JSON.parse(localStorage.getItem(LOCAL_CART_KEY)) || [];
            localCartRaw = localCartRaw.map(String);
        } catch(e) {}

        let localUnique = [...new Set(localCartRaw)];
        const localStr = localUnique.slice().sort().join(',');
        const sessionStr = sessionIds.slice().map(String).sort().join(',');

        if (localStr !== sessionStr && !window.isSyncingCart) {
            window.isSyncingCart = true;
            
            fetch('cart.php?action=sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: localUnique })
            }).then(r => r.json()).then(data => {
                if (data.ok) location.replace('cart.php');
            }).catch(err => {
                window.isSyncingCart = false;
            });
        }
    })();
  </script>
  
  <script src="script.js"></script>

</body>
</html>
<?php ob_end_flush(); ?>