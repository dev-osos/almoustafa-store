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
    '.alm-pd-backdrop{position:fixed;inset:0;background:rgba(28,28,23,0.78);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);z-index:9100;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity 0.3s cubic-bezier(0.4,0,0.2,1);}',
    '.alm-pd-backdrop.open{opacity:1;pointer-events:auto;}',
    /* invite sheet (keep old simple style) */
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
    /* ── Address modal (profile-edit style) ── */
    '.apd-addr-modal{position:relative;width:100%;max-width:520px;background:linear-gradient(180deg,#3c0004 0%,#2a0003 100%);border:1px solid rgba(254,214,91,0.18);border-radius:30px;box-shadow:0 40px 100px rgba(0,0,0,0.65),inset 0 1px 0 rgba(254,214,91,0.1);padding:42px 36px 34px;direction:rtl;overflow:hidden;transform:scale(0.94) translateY(10px);transition:transform 0.3s cubic-bezier(0.4,0,0.2,1);}',
    '.alm-pd-backdrop.open .apd-addr-modal{transform:scale(1) translateY(0);}',
    '.apd-addr-modal::before{content:"";position:absolute;top:-120px;left:50%;transform:translateX(-50%);width:340px;height:240px;background:radial-gradient(ellipse,rgba(254,214,91,0.2) 0%,transparent 70%);pointer-events:none;}',
    '.apd-addr-modal::after{content:"";position:absolute;inset:1px;border-radius:29px;border:1px solid rgba(254,214,91,0.07);pointer-events:none;}',
    '.apd-addr-close{position:absolute;top:16px;left:16px;width:36px;height:36px;border-radius:50%;border:1px solid rgba(254,214,91,0.2);background:rgba(254,214,91,0.08);color:rgba(254,214,91,0.65);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background 0.2s,color 0.2s;z-index:2;}',
    '.apd-addr-close:hover{background:rgba(254,214,91,0.16);color:#fed65b;}',
    '.apd-addr-close .material-symbols-outlined{font-size:18px;}',
    '.apd-addr-header{position:relative;z-index:1;text-align:center;margin-bottom:28px;}',
    '.apd-addr-icon{width:78px;height:78px;margin:0 auto 16px;border-radius:50%;background:linear-gradient(135deg,rgba(254,214,91,0.15) 0%,rgba(254,214,91,0.06) 100%);border:2px solid rgba(254,214,91,0.32);box-shadow:0 16px 40px rgba(254,214,91,0.1);display:flex;align-items:center;justify-content:center;}',
    '.apd-addr-icon span{font-size:36px;color:#fed65b;font-variation-settings:"FILL" 1,"wght" 300,"GRAD" 0,"opsz" 24;}',
    '.apd-addr-title{font-family:"Amiri",serif;font-size:1.55rem;font-weight:700;color:#fff;margin-bottom:8px;}',
    '.apd-addr-sub{font-size:0.82rem;color:rgba(255,255,255,0.45);line-height:1.8;}',
    '.apd-addr-divider{width:56px;height:2px;margin:16px auto 0;border-radius:999px;background:linear-gradient(90deg,transparent,#fed65b,transparent);}',
    '.apd-addr-form{position:relative;z-index:1;}',
    '.apd-addr-field{margin-bottom:18px;}',
    '.apd-addr-label{display:block;margin-bottom:10px;font-size:0.78rem;color:rgba(255,255,255,0.55);}',
    '.apd-addr-input-wrap{position:relative;}',
    '.apd-addr-input-icon{position:absolute;top:50%;right:16px;transform:translateY(-50%);color:rgba(254,214,91,0.7);font-size:19px;pointer-events:none;}',
    '.apd-addr-input{width:100%;min-height:58px;background:rgba(255,255,255,0.06) !important;border:1px solid rgba(254,214,91,0.15) !important;border-radius:16px;color:#fff !important;padding:14px 46px 14px 16px !important;font-size:0.92rem;line-height:1.8;outline:none;transition:border-color 0.2s,box-shadow 0.2s,background 0.2s;box-sizing:border-box;font-family:"Manrope",sans-serif;-webkit-appearance:none;appearance:none;}',
    '.apd-addr-input::placeholder{color:rgba(255,255,255,0.3) !important;}',
    '.apd-addr-input:focus{border-color:rgba(254,214,91,0.45) !important;box-shadow:0 0 0 4px rgba(254,214,91,0.08) !important;background:rgba(255,255,255,0.09) !important;}',
    '.apd-addr-input:disabled{opacity:0.45;cursor:not-allowed;}',
    /* autocomplete suggestions */
    '.apd-addr-ac{position:relative;}',
    '.apd-addr-suggestions{position:absolute;top:calc(100% + 8px);right:0;left:0;max-height:220px;overflow-y:auto;background:#2a0003;border:1px solid rgba(254,214,91,0.15);border-radius:16px;box-shadow:0 24px 60px rgba(0,0,0,0.5);padding:8px;z-index:4;display:none;}',
    '.apd-addr-suggestions.open{display:block;}',
    '.apd-addr-suggestion{width:100%;border:none;background:transparent;color:rgba(255,255,255,0.82);text-align:right;padding:11px 12px;border-radius:12px;cursor:pointer;font-size:0.84rem;transition:background 0.2s,color 0.2s;font-family:"Manrope",sans-serif;}',
    '.apd-addr-suggestion:hover,.apd-addr-suggestion.active{background:rgba(254,214,91,0.1);color:#fed65b;}',
    '.apd-addr-empty{padding:12px;text-align:center;font-size:0.76rem;color:rgba(255,255,255,0.35);}',
    /* grid */
    '.apd-addr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:12px;}',
    /* chip */
    '.apd-addr-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;background:rgba(254,214,91,0.08);border:1px solid rgba(254,214,91,0.18);color:rgba(255,255,255,0.7);font-size:0.74rem;margin-bottom:12px;}',
    '.apd-addr-chip .material-symbols-outlined{color:#fed65b;font-size:15px;}',
    /* hint + actions */
    '.apd-addr-hint{font-size:0.74rem;color:rgba(255,255,255,0.35);line-height:1.7;margin-bottom:20px;}',
    '.apd-addr-msg{text-align:center;font-size:0.75rem;margin-bottom:10px;min-height:18px;}',
    '.apd-addr-actions{display:flex;gap:12px;}',
    '.apd-addr-btn-primary{flex:1;min-height:50px;border-radius:14px;font-family:"Manrope",sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;border:none;background:linear-gradient(135deg,#fed65b 0%,#f0bf1a 100%);color:#3c0004;box-shadow:0 12px 30px rgba(254,214,91,0.25);transition:transform 0.2s,box-shadow 0.2s;}',
    '.apd-addr-btn-primary:hover{transform:translateY(-1px);box-shadow:0 16px 34px rgba(254,214,91,0.35);}',
    '.apd-addr-btn-primary:disabled{opacity:0.45;cursor:not-allowed;transform:none;}',
    '.apd-addr-btn-secondary{flex:1;min-height:50px;border-radius:14px;font-family:"Manrope",sans-serif;font-size:0.88rem;font-weight:700;cursor:pointer;border:1px solid rgba(254,214,91,0.18);background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.65);transition:background 0.2s,border-color 0.2s,color 0.2s;}',
    '.apd-addr-btn-secondary:hover{background:rgba(255,255,255,0.09);border-color:rgba(254,214,91,0.3);color:#fff;}',
    '@media(max-width:480px){.apd-addr-modal{padding:34px 20px 24px;max-width:95vw;border-radius:24px;}.apd-addr-grid{grid-template-columns:1fr;}.apd-addr-actions{flex-direction:column;}}',
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
    '<div class="apd-addr-modal">' +
      '<button class="apd-addr-close" id="alm-pd-addr-close" type="button" aria-label="إغلاق">' +
        '<span class="material-symbols-outlined">close</span>' +
      '</button>' +
      '<div class="apd-addr-header">' +
        '<div class="apd-addr-icon"><span class="material-symbols-outlined">edit_location_alt</span></div>' +
        '<div class="apd-addr-title">تعديل العنوان</div>' +
        '<div class="apd-addr-sub">أدخل عنوانك بالتفصيل ليصل الطلب إليك بسهولة ودقة أكبر.</div>' +
        '<div class="apd-addr-divider"></div>' +
      '</div>' +
      '<div class="apd-addr-form">' +
        '<div class="apd-addr-chip"><span class="material-symbols-outlined">auto_awesome</span>ابدأ بكتابة اسم المحافظة أو المدينة لتصفية الاختيارات بسرعة</div>' +
        '<div class="apd-addr-grid">' +
          '<div class="apd-addr-field" style="margin-bottom:0;">' +
            '<label class="apd-addr-label" for="alm-pd-gov">المحافظة</label>' +
            '<div class="apd-addr-ac">' +
              '<div class="apd-addr-input-wrap">' +
                '<span class="material-symbols-outlined apd-addr-input-icon">map</span>' +
                '<input class="apd-addr-input" id="alm-pd-gov" type="text" autocomplete="off" placeholder="اختر المحافظة"/>' +
              '</div>' +
              '<div class="apd-addr-suggestions" id="alm-pd-gov-suggestions"></div>' +
            '</div>' +
          '</div>' +
          '<div class="apd-addr-field" style="margin-bottom:0;">' +
            '<label class="apd-addr-label" for="alm-pd-city">المدينة</label>' +
            '<div class="apd-addr-ac">' +
              '<div class="apd-addr-input-wrap">' +
                '<span class="material-symbols-outlined apd-addr-input-icon">location_city</span>' +
                '<input class="apd-addr-input" id="alm-pd-city" type="text" autocomplete="off" placeholder="اختر المحافظة أولاً" disabled/>' +
              '</div>' +
              '<div class="apd-addr-suggestions" id="alm-pd-city-suggestions"></div>' +
            '</div>' +
          '</div>' +
        '</div>' +
        '<div class="apd-addr-field" style="margin-bottom:20px;">' +
          '<label class="apd-addr-label" for="alm-pd-detail">العنوان التفصيلي</label>' +
          '<div class="apd-addr-input-wrap">' +
            '<span class="material-symbols-outlined apd-addr-input-icon" style="top:20px;transform:none;">home_pin</span>' +
            '<textarea class="apd-addr-input" id="alm-pd-detail" rows="2" placeholder="الشارع واسم العمارة ورقم الشقة أو الدور السكني" style="min-height:unset;resize:none;"></textarea>' +
          '</div>' +
        '</div>' +
        '<div class="apd-addr-hint">سيتم حفظ التعديل مباشرة على حسابك الحالي داخل الجلسة.</div>' +
        '<div class="apd-addr-msg" id="alm-pd-addr-msg"></div>' +
        '<div class="apd-addr-actions">' +
          '<button class="apd-addr-btn-primary" id="alm-pd-save-btn" type="button">حفظ العنوان</button>' +
          '<button class="apd-addr-btn-secondary" id="alm-pd-addr-cancel" type="button">إلغاء</button>' +
        '</div>' +
      '</div>' +
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

  function norm(s) {
    return (s || '').replace(/[أإآا]/g, 'ا').replace(/ى/g, 'ي').trim().toLowerCase();
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

  /* ─── Account button ───────────────────────────────────────── */
  var accountBtn = document.getElementById('btn-account');
  if (accountBtn) {
    accountBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (getSession()) {
        dd.classList.contains('open') ? apdClose() : apdOpen();
      } else if (window.almAuth) {
        window.almAuth.open('login');
      } else {
        window.location.href = 'index.html';
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
    var mainDd = document.getElementById('profile-dropdown');
    if (mainDd) mainDd.classList.remove('open');
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

  /* ─── Address modal — autocomplete ────────────────────────── */
  var GOVS = [];
  var CITIES = [];
  var selectedGovId = null;
  var selectedGovName = '';
  var selectedCityName = '';

  var govInput    = document.getElementById('alm-pd-gov');
  var cityInput   = document.getElementById('alm-pd-city');
  var govSugg     = document.getElementById('alm-pd-gov-suggestions');
  var citySugg    = document.getElementById('alm-pd-city-suggestions');

  function fetchGovs() {
    if (GOVS.length) return;
    fetch('dat-docs/govs.json')
      .then(function (r) { return r.json(); })
      .then(function (d) { GOVS = d.data.listZonesDropdown || []; })
      .catch(function () {});
  }

  function fetchCities(govId) {
    CITIES = [];
    cityInput.disabled = true;
    cityInput.placeholder = 'جاري التحميل...';
    fetch('dat-docs/cities/' + govId + '.json')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        CITIES = d.data.listZonesDropdown || [];
        cityInput.disabled = false;
        cityInput.placeholder = 'اكتب لاختيار المدينة...';
      })
      .catch(function () { cityInput.placeholder = 'تعذّر تحميل المدن'; });
  }

  function buildSuggestions(container, items, query, onSelect) {
    container.innerHTML = '';
    var q = norm(query);
    var filtered = q ? items.filter(function (it) { return norm(it.name).includes(q); }) : items.slice(0, 40);
    if (!filtered.length) {
      var empty = document.createElement('div');
      empty.className = 'apd-addr-empty';
      empty.textContent = 'لا توجد نتائج';
      container.appendChild(empty);
    } else {
      filtered.forEach(function (it) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'apd-addr-suggestion';
        btn.textContent = it.name;
        btn.addEventListener('mousedown', function (e) {
          e.preventDefault();
          onSelect(it);
        });
        container.appendChild(btn);
      });
    }
    container.classList.add('open');
  }

  function closeAllSugg() {
    govSugg.classList.remove('open');
    citySugg.classList.remove('open');
  }

  govInput.addEventListener('focus', function () {
    fetchGovs();
    buildSuggestions(govSugg, GOVS, govInput.value, function (gov) {
      govInput.value = gov.name;
      selectedGovId = gov.id;
      selectedGovName = gov.name;
      govSugg.classList.remove('open');
      selectedCityName = '';
      cityInput.value = '';
      fetchCities(gov.id);
    });
  });

  govInput.addEventListener('input', function () {
    selectedGovId = null;
    selectedGovName = '';
    buildSuggestions(govSugg, GOVS, govInput.value, function (gov) {
      govInput.value = gov.name;
      selectedGovId = gov.id;
      selectedGovName = gov.name;
      govSugg.classList.remove('open');
      selectedCityName = '';
      cityInput.value = '';
      fetchCities(gov.id);
    });
  });

  cityInput.addEventListener('focus', function () {
    if (!CITIES.length) return;
    buildSuggestions(citySugg, CITIES, cityInput.value, function (city) {
      cityInput.value = city.name;
      selectedCityName = city.name;
      citySugg.classList.remove('open');
    });
  });

  cityInput.addEventListener('input', function () {
    selectedCityName = '';
    if (!CITIES.length) return;
    buildSuggestions(citySugg, CITIES, cityInput.value, function (city) {
      cityInput.value = city.name;
      selectedCityName = city.name;
      citySugg.classList.remove('open');
    });
  });

  document.addEventListener('click', function (e) {
    if (!addrBack.contains(e.target)) return;
    if (!govInput.contains(e.target) && !govSugg.contains(e.target)) govSugg.classList.remove('open');
    if (!cityInput.contains(e.target) && !citySugg.contains(e.target)) citySugg.classList.remove('open');
  });

  /* ─── Address modal — open / close / save ──────────────────── */
  function openAddressModal() {
    var s = getSession() || {};
    govInput.value    = s.governorate || '';
    cityInput.value   = s.city || '';
    selectedGovName   = s.governorate || '';
    selectedCityName  = s.city || '';
    selectedGovId     = s.governorateId || null;
    document.getElementById('alm-pd-detail').value = s.addressDetails || s.address_detail || '';
    document.getElementById('alm-pd-addr-msg').textContent = '';
    cityInput.disabled = !selectedGovId;
    cityInput.placeholder = selectedGovId ? 'اكتب لاختيار المدينة...' : 'اختر المحافظة أولاً';
    if (selectedGovId && !CITIES.length) fetchCities(selectedGovId);
    closeAllSugg();
    fetchGovs();
    var btn = document.getElementById('alm-pd-save-btn');
    btn.disabled = false;
    btn.textContent = 'حفظ العنوان';
    addrBack.classList.add('open');
  }

  function closeAddressModal() {
    addrBack.classList.remove('open');
    closeAllSugg();
  }

  document.getElementById('apd-btn-address').addEventListener('click', function (e) {
    e.stopPropagation();
    apdClose();
    openAddressModal();
  });
  document.getElementById('alm-pd-addr-close').addEventListener('click', closeAddressModal);
  document.getElementById('alm-pd-addr-cancel').addEventListener('click', closeAddressModal);
  addrBack.addEventListener('click', function (e) { if (e.target === addrBack) closeAddressModal(); });

  document.getElementById('alm-pd-save-btn').addEventListener('click', async function () {
    var s = getSession();
    if (!s) return;
    var gov    = govInput.value.trim() || selectedGovName;
    var city   = cityInput.value.trim() || selectedCityName;
    var detail = document.getElementById('alm-pd-detail').value.trim();
    var msg    = document.getElementById('alm-pd-addr-msg');
    var btn    = document.getElementById('alm-pd-save-btn');

    btn.disabled = true;
    btn.textContent = 'جارٍ الحفظ...';
    msg.textContent = '';

    try {
      var res = await fetch('apis/users/profile.php', {
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
        governorateId:  selectedGovId,
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
