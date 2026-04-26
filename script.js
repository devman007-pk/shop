// script.js - Fixed Realtime Sync, Cart Animations & Pagination
(function () {
  'use strict';

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (m) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]; });
  }

  // --- 1. LocalStorage (ระบบความจำสำหรับหัวใจ และ ตะกร้า) ---
  const WISH_KEY = 'site_wishlist_v1';
  const CART_KEY = 'site_cart_v1';

  function getWishlist() {
    try { return JSON.parse(localStorage.getItem(WISH_KEY) || '[]'); } catch(e) { return []; }
  }
  function saveWishlist(arr) {
    localStorage.setItem(WISH_KEY, JSON.stringify(arr));
  }

  function getCart() {
    try { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); } catch(e) { return []; }
  }
  function saveCart(arr) {
    localStorage.setItem(CART_KEY, JSON.stringify(arr));
  }

  function animateBadge(badge) {
    badge.style.transform = 'scale(1.5)';
    badge.style.transition = 'transform 0.2s ease-out';
    setTimeout(() => { badge.style.transform = 'scale(1)'; }, 200);
  }

  // --- 2. Sync UI ---
  function syncAllHearts(animate = false) {
    const savedWish = getWishlist();
    document.querySelectorAll('.wish-count, .count-badge.wish').forEach(b => {
      if (b.textContent !== savedWish.length.toString()) {
        b.textContent = savedWish.length;
        if (animate) animateBadge(b);
      }
    });

    document.querySelectorAll('.fav-btn').forEach(btn => {
      const pid = btn.dataset.pid || (btn.closest('.product-card') && btn.closest('.product-card').dataset.productId);
      if (pid) {
        if (savedWish.includes(String(pid))) {
          btn.classList.add('active'); 
          btn.style.color = '#ff4d4f';
        } else {
          btn.classList.remove('active'); 
          btn.style.color = ''; 
        }
      }
    });
  }

  function syncCartCount(animate = false) {
    const savedCart = getCart();
    document.querySelectorAll('.cart-count').forEach(b => {
      if (b.textContent !== savedCart.length.toString()) {
        b.textContent = savedCart.length;
        if (animate) animateBadge(b);
      }
    });
  }

  // --- 3. ดักจับการกดหัวใจ และ ตะกร้าสินค้า ---
  document.addEventListener('click', function(e) {
    const favBtn = e.target.closest('.fav-btn');
    if (favBtn) {
      e.preventDefault(); e.stopPropagation();
      const pid = favBtn.dataset.pid || (favBtn.closest('.product-card') && favBtn.closest('.product-card').dataset.productId);
      if (!pid) return;
      let saved = getWishlist();
      if (saved.includes(String(pid))) { saved = saved.filter(id => id !== String(pid)); } 
      else { saved.push(String(pid)); }
      saveWishlist(saved);
      syncAllHearts(true); 
      return;
    }

    const cartBtn = e.target.closest('.add-cart, .btn-add');
    if (cartBtn) {
      e.preventDefault(); e.stopPropagation();
      const pid = cartBtn.dataset.id || (cartBtn.closest('.product-card') && cartBtn.closest('.product-card').dataset.productId);
      if (pid) {
        let cart = getCart();
        const originalHtml = cartBtn.innerHTML;
        if (!cart.includes(String(pid))) {
          cart.push(String(pid)); 
          saveCart(cart);
          syncCartCount(true); 
          cartBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> เพิ่มแล้ว!';
        } else {
          cartBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> มีในตะกร้าแล้ว!';
        }
        cartBtn.style.background = 'linear-gradient(90deg, #2bb673, #1e90ff)';
        cartBtn.style.color = '#fff';
        setTimeout(() => {
          cartBtn.innerHTML = originalHtml;
          cartBtn.style.background = ''; 
          cartBtn.style.color = '';
        }, 1500);
      }
    }
  });

  // --- 4. แก้ปัญหา Carousel ---
  function initCarousels() {
    document.querySelectorAll('[data-carousel]').forEach(carousel => {
      const track = carousel.querySelector('.carousel-track');
      if (!track) return;
      const items = Array.from(track.querySelectorAll('.carousel-item'));
      if (!items.length) return;

      const configuredPerPage = Math.max(1, parseInt(carousel.dataset.perPage) || 4);
      const autoplay = (carousel.dataset.autoplay !== undefined && carousel.dataset.autoplay !== 'false');
      const interval = parseInt(carousel.dataset.autoplayInterval) || 3000;
      const idle = parseInt(carousel.dataset.autoplayIdle) || 5000;

      let gap = 0, itemW = 0, perPage = configuredPerPage, pageW = 0;
      let totalPages = Math.max(1, Math.ceil(items.length / perPage));
      let pageIndex = 0, autoplayTimer = null, lastInteraction = 0, isDragging = false;

      function recalc() {
        gap = parseFloat(getComputedStyle(track).gap) || 0;
        itemW = items[0] ? Math.round(items[0].getBoundingClientRect().width) : 0;
        pageW = Math.round(perPage * itemW + Math.max(0, (perPage - 1) * gap));
        totalPages = Math.max(1, Math.ceil(items.length / perPage));
        if (pageIndex >= totalPages) pageIndex = totalPages - 1;
      }
      function scrollToPage(idx, behavior = 'smooth') { 
        idx = Math.max(0, Math.min(totalPages - 1, idx)); 
        pageIndex = idx; 
        track.scrollTo({ left: Math.round(idx * pageW), behavior }); 
      }
      function nextPage(manual = false) { if (manual) lastInteraction = Date.now(); scrollToPage((pageIndex + 1) % totalPages); }
      function prevPage(manual = false) { if (manual) lastInteraction = Date.now(); scrollToPage((pageIndex - 1 + totalPages) % totalPages); }

      function startAutoplay() { if (!autoplay || autoplayTimer) return; lastInteraction = 0; autoplayTimer = setInterval(() => { if (Date.now() - lastInteraction < idle) return; nextPage(false); }, interval); }
      function stopAutoplay() { if (autoplayTimer) { clearInterval(autoplayTimer); autoplayTimer = null; } }

      const prevBtn = carousel.querySelector('.carousel-btn.prev');
      const nextBtn = carousel.querySelector('.carousel-btn.next');
      if (prevBtn) prevBtn.addEventListener('click', (e) => { e.preventDefault(); prevPage(true); });
      if (nextBtn) nextBtn.addEventListener('click', (e) => { e.preventDefault(); nextPage(true); });

      let startX = 0, startScroll = 0;
      track.addEventListener('pointerdown', (e) => { 
        if (e.target.closest('button, a, .fav-btn')) return; 
        isDragging = true; startX = e.clientX; startScroll = track.scrollLeft; 
        try { track.setPointerCapture(e.pointerId); } catch (e) {} 
        lastInteraction = Date.now(); stopAutoplay(); 
      });
      track.addEventListener('pointermove', (e) => { if (!isDragging) return; track.scrollLeft = startScroll - (e.clientX - startX); });
      function endDrag(e) { if (!isDragging) return; isDragging = false; try { track.releasePointerCapture(e.pointerId); } catch (err) {} recalc(); scrollToPage(Math.round((track.scrollLeft || 0) / (pageW || 1)), 'smooth'); lastInteraction = Date.now(); if (autoplay) startAutoplay(); }
      track.addEventListener('pointerup', endDrag);
      track.addEventListener('pointercancel', endDrag);
      track.addEventListener('pointerleave', (e) => { if (isDragging) endDrag(e); });

      let rt;
      window.addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(() => { recalc(); scrollToPage(pageIndex, 'auto'); }, 120); });
      setTimeout(() => { recalc(); scrollToPage(0, 'auto'); if (autoplay) startAutoplay(); }, 200);
    });
  }

  // --- 5. Sidebar ย่อ/ขยาย ---
  function initCollapsibles() {
    document.querySelectorAll('.page-main .sidebar .filter-section > h4').forEach(h => {
      const sect = h.parentElement;
      const body = sect ? sect.querySelector('.filter-body') : null;
      if (sect && body) {
        sect.classList.add('collapsed');
        body.style.maxHeight = '0';
        body.style.opacity = '0';
      }
      h.addEventListener('click', () => {
        if (!sect) return;
        sect.classList.toggle('collapsed');
        if (body) {
          if (sect.classList.contains('collapsed')) { body.style.maxHeight = '0'; body.style.opacity = '0'; } 
          else { body.style.maxHeight = '900px'; body.style.opacity = '1'; }
        }
      });
    });
  }

  // --- 6. โหลดสินค้าด้วย AJAX พร้อมระบบแบ่งหน้า (Pagination) ---
  let perPage = 15; // ⚡ ตั้งค่า 1 หน้า = 15 ชิ้น
  let currentFilters = {}; // เก็บค่าตัวกรองปัจจุบัน

  function formatPrice(n) { try { return new Intl.NumberFormat('th-TH').format(Number(n)); } catch (e) { return String(n); } }

  async function loadProducts(opts = {}) {
    currentFilters = { ...opts }; // จำค่าไว้เผื่อกดเปลี่ยนหน้า
    const grid = document.getElementById('productGrid'), totalCountEl = document.getElementById('totalCount');
    if (!grid || typeof window.SHOP_API === 'undefined') return;
    
    const qs = new URLSearchParams();
    qs.set('ajax', '1');
    qs.set('page', opts.page || 1);
    qs.set('per_page', perPage);
    ['q', 'category', 'min_price', 'max_price', 'sort'].forEach(k => { if (opts[k]) qs.set(k, opts[k]); });
    if (opts.in_stock) qs.set('in_stock', '1');

    try {
      const res = await fetch(window.SHOP_API + '?' + qs.toString(), { credentials: 'same-origin' });
      if (!res.ok) throw new Error('Error ' + res.status);
      const data = await res.json();
      grid.innerHTML = '';
      
      data.items.forEach(item => {
        const card = document.createElement('article');
        card.className = 'product-card';
        card.dataset.productId = item.id;
        
        const priceDisplay = (item.price === null) ? 'สอบถามราคา' : `฿${formatPrice(item.price)}`;

        card.innerHTML = `
          <div class="product-thumb">
            <img src="${escapeHtml(item.image_url || '')}" alt="${escapeHtml(item.title || '')}" />
            <button class="fav-btn" type="button" aria-label="เพิ่มรายการโปรด" data-pid="${escapeHtml(item.id)}">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12.1 21s-7.6-4.8-9.5-7.1C-0.6 11.5 2.2 6.6 6.6 6.6c2.3 0 3.9 1.5 4.9 2.6 1-1.1 2.6-2.6 4.9-2.6 4.4 0 7.2 4.9 3.9 7.3-1.9 2.3-9.5 7.1-9.5 7.1z" stroke="currentColor" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
          <div class="product-meta">
            <h3 class="prod-title">${escapeHtml(item.title)}</h3>
            <div class="product-price" style="color: #9B0F06; font-weight: 800; font-size: 1.1rem; margin-top: 8px;">
                ${priceDisplay}
            </div>
            <div class="card-actions">
              <button class="add-cart btn-icon" data-id="${escapeHtml(item.id)}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                เพิ่มในตะกร้า
              </button>
            </div>
          </div>
        `;
        grid.appendChild(card);
      });
      
      if (totalCountEl) totalCountEl.textContent = data.total;

      // ---------------------------------------------
      // ⚡ สร้างปุ่มแบ่งหน้า (Pagination) ต่อท้ายสินค้า
      // ---------------------------------------------
      let paginationWrap = document.getElementById('paginationWrap');
      if (!paginationWrap) {
          paginationWrap = document.createElement('div');
          paginationWrap.id = 'paginationWrap';
          paginationWrap.className = 'pagination-wrap';
          grid.parentNode.insertBefore(paginationWrap, grid.nextSibling); 
      }

      const totalPages = Math.max(1, Math.ceil(data.total / data.per_page));
      const currentPage = data.page;

      let prevDisabled = currentPage <= 1 ? 'disabled' : '';
      let nextDisabled = currentPage >= totalPages ? 'disabled' : '';

      paginationWrap.innerHTML = `
          <div class="pagination-controls">
              <button class="page-btn prev-page" ${prevDisabled}>&laquo; ก่อนหน้า</button>
              <button class="page-btn next-page" ${nextDisabled}>ถัดไป &raquo;</button>
          </div>
          <div class="page-info">หน้า ${currentPage} จาก ${totalPages}</div>
      `;

      const prevBtn = paginationWrap.querySelector('.prev-page');
      const nextBtn = paginationWrap.querySelector('.next-page');

      if (prevBtn && !prevDisabled) {
          prevBtn.addEventListener('click', () => {
              loadProducts({ ...currentFilters, page: currentPage - 1 });
              window.scrollTo({ top: document.querySelector('.toolbar').offsetTop - 20, behavior: 'smooth' });
          });
      }
      if (nextBtn && !nextDisabled) {
          nextBtn.addEventListener('click', () => {
              loadProducts({ ...currentFilters, page: currentPage + 1 });
              window.scrollTo({ top: document.querySelector('.toolbar').offsetTop - 20, behavior: 'smooth' });
          });
      }

      // ซ่อนปุ่มถ้าไม่มีสินค้าเลย
      paginationWrap.style.display = data.total > 0 ? 'flex' : 'none';

    } catch (err) {}
  }
  window.loadProducts = loadProducts;

  // --- เริ่มต้นแอปพลิเคชัน ---
  function initAll() {
    initCarousels();
    initCollapsibles();

    document.querySelectorAll('.btn-reset').forEach(b => {
      b.addEventListener('click', (e) => {
        e.preventDefault();
        const sect = b.closest('.filter-section');
        if (!sect) return;
        sect.querySelectorAll('input[type="checkbox"]:checked, input[type="radio"]:checked').forEach(i => i.checked = false);
        sect.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
      });
    });

    if (typeof window.SHOP_API !== 'undefined') loadProducts({ page: 1 });

    syncAllHearts(false);
    syncCartCount(false);
  }

  document.addEventListener('DOMContentLoaded', () => { requestAnimationFrame(initAll); });

  const mo = new MutationObserver(muts => {
    let hasNewCards = false;
    muts.forEach(m => {
      m.addedNodes && m.addedNodes.forEach(node => {
        if (node.nodeType === 1 && (node.classList.contains('product-card') || node.querySelector('.product-card'))) { hasNewCards = true; }
      });
    });
    if (hasNewCards) syncAllHearts(false);
  });
  mo.observe(document.documentElement, { childList: true, subtree: true });

})();