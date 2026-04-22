<?php
// checkout.php - หน้าตรวจสอบรายการและชำระเงิน (อัปเดตช่องเบอร์โทร 10 หลัก)
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';

// 1. ตรวจสอบว่ามีสินค้าในตะกร้าไหม
if (empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

// 2. ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header("Location: login.php");
    exit;
}

$pdo = getPDO();
$userId = $_SESSION['user_id'];

// 3. ดึงข้อมูลผู้ใช้มาใส่ในช่องกรอก (Pre-fill)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 4. คำนวณยอดเงิน
$cartItems = [];
$subtotal = 0.0;
$pids = array_keys($_SESSION['cart']);

if (!empty($pids)) {
    $placeholders = implode(',', array_fill(0, count($pids), '?'));
    $sql = "SELECT p.id, p.name, p.price, 
            (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url
            FROM products p WHERE p.id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($pids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $p) {
        $qty = (int)$_SESSION['cart'][$p['id']];
        $lineTotal = (float)$p['price'] * $qty;
        $subtotal += $lineTotal;
        $cartItems[] = array_merge($p, ['qty' => $qty, 'line_total' => $lineTotal]);
    }
}

// ส่วนลด
$discountAmount = 0.0;
$discountCode = $_SESSION['discount_code'] ?? null;
if ($discountCode === 'SAVE10') {
    $discountAmount = $subtotal * 0.10;
} elseif ($discountCode === 'MINUS50') {
    $discountAmount = 50.00;
}
$grandTotal = $subtotal - $discountAmount;

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ฟังก์ชันแปลง ID เป็น SKU
function formatSKU($id) {
    if (is_numeric($id)) return sprintf("PROD-%04d", (int)$id);
    return $id;
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" /><meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ชำระเงิน - Checkout</title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    .checkout-page { padding: 48px 0; background: #f9fbff; }
    .checkout-grid { display: grid; grid-template-columns: 1fr 450px; gap: 32px; align-items: start; }
    .card { background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 12px 32px rgba(9,30,45,0.04); border: 1px solid rgba(11,47,74,0.03); margin-bottom: 24px; }
    
    .section-title { font-size: 1.25rem; font-weight: 800; color: var(--navy); margin-bottom: 24px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f0f4f8; padding-bottom: 12px; }
    
    /* สไตล์ฟอร์ม */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    .form-group { display: flex; flex-direction: column; }
    .form-group.full-width { grid-column: span 2; }
    label { font-weight: 700; margin-bottom: 6px; color: var(--navy); font-size: 0.9rem; }
    input[type="text"], input[type="email"], input[type="tel"], textarea { padding: 12px 14px; border-radius: 8px; border: 1px solid rgba(11,47,74,0.15); font-family: inherit; font-size: 0.95rem; background: #fcfcfd; width: 100%; box-sizing: border-box; resize: vertical; }
    input:focus, textarea:focus { outline: none; border-color: #1677ff; background: #fff; box-shadow: 0 0 0 3px rgba(22,119,255,0.1); }

    /* Checkbox & ส่วนของใบกำกับภาษี */
    .checkbox-container { display: flex; align-items: center; gap: 10px; font-weight: 700; color: var(--navy); font-size: 1rem; cursor: pointer; margin-bottom: 15px; margin-top: 25px; border-top: 1px dashed #eee; padding-top: 25px; }
    .checkbox-container input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #1677ff; }
    
    .tax-invoice-form { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; display: none; margin-bottom: 20px; }
    .tax-invoice-form.active { display: block; animation: fadeIn 0.3s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

    /* =========================================
       ตารางสรุปคำสั่งซื้อ (Order Table) 
       ========================================= */
    .order-table { border: 1px solid #eef2f6; border-radius: 8px; overflow: hidden; font-size: 0.95rem; background: #fff; }
    .order-table-header { display: flex; justify-content: space-between; padding: 14px 16px; background: #fdfdfd; font-weight: 800; color: var(--navy); border-bottom: 1px solid #eef2f6; }
    
    /* รายการสินค้าแต่ละชิ้น */
    .order-table-items { max-height: 350px; overflow-y: auto; }
    .order-table-item { display: flex; justify-content: space-between; align-items: center; padding: 16px; border-bottom: 1px solid #eef2f6; gap: 12px; background: #fff; }
    .order-table-item:last-child { border-bottom: none; }
    .order-table-item img { width: 55px; height: 55px; object-fit: contain; border-radius: 8px; border: 1px solid #eee; flex-shrink: 0; background: #fff; }
    .order-table-info { flex: 1; display: flex; flex-direction: column; gap: 4px; }
    .order-table-sku { font-size: 0.75rem; color: #888; font-weight: 700; }
    .order-table-name { font-size: 0.9rem; font-weight: 700; color: var(--navy); line-height: 1.4; }
    .order-table-price { font-weight: 700; color: var(--navy); white-space: nowrap; text-align: right; }

    /* สรุปยอดด้านล่าง */
    .order-table-row { display: flex; justify-content: space-between; padding: 14px 16px; border-top: 1px solid #eef2f6; font-weight: 600; color: var(--navy); background: #fff; }
    .order-table-row.discount { color: #127a3b; }
    .order-table-row.total { background: #fcfdfe; font-weight: 900; font-size: 1.15rem; border-top: 2px solid #eef2f6; padding: 20px 16px; }
    .order-table-row.total .price { color: #ff4d4f; font-size: 1.25rem; }

    /* ปุ่มยืนยัน */
    .btn-confirm { 
      background: #1677ff; 
      color: #fff; 
      width: 100%; 
      padding: 16px; 
      border-radius: 10px; 
      border: 0; 
      font-size: 1.05rem; 
      font-weight: 800; 
      cursor: pointer; 
      box-shadow: 0 4px 12px rgba(22, 119, 255, 0.2); 
      transition: all 0.2s; 
      margin-top: 20px; 
    }
    .btn-confirm:hover { background: #0958d9; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(22, 119, 255, 0.3); }

    @media (max-width: 900px) { .checkout-grid { grid-template-columns: 1fr; } }
    @media (max-width: 600px) { .form-grid, .form-group.full-width { grid-template-columns: 1fr; grid-column: span 1; } }
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container checkout-page">
    <form action="process-order.php" method="POST">
      <input type="hidden" name="payment_method" value="bank_transfer">

      <div class="checkout-grid">
        
        <div>
          <div class="card">
            <h2 class="section-title">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
              ข้อมูลที่อยู่จัดส่ง
            </h2>
            
            <div class="form-grid">
              <div class="form-group">
                <label>ชื่อจริง</label>
                <input type="text" name="first_name" value="<?php echo h($user['first_name'] ?? ''); ?>" required placeholder="ชื่อ">
              </div>
              <div class="form-group">
                <label>นามสกุล</label>
                <input type="text" name="last_name" value="<?php echo h($user['last_name'] ?? ''); ?>" placeholder="นามสกุล">
              </div>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label>เบอร์โทรศัพท์ (10 หลัก)</label>
                <input type="tel" name="phone" value="<?php echo h($user['phone'] ?? ''); ?>" required 
                       placeholder="08x-xxx-xxxx" 
                       pattern="[0-9]{10}" 
                       maxlength="10" 
                       oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                       title="กรุณากรอกเบอร์โทรศัพท์ 10 หลัก (เฉพาะตัวเลข)">
              </div>
              <div class="form-group">
                <label>อีเมล</label>
                <input type="email" name="email" value="<?php echo h($user['email'] ?? ''); ?>" required placeholder="example@mail.com">
              </div>
            </div>

            <div class="form-grid">
              <div class="form-group full-width">
                <label>ที่อยู่ปัจจุบัน</label>
                <input type="text" name="address" value="<?php echo h($user['address'] ?? ''); ?>" required placeholder="บ้านเลขที่, ซอย, ถนน, ชื่อหมู่บ้าน/อาคาร">
              </div>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label>ตำบล / แขวง</label>
                <input type="text" name="subdistrict" value="<?php echo h($user['subdistrict'] ?? ''); ?>" required placeholder="ตำบล">
              </div>
              <div class="form-group">
                <label>อำเภอ / เขต</label>
                <input type="text" name="district" value="<?php echo h($user['district'] ?? ''); ?>" required placeholder="อำเภอ">
              </div>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label>จังหวัด</label>
                <input type="text" name="province" value="<?php echo h($user['province'] ?? ''); ?>" required placeholder="จังหวัด">
              </div>
              <div class="form-group">
                <label>รหัสไปรษณีย์</label>
                <input type="text" name="zipcode" value="<?php echo h($user['zipcode'] ?? ''); ?>" required placeholder="รหัสไปรษณีย์">
              </div>
            </div>

            <div class="form-grid" style="margin-top: 10px;">
              <div class="form-group full-width">
                <label>บันทึกเพิ่มเติม (ไม่บังคับ)</label>
                <textarea name="order_notes" rows="3" placeholder="ระบุความต้องการเพิ่มเติม เช่น ฝากพัสดุไว้ที่ป้อมยาม, ขอเปลี่ยนสีสินค้า ฯลฯ"></textarea>
              </div>
            </div>

            <label class="checkbox-container">
              <input type="checkbox" id="req_tax_invoice" name="req_tax_invoice" value="1">
              จัดส่งไปที่อยู่อื่น?
            </label>

            <div class="tax-invoice-form" id="tax_invoice_fields">
              <h4 style="margin: 0 0 15px 0; color: var(--navy);">ข้อมูลใบกำกับภาษี</h4>
              
              <div class="form-grid">
                <div class="form-group">
                  <label>ชื่อจริง</label>
                  <input type="text" name="tax_first_name" placeholder="ชื่อ">
                </div>
                <div class="form-group">
                  <label>นามสกุล</label>
                  <input type="text" name="tax_last_name" placeholder="นามสกุล">
                </div>
              </div>

              <div class="form-grid">
                <div class="form-group full-width">
                  <label>ชื่อบริษัท (ถ้ามี)</label>
                  <input type="text" name="tax_company_name" placeholder="บริษัท ตัวอย่าง จำกัด">
                </div>
              </div>

              <div class="form-grid">
                <div class="form-group full-width">
                  <label>ที่อยู่ปัจจุบัน</label>
                  <input type="text" name="tax_address" placeholder="บ้านเลขที่, ซอย, ถนน, ชื่อหมู่บ้าน/อาคาร">
                </div>
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label>ตำบล / แขวง</label>
                  <input type="text" name="tax_subdistrict" placeholder="ตำบล">
                </div>
                <div class="form-group">
                  <label>อำเภอ / เขต</label>
                  <input type="text" name="tax_district" placeholder="อำเภอ">
                </div>
              </div>

              <div class="form-grid">
                <div class="form-group">
                  <label>จังหวัด</label>
                  <input type="text" name="tax_province" placeholder="จังหวัด">
                </div>
                <div class="form-group">
                  <label>รหัสไปรษณีย์</label>
                  <input type="text" name="tax_zipcode" placeholder="รหัสไปรษณีย์">
                </div>
              </div>

            </div>

            <p style="margin-top: 15px; font-size: 0.85rem; color: #888;">* โปรดตรวจสอบความถูกต้องของที่อยู่และข้อมูลก่อนกดชำระเงิน</p>
          </div>
        </div>

        <aside>
          <div class="card" style="padding: 24px;">
            <h3 style="margin:0 0 20px 0; font-weight:800; font-size:1.3rem; color: var(--navy);">รายการสั่งซื้อของคุณ</h3>
            
            <div class="order-table">
              
              <div class="order-table-header">
                <div>สินค้า</div>
                <div>ยอดรวม</div>
              </div>

              <div class="order-table-items">
                <?php foreach ($cartItems as $it): ?>
                  <div class="order-table-item">
                    <img src="<?php echo h($it['image_url'] ?? 'placeholder.png'); ?>" alt="">
                    <div class="order-table-info">
                      <div class="order-table-sku">รหัส: <?php echo h(formatSKU($it['id'])); ?></div>
                      <div class="order-table-name"><?php echo h($it['name']); ?> <span style="color:#888; font-weight:600;">× <?php echo $it['qty']; ?></span></div>
                    </div>
                    <div class="order-table-price">฿<?php echo number_format($it['line_total'], 2); ?></div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="order-table-row">
                <span>ยอดรวม</span>
                <span>฿<?php echo number_format($subtotal, 2); ?></span>
              </div>

              <div class="order-table-row">
                <span>การจัดส่ง</span>
                <span style="color: #127a3b;">จัดส่งฟรี</span>
              </div>

              <?php if ($discountAmount > 0): ?>
                <div class="order-table-row discount">
                  <span>ส่วนลด (<?php echo h($discountCode); ?>)</span>
                  <span>-฿<?php echo number_format($discountAmount, 2); ?></span>
                </div>
              <?php endif; ?>

              <div class="order-table-row total">
                <span>รวม</span>
                <span class="price">฿<?php echo number_format($grandTotal, 2); ?></span>
              </div>

            </div>

            <button type="submit" class="btn-confirm">ดำเนินการชำระเงิน</button>
          </div>
        </aside>

      </div>
    </form>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('req_tax_invoice');
        const taxFields = document.getElementById('tax_invoice_fields');
        const taxInputs = taxFields.querySelectorAll('input[type="text"]');

        checkbox.addEventListener('change', function() {
            if (this.checked) {
                taxFields.classList.add('active');
                taxInputs.forEach(input => {
                    // ไม่บังคับกรอก นามสกุล และ ชื่อบริษัท (เผื่อเลือกกรอกแค่อย่างใดอย่างหนึ่ง)
                    if(input.name !== 'tax_last_name' && input.name !== 'tax_company_name') {
                        input.required = true;
                    }
                });
            } else {
                taxFields.classList.remove('active');
                taxInputs.forEach(input => input.required = false);
            }
        });
    });
  </script>

  <script src="script.js"></script>
</body>
</html>