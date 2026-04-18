/**
 * nav-shared.js
 * Shared nav behaviour for all pages except index.html
 * Handles: account button, cart badge, mobile menu
 */
(function () {
  'use strict';

  /* ─── Session ─────────────────────────────────────────────── */
  function getSession() {
    try { return JSON.parse(localStorage.getItem('alm_session')); } catch { return null; }
  }
  function clearSession() { localStorage.removeItem('alm_session'); }

  /* ─── Cart ─────────────────────────────────────────────────── */
  function getCart() {
    try { return JSON.parse(localStorage.getItem('alm_cart')) || []; } catch { return []; }
  }
  function saveCart(c) { localStorage.setItem('alm_cart', JSON.stringify(c)); }

  function toArabicPrice(n) {
    return n.toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ج.م';
  }

  /* ─── Inject CSS ───────────────────────────────────────────── */
  var style = document.createElement('style');
  style.textContent = [
    /* ── Mini profile dropdown ── */
    '#nav-pd{position:absolute;top:calc(100% + 12px);left:0;width:260px;background:#fdf9f0;border:1px solid rgba(60,0,4,0.1);border-radius:16px;box-shadow:0 16px 48px rgba(0,0,0,0.13);z-index:500;direction:rtl;overflow:hidden;opacity:0;pointer-events:none;transform:translateY(-8px);transition:opacity 0.2s ease,transform 0.2s ease;}',
    '#nav-pd.open{opacity:1;pointer-events:auto;transform:translateY(0);}',
    '.nav-pd-hd{background:#3c0004;padding:14px 16px;display:flex;align-items:center;gap:12px;}',
    '.nav-pd-av{width:40px;height:40px;border-radius:50%;background:rgba(254,214,91,0.18);border:1.5px solid rgba(254,214,91,0.3);display:flex;align-items:center;justify-content:center;flex-shrink:0;}',
    '.nav-pd-av .material-symbols-outlined{color:#fed65b;font-size:22px;font-variation-settings:"FILL" 1,"wght" 300,"GRAD" 0,"opsz" 24;}',
    '.nav-pd-nm{font-family:"Amiri",serif;font-size:1rem;color:#fff;font-weight:700;line-height:1.2;}',
    '.nav-pd-ph{font-size:0.72rem;color:rgba(255,255,255,0.5);margin-top:2px;direction:ltr;text-align:right;}',
    '.nav-pd-wallet{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-bottom:1px solid rgba(60,0,4,0.07);}',
    '.nav-pd-wl{display:flex;align-items:center;gap:5px;font-size:0.78rem;color:#897270;}',
    '.nav-pd-wl .material-symbols-outlined{font-size:15px;}',
    '.nav-pd-wv{font-family:"Amiri",serif;font-size:0.92rem;font-weight:700;color:#735c00;}',
    '.nav-pd-acts{padding:8px;}',
    '.nav-pd-ab{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;border:none;background:none;cursor:pointer;font-family:"Manrope",sans-serif;font-size:0.83rem;color:#1c1c17;width:100%;text-align:right;transition:background 0.15s;text-decoration:none;}',
    '.nav-pd-ab:hover{background:rgba(60,0,4,0.06);}',
    '.nav-pd-ab .material-symbols-outlined{font-size:17px;color:#897270;}',
    '.nav-pd-out{color:#b91c1c!important;}',
    '.nav-pd-out .material-symbols-outlined{color:#b91c1c!important;}',

    /* ── Cart dropdown (shared) ── */
    '#nav-cart-dd{position:absolute;top:calc(100% + 10px);left:0;width:320px;background:#fdf9f0;border:1px solid rgba(60,0,4,0.1);border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,0.15);z-index:300;direction:rtl;overflow:hidden;opacity:0;pointer-events:none;transform:translateY(-8px);transition:opacity 0.2s ease,transform 0.2s ease;}',
    '#nav-cart-dd.open{opacity:1;pointer-events:auto;transform:translateY(0);}',

    /* ── Mobile menu ── */
    '#nav-mm-bd{position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:399;opacity:0;pointer-events:none;transition:opacity 0.3s;}',
    '#nav-mm-bd.open{opacity:1;pointer-events:auto;}',
    '#nav-mm{position:fixed;top:0;left:0;width:280px;height:100vh;background:rgba(253,249,240,0.98);backdrop-filter:blur(20px);z-index:400;padding:100px 24px 24px;transform:translateX(-100%);transition:transform 0.3s cubic-bezier(0.4,0,0.2,1);box-shadow:10px 0 40px rgba(0,0,0,0.1);overflow-y:auto;}',
    '#nav-mm.open{transform:translateX(0);}',
    '#nav-mm-cls{position:absolute;top:32px;right:24px;width:40px;height:40px;border-radius:50%;border:1px solid rgba(60,0,4,0.2);background:none;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#3c0004;}',
    '.nav-mm-links{display:flex;flex-direction:column;gap:0;}',
    '.nav-mm-a{display:flex;align-items:center;gap:14px;color:rgba(60,0,4,0.7);font-family:"Amiri",serif;font-weight:600;font-size:1.35rem;padding:20px 0;border-bottom:1px solid rgba(60,0,4,0.1);text-decoration:none;transition:color 0.2s;min-height:64px;}',
    '.nav-mm-a:hover{color:#3c0004;}',
    '.nav-mm-a.cur{color:#3c0004;font-weight:700;}',
    '.nav-mm-a .material-symbols-outlined{font-size:26px;}'
  ].join('');
  document.head.appendChild(style);

  /* ─── Inject HTML ──────────────────────────────────────────── */

  // Profile dropdown
  var pdEl = document.createElement('div');
  pdEl.id = 'nav-pd';
  pdEl.innerHTML =
    '<div class="nav-pd-hd">' +
      '<div class="nav-pd-av"><span class="material-symbols-outlined">account_circle</span></div>' +
      '<div><div class="nav-pd-nm" id="nav-pd-nm">—</div><div class="nav-pd-ph" id="nav-pd-ph">—</div></div>' +
    '</div>' +
    '<div class="nav-pd-wallet">' +
      '<div class="nav-pd-wl"><span class="material-symbols-outlined">account_balance_wallet</span>رصيد المحفظة</div>' +
      '<div class="nav-pd-wv" id="nav-pd-wv">—</div>' +
    '</div>' +
    '<div class="nav-pd-acts">' +
      '<a href="onboarding.html" class="nav-pd-ab"><span class="material-symbols-outlined">edit_location_alt</span>تعديل بياناتي</a>' +
      '<button class="nav-pd-ab nav-pd-out" id="nav-pd-out"><span class="material-symbols-outlined">logout</span>تسجيل الخروج</button>' +
    '</div>';

  // Mobile menu
  var cur = (location.pathname.split('/').pop() || 'index.html');
  function ac(p) { return cur === p ? ' cur' : ''; }

  var mmBd = document.createElement('div');
  mmBd.id = 'nav-mm-bd';

  var mmEl = document.createElement('div');
  mmEl.id = 'nav-mm';
  mmEl.innerHTML =
    '<button id="nav-mm-cls"><span class="material-symbols-outlined" style="font-size:18px">close</span></button>' +
    '<div class="nav-mm-links">' +
      '<a href="index.html" class="nav-mm-a' + ac('index.html') + '"><span class="material-symbols-outlined">home</span>تراثنا</a>' +
      '<a href="collections.html" class="nav-mm-a' + ac('collections.html') + '"><span class="material-symbols-outlined">store</span>منتجاتنا</a>' +
      '<a href="reviews.html" class="nav-mm-a' + ac('reviews.html') + '"><span class="material-symbols-outlined">star</span>آراء العملاء</a>' +
      '<a href="about.html" class="nav-mm-a' + ac('about.html') + '"><span class="material-symbols-outlined">groups</span>من نحن</a>' +
    '</div>';

  document.body.appendChild(mmBd);
  document.body.appendChild(mmEl);

  /* ─── Account button ───────────────────────────────────────── */
  var accountBtn = document.getElementById('btn-account');

  function updateAccountIcon() {
    if (!accountBtn) return;
    var icon = accountBtn.querySelector('.material-symbols-outlined');
    if (!icon) return;
    if (getSession()) {
      icon.textContent = 'account_circle';
      icon.style.fontVariationSettings = "'FILL' 1,'wght' 300,'GRAD' 0,'opsz' 24";
    } else {
      icon.textContent = 'person';
      icon.style.fontVariationSettings = "'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24";
    }
  }

  function openPd() {
    var s = getSession();
    if (!s) return;
    document.getElementById('nav-pd-nm').textContent = s.name || 'مستخدم';
    document.getElementById('nav-pd-ph').textContent = s.phone || '—';
    // Fetch wallet balance
    fetch('apis/users/wallet.php')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok && d.balance !== undefined) {
          document.getElementById('nav-pd-wv').textContent =
            parseFloat(d.balance).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ج.م';
        }
      })
      .catch(function () {});
    pdEl.classList.add('open');
  }
  function closePd() { pdEl.classList.remove('open'); }

  if (accountBtn) {
    // Attach dropdown to the button's parent wrapper (needs position:relative)
    var acWrap = accountBtn.parentElement;
    acWrap.style.position = 'relative';
    acWrap.appendChild(pdEl);

    accountBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (getSession()) {
        pdEl.classList.contains('open') ? closePd() : openPd();
      } else if (window.almAuth) {
        window.almAuth.open('login');
      } else {
        window.location.href = 'index.html#login';
      }
    });
    document.addEventListener('click', function (e) {
      if (!pdEl.contains(e.target) && e.target !== accountBtn) closePd();
    });
  }

  var pdOutBtn = document.getElementById('nav-pd-out');
  if (pdOutBtn) pdOutBtn.addEventListener('click', function () {
    clearSession();
    window.location.href = 'index.html';
  });

  updateAccountIcon();

  // Update icon after login via auth-modal
  document.addEventListener('alm:session-changed', function () {
    updateAccountIcon();
    closePd();
  });

  /* ─── Cart badge ───────────────────────────────────────────── */
  function updateCartBadge() {
    var badge = document.getElementById('cart-badge');
    if (!badge) return;
    var total = getCart().reduce(function (s, i) { return s + (i.qty || 1); }, 0);
    badge.textContent = total;
    badge.style.display = total > 0 ? 'flex' : 'none';
  }

  /* ─── Cart dropdown (for pages without their own cart JS) ──── */
  var cartBtn   = document.getElementById('btn-cart');
  var cartDdBody = document.getElementById('cart-dd-body');
  var cartWrapper = document.getElementById('cart-icon-wrapper');

  // Only init if this page doesn't already have a cart dropdown body
  if (cartBtn && cartWrapper && !cartDdBody) {
    var ddEl = document.createElement('div');
    ddEl.id = 'nav-cart-dd';
    ddEl.setAttribute('aria-hidden', 'true');
    var ddBody = document.createElement('div');
    ddBody.id = 'nav-cart-dd-body';
    ddEl.appendChild(ddBody);
    cartWrapper.style.position = 'relative';
    cartWrapper.appendChild(ddEl);

    function buildCartRow(item) {
      var row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid rgba(60,0,4,0.06);direction:rtl;';
      var img = document.createElement('img');
      img.src = item.image || 'logo.png';
      img.style.cssText = 'width:44px;height:44px;border-radius:10px;object-fit:cover;flex-shrink:0;';
      var info = document.createElement('div');
      info.style.cssText = 'flex:1;min-width:0;';
      info.innerHTML = '<div style="font-family:\'Amiri\',serif;font-size:0.9rem;color:#3c0004;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + (item.name || '') + '</div>' +
        '<div style="font-size:0.75rem;color:#897270;margin-top:2px;">' + toArabicPrice(item.price * (item.qty || 1)) + '</div>';
      var qty = document.createElement('span');
      qty.style.cssText = 'font-size:0.75rem;color:#897270;flex-shrink:0;';
      qty.textContent = '×' + (item.qty || 1);
      row.append(img, info, qty);
      return row;
    }

    function renderNavCart() {
      var items = getCart();
      ddBody.innerHTML = '';
      var header = document.createElement('div');
      header.style.cssText = 'display:flex;align-items:center;gap:8px;padding:12px 14px;border-bottom:1px solid rgba(60,0,4,0.08);font-family:"Amiri",serif;font-size:0.95rem;font-weight:700;color:#3c0004;direction:rtl;';
      header.innerHTML = '<span class="material-symbols-outlined" style="font-size:18px;">shopping_bag</span>سلة التسوق';
      ddBody.appendChild(header);
      if (!items.length) {
        var empty = document.createElement('div');
        empty.style.cssText = 'text-align:center;padding:24px 16px;color:#897270;font-size:0.85rem;';
        empty.textContent = 'السلة فارغة';
        ddBody.appendChild(empty);
      } else {
        var list = document.createElement('div');
        list.style.maxHeight = '220px';
        list.style.overflowY = 'auto';
        items.forEach(function (i) { list.appendChild(buildCartRow(i)); });
        var total = items.reduce(function (s, i) { return s + i.price * (i.qty || 1); }, 0);
        var footer = document.createElement('div');
        footer.style.cssText = 'padding:12px 14px;border-top:1px solid rgba(60,0,4,0.08);';
        footer.innerHTML =
          '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;direction:rtl;">' +
            '<span style="font-size:0.82rem;color:#897270;">الإجمالي</span>' +
            '<span style="font-family:\'Amiri\',serif;font-size:1rem;font-weight:700;color:#3c0004;">' + toArabicPrice(total) + '</span>' +
          '</div>';
        var checkoutBtn = document.createElement('button');
        checkoutBtn.textContent = 'إتمام الطلب';
        checkoutBtn.style.cssText = 'width:100%;padding:10px;background:#3c0004;color:#fff;border:none;border-radius:10px;font-family:"Manrope",sans-serif;font-size:0.88rem;font-weight:600;cursor:pointer;';
        checkoutBtn.addEventListener('click', function () { window.location.href = 'checkout.html'; });
        footer.appendChild(checkoutBtn);
        ddBody.append(list, footer);
      }
    }

    function closeNavCart() {
      ddEl.classList.remove('open');
      ddEl.setAttribute('aria-hidden', 'true');
      cartBtn.setAttribute('aria-expanded', 'false');
    }

    cartBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = ddEl.classList.contains('open');
      if (!isOpen) renderNavCart();
      ddEl.classList.toggle('open');
      ddEl.setAttribute('aria-hidden', String(isOpen));
      cartBtn.setAttribute('aria-expanded', String(!isOpen));
    });
    document.addEventListener('click', function (e) {
      if (!cartWrapper.contains(e.target)) closeNavCart();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeNavCart();
    });
    renderNavCart();
  }

  updateCartBadge();

  /* ─── Mobile menu ──────────────────────────────────────────── */
  var menuBtn = document.getElementById('btn-mobile-menu');

  function openMM() {
    mmEl.classList.add('open');
    mmBd.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeMM() {
    mmEl.classList.remove('open');
    mmBd.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (menuBtn) {
    menuBtn.addEventListener('click', openMM);
  }
  mmBd.addEventListener('click', closeMM);
  document.getElementById('nav-mm-cls').addEventListener('click', closeMM);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMM();
  });

})();
