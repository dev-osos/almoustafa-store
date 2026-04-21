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
    /* ── Profile dropdown — identical to index.html #profile-dropdown ── */
    '#nav-pd{position:fixed;transform:translateY(-8px);width:230px;background:rgba(22,10,3,0.97);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.1);border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,0.55),inset 0 1px 0 rgba(255,255,255,0.07);padding:16px 16px 14px;opacity:0;pointer-events:none;transition:opacity 0.2s ease,transform 0.2s ease;z-index:600;direction:rtl;}',
    '#nav-pd.open{opacity:1;pointer-events:auto;transform:translateY(0);}',
    '#nav-pd::before{content:"";position:absolute;top:-6px;left:14px;transform:rotate(45deg);width:11px;height:11px;background:rgba(22,10,3,0.97);border-left:1px solid rgba(255,255,255,0.1);border-top:1px solid rgba(255,255,255,0.1);}',
    '.pdd-header{display:flex;align-items:center;gap:10px;margin-bottom:12px;}',
    '.pdd-avatar{width:40px;height:40px;border-radius:50%;flex-shrink:0;background:rgba(254,214,91,0.1);border:2px solid rgba(254,214,91,0.28);display:flex;align-items:center;justify-content:center;}',
    '.pdd-avatar span{color:#fed65b;font-size:22px;font-variation-settings:"FILL" 1,"wght" 300,"GRAD" 0,"opsz" 24;}',
    '.pdd-info{flex:1;min-width:0;}',
    '.pdd-name{font-family:"Amiri",serif;font-size:0.95rem;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
    '.pdd-phone{font-size:0.72rem;color:rgba(255,255,255,0.4);direction:ltr;text-align:right;margin-top:1px;}',
    '.pdd-edit-list{display:flex;flex-direction:column;gap:8px;margin-bottom:12px;}',
    '.pdd-edit-item{width:100%;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:10px;cursor:pointer;transition:background 0.2s,border-color 0.2s;text-decoration:none;}',
    '.pdd-edit-item:hover{background:rgba(255,255,255,0.08);border-color:rgba(254,214,91,0.22);}',
    '.pdd-edit-item-main{min-width:0;display:flex;align-items:center;gap:8px;flex:1;}',
    '.pdd-edit-item-main .material-symbols-outlined{font-size:16px;color:rgba(254,214,91,0.72);flex-shrink:0;}',
    '.pdd-edit-copy{min-width:0;display:flex;flex-direction:column;gap:3px;text-align:right;}',
    '.pdd-edit-label{font-size:0.68rem;color:rgba(255,255,255,0.35);}',
    '.pdd-edit-value{font-size:0.74rem;color:rgba(255,255,255,0.8);line-height:1.45;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
    '.pdd-edit-action{display:flex;align-items:center;gap:4px;flex-shrink:0;font-size:0.68rem;color:#fed65b;}',
    '.pdd-edit-action .material-symbols-outlined{font-size:15px;}',
    '.pdd-wallet-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:9px 10px;background:rgba(254,214,91,0.06);border-radius:8px;border:1px solid rgba(254,214,91,0.12);cursor:pointer;transition:background 0.2s,border-color 0.2s;}',
    '.pdd-wallet-row:hover{background:rgba(254,214,91,0.1);border-color:rgba(254,214,91,0.2);}',
    '.pdd-wallet-label{display:flex;align-items:center;gap:5px;font-size:0.71rem;color:rgba(255,255,255,0.4);}',
    '.pdd-wallet-icon{font-size:14px;color:rgba(254,214,91,0.6);}',
    '.pdd-wallet-balance{font-family:"Manrope",sans-serif;font-size:0.82rem;font-weight:700;color:#fed65b;direction:ltr;}',
    '.pdd-divider{border:none;border-top:1px solid rgba(255,255,255,0.07);margin:0 0 12px;}',
    '.pdd-logout-btn{width:100%;padding:10px 12px;background:rgba(220,50,50,0.1);border:1px solid rgba(220,50,50,0.2);border-radius:10px;display:flex;align-items:center;gap:8px;cursor:pointer;font-family:"Manrope",sans-serif;font-size:0.83rem;color:rgba(220,100,100,0.9);transition:background 0.2s,border-color 0.2s;}',
    '.pdd-logout-btn:hover{background:rgba(220,50,50,0.18);border-color:rgba(220,50,50,0.38);}',
    '.pdd-logout-btn span{font-size:17px;}',

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

  // Profile dropdown — exact replica of #profile-dropdown in index.html
  var pdEl = document.createElement('div');
  pdEl.id = 'nav-pd';
  pdEl.innerHTML =
    '<div class="pdd-header">' +
      '<div class="pdd-avatar"><span class="material-symbols-outlined">account_circle</span></div>' +
      '<div class="pdd-info">' +
        '<div class="pdd-name" id="npd-name">مستخدم</div>' +
        '<div class="pdd-phone" id="npd-phone">—</div>' +
      '</div>' +
    '</div>' +
    '<div class="pdd-edit-list">' +
      '<div class="pdd-edit-item" style="cursor:default;">' +
        '<div class="pdd-edit-item-main">' +
          '<span class="material-symbols-outlined">sell</span>' +
          '<div class="pdd-edit-copy">' +
            '<div class="pdd-edit-label">الشريحة</div>' +
            '<div class="pdd-edit-value" id="npd-segment">لم يتم تحديد الشريحة بعد</div>' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<button class="pdd-edit-item" id="npd-btn-invite" type="button">' +
        '<div class="pdd-edit-item-main">' +
          '<span class="material-symbols-outlined">card_giftcard</span>' +
          '<div class="pdd-edit-copy">' +
            '<div class="pdd-edit-label">كود الدعوة</div>' +
            '<div class="pdd-edit-value" id="npd-invite-code">-</div>' +
          '</div>' +
        '</div>' +
        '<div class="pdd-edit-action"><span class="material-symbols-outlined">open_in_new</span></div>' +
      '</button>' +
      '<button class="pdd-edit-item" id="npd-btn-address" type="button">' +
        '<div class="pdd-edit-item-main">' +
          '<span class="material-symbols-outlined">edit_location_alt</span>' +
          '<div class="pdd-edit-copy">' +
            '<div class="pdd-edit-label">العنوان</div>' +
            '<div class="pdd-edit-value" id="npd-address">تعديل العنوان الحالي</div>' +
          '</div>' +
        '</div>' +
        '<div class="pdd-edit-action"><span class="material-symbols-outlined">edit</span></div>' +
      '</button>' +
    '</div>' +
    '<div class="pdd-wallet-row">' +
      '<div class="pdd-wallet-label"><span class="material-symbols-outlined pdd-wallet-icon">account_balance_wallet</span>رصيد المحفظة</div>' +
      '<div class="pdd-wallet-balance" id="npd-wallet">0.00 ج.م</div>' +
    '</div>' +
    '<hr class="pdd-divider"/>' +
    '<button id="npd-btn-logout" class="pdd-logout-btn"><span class="material-symbols-outlined">logout</span>تسجيل الخروج</button>';

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

  // Append to body so it's outside the nav stacking context
  document.body.appendChild(pdEl);

  function positionPd() {
    if (!accountBtn) return;
    var r = accountBtn.getBoundingClientRect();
    pdEl.style.top  = (r.bottom + 12) + 'px';
    // align to left edge of button (RTL: dropdown opens leftward from button)
    var left = r.left - pdEl.offsetWidth + r.width;
    pdEl.style.left = Math.max(8, left) + 'px';
  }

  var segmentLabels = { 'مستهلك': 'مستهلك', 'جملة': 'جملة', 'corporate': 'جملة الجملة', 'consumer': 'مستهلك', 'wholesale': 'جملة' };

  function buildAddressLabel(gov, city, details) {
    return [gov, city, details].filter(Boolean).join('، ');
  }

  function openPd() {
    var s = getSession();
    if (!s) return;
    document.getElementById('npd-name').textContent = s.name || 'مستخدم';
    document.getElementById('npd-phone').textContent = s.phone || '—';
    document.getElementById('npd-segment').textContent = segmentLabels[s.segment] || s.segment || 'لم يتم تحديد الشريحة بعد';
    document.getElementById('npd-invite-code').textContent = s.referral_code || s.invitationCode || '…';
    var addr = buildAddressLabel(s.governorate || '', s.city || '', s.addressDetails || s.address || '') || 'لم يتم تحديد العنوان بعد';
    document.getElementById('npd-address').textContent = addr;
    var bal = s.wallet != null ? s.wallet : 0;
    document.getElementById('npd-wallet').textContent = Number(bal).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ج.م';
    positionPd();
    pdEl.classList.add('open');
  }
  function closePd() { pdEl.classList.remove('open'); }

  if (accountBtn) {

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

  var pdLogoutBtn = document.getElementById('npd-btn-logout');
  if (pdLogoutBtn) pdLogoutBtn.addEventListener('click', function () {
    clearSession();
    window.location.href = 'index.html';
  });

  var pdInviteBtn = document.getElementById('npd-btn-invite');
  if (pdInviteBtn) pdInviteBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    var s = getSession();
    var code = s && (s.referral_code || s.invitationCode);
    if (code) {
      navigator.clipboard && navigator.clipboard.writeText(code).catch(function(){});
    }
    closePd();
    window.location.href = 'index.html';
  });

  var pdAddressBtn = document.getElementById('npd-btn-address');
  if (pdAddressBtn) pdAddressBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    closePd();
    window.location.href = 'index.html';
  });

  updateAccountIcon();

  // Update icon after login via auth-modal
  document.addEventListener('alm:session-changed', function () {
    updateAccountIcon();
    closePd();
  });

  // Close profile dropdown whenever any modal opens
  document.addEventListener('alm:modal-open', closePd);
  document.addEventListener('click', function(e) {
    var backdrop = document.getElementById('modal-backdrop');
    if (backdrop && backdrop.classList.contains('open')) closePd();
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
