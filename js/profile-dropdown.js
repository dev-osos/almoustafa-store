/**
 * profile-dropdown.js
 * Standalone profile dropdown for all pages.
 * On mobile (≤768px): account button navigates to profile.html directly.
 * On desktop: shows dropdown; invite/address items link to profile.html.
 */
(function () {
  'use strict';

  if (document.getElementById('alm-profile-dd')) return;

  /* ─── Session ──────────────────────────────────────────────── */
  function getSession() {
    try { return JSON.parse(localStorage.getItem('alm_session')); } catch { return null; }
  }

  /* ─── CSS ──────────────────────────────────────────────────── */
  var style = document.createElement('style');
  style.textContent = [
    /* dropdown */
    '#alm-profile-dd{position:fixed;transform:translateY(-8px);width:240px;background:rgba(22,10,3,0.97);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.1);border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,0.55),inset 0 1px 0 rgba(255,255,255,0.07);padding:16px 16px 14px;opacity:0;pointer-events:none;transition:opacity 0.2s ease,transform 0.2s ease;z-index:600;direction:rtl;}',
    '#alm-profile-dd.open{opacity:1;pointer-events:auto;transform:translateY(0);}',
    '#alm-profile-dd::before{content:"";position:absolute;top:-6px;left:14px;transform:rotate(45deg);width:11px;height:11px;background:rgba(22,10,3,0.97);border-left:1px solid rgba(255,255,255,0.1);border-top:1px solid rgba(255,255,255,0.1);}',
    /* header */
    '.apd-header{display:flex;align-items:center;gap:10px;margin-bottom:12px;}',
    '.apd-avatar{width:40px;height:40px;border-radius:50%;flex-shrink:0;background:rgba(254,214,91,0.1);border:2px solid rgba(254,214,91,0.28);display:flex;align-items:center;justify-content:center;}',
    '.apd-avatar span{color:#fed65b;font-size:22px;font-variation-settings:"FILL" 1,"wght" 300,"GRAD" 0,"opsz" 24;}',
    '.apd-info{flex:1;min-width:0;}',
    '.apd-name{font-family:"Amiri",serif;font-size:0.95rem;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
    '.apd-phone{font-size:0.72rem;color:rgba(255,255,255,0.4);direction:ltr;text-align:right;margin-top:1px;}',
    /* list items */
    '.apd-list{display:flex;flex-direction:column;gap:8px;margin-bottom:12px;}',
    '.apd-item{width:100%;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:10px;cursor:pointer;transition:background 0.2s,border-color 0.2s;text-decoration:none;box-sizing:border-box;}',
    '.apd-item:hover{background:rgba(255,255,255,0.08);border-color:rgba(254,214,91,0.22);}',
    '.apd-item-main{min-width:0;display:flex;align-items:center;gap:8px;flex:1;}',
    '.apd-item-main .material-symbols-outlined{font-size:16px;color:rgba(254,214,91,0.72);flex-shrink:0;}',
    '.apd-item-copy{min-width:0;display:flex;flex-direction:column;gap:3px;text-align:right;}',
    '.apd-item-label{font-size:0.68rem;color:rgba(255,255,255,0.35);}',
    '.apd-item-value{font-size:0.74rem;color:rgba(255,255,255,0.8);line-height:1.45;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
    '.apd-item-action{display:flex;align-items:center;flex-shrink:0;color:#fed65b;}',
    '.apd-item-action .material-symbols-outlined{font-size:15px;}',
    /* wallet */
    '.apd-wallet{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:9px 10px;background:rgba(254,214,91,0.06);border-radius:8px;border:1px solid rgba(254,214,91,0.12);}',
    '.apd-wallet-label{display:flex;align-items:center;gap:5px;font-size:0.71rem;color:rgba(255,255,255,0.4);}',
    '.apd-wallet-label .material-symbols-outlined{font-size:14px;color:rgba(254,214,91,0.6);}',
    '.apd-wallet-balance{font-family:"Manrope",sans-serif;font-size:0.82rem;font-weight:700;color:#fed65b;direction:ltr;}',
    /* divider + logout */
    '.apd-divider{border:none;border-top:1px solid rgba(255,255,255,0.07);margin:0 0 12px;}',
    '.apd-logout{width:100%;padding:10px 12px;background:rgba(220,50,50,0.1);border:1px solid rgba(220,50,50,0.2);border-radius:10px;display:flex;align-items:center;gap:8px;cursor:pointer;font-family:"Manrope",sans-serif;font-size:0.83rem;color:rgba(220,100,100,0.9);transition:background 0.2s,border-color 0.2s;box-sizing:border-box;}',
    '.apd-logout:hover{background:rgba(220,50,50,0.18);border-color:rgba(220,50,50,0.38);}',
    '.apd-logout .material-symbols-outlined{font-size:17px;}',
  ].join('');
  document.head.appendChild(style);

  /* ─── Dropdown HTML ───────────────────────────── */
  var dd = document.createElement('div');
  dd.id = 'alm-profile-dd';
  dd.innerHTML =
    '<div class="apd-header">' +
      '<div class="apd-avatar"><span class="material-symbols-outlined">account_circle</span></div>' +
      '<div class="apd-info">' +
        '<div class="apd-name" id="apd-name">مستخدم</div>' +
        '<div class="apd-phone" id="apd-phone">—</div>' +
      '</div>' +
    '</div>' +
    '<div class="apd-list">' +
      '<div class="apd-item">' +
        '<div class="apd-item-main"><span class="material-symbols-outlined">sell</span>' +
          '<div class="apd-item-copy"><div class="apd-item-label">الشريحة</div><div class="apd-item-value" id="apd-segment">—</div></div>' +
        '</div>' +
      '</div>' +
      '<a class="apd-item" href="profile.html">' +
        '<div class="apd-item-main"><span class="material-symbols-outlined">card_giftcard</span>' +
          '<div class="apd-item-copy"><div class="apd-item-label">كود الدعوة</div><div class="apd-item-value" id="apd-invite">…</div></div>' +
        '</div>' +
        '<div class="apd-item-action"><span class="material-symbols-outlined">chevron_left</span></div>' +
      '</a>' +
      '<a class="apd-item" href="profile.html">' +
        '<div class="apd-item-main"><span class="material-symbols-outlined">edit_location_alt</span>' +
          '<div class="apd-item-copy"><div class="apd-item-label">العنوان</div><div class="apd-item-value" id="apd-address">—</div></div>' +
        '</div>' +
        '<div class="apd-item-action"><span class="material-symbols-outlined">chevron_left</span></div>' +
      '</a>' +
    '</div>' +
    '<div class="apd-wallet">' +
      '<div class="apd-wallet-label"><span class="material-symbols-outlined">account_balance_wallet</span>رصيد المحفظة</div>' +
      '<div class="apd-wallet-balance" id="apd-wallet">0.00 ج.م</div>' +
    '</div>' +
    '<hr class="apd-divider"/>' +
    '<button class="apd-logout" id="apd-logout" type="button">' +
      '<span class="material-symbols-outlined">logout</span>تسجيل الخروج' +
    '</button>';
  document.body.appendChild(dd);

  /* ─── Helpers ──────────────────────────────────────────────── */
  var SEGMENT_LABELS = { consumer: 'مستهلك', wholesale: 'جملة', corporate: 'جملة الجملة' };

  function buildAddress(s) {
    return [s.governorate, s.city, s.addressDetails || s.address_detail || s.address]
      .filter(Boolean).join('، ') || 'لم يتم تحديد العنوان بعد';
  }

  function fmtWallet(val) {
    return Number(val || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ج.م';
  }

  /* ─── Dropdown positioning ─────────────────────────────────── */
  function apdPosition() {
    var btn = document.getElementById('btn-account');
    if (!btn) return;
    var r = btn.getBoundingClientRect();
    dd.style.top = (r.bottom + 10) + 'px';
    var left = r.left - dd.offsetWidth + r.width;
    dd.style.left = Math.max(8, left) + 'px';
  }

  /* ─── Dropdown open / close ────────────────────────────────── */
  function apdOpen() {
    var s = getSession();
    if (!s) return;
    document.getElementById('apd-name').textContent = s.name || 'مستخدم';
    document.getElementById('apd-phone').textContent = s.phone || '—';
    document.getElementById('apd-segment').textContent = SEGMENT_LABELS[s.segment] || 'لم يتم تحديد الشريحة بعد';
    document.getElementById('apd-invite').textContent = s.referral_code || s.invitationCode || '…';
    document.getElementById('apd-address').textContent = buildAddress(s);
    document.getElementById('apd-wallet').textContent = fmtWallet(s.wallet);
    apdPosition();
    dd.classList.add('open');
  }

  function apdClose() { dd.classList.remove('open'); }

  /* ─── Icon state ───────────────────────────────────────────── */
  function updateIcon() {
    var btn = document.getElementById('btn-account');
    if (!btn) return;
    var icon = btn.querySelector('.material-symbols-outlined');
    if (!icon) return;
    if (getSession()) {
      icon.textContent = 'account_circle';
      icon.style.fontVariationSettings = "'FILL' 1,'wght' 300,'GRAD' 0,'opsz' 24";
    } else {
      icon.textContent = 'person';
      icon.style.fontVariationSettings = "'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24";
    }
  }

  /* ─── Account button ───────────────────────────────────────── */
  /* Skip if the page has its own inline profile dropdown handler */
  var accountBtn = document.getElementById('btn-account');
  if (accountBtn && !document.getElementById('profile-dropdown')) {
    accountBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (getSession()) {
        /* mobile → go to profile page; desktop → toggle dropdown */
        if (window.innerWidth <= 768) {
          window.location.href = 'profile.html';
        } else {
          dd.classList.contains('open') ? apdClose() : apdOpen();
        }
      } else {
        window.location.href = 'login.html?return=' + encodeURIComponent(location.pathname + location.search);
      }
    });
  }

  document.addEventListener('click', function (e) {
    if (!dd.contains(e.target) && e.target !== accountBtn) apdClose();
  });

  /* ─── Logout ───────────────────────────────────────────────── */
  document.getElementById('apd-logout').addEventListener('click', function () {
    localStorage.removeItem('alm_session');
    apdClose();
    window.location.href = 'index.html';
  });

  /* ─── Session verification on load ────────────────────────── */
  updateIcon();
  if (getSession()) {
    fetch('apis/users/me.php')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) {
          localStorage.removeItem('alm_session');
          updateIcon();
        }
      })
      .catch(function () {});
  }

  /* ─── Listen to session changes (from auth-modal.js) ──────── */
  document.addEventListener('alm:session-changed', function () {
    updateIcon();
    apdClose();
  });

  /* ─── Public API ───────────────────────────────────────────── */
  window.almPD = {
    open: apdOpen,
    close: apdClose,
    updateIcon: updateIcon,
  };

})();
