// shop-widgets.js (patched) - ensure header wishlist badge placement + normalized fav button
(function () {
  'use strict';

  const HEART_SVG = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
                    '<path d="M12.1 21s-7.6-4.8-9.5-7.1C-0.6 11.5 2.2 6.6 6.6 6.6c2.3 0 3.9 1.5 4.9 2.6 1-1.1 2.6-2.6 4.9-2.6 4.4 0 7.2 4.9 3.9 7.3-1.9 2.3-9.5 7.1-9.5 7.1z" stroke="currentColor" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  function getProductId(card) {
    if (!card) return null;
    if (card.dataset.productId) return card.dataset.productId;
    if (card.dataset.id) return card.dataset.id;
    const link = card.querySelector('a[href*="product"], a[href*="product.php"], a[href*="/product/"]');
    if (link) {
      const m = link.getAttribute('href').match(/(?:id=|\/product\/)(\d+)/);
      if (m) return m[1];
    }
    if (card.id) {
      const mm = card.id.match(/(\d+)/);
      if (mm) return mm[1];
    }
    return null;
  }

  function addToWishlistAjax(pid) {
    return fetch('wishlist.php?action=toggle&id=' + encodeURIComponent(pid), { method: 'POST', credentials: 'same-origin' })
      .then(r => r.json().catch(() => ({ ok: r.ok })));
  }
  function addToCartRedirect(pid) {
    window.location.href = 'cart.php?add=' + encodeURIComponent(pid);
  }

  // Robust ensureHeaderWishBadge:
  // - find best target action-item inside .actions-box (search text / icon)
  // - find any existing badge candidates (various selectors)
  // - prefer badge already inside actions-box; else pick badge near (0,0) (misplaced) or create new
  // - move chosen badge into target and remove other duplicates
  function ensureHeaderWishBadge() {
    try {
      const actionsBox = document.querySelector('.actions-box');
      const badgeSelectors = ['.wish-count', '.count-badge.wish', '[data-wish-count]', '.wish-badge', '.count-badge'];
      const candidates = [];
      badgeSelectors.forEach(sel => {
        document.querySelectorAll(sel).forEach(el => {
          if (!candidates.includes(el)) candidates.push(el);
        });
      });

      // find wishlist action container inside actionsBox
      let wishlistAction = null;
      if (actionsBox) {
        // look for element with wishlist text or svg heart
        const items = Array.from(actionsBox.querySelectorAll('.action-item, button, a, div')).filter(Boolean);
        for (const el of items) {
          const txt = (el.textContent || '').trim().toLowerCase();
          if (/wish|wishlist|โปรด|รายการโปรด|my wishlist|หัวใจ|♥|♡/i.test(txt)) { wishlistAction = el; break; }
          // check for heart-like svg
          if (el.querySelector && el.querySelector('svg')) {
            const sv = el.querySelector('svg').outerHTML.toLowerCase();
            if (sv.includes('path') && sv.includes('d=')) { wishlistAction = el; break; }
          }
        }
        // fallback: first action-item or actionsBox itself
        if (!wishlistAction) wishlistAction = actionsBox.querySelector('.action-item') || actionsBox;
      }

      // choose badge to keep: prefer one already inside actionsBox
      let badgeToKeep = candidates.find(b => actionsBox && actionsBox.contains(b)) || null;

      // if none, try to find candidate near top-left (misplaced badge)
      if (!badgeToKeep) {
        badgeToKeep = candidates.find(b => {
          const r = b.getBoundingClientRect();
          return r && (r.x < 80 && r.y < 80); // likely misplaced at viewport origin
        }) || candidates[0] || null;
      }

      // if still none, create new
      if (!badgeToKeep) {
        badgeToKeep = document.createElement('span');
        badgeToKeep.className = 'wish-count count-badge';
        badgeToKeep.textContent = '0';
      }

      // remove other candidates (duplicates)
      candidates.forEach(b => { if (b !== badgeToKeep) try { b.remove(); } catch(e){} });

      // ensure target container exists
      const target = wishlistAction || document.querySelector('.actions-box') || document.body;
      // make sure target is positioned so absolute badge positions correctly
      try {
        const comp = getComputedStyle(target);
        if (comp.position === 'static') target.style.position = 'relative';
      } catch (e){}

      // move and mark
      if (badgeToKeep.parentElement !== target) target.appendChild(badgeToKeep);
      badgeToKeep.classList.add('wish-count', 'count-badge', 'in-action-item');
      // reset inline styles that could break layout
      badgeToKeep.style.position = 'absolute';
      badgeToKeep.style.top = '8px';
      badgeToKeep.style.left = '8px';
      badgeToKeep.style.zIndex = 120;
      // ensure numeric content is a number
      badgeToKeep.textContent = String(Number(badgeToKeep.textContent || 0) || 0);

      return badgeToKeep;
    } catch (err) {
      console.warn('ensureHeaderWishBadge error', err);
      return null;
    }
  }

  function updateHeaderWishCount(deltaOrValue, isAbsolute) {
    try {
      const badge = ensureHeaderWishBadge();
      if (!badge) return;
      if (isAbsolute) {
        badge.textContent = String(Number(deltaOrValue) || 0);
        return;
      }
      const delta = Number(deltaOrValue) || 0;
      const cur = Number(badge.textContent || 0);
      badge.textContent = String(Math.max(0, cur + delta));
    } catch (e) {
      console.warn('updateHeaderWishCount error', e);
    }
  }

  function bindFavButton(btn, pid) {
    if (!btn) return;
    if (btn.closest && btn.closest('.actions-box')) {
      if (!btn.getAttribute('role')) btn.setAttribute('role','button');
      if (!btn.getAttribute('aria-pressed')) btn.setAttribute('aria-pressed', btn.classList.contains('active') ? 'true' : 'false');
      // ensure badge exists
      ensureHeaderWishBadge();
      // but do not attach product card-specific network logic here
      return;
    }
    if (btn._boundFav) return; btn._boundFav = true;
    if (!btn.getAttribute('role')) btn.setAttribute('role','button');
    if (!btn.getAttribute('aria-pressed')) btn.setAttribute('aria-pressed', btn.classList.contains('active') ? 'true' : 'false');

    btn.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      const wasActive = btn.classList.contains('active');
      btn.classList.toggle('active');
      btn.setAttribute('aria-pressed', btn.classList.contains('active') ? 'true' : 'false');
      // optimistic count change
      updateHeaderWishCount(btn.classList.contains('active') ? 1 : -1);

      if (!pid) return;
      addToWishlistAjax(pid).then(res => {
        if (res && typeof res.success !== 'undefined') {
          if (!res.success) {
            btn.classList.toggle('active');
            btn.setAttribute('aria-pressed', btn.classList.contains('active') ? 'true' : 'false');
            updateHeaderWishCount(wasActive ? 1 : -1);
            if (res.message) console.warn('Wishlist error:', res.message);
          }
        }
      }).catch(() => {
        try {
          const key = 'local_wishlist';
          let arr = JSON.parse(localStorage.getItem(key) || '[]');
          if (!wasActive) { if (!arr.includes(pid)) arr.push(pid); }
          else arr = arr.filter(x => x !== pid);
          localStorage.setItem(key, JSON.stringify(arr));
        } catch (err) { console.warn(err); }
      });
    }, { passive: false });
  }

  function bindAddButton(btn, pid) {
    if (!btn) return;
    if (btn._boundAdd) return; btn._boundAdd = true;
    btn.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      if (!pid) { alert('ไม่พบรหัสสินค้า'); return; }
      addToCartRedirect(pid);
    });
  }

  function initCard(card) {
    if (!card || card.dataset.widgetsInitialized === '1') return;
    const pid = getProductId(card);

    let thumb = card.querySelector('.product-thumb');
    if (!thumb) {
      thumb = document.createElement('div');
      thumb.className = 'product-thumb empty-thumb';
      card.insertBefore(thumb, card.firstChild);
    }

    let fav = thumb.querySelector('.fav-btn, .wish-btn') || card.querySelector('.fav-btn, .wish-btn');
    if (fav && fav.parentElement !== thumb) thumb.appendChild(fav);

    if (!fav) {
      fav = document.createElement('button');
      fav.type = 'button';
      fav.className = 'fav-btn';
      fav.setAttribute('aria-label','เพิ่มรายการโปรด');
      fav.setAttribute('aria-pressed','false');
      fav.innerHTML = HEART_SVG;
      thumb.appendChild(fav);
    } else {
      fav.classList.remove('wish-btn');
      fav.classList.add('fav-btn');
      fav.innerHTML = HEART_SVG;
      if (!fav.getAttribute('aria-label')) fav.setAttribute('aria-label','เพิ่มรายการโปรด');
      if (!fav.getAttribute('aria-pressed')) fav.setAttribute('aria-pressed', fav.classList.contains('active') ? 'true' : 'false');
    }

    let meta = card.querySelector('.meta-actions');
    if (!meta) {
      meta = document.createElement('div');
      meta.className = 'meta-actions';
      const price = card.querySelector('.product-price');
      if (price) price.insertAdjacentElement('afterend', meta);
      else card.appendChild(meta);
    }

    let addBtn = meta.querySelector('.add-cart') || meta.querySelector('.btn-add') || card.querySelector('.add-cart, .btn-add');
    if (addBtn && addBtn.parentElement !== meta) meta.appendChild(addBtn);
    if (!addBtn) {
      addBtn = document.createElement('button');
      addBtn.type = 'button';
      addBtn.className = 'add-cart';
      addBtn.textContent = 'เพิ่มในตะกร้า';
      addBtn.title = 'เพิ่มในตะกร้า';
      meta.appendChild(addBtn);
    } else {
      addBtn.classList.remove('btn-add');
      addBtn.classList.add('add-cart');
    }

    bindFavButton(fav, pid);
    if (pid && !addBtn.dataset.id) addBtn.dataset.id = pid;
    bindAddButton(addBtn, pid);

    if (!card._cardBound) {
      card.addEventListener('click', function (e) {
        if (e.target.closest('.fav-btn') || e.target.closest('.add-cart')) return;
        const link = card.querySelector('a[href*="product"], a[href*="product.php"], a[href*="/product/"]');
        if (link) { window.location.href = link.href; return; }
      });
      card.addEventListener('dblclick', function (e) { if (!pid) return; addToCartRedirect(pid); });
      card._cardBound = true;
    }

    card.dataset.widgetsInitialized = '1';
  }

  function initAll() {
    document.querySelectorAll('.product-card').forEach(initCard);
    // ensure header badge on init and after small delay
    setTimeout(ensureHeaderWishBadge, 70);
    // also watch for badges added elsewhere and correct them
    const mo = new MutationObserver(muts => {
      muts.forEach(m => {
        m.addedNodes && m.addedNodes.forEach(node => {
          if (!(node instanceof HTMLElement)) return;
          if (node.matches && node.matches('.product-card')) initCard(node);
          else node.querySelectorAll && node.querySelectorAll('.product-card').forEach(initCard);
          // if badge-like nodes created, ensure header badge reattached
          if (node.matches && (node.matches('.count-badge') || node.matches('.wish-count'))) ensureHeaderWishBadge();
          if (node.querySelectorAll && (node.querySelectorAll('.count-badge').length || node.querySelectorAll('.wish-count').length)) ensureHeaderWishBadge();
        });
      });
    });
    mo.observe(document.body, { childList: true, subtree: true });
  }

  const grid = document.getElementById('productGrid') || document.querySelector('.carousel-track');
  if (grid) {
    initAll();
  } else {
    document.addEventListener('DOMContentLoaded', initAll);
  }

  window.updateHeaderWishCount = updateHeaderWishCount;

})();