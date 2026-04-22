/**
 * profile-dropdown.js
 * Standalone profile dropdown for all pages.
 * Drop this file + auth-modal.js (for login panel) on any page that has #btn-account.
 * Exposes: window.almPD = { open, close, updateIcon }
 */
(function () {
  'use strict';

  if (document.getElementById('alm-profile-dd')) return;

  /* ─── Session ──────────────────────────────────────────────── */
  function getSession() {
    try { return JSON.parse(localStorage.getItem('alm_session')); } catch { return null; }
  }
  function setSession(d) {
    localStorage.setItem('alm_session', JSON.stringify(d));
  }

  /* ─── CSS ──────────────────────────────────────────────────── */
  var style = document.createElement('style');
  style.textContent = [
    /* dropdown */
    '#alm-profile-dd{position:fixed;transform:translateY(-8px);width:240px;background:rgba(22,10,3,0.97);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.1);border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,0.55),inset 0 1px 0 rgba(255,255,255,0.07);padding:16px 16px 14px;opacity:0;pointer-events:none;transition:opacity 0.2s ease,transform 0.2s ease;z-index:600;direction:rtl;}',
    '#alm-profile-dd.open{opacity:1;pointer-events:auto;transform:translateY(0);}',
    '#alm-profile-dd::before{content:"";position:absolute;top:-6px;left:14px;transform:rotate(45deg);width:11px;height:11px;background:rgba(22,10,3,0.97);border-left:1px solid rgba(255,255,255,0.1);border-top:1px solid rgba(255,255,255,0.1);}',
    '@media(max-width:768px){#alm-profile-dd{left:50% !important;transform:translateX(-50%) translateY(-8px);width:260px;}#alm-profile-dd.open{transform:translateX(-50%) translateY(0);}#alm-profile-dd::before{left:50%;transform:translateX(-50%) rotate(45deg);}}',
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
    /* shared modal backdrop */
    '.alm-pd-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:9100;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity 0.25s ease;}',
    '.alm-pd-backdrop.open{opacity:1;pointer-events:auto;}',
    '.alm-pd-sheet{position:relative;width:100%;max-width:380px;background:rgba(22,10,3,0.97);border:1px solid rgba(255,255,255,0.1);border-radius:22px;box-shadow:0 30px 80px rgba(0,0,0,0.6);padding:28px 24px 24px;direction:rtl;transform:scale(0.95);transition:transform 0.25s ease;}',
    '.alm-pd-backdrop.open .alm-pd-sheet{transform:scale(1);}',
    '.alm-pd-sheet-title{font-family:"Amiri",serif;font-size:1.1rem;font-weight:700;color:#fff;margin-bottom:18px;text-align:center;}',
    '.alm-pd-close{position:absolute;top:14px;left:14px;width:30px;height:30px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.06);border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.5);transition:background 0.2s;}',
    '.alm-pd-close:hover{background:rgba(255,255,255,0.12);color:#fff;}',
    '.alm-pd-close .material-symbols-outlined{font-size:15px;}',
    /* invite modal */
    '.alm-pd-invite-code{font-family:"Manrope",sans-serif;font-size:1.8rem;font-weight:700;color:#fed65b;letter-spacing:0.12em;text-align:center;padding:16px;background:rgba(254,214,91,0.07);border:1px solid rgba(254,214,91,0.18);border-radius:12px;margin-bottom:14px;direction:ltr;}',
    '.alm-pd-copy-btn{width:100%;padding:12px;background:linear-gradient(135deg,#fed65b,#f5c400);color:#3c0004;border:none;border-radius:12px;font-family:"Amiri",serif;font-size:1rem;font-weight:700;cursor:pointer;transition:opacity 0.2s;}',
    '.alm-pd-copy-btn:hover{opacity:0.88;}',
    '.alm-pd-copy-hint{text-align:center;font-size:0.75rem;color:rgba(255,255,255,0.35);margin-top:10px;min-height:18px;}',
    /* address modal */
    '.alm-pd-row{display:flex;gap:10px;margin-bottom:0;}',
    '.alm-pd-row .alm-pd-field{flex:1;min-width:0;margin-bottom:14px;}',
    '.alm-pd-field{margin-bottom:14px;}',
    '.alm-pd-field label{display:block;font-size:0.72rem;font-weight:600;color:rgba(255,255,255,0.4);margin-bottom:6px;letter-spacing:0.04em;}',
    '.alm-pd-field input,.alm-pd-field textarea{width:100%;padding:11px 14px;border:1.5px solid rgba(255,255,255,0.1);border-radius:10px;background:rgba(255,255,255,0.06);color:#fff;font-family:"Manrope",sans-serif;font-size:0.85rem;outline:none;transition:border-color 0.2s;box-sizing:border-box;}',
    '.alm-pd-field input:focus,.alm-pd-field textarea:focus{border-color:rgba(254,214,91,0.45);}',
    '.alm-pd-field textarea{resize:vertical;min-height:70px;}',
    '.alm-pd-save-btn{width:100%;padding:12px;background:linear-gradient(135deg,#fed65b,#f5c400);color:#3c0004;border:none;border-radius:12px;font-family:"Amiri",serif;font-size:1rem;font-weight:700;cursor:pointer;margin-top:4px;transition:opacity 0.2s;}',
    '.alm-pd-save-btn:hover{opacity:0.88;}',
    '.alm-pd-save-btn:disabled{opacity:0.45;cursor:not-allowed;}',
    '.alm-pd-msg{text-align:center;font-size:0.75rem;margin-top:10px;min-height:18px;color:rgba(255,120,120,0.85);}',
  ].join('');
  document.head.appendChild(style);

  /* ─── Dropdown HTML ────────────────────────────────────────── */
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
      '<div class="apd-item" style="cursor:default;">' +
        '<div class="apd-item-main">' +
          '<span class="material-symbols-outlined">sell</span>' +
          '<div class="apd-item-copy">' +
            '<div class="apd-item-label">الشريحة</div>' +
            '<div class="apd-item-value" id="apd-segment">لم يتم تحديد الشريحة بعد</div>' +
          '</div>' +
        '</div>' +
      '</div>' +
      '<button class="apd-item" id="apd-btn-invite" type="button">' +
        '<div class="apd-item-main">' +
          '<span class="material-symbols-outlined">card_giftcard</span>' +
          '<div class="apd-item-copy">' +
            '<div class="apd-item-label">كود الدعوة</div>' +
            '<div class="apd-item-value" id="apd-invite">-</div>' +
          '</div>' +
        '</div>' +
        '<div class="apd-item-action"><span class="material-symbols-outlined">open_in_new</span></div>' +
      '</button>' +
      '<button class="apd-item" id="apd-btn-address" type="button">' +
        '<div class="apd-item-main">' +
          '<span class="material-symbols-outlined">edit_location_alt</span>' +
          '<div class="apd-item-copy">' +
            '<div class="apd-item-label">العنوان</div>' +
            '<div class="apd-item-value" id="apd-address">تعديل العنوان الحالي</div>' +
          '</div>' +
        '</div>' +
        '<div class="apd-item-action"><span class="material-symbols-outlined">edit</span></div>' +
      '</button>' +
    '</div>' +
    '<div class="apd-wallet">' +
      '<div class="apd-wallet-label"><span class="material-symbols-outlined">account_balance_wallet</span>رصيد المحفظة</div>' +
      '<div class="apd-wallet-balance" id="apd-wallet">0.00 ج.م</div>' +
    '</div>' +
    '<hr class="apd-divider"/>' +
    '<button class="apd-logout" id="apd-logout"><span class="material-symbols-outlined">logout</span>تسجيل الخروج</button>';
  document.body.appendChild(dd);

  /* ─── Invite modal HTML ────────────────────────────────────── */
  var inviteBack = document.createElement('div');
  inviteBack.className = 'alm-pd-backdrop';
  inviteBack.id = 'alm-pd-invite-backdrop';
  inviteBack.innerHTML =
    '<div class="alm-pd-sheet">' +
      '<button class="alm-pd-close" id="alm-pd-invite-close"><span class="material-symbols-outlined">close</span></button>' +
      '<div class="alm-pd-sheet-title">كود الدعوة الخاص بك</div>' +
      '<div class="alm-pd-invite-code" id="alm-pd-invite-display">…</div>' +
      '<button class="alm-pd-copy-btn" id="alm-pd-copy-btn">نسخ الكود</button>' +
      '<div class="alm-pd-copy-hint" id="alm-pd-copy-hint"></div>' +
    '</div>';
  document.body.appendChild(inviteBack);

  /* ─── Address modal HTML ───────────────────────────────────── */
  var addrBack = document.createElement('div');
  addrBack.className = 'alm-pd-backdrop';
  addrBack.id = 'alm-pd-addr-backdrop';
  addrBack.innerHTML =
    '<div class="alm-pd-sheet">' +
      '<button class="alm-pd-close" id="alm-pd-addr-close"><span class="material-symbols-outlined">close</span></button>' +
      '<div class="alm-pd-sheet-title">تعديل العنوان</div>' +
      '<div class="alm-pd-row">' +
        '<div class="alm-pd-field">' +
          '<label for="alm-pd-gov">المحافظة</label>' +
          '<input id="alm-pd-gov" type="text" placeholder="مثال: القاهرة" autocomplete="off"/>' +
        '</div>' +
        '<div class="alm-pd-field">' +
          '<label for="alm-pd-city">المدينة / الحي</label>' +
          '<input id="alm-pd-city" type="text" placeholder="مثال: مدينة نصر" autocomplete="off"/>' +
        '</div>' +
      '</div>' +
      '<div class="alm-pd-field">' +
        '<label for="alm-pd-detail">تفاصيل العنوان</label>' +
        '<textarea id="alm-pd-detail" placeholder="رقم الشارع، العمارة، الشقة..."></textarea>' +
      '</div>' +
      '<button class="alm-pd-save-btn" id="alm-pd-save-btn">حفظ العنوان</button>' +
      '<div class="alm-pd-msg" id="alm-pd-addr-msg"></div>' +
    '</div>';
  document.body.appendChild(addrBack);

  /* ─── Helpers ──────────────────────────────────────────────── */
  var SEGMENT_LABELS = { consumer: 'مستهلك', wholesale: 'جملة', corporate: 'جملة الجملة', 'مستهلك': 'مستهلك', 'جملة': 'جملة' };

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
    if (window.innerWidth > 768) {
      var left = r.left - dd.offsetWidth + r.width;
      dd.style.left = Math.max(8, left) + 'px';
    }
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

  /* ─── Login guide toast ────────────────────────────────────── */
  var guideStyle = document.createElement('style');
  guideStyle.textContent =
    '#alm-login-guide{position:fixed;inset:0;z-index:9200;display:flex;align-items:flex-end;justify-content:center;padding:24px;pointer-events:none;opacity:0;transition:opacity 0.3s ease;}' +
    '#alm-login-guide.open{opacity:1;pointer-events:auto;}' +
    '#alm-login-guide-card{width:100%;max-width:400px;background:rgba(22,10,3,0.97);border:1px solid rgba(254,214,91,0.25);border-radius:20px;padding:24px 20px 20px;box-shadow:0 20px 60px rgba(0,0,0,0.6);direction:rtl;transform:translateY(20px);transition:transform 0.3s ease;}' +
    '#alm-login-guide.open #alm-login-guide-card{transform:translateY(0);}' +
    '#alm-login-guide-title{font-family:"Amiri",serif;font-size:1.05rem;font-weight:700;color:#fed65b;margin-bottom:14px;display:flex;align-items:center;gap:8px;}' +
    '#alm-login-guide-title .material-symbols-outlined{font-size:20px;}' +
    '.alm-guide-step{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px;}' +
    '.alm-guide-num{width:22px;height:22px;border-radius:50%;background:rgba(254,214,91,0.15);border:1px solid rgba(254,214,91,0.3);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:#fed65b;flex-shrink:0;margin-top:1px;}' +
    '.alm-guide-text{font-size:0.82rem;color:rgba(255,255,255,0.75);line-height:1.5;}' +
    '.alm-guide-text strong{color:#fff;}' +
    '#alm-guide-btns{display:flex;gap:10px;margin-top:16px;}' +
    '#alm-guide-go-btn{flex:1;padding:11px;background:linear-gradient(135deg,#fed65b,#f5c400);color:#3c0004;border:none;border-radius:12px;font-family:"Amiri",serif;font-size:0.95rem;font-weight:700;cursor:pointer;}' +
    '#alm-guide-dismiss{padding:11px 16px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:12px;color:rgba(255,255,255,0.5);font-family:"Manrope",sans-serif;font-size:0.8rem;cursor:pointer;}';
  document.head.appendChild(guideStyle);

  var guideEl = document.createElement('div');
  guideEl.id = 'alm-login-guide';
  guideEl.innerHTML =
    '<div id="alm-login-guide-card">' +
      '<div id="alm-login-guide-title"><span class="material-symbols-outlined">info</span>كيفية تسجيل الدخول</div>' +
      '<div class="alm-guide-step"><div class="alm-guide-num">١</div><div class="alm-guide-text">انتقل إلى <strong>الصفحة الرئيسية</strong> للمتجر</div></div>' +
      '<div class="alm-guide-step"><div class="alm-guide-num">٢</div><div class="alm-guide-text">اضغط على أيقونة <strong>person</strong> (الشخص) في شريط التنقل العلوي</div></div>' +
      '<div class="alm-guide-step"><div class="alm-guide-num">٣</div><div class="alm-guide-text">ستظهر نافذة تسجيل الدخول — أدخل <strong>رقم هاتفك وكلمة المرور</strong></div></div>' +
      '<div class="alm-guide-step"><div class="alm-guide-num">٤</div><div class="alm-guide-text">إذا لم يكن لديك حساب، اختر <strong>إنشاء حساب جديد</strong> من نفس النافذة</div></div>' +
      '<div id="alm-guide-btns">' +
        '<button id="alm-guide-go-btn">الذهاب إلى الصفحة الرئيسية</button>' +
        '<button id="alm-guide-dismiss">إغلاق</button>' +
      '</div>' +
    '</div>';
  document.body.appendChild(guideEl);

  document.getElementById('alm-guide-go-btn').addEventListener('click', function () {
    window.location.href = 'index.html#login';
  });
  document.getElementById('alm-guide-dismiss').addEventListener('click', function () {
    guideEl.classList.remove('open');
  });
  guideEl.addEventListener('click', function (e) {
    if (e.target === guideEl) guideEl.classList.remove('open');
  });

  /* ─── Account button ───────────────────────────────────────── */
  var accountBtn = document.getElementById('btn-account');
  if (accountBtn) {
    accountBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (getSession()) {
        dd.classList.contains('open') ? apdClose() : apdOpen();
      } else {
        var isIndex = window.location.pathname === '/' ||
                      window.location.pathname.endsWith('index.html') ||
                      window.location.pathname.endsWith('/');
        if (isIndex && window.almAuth) {
          window.almAuth.open('login');
        } else if (isIndex && typeof openModal === 'function') {
          showPanel('login'); openModal();
        } else {
          window.location.href = 'index.html#login';
        }
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

  /* ─── Invite modal ─────────────────────────────────────────── */
  function openInviteModal() {
    var s = getSession();
    var code = (s && (s.referral_code || s.invitationCode)) || '—';
    document.getElementById('alm-pd-invite-display').textContent = code;
    document.getElementById('alm-pd-copy-hint').textContent = '';
    inviteBack.classList.add('open');
  }
  function closeInviteModal() { inviteBack.classList.remove('open'); }

  document.getElementById('apd-btn-invite').addEventListener('click', function (e) {
    e.stopPropagation();
    apdClose();
    openInviteModal();
  });
  document.getElementById('alm-pd-invite-close').addEventListener('click', closeInviteModal);
  inviteBack.addEventListener('click', function (e) { if (e.target === inviteBack) closeInviteModal(); });

  document.getElementById('alm-pd-copy-btn').addEventListener('click', function () {
    var code = document.getElementById('alm-pd-invite-display').textContent;
    var hint = document.getElementById('alm-pd-copy-hint');
    if (navigator.clipboard && code && code !== '—') {
      navigator.clipboard.writeText(code).then(function () {
        hint.style.color = 'rgba(100,220,130,0.9)';
        hint.textContent = 'تم نسخ الكود ✓';
        setTimeout(function () { hint.textContent = ''; }, 2500);
      }).catch(function () {
        hint.style.color = 'rgba(255,120,120,0.85)';
        hint.textContent = 'تعذّر النسخ تلقائياً';
      });
    }
  });

  /* ─── Address modal ────────────────────────────────────────── */
  function openAddressModal() {
    var s = getSession() || {};
    document.getElementById('alm-pd-gov').value    = s.governorate || '';
    document.getElementById('alm-pd-city').value   = s.city || '';
    document.getElementById('alm-pd-detail').value = s.addressDetails || s.address_detail || s.address || '';
    document.getElementById('alm-pd-addr-msg').textContent = '';
    var btn = document.getElementById('alm-pd-save-btn');
    btn.disabled = false;
    btn.textContent = 'حفظ العنوان';
    addrBack.classList.add('open');
  }
  function closeAddressModal() { addrBack.classList.remove('open'); }

  document.getElementById('apd-btn-address').addEventListener('click', function (e) {
    e.stopPropagation();
    apdClose();
    openAddressModal();
  });
  document.getElementById('alm-pd-addr-close').addEventListener('click', closeAddressModal);
  addrBack.addEventListener('click', function (e) { if (e.target === addrBack) closeAddressModal(); });

  document.getElementById('alm-pd-save-btn').addEventListener('click', async function () {
    var s = getSession();
    if (!s) return;
    var gov    = document.getElementById('alm-pd-gov').value.trim();
    var city   = document.getElementById('alm-pd-city').value.trim();
    var detail = document.getElementById('alm-pd-detail').value.trim();
    var msg    = document.getElementById('alm-pd-addr-msg');
    var btn    = document.getElementById('alm-pd-save-btn');

    btn.disabled = true;
    btn.textContent = 'جارٍ الحفظ...';
    msg.textContent = '';

    try {
      var res  = await fetch('apis/users/profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          segment:        s.segment || 'consumer',
          governorate:    gov,
          city:           city,
          address_detail: detail,
        }),
      });
      var data = await res.json();
      if (!res.ok) {
        msg.style.color = 'rgba(255,120,120,0.85)';
        msg.textContent = data.error || 'تعذّر الحفظ';
        return;
      }
      setSession(Object.assign({}, s, {
        governorate:    gov,
        city:           city,
        address_detail: detail,
        addressDetails: detail,
      }));
      msg.style.color = 'rgba(100,220,130,0.9)';
      msg.textContent = 'تم حفظ العنوان ✓';
      setTimeout(closeAddressModal, 1000);
    } catch {
      msg.style.color = 'rgba(255,120,120,0.85)';
      msg.textContent = 'خطأ في الاتصال، يرجى المحاولة مجدداً';
    } finally {
      btn.disabled = false;
      btn.textContent = 'حفظ العنوان';
    }
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
