<?php
// cart.php - "ตะกร้าของฉัน" (My Cart) page พร้อมเลย์เอาต์สรุปยอดแบบใหม่ (แก้ไขปุ่มใช้โค้ดไม่ให้ขยับ)
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
// 2. จัดการคำสั่งจาก Form (อัปเดตจำนวน / ลบสินค้า / ส่วนลด)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_GET['action'])) {
    
    // ระบบอัปเดตจำนวนสินค้า
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
        unset($_SESSION['discount_code']);
    }

    // === ระบบจัดการโค้ดส่วนลด ===
    if (isset($_POST['apply_discount'])) {
        $code = trim($_POST['discount_code'] ?? '');
        if ($code !== '') {
            $_SESSION['discount_code'] = strtoupper($code);
        }
    }
    if (isset($_POST['remove_discount'])) {
        unset($_SESSION['discount_code']);
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

// ---------------------------------------------------------
// 4. คำนวณส่วนลด และยอดสรุป
// ---------------------------------------------------------
$discountCode = $_SESSION['discount_code'] ?? null;
$discountAmount = 0.00;
$discountError = false;

if ($discountCode && $subtotal > 0) {
    if ($discountCode === 'SAVE10') {
        $discountAmount = $subtotal * 0.10;
    } elseif ($discountCode === 'MINUS50') {
        $discountAmount = 50.00;
    } else {
        $discountError = true;
        unset($_SESSION['discount_code']);
        $discountCode = null;
    }
    if ($discountAmount > $subtotal) $discountAmount = $subtotal;
}

$shipping = 0.00;
$tax = 0.00;
$grandTotal = $subtotal - $discountAmount + $shipping + $tax;

function fmtPrice($p) {
    if ($p === null || $p === '') return null;
    return number_format((float)$p, 2);
}

function formatSKU($id) {
    if (is_numeric($id)) return sprintf("PROD-%04d", (int)$id);
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
    
    .cart-summary { background:#fff; border-radius:12px; padding:24px; border:1px solid rgba(11,47,74,0.04); box-shadow:0 12px 32px rgba(9,30,45,0.05); }
    
    .btn { display:inline-flex; align-items:center; justify-content:center; padding:10px 14px; border-radius:8px; border:0; cursor:pointer; font-weight:800; text-decoration:none !important; text-align:center; transition: all 0.2s; }
    
    .btn-primary { background: #1677ff; color: #fff; box-shadow: 0 4px 12px rgba(22, 119, 255, 0.2); }
    .btn-primary:hover { background: #0958d9; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(22, 119, 255, 0.3); }
    
    .btn-outline { border:1px solid rgba(11,47,74,0.15); background:#fff; color:var(--navy); }
    .btn-outline:hover { background:#f0f7ff; border-color:var(--blue); color:var(--blue); }
    
    .btn-danger { background: #fff; border: 1px solid rgba(255,77,79,0.3); color: #ff4d4f; }
    .btn-danger:hover { background: #ff4d4f; color: #fff; border-color: #ff4d4f; }

    .btn-clear { background: #4a5568; color: #fff; width: auto; padding: 10px 20px; border-radius: 8px; font-size:0.95rem; }

    .qty-control { display:flex; align-items:center; background:#fff; border:1px solid rgba(11,47,74,0.1); border-radius:8px; overflow:hidden; }
    .qty-btn { width:32px; height:32px; background:#f7f9fb; border:none; display:flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; color:var(--navy); cursor:pointer; }
    .qty-input-box { width:44px; height:32px; border:none; text-align:center; font-weight:700; color:var(--navy); outline:none; }

    .empty-cart { padding: 60px 20px; text-align: center; background: #fff; border-radius: 16px; box-shadow: 0 12px 32px rgba(9,30,45,0.04); border: 1px solid rgba(11,47,74,0.03); }

    /* สไตล์ช่องส่วนลด */
    .discount-box { margin-top: 24px; padding-top: 24px; border-top: 1px dashed rgba(11,47,74,0.15); }
    .discount-input { flex: 1; padding: 12px 14px; border: 1px solid rgba(11,47,74,0.15); border-radius: 8px; font-family: inherit; font-size: 0.95rem; outline: none; transition: 0.2s; }
    .discount-input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(30,144,255,0.1); }
    
    /* แก้ไขปุ่มใช้โค้ด: เอา transform: scale() ออก เพื่อไม่ให้ปุ่มขยับ/กระพริบ */
    .btn-apply { background: linear-gradient(90deg, #1e2124, #4a5568); color: white; padding: 12px 20px; border-radius: 8px; border: none; font-weight: 800; cursor: pointer; transition: background-color 0.2s; }
    .btn-apply:hover { background: var(--navy); }

    .active-discount { display: flex; justify-content: space-between; align-items: center; background: #f6fff6; border: 1px solid rgba(18,122,59,0.2); padding: 12px 16px; border-radius: 8px; color: #127a3b; font-weight: 700; font-size: 0.95rem; }

    @media (max-width:900px){ .cart-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container cart-page">
    <?php if (empty($cartItems)): ?>
      
      <div style="max-width: 600px; margin: 0 auto;">
        <h1 style="margin-bottom:24px; font-weight:800; font-size:1.8rem; color:var(--navy);">ตะกร้าของฉัน</h1>
        <div class="empty-cart">
          <div style="font-size: 4rem; margin-bottom: 16px;">🛒</div>
          <div style="margin-bottom:24px; color: var(--navy); font-size: 1.15rem; font-weight: 700;">ตะกร้าของคุณยังไม่มีสินค้า</div>
          <a class="btn btn-primary" href="shop.php" style="padding: 14px 32px; font-size: 1.05rem;">ไปเลือกซื้อสินค้า</a>
        </div>
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
              <div class="cart-item">
                <div class="thumb">
                  <?php if (!empty($it['image_url'])): ?>
                    <img src="<?php echo h($it['image_url']); ?>" alt="" style="max-width:85%;max-height:85%;object-fit:contain;">
                  <?php else: ?>
                    <div style="color:#ccc;">ไม่มีรูป</div>
                  <?php endif; ?>
                </div>
                <div class="meta">
                  <div class="sku">รหัสสินค้า: <?php echo h(formatSKU($it['id'])); ?></div>
                  <div class="title"><?php echo h($it['name']); ?></div>
                  <div class="price"><?php echo ($it['price'] !== null) ? h(fmtPrice($it['price'])) . ' ฿' : 'สอบถามราคา'; ?></div>
                </div>
                <div class="actions">
                  <div class="qty-control">
                    <form method="post" action="cart.php" style="display:flex;">
                      <input type="hidden" name="update_qty" value="1"><input type="hidden" name="pid" value="<?php echo h($it['id']); ?>"><input type="hidden" name="qty" value="<?php echo (int)$it['qty'] - 1; ?>">
                      <button type="submit" class="qty-btn">−</button>
                    </form>
                    <input type="number" value="<?php echo (int)$it['qty']; ?>" class="qty-input-box" readonly>
                    <form method="post" action="cart.php" style="display:flex;">
                      <input type="hidden" name="update_qty" value="1"><input type="hidden" name="pid" value="<?php echo h($it['id']); ?>"><input type="hidden" name="qty" value="<?php echo (int)$it['qty'] + 1; ?>">
                      <button type="submit" class="qty-btn">+</button>
                    </form>
                  </div>
                  <form method="post" action="cart.php"><button type="submit" name="remove" value="<?php echo h($it['id']); ?>" class="btn btn-danger" style="padding:6px 12px;font-size:0.85rem;">ลบ</button></form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <aside>
          <div class="cart-summary">
            <h3 style="margin:0 0 16px 0; font-weight:800; font-size:1.2rem;">สรุปคำสั่งซื้อ</h3>
            <div style="display:flex;justify-content:space-between;margin:12px 0;font-weight:600; color:var(--muted);">
              <div>ยอดรวมสินค้า</div>
              <div><?php echo h(fmtPrice($subtotal)); ?> ฿</div>
            </div>

            <?php if ($discountAmount > 0): ?>
            <div style="display:flex;justify-content:space-between;margin:12px 0;font-weight:700; color:#127a3b;">
              <div>ส่วนลด</div>
              <div>-<?php echo h(fmtPrice($discountAmount)); ?> ฿</div>
            </div>
            <?php endif; ?>

            <div style="display:flex;justify-content:space-between;border-top:2px dashed rgba(11,47,74,0.1);padding-top:16px;margin-top:12px; margin-bottom: 8px;">
              <div style="font-weight:900; font-size:1.15rem; color:var(--navy);">ยอดรวมสุทธิ</div>
              <div style="font-weight:900; font-size:1.4rem; color:#ff4d4f;"><?php echo h(fmtPrice($grandTotal)); ?> ฿</div>
            </div>

            <div class="discount-box">
              <h4 style="margin: 0 0 12px 0; font-size: 0.95rem; color: var(--navy); display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><circle cx="7" cy="7" r="1"></circle></svg>
                เพิ่มรหัสส่วนลด
              </h4>
              
              <?php if ($discountError): ?>
                <div style="color: #ff4d4f; font-size: 0.85rem; margin-bottom: 8px; font-weight: 600;">❌ โค้ดส่วนลดไม่ถูกต้อง</div>
              <?php endif; ?>

              <?php if (!$discountCode): ?>
                <form method="post" action="cart.php" style="display: flex; gap: 8px; margin: 0;">
                  <input type="text" name="discount_code" class="discount-input" placeholder="พิมพ์รหัสที่นี่...">
                  <button type="submit" name="apply_discount" value="1" class="btn-apply">ใช้โค้ด</button>
                </form>
              <?php else: ?>
                <div class="active-discount">
                  <div style="display: flex; align-items: center; gap: 6px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <?php echo h($discountCode); ?>
                  </div>
                  <form method="post" action="cart.php" style="margin: 0;">
                    <button type="submit" name="remove_discount" value="1" style="background:none; border:none; color:#ff4d4f; cursor:pointer; font-weight:700;">นำออก</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>

            <div style="margin-top:24px; display:flex; flex-direction:column; gap:12px;">
              <a class="btn btn-primary" href="checkout.php" style="width:100%; font-size:1.1rem; padding: 14px;">ดำเนินการชำระเงิน</a>
              <a class="btn btn-outline" href="index.php" style="width:100%; padding: 12px;">กลับไปเลือกซื้อสินค้า</a>
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