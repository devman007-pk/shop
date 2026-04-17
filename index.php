<?php
declare(strict_types=1);

$brands = [];
$products = [];

// load config if exists
$configLoaded = false;
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    if (function_exists('getPDO')) {
        try {
            $pdo = getPDO();

            // brands
            $stmt = $pdo->query("SELECT id, name, logo_url, NULL AS product_count FROM brands LIMIT 12");
            $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // products
            $stmt = $pdo->query("SELECT id, name, COALESCE(image_url, NULL) AS image_url, COALESCE(price, NULL) AS price FROM products LIMIT 12");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $configLoaded = true;
        } catch (Throwable $e) {
            $configLoaded = false;
        }
    }
}

if (!$configLoaded) {
    $brands = [
        ['id' => 1, 'name' => 'HIKVISION', 'logo_url' => 'https://placehold.co/120x60?text=HIKVISION', 'product_count' => 681],
        ['id' => 2, 'name' => 'DAHUA', 'logo_url' => 'https://placehold.co/120x60?text=DAHUA', 'product_count' => 337],
        ['id' => 3, 'name' => 'IMOU', 'logo_url' => 'https://placehold.co/120x60?text=IMOU', 'product_count' => 64],
        ['id' => 4, 'name' => 'VIGI', 'logo_url' => 'https://placehold.co/120x60?text=VIGI', 'product_count' => 64],
        ['id' => 5, 'name' => 'Brand E', 'logo_url' => 'https://placehold.co/120x60?text=OTHER', 'product_count' => 120],
    ];

    $products = [
        ['id' => 'p1', 'name' => 'สินค้า ตัวอย่าง 1', 'image_url' => null, 'price' => 1290.00],
        ['id' => 'p2', 'name' => 'สินค้า ตัวอย่าง 2', 'image_url' => null, 'price' => 2990.00],
        ['id' => 'p3', 'name' => 'สินค้า ตัวอย่าง 3', 'image_url' => null, 'price' => 459.00],
        ['id' => 'p4', 'name' => 'สินค้า ตัวอย่าง 4', 'image_url' => null, 'price' => 9999.00],
    ];
}

// ensure we have 12 products
$desiredTotal = 12;
if (count($products) > 0) {
    $i = 0;
    while (count($products) < $desiredTotal) {
        $src = $products[$i % count($products)];
        $clone = $src;
        $clone['id'] = (string)$src['id'] . '-c' . (int)count($products);
        $products[] = $clone;
        $i++;
        if ($i > 1000) break;
    }
} else {
    for ($k = 1; $k <= $desiredTotal; $k++) {
        $products[] = [
            'id' => 'ph' . $k,
            'name' => 'สินค้า ตัวอย่าง ' . $k,
            'image_url' => null,
            'price' => null
        ];
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function formatPrice($p) {
    if ($p === null || $p === '') return null;
    if (!is_numeric($p)) return null;
    return number_format((float)$p, 2);
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ร้านตัวอย่าง - ธีม ฟ้า น้ำเงิน เขียว</title>

  <link rel="stylesheet" href="styles.css">

</head>
<body>

  <?php
  if (file_exists(__DIR__ . '/navbar.php')) {
      include __DIR__ . '/navbar.php';
  } else {
      echo '<header style="padding:20px;"><h1>Brand</h1></header>';
  }
  ?>

  <main>
    <section id="home" class="hero">
      <div class="container hero-content">
        <h1>ยินดีต้อนรับสู่ร้านตัวอย่าง</h1>
        <p>สินค้าเกรดคุณภาพ พร้อมแบรนด์ชั้นนำ เลือกซื้อได้ง่าย ๆ</p>
        <div class="hero-cta">
          <a class="btn btn-primary" href="shop.php">ไปที่ร้านค้า</a>
          <a class="btn btn-outline" href="brand.php">ดูแบรนด์</a>
        </div>
      </div>
    </section>

    <section class="container section">
      <div class="carousel-box shop-by-brands">
        <div class="section-header">
          <h2 class="section-title">Shop By Brands</h2>
          <a class="see-more" href="brand.php">ดูเพิ่มเติม</a>
        </div>

        <div class="carousel" data-carousel data-per-page="5" data-autoplay="true" data-autoplay-interval="2500">
          <button class="carousel-btn prev" aria-label="เลื่อนกลับ">‹</button>
          <div class="carousel-track" role="list">
            <?php foreach ($brands as $b): ?>
              <div class="carousel-item" role="listitem">
                <div class="brand-card">
                  <div class="brand-thumb">
                    <img src="<?php echo h($b['logo_url']); ?>" alt="<?php echo h($b['name']); ?>">
                  </div>
                  <div class="brand-name"><?php echo h($b['name']); ?></div>
                  <div class="brand-count"><?php echo isset($b['product_count']) ? h($b['product_count']).' products' : ''; ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <button class="carousel-btn next" aria-label="เลื่อนถัดไป">›</button>
        </div>
      </div>
    </section>

    <section id="shop" class="container section">
      <div class="carousel-box">
        <div class="section-header">
          <h2 class="section-title">สินค้าแนะนำ</h2>
          <a class="see-more" href="shop.php">ดูเพิ่มเติม</a>
        </div>

        <div class="carousel" data-carousel data-per-page="4" data-autoplay="true" data-autoplay-interval="3000">
          <button class="carousel-btn prev" aria-label="เลื่อนกลับ">‹</button>
          <div class="carousel-track" role="list">
            <?php foreach ($products as $p): ?>
              <div class="carousel-item" role="listitem">
                <article class="product-card" data-product-id="<?php echo h($p['id']); ?>">
                  <div class="product-thumb <?php echo empty($p['image_url']) ? 'empty-thumb' : ''; ?>">
                    <?php if (!empty($p['image_url'])): ?>
                      <img src="<?php echo h($p['image_url']); ?>" alt="<?php echo h($p['name']); ?>">
                    <?php else: ?>
                      <span class="thumb-label">รูปสินค้า</span>
                    <?php endif; ?>
                    
                    <button class="fav-btn" type="button" aria-label="เพิ่มรายการโปรด" data-pid="<?php echo h($p['id']); ?>">
                      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12.1 21s-7.6-4.8-9.5-7.1C-0.6 11.5 2.2 6.6 6.6 6.6c2.3 0 3.9 1.5 4.9 2.6 1-1.1 2.6-2.6 4.9-2.6 4.4 0 7.2 4.9 3.9 7.3-1.9 2.3-9.5 7.1-9.5 7.1z" stroke="currentColor" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </button>
                  </div>

                  <h3 class="prod-title"><?php echo h($p['name']); ?></h3>

                  <?php $fmt = formatPrice(isset($p['price']) ? $p['price'] : null); ?>
                  <div class="product-price">
                    <?php if ($fmt !== null): ?>
                      <?php echo h($fmt); ?> ฿
                    <?php else: ?>
                      สอบถามราคา
                    <?php endif; ?>
                  </div>

                  <div class="card-actions">
                    <button class="add-cart btn-icon" type="button" data-id="<?php echo h($p['id']); ?>">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                      เพิ่มในตะกร้า
                    </button>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
          <button class="carousel-btn next" aria-label="เลื่อนถัดไป">›</button>
        </div>
      </div>
    </section>

    <section id="wifi" class="container section">
      <div class="carousel-box">
        <div class="section-header">
          <h2 class="section-title">กล้องวงจรปิด WIFI</h2>
          <a class="see-more" href="shop.php">ดูเพิ่มเติม</a>
        </div>

        <div class="carousel" data-carousel data-per-page="4" data-autoplay="true" data-autoplay-interval="2800">
          <button class="carousel-btn prev" aria-label="เลื่อนกลับ">‹</button>
          <div class="carousel-track" role="list">
            <?php for ($i = 1; $i <= 12; $i++): ?>
              <div class="carousel-item" role="listitem">
                <div class="product-card" data-product-id="wifi-<?php echo $i; ?>">
                  <div class="product-thumb empty-thumb">
                    <span class="thumb-label">สินค้า</span>
                    
                    <button class="fav-btn" type="button" aria-label="เพิ่มรายการโปรด" data-pid="wifi-<?php echo $i; ?>">
                      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12.1 21s-7.6-4.8-9.5-7.1C-0.6 11.5 2.2 6.6 6.6 6.6c2.3 0 3.9 1.5 4.9 2.6 1-1.1 2.6-2.6 4.9-2.6 4.4 0 7.2 4.9 3.9 7.3-1.9 2.3-9.5 7.1-9.5 7.1z" stroke="currentColor" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </button>
                  </div>
                  <h3 class="prod-title">WIFI <?php echo $i; ?></h3>
                  <div class="product-price">999,999.00 ฿</div>
                  <div class="card-actions">
                    <button class="add-cart btn-icon" type="button" data-id="wifi-<?php echo $i; ?>">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                      เพิ่มในตะกร้า
                    </button>
                  </div>
                </div>
              </div>
            <?php endfor; ?>
          </div>
          <button class="carousel-btn next" aria-label="เลื่อนถัดไป">›</button>
        </div>
      </div>
    </section>

  </main>

  <?php
  $footerPath = __DIR__ . '/footer.php';
  if (file_exists($footerPath)) {
      include_once $footerPath;
  }
  ?>

  <script src="script.js"></script>

</body>
</html>