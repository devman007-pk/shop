<?php
declare(strict_types=1);
session_start();

$brands = [];
$products = [];
$recommendedProducts = []; 

// load config if exists
$configLoaded = false;
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    if (function_exists('getPDO')) {
        try {
            $pdo = getPDO();

            // ดึงแบรนด์และนับจำนวนสินค้าจากตาราง product_tags
            $stmt = $pdo->query("
                SELECT b.id, b.name, COALESCE(b.logo_url, '') AS logo_url,
                (
                    SELECT COUNT(DISTINCT pt.product_id) 
                    FROM product_tags pt 
                    JOIN products p ON pt.product_id = p.id 
                    WHERE pt.tag_group = 'brand' AND pt.tag_value = b.name AND p.is_active = 1
                ) AS product_count 
                FROM brands b 
                LIMIT 12
            ");
            if ($stmt) $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // เช็คก่อนว่ามีช่อง show_price ในฐานข้อมูลไหม (กันเว็บพัง Error 500)
            $hasShowPrice = false;
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'show_price'")->fetchAll();
                if (count($cols) > 0) $hasShowPrice = true;
            } catch (Throwable $e) {}
            
            $spCol = $hasShowPrice ? "p.show_price" : "1 AS show_price";

            // products (สินค้าใหม่)
            $stmtNew = $pdo->query("
                SELECT p.id, p.name, p.price, $spCol,
                (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url
                FROM products p 
                WHERE p.is_active = 1 AND p.is_new_product = 1
                ORDER BY p.id DESC
                LIMIT 12
            ");
            if ($stmtNew) $products = $stmtNew->fetchAll(PDO::FETCH_ASSOC);

            // recommended products (สินค้าแนะนำ)
            $stmtRec = $pdo->query("
                SELECT p.id, p.name, p.price, $spCol,
                (SELECT pi.url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.position ASC LIMIT 1) AS image_url
                FROM products p 
                WHERE p.is_active = 1 AND p.is_recommended = 1
                ORDER BY RAND()
                LIMIT 12
            ");
            if ($stmtRec) $recommendedProducts = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

            $configLoaded = true;
        } catch (Throwable $e) {
            $configLoaded = false;
        }
    }
}

if (!$configLoaded) {
    $brands = [
        ['id' => 1, 'name' => 'HIKVISION', 'logo_url' => 'https://placehold.co/120x60?text=HIKVISION', 'product_count' => 681],
        ['id' => 2, 'name' => 'DAHUA', 'logo_url' => 'https://placehold.co/120x60?text=DAHUA', 'product_count' => 337]
    ];
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
  
  <style>
    /* Hero Banner */
    .hero-banner-wrapper { position: relative; width: 100%; max-width: 1920px; margin: 0 auto; overflow: hidden; background: #0b2f4a; }
    .hero-slider-track { display: flex; transition: transform 0.5s ease-in-out; height: 450px; }
    @media (min-width: 1440px) { .hero-slider-track { height: 550px; } }
    .hero-slide { min-width: 100%; height: 100%; background-size: cover; background-position: center; position: relative; display: flex; align-items: center; justify-content: flex-start; padding: 0 10%; box-sizing: border-box; }
    .hero-slide-content { position: relative; z-index: 2; max-width: 600px; width: 100%; text-align: left; margin-top: 200px; }
    .btn-hero { display: inline-block; background: linear-gradient(90deg, #1e90ff, #0056b3); color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 800; font-size: 1.1rem; box-shadow: 0 8px 20px rgba(30, 144, 255, 0.4); transition: transform 0.2s, box-shadow 0.2s; }
    .btn-hero:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(30, 144, 255, 0.6); color: white; }
    .hero-arrow { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.8); color: var(--navy); border: none; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 50%; transition: 0.3s; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .hero-arrow:hover { background: #ffffff; color: var(--blue); box-shadow: 0 6px 16px rgba(0,0,0,0.2); }
    .hero-arrow.prev { left: 20px; } .hero-arrow.next { right: 20px; }
    .hero-arrow svg { width: 22px; height: 22px; }
    .hero-dots { position: absolute; bottom: 20px; width: 100%; text-align: center; z-index: 10; }
    .hero-dot { display: inline-block; width: 10px; height: 10px; margin: 0 5px; background: rgba(255,255,255,0.4); border-radius: 50%; cursor: pointer; transition: 0.3s; }
    .hero-dot.active { background: white; width: 28px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    @media (max-width: 768px) { .hero-slider-track { height: 220px; } .hero-arrow { width: 32px; height: 32px; } .hero-arrow.prev { left: 10px; } .hero-arrow.next { right: 10px; } .btn-hero { padding: 8px 20px; font-size: 0.95rem; } .hero-slide { padding: 0 5%; justify-content: center; } .hero-slide-content { text-align: center; margin-top: 80px; } }

    /* Brands */
    .shop-by-brands .carousel-item a { display: block; width: 100%; }
    .shop-by-brands .brand-card { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px; background: #fff; border: 1px solid #eee; border-radius: 8px; text-align: center; height: 140px; width: 100%; box-sizing: border-box; transition: all 0.2s ease; }
    .shop-by-brands .brand-card:hover { border-color: #1677ff; box-shadow: 0 4px 15px rgba(0,0,0,0.06); transform: translateY(-2px); }
    .shop-by-brands .brand-thumb { width: 100%; height: 55px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
    .shop-by-brands .brand-thumb img { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; }
    .shop-by-brands .brand-name { font-weight: 700; font-size: 0.95rem; color: #222; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .shop-by-brands .brand-count { font-size: 0.8rem; color: #888; margin-top: 3px; }
  </style>
</head>
<body>

  <?php
  if (file_exists(__DIR__ . '/navbar.php')) {
      include __DIR__ . '/navbar.php';
  }
  ?>

  <main>
    <section class="hero-banner-wrapper">
      <div class="hero-slider-track" id="heroSliderTrack">
        <div class="hero-slide" style="background-image: url('banner/1.png');"><div class="hero-slide-content"><a href="shop.php" class="btn-hero">Shop Now</a></div></div>
        <div class="hero-slide" style="background-image: url('banner/2.png');"><div class="hero-slide-content"><a href="shop.php" class="btn-hero" style="background: linear-gradient(90deg, #ff4d4f, #d9363e);">Shop Now</a></div></div>
        <div class="hero-slide" style="background-image: url('banner/3.png');"><div class="hero-slide-content"><a href="shop.php" class="btn-hero">Shop Now</a></div></div>
      </div>
      <button class="hero-arrow prev" onclick="moveHeroSlide(-1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></button>
      <button class="hero-arrow next" onclick="moveHeroSlide(1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg></button>
      <div class="hero-dots"><span class="hero-dot active" onclick="setHeroSlide(0)"></span><span class="hero-dot" onclick="setHeroSlide(1)"></span><span class="hero-dot" onclick="setHeroSlide(2)"></span></div>
    </section>

    <section class="container section">
      <div class="carousel-box shop-by-brands">
        <div class="section-header">
          <h2 class="section-title">Shop By Brands</h2>
          <a class="see-more" href="brand.php">ดูเพิ่มเติม</a>
        </div>
        <div class="carousel" data-carousel data-per-page="5" data-autoplay="true" data-autoplay-interval="2500">
          <button class="carousel-btn prev">‹</button>
          <div class="carousel-track" role="list">
            <?php foreach ($brands as $b): ?>
              <div class="carousel-item" role="listitem">
                <a href="shop.php?brand=<?php echo urlencode($b['name']); ?>" style="text-decoration:none; color:inherit;">
                    <div class="brand-card">
                      <div class="brand-thumb"><img src="<?php echo h($b['logo_url']); ?>" alt="<?php echo h($b['name']); ?>"></div>
                      <div class="brand-name"><?php echo h($b['name']); ?></div>
                      <div class="brand-count"><?php echo number_format((int)($b['product_count'] ?? 0)); ?> Products</div>
                    </div>
                </a>
              </div>
            <?php endforeach; ?>
            <?php if(empty($brands)): ?>
                <div style="width: 100%; text-align: center; padding: 40px; color: #888;">ยังไม่มีแบรนด์</div>
            <?php endif; ?>
          </div>
          <button class="carousel-btn next">›</button>
        </div>
      </div>
    </section>

    <section id="shop" class="container section">
      <div class="carousel-box">
        <div class="section-header">
          <h2 class="section-title">สินค้าใหม่</h2>
          <a class="see-more" href="shop.php">ดูเพิ่มเติม</a>
        </div>
        <div class="carousel" data-carousel data-per-page="4" data-autoplay="true" data-autoplay-interval="3000">
          <button class="carousel-btn prev">‹</button>
          <div class="carousel-track" role="list">
            <?php if(empty($products)): ?>
                <div style="width: 100%; text-align: center; padding: 40px; color: #888;">ยังไม่มีสินค้าในหมวดหมู่นี้</div>
            <?php else: ?>
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
                      
                      <div class="product-price" style="color: #9B0F06; font-weight: 800; font-size: 1.1rem; margin-top: 8px;">
                        <?php if (isset($p['show_price']) && $p['show_price'] == 0): ?>
                          <span>สอบถามราคา</span>
                        <?php elseif ($fmt !== null): ?>
                          ฿<?php echo h($fmt); ?> 
                        <?php else: ?>
                          <span>สอบถามราคา</span>
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
            <?php endif; ?>
          </div>
          <button class="carousel-btn next">›</button>
        </div>
      </div>
    </section>

    <section id="wifi" class="container section">
      <div class="carousel-box">
        <div class="section-header">
          <h2 class="section-title">สินค้าแนะนำ</h2>
          <a class="see-more" href="shop.php">ดูเพิ่มเติม</a>
        </div>
        <div class="carousel" data-carousel data-per-page="4" data-autoplay="false">
          <button class="carousel-btn prev">‹</button>
          <div class="carousel-track" role="list">
            <?php if(empty($recommendedProducts)): ?>
                <div style="width: 100%; text-align: center; padding: 40px; color: #888;">ยังไม่มีสินค้าในหมวดหมู่นี้</div>
            <?php else: ?>
                <?php foreach ($recommendedProducts as $rp): ?>
                  <div class="carousel-item" role="listitem">
                    <article class="product-card" data-product-id="<?php echo h($rp['id']); ?>">
                      <div class="product-thumb <?php echo empty($rp['image_url']) ? 'empty-thumb' : ''; ?>">
                        <?php if (!empty($rp['image_url'])): ?>
                          <img src="<?php echo h($rp['image_url']); ?>" alt="<?php echo h($rp['name']); ?>">
                        <?php else: ?>
                          <span class="thumb-label">รูปสินค้า</span>
                        <?php endif; ?>

                        <button class="fav-btn" type="button" aria-label="เพิ่มรายการโปรด" data-pid="<?php echo h($rp['id']); ?>">
                          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.1 21s-7.6-4.8-9.5-7.1C-0.6 11.5 2.2 6.6 6.6 6.6c2.3 0 3.9 1.5 4.9 2.6 1-1.1 2.6-2.6 4.9-2.6 4.4 0 7.2 4.9 3.9 7.3-1.9 2.3-9.5 7.1-9.5 7.1z" stroke="currentColor" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                          </svg>
                        </button>
                      </div>

                      <h3 class="prod-title"><?php echo h($rp['name']); ?></h3>
                      <?php $fmtRec = formatPrice(isset($rp['price']) ? $rp['price'] : null); ?>
                      
                      <div class="product-price" style="color: #9B0F06; font-weight: 800; font-size: 1.1rem; margin-top: 8px;">
                        <?php if (isset($rp['show_price']) && $rp['show_price'] == 0): ?>
                          <span>สอบถามราคา</span>
                        <?php elseif ($fmtRec !== null): ?>
                          ฿<?php echo h($fmtRec); ?> 
                        <?php else: ?>
                          <span>สอบถามราคา</span>
                        <?php endif; ?>
                      </div>
                      
                      <div class="card-actions">
                        <button class="add-cart btn-icon" type="button" data-id="<?php echo h($rp['id']); ?>">
                          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                          เพิ่มในตะกร้า
                        </button>
                      </div>

                    </article>
                  </div>
                <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <button class="carousel-btn next">›</button>
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

  <script>
    let currentHeroSlide = 0;
    const totalHeroSlides = 3; 
    const heroSliderTrack = document.getElementById('heroSliderTrack');
    const heroDots = document.querySelectorAll('.hero-dot');
    let heroInterval;

    function updateHeroSlider() {
        heroSliderTrack.style.transform = `translateX(-${currentHeroSlide * 100}%)`;
        heroDots.forEach((dot, index) => { dot.classList.toggle('active', index === currentHeroSlide); });
    }
    function moveHeroSlide(step) {
        currentHeroSlide = (currentHeroSlide + step + totalHeroSlides) % totalHeroSlides;
        updateHeroSlider(); resetHeroInterval();
    }
    function setHeroSlide(index) {
        currentHeroSlide = index;
        updateHeroSlider(); resetHeroInterval();
    }
    function startHeroInterval() { heroInterval = setInterval(() => { moveHeroSlide(1); }, 4000); }
    function resetHeroInterval() { clearInterval(heroInterval); startHeroInterval(); }

    startHeroInterval();
  </script>
  <script src="script.js"></script>
</body>
</html>