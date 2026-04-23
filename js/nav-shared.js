/**
 * nav-shared.js
 * Shared nav behaviour for all pages except index.html
 * Handles: cart badge, cart dropdown, mobile menu
 * Profile dropdown is handled by auth-modal.js
 */
(function () {
  'use strict';

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {

  /* ─── Session ─────────────────────────────────────────────── */
  function getSession() {
    try { return JSON.parse(localStorage.getItem('alm_session')); } catch { return null; }
  }

  /* ─── Cart ─────────────────────────────────────────────────── */
  function getCart() {
    try { return JSON.parse(localStorage.getItem('alm_cart')) || []; } catch { return []; }
  }

  function toArabicPrice(n) {
    return n.toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ج.م';
  }

  /* ─── Inject CSS ───────────────────────────────────────────── */
  var style = document.createElement('style');
  style.textContent = [
    /* ── Cart dropdown (shared) ── */
    '#nav-cart-dd{position:absolute;top:calc(100% + 10px);left:0;width:320px;background:#fdf9f0;border:1px solid rgba(60,0,4,0.1);border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,0.15);z-index:300;direction:rtl;overflow:hidden;opacity:0;pointer-events:none;transform:translateY(-8px);transition:opacity 0.2s ease,transform 0.2s ease;}',
    '#nav-cart-dd.open{opacity:1;pointer-events:auto;transform:translateY(0);}',
  ].join('');
  document.head.appendChild(style);

  /* ─── Cart badge ───────────────────────────────────────────── */
  function updateCartBadge() {
    var badge = document.getElementById('cart-badge');
    if (!badge) return;
    var total = getCart().reduce(function (s, i) { return s + (i.qty || 1); }, 0);
    badge.textContent = total;
    badge.style.display = total > 0 ? 'flex' : 'none';
  }

  /* ─── Cart dropdown (for pages without their own cart JS) ──── */
  var cartBtn    = document.getElementById('btn-cart');
  var cartDdBody = document.getElementById('cart-dd-body');
  var cartWrapper = document.getElementById('cart-icon-wrapper');

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

  } /* end init */
})();
