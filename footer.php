<?php
// footer.php - reusable footer fragment
// Place this file next to your index.php and include with:
//    include __DIR__ . '/footer.php';

// simple esc helper if not defined
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Allow optional override variables before include:
// $footer_links (array of ['label'=>'...', 'href'=>'...'])
// $footer_contact (array with 'email' and 'phone')
// $company_name (string)
$footer_links = $footer_links ?? [
    ['label' => 'หน้าแรก', 'href' => 'index.php'],
    ['label' => 'ร้านค้า', 'href' => 'shop.php'],
    ['label' => 'แบรนด์', 'href' => 'brand.php'],
    ['label' => 'เกี่ยวกับเรา', 'href' => 'aboutas.php'],
    ['label' => 'ผลงานของเรา', 'href' => 'ourwork.php'],
    ['label' => 'ติดต่อเรา', 'href' => 'contactus.php'],
];
$footer_contact = $footer_contact ?? [
    'email' => 'navapat@otm.co.th',
    'phone' => '081-649-2504',
];
$company_name = $company_name ?? 'บริษัท ออน ไทม์ แมนเนจเม้นท์ จำกัด';
$year = date('Y');
?>
<!-- Inline footer styles: make links white and remove underline -->
<style>
/* Footer links: white color, no underline */
.site-footer--dark a,
.site-footer--dark a:link,
.site-footer--dark a:visited {
  color: #ffffff !important;
  text-decoration: none !important;
}

/* Keep on hover/focus also white and no underline (slight opacity change optional) */
.site-footer--dark a:hover,
.site-footer--dark a:focus {
  color: #ffffff !important;
  text-decoration: none !important;
  opacity: 0.92;
}

/* Remove bullets and pad for footer lists */
.site-footer--dark .footer-links,
.site-footer--dark .contact-list {
  list-style: none !important;
  padding-left: 0 !important;
  margin: 0 !important;
}
.site-footer--dark .footer-links li,
.site-footer--dark .contact-list li {
  margin-bottom: 6px;
}
</style>

<footer class="site-footer site-footer--dark" role="contentinfo">
  <div class="container footer-top">
    <div class="footer-col footer-links-col">
      <h4>ลิงก์ที่มีประโยชน์</h4>
      <ul class="footer-links">
        <?php foreach ($footer_links as $ln): ?>
          <li><a href="<?php echo h($ln['href'] ?? '#'); ?>"><?php echo h($ln['label'] ?? ''); ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="footer-col footer-about-col">
      <h4>เกี่ยวกับเรา</h4>
      <p>ความพึงพอใจของท่านคือบริการของเรา</p>
    </div>

    <div class="footer-col footer-contact-col">
      <h4>ติดต่อเรา</h4>
      <ul class="contact-list">
        <li><span class="contact-icon">✉️</span> <a href="mailto:<?php echo h($footer_contact['email']); ?>"><?php echo h($footer_contact['email']); ?></a></li>
        <li><span class="contact-icon">📞</span> <a href="tel:<?php echo preg_replace('/\D+/', '', $footer_contact['phone']); ?>"><?php echo h($footer_contact['phone']); ?></a></li>
      </ul>
    </div>
  </div>

  <div class="container footer-bottom" style="padding-top:18px;">
    <div class="copyright">
      <small>ลิขสิทธิ์ © <?php echo h($year); ?> <?php echo h($company_name); ?></small>
    </div>
  </div>
</footer>