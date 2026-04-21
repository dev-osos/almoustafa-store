/**
 * auth-modal.js
 * Shared login / register modal for all pages.
 * Exposes: window.almAuth.open(panel)  — panel: 'login' | 'register'
 * Dispatches: CustomEvent 'alm:session-changed' on login/logout
 */
(function () {
  'use strict';

  /* ─── Session helpers ──────────────────────────────────────── */
  function getSession() {
    try { return JSON.parse(localStorage.getItem('alm_session')); } catch { return null; }
  }
  function setSession(data) {
    localStorage.setItem('alm_session', JSON.stringify(data));
  }
  function notifySessionChange() {
    document.dispatchEvent(new CustomEvent('alm:session-changed'));
  }

  /* ─── Inject CSS ───────────────────────────────────────────── */
  var style = document.createElement('style');
  style.textContent = `
    #alm-modal-backdrop {
      position: fixed; inset: 0;
      background: rgba(28,28,23,0.72);
      backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
      z-index: 9000;
      display: flex; align-items: center; justify-content: center;
      padding: 16px;
      opacity: 0; pointer-events: none;
      transition: opacity 0.3s cubic-bezier(0.4,0,0.2,1);
    }
    #alm-modal-backdrop.open { opacity: 1; pointer-events: auto; }
    #alm-login-modal {
      position: relative; z-index: 9001;
      width: 100%; max-width: 420px;
      background: rgba(60,0,4,0.82);
      backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
      border-radius: 28px;
      border: 1px solid rgba(255,255,255,0.1);
      box-shadow: 0 40px 100px rgba(0,0,0,0.65), inset 0 1px 0 rgba(255,255,255,0.12);
      padding: 44px 38px 40px;
      overflow: hidden;
      transform: scale(0.95);
      transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
    }
    #alm-modal-backdrop.open #alm-login-modal { transform: scale(1); }
    #alm-login-modal::before {
      content: ''; position: absolute; top: -70px; left: 50%;
      transform: translateX(-50%);
      width: 280px; height: 200px;
      background: radial-gradient(ellipse, rgba(254,214,91,0.22) 0%, transparent 68%);
      pointer-events: none;
    }
    #alm-login-modal::after {
      content: ''; position: absolute; bottom: -50px; right: -50px;
      width: 180px; height: 180px;
      background: radial-gradient(circle, rgba(254,214,91,0.08) 0%, transparent 65%);
      border-radius: 50%; pointer-events: none;
    }
    .alm-modal-close-btn {
      position: absolute; top: 16px; left: 16px;
      width: 34px; height: 34px;
      border: 1px solid rgba(255,255,255,0.14);
      background: rgba(255,255,255,0.06);
      border-radius: 50%; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      color: rgba(255,255,255,0.5);
      transition: background 0.2s; z-index: 2;
    }
    .alm-modal-close-btn:hover { background: rgba(255,255,255,0.14); color: #fff; }
    .alm-modal-logo-area {
      display: flex; flex-direction: column; align-items: center;
      margin-bottom: 28px; position: relative; z-index: 1;
    }
    .alm-modal-logo-ring {
      width: 76px; height: 76px; border-radius: 50%;
      border: 2px solid rgba(254,214,91,0.32);
      display: flex; align-items: center; justify-content: center;
      background: rgba(254,214,91,0.07);
      margin-bottom: 12px;
      box-shadow: 0 0 28px rgba(254,214,91,0.12);
    }
    .alm-modal-logo-ring img { width: 54px; height: 54px; object-fit: contain; border-radius: 50%; }
    .alm-modal-logo-area h2 { font-family: 'Amiri', serif; color: #fff; font-size: 1.5rem; font-weight: 700; }
    .alm-modal-logo-area p { color: rgba(255,255,255,0.4); font-size: 0.78rem; margin-top: 4px; }
    .alm-modal-gold-divider {
      width: 44px; height: 2px;
      background: linear-gradient(90deg, transparent, #fed65b, transparent);
      border-radius: 2px; margin: 9px auto 0;
    }
    .alm-modal-tabs {
      display: flex; background: rgba(255,255,255,0.06);
      border-radius: 12px; padding: 4px; margin-bottom: 26px;
      position: relative; z-index: 1;
    }
    .alm-modal-tab {
      flex: 1; text-align: center; padding: 9px 0;
      font-family: 'Amiri', serif; font-size: 0.88rem; font-weight: 700;
      color: rgba(255,255,255,0.35); border-radius: 9px; cursor: pointer;
      transition: all 0.2s;
    }
    .alm-modal-tab.active { background: rgba(254,214,91,0.15); color: #fed65b; box-shadow: 0 1px 4px rgba(0,0,0,0.2); }
    .alm-modal-panel { display: none; position: relative; z-index: 1; }
    .alm-modal-panel.active { display: block; animation: almModalFadeIn 0.2s ease-out; }
    @keyframes almModalFadeIn { from { opacity: 0; } to { opacity: 1; } }
    .alm-modal-field { margin-bottom: 15px; }
    .alm-modal-field label { display: block; font-size: 0.73rem; font-weight: 600; color: rgba(255,255,255,0.4); margin-bottom: 7px; letter-spacing: 0.05em; }
    .alm-modal-field-wrap { position: relative; }
    .alm-modal-field input {
      width: 100%; padding: 13px 16px 13px 44px;
      border: 1.5px solid rgba(255,255,255,0.1); border-radius: 12px;
      background: rgba(255,255,255,0.06);
      color: #fff; font-family: 'Manrope', sans-serif; font-size: 0.87rem;
      outline: none; transition: border-color 0.2s, background 0.2s;
    }
    .alm-modal-field input:focus { border-color: rgba(254,214,91,0.5); background: rgba(255,255,255,0.09); }
    .alm-modal-field input::placeholder { color: rgba(255,255,255,0.22); }
    .alm-modal-field-icon {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      color: rgba(255,255,255,0.28); font-size: 19px; pointer-events: none;
    }
    .alm-modal-btn-gold {
      width: 100%; padding: 13px;
      background: linear-gradient(135deg, #fed65b 0%, #f5c400 100%);
      color: #3c0004; border: none; border-radius: 12px;
      font-family: 'Amiri', serif; font-size: 1rem; font-weight: 700;
      cursor: pointer; letter-spacing: 0.04em; margin-top: 6px;
      box-shadow: 0 4px 20px rgba(254,214,91,0.28);
      transition: transform 0.15s, box-shadow 0.15s;
    }
    .alm-modal-btn-gold:hover { transform: translateY(-1px); box-shadow: 0 6px 28px rgba(254,214,91,0.4); }
    .alm-modal-btn-gold:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }
    .alm-modal-btn-outline {
      width: 100%; padding: 13px; background: transparent;
      color: rgba(255,255,255,0.5); border: 1.5px solid rgba(255,255,255,0.12); border-radius: 12px;
      font-family: 'Amiri', serif; font-size: 0.92rem; font-weight: 700;
      cursor: pointer; margin-top: 8px; transition: border-color 0.2s, color 0.2s;
    }
    .alm-modal-btn-outline:hover { border-color: rgba(255,255,255,0.25); color: #fff; }
    .alm-modal-forgot { text-align: center; margin-top: 14px; font-size: 0.77rem; color: rgba(254,214,91,0.6); cursor: pointer; }
    .alm-modal-forgot:hover { color: #fed65b; }
    .alm-modal-separator { border: none; border-top: 1px solid rgba(255,255,255,0.07); margin: 16px 0; }
    .alm-modal-footer { text-align: center; font-size: 0.78rem; color: rgba(255,255,255,0.3); }
    .alm-modal-footer span { color: #fed65b; font-weight: 700; cursor: pointer; }
    .alm-modal-footer span:hover { text-decoration: underline; }
    .alm-otp-row { display: flex; gap: 8px; justify-content: center; margin-bottom: 14px; }
    .alm-otp-box {
      width: 56px; height: 60px;
      background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.1);
      border-radius: 12px; text-align: center;
      font-family: 'Manrope', sans-serif; font-size: 1.4rem; font-weight: 700; color: #fed65b;
      outline: none; caret-color: #fed65b; transition: border-color 0.2s, background 0.2s;
    }
    .alm-otp-box:focus { border-color: rgba(254,214,91,0.55); background: rgba(254,214,91,0.08); }
    .alm-modal-otp-hint { text-align: center; font-size: 0.75rem; color: rgba(255,255,255,0.35); margin-bottom: 18px; line-height: 1.5; }
    .alm-modal-otp-hint .hl { color: rgba(254,214,91,0.75); }
    .alm-modal-timer { text-align: center; font-size: 0.75rem; color: rgba(255,255,255,0.35); margin-bottom: 6px; }
    .alm-modal-timer .hl { color: #fed65b; font-weight: 700; }
    .alm-modal-back-link { text-align: center; font-size: 0.78rem; color: rgba(255,255,255,0.3); cursor: pointer; margin-top: 6px; }
    .alm-modal-back-link:hover { color: rgba(255,255,255,0.6); }
    .alm-phone-row { display: flex; gap: 8px; align-items: stretch; }
    .alm-cc-btn {
      flex-shrink: 0; display: flex; align-items: center; gap: 5px; padding: 0 10px;
      background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.1); border-radius: 12px;
      color: #fff; font-family: 'Manrope', sans-serif; font-size: 0.82rem;
      cursor: pointer; white-space: nowrap; transition: background 0.2s, border-color 0.2s; position: relative;
    }
    .alm-cc-btn:hover { background: rgba(255,255,255,0.1); border-color: rgba(254,214,91,0.3); }
    .alm-cc-btn .flag { font-size: 1.1rem; line-height: 1; }
    .alm-cc-btn .dial { font-weight: 700; letter-spacing: 0.03em; direction: ltr; }
    .alm-cc-btn .chevron { font-size: 14px; opacity: 0.5; font-family: 'Material Symbols Outlined'; font-weight: normal; }
    .alm-cc-dropdown {
      position: absolute; top: calc(100% + 6px); right: 0;
      width: 260px; max-height: 240px; overflow-y: auto;
      background: rgba(22,10,3,0.98); border: 1px solid rgba(255,255,255,0.12);
      border-radius: 14px; box-shadow: 0 16px 48px rgba(0,0,0,0.6);
      z-index: 700; display: none; direction: rtl;
    }
    .alm-cc-dropdown.open { display: block; }
    .alm-cc-search { padding: 10px 12px 6px; position: sticky; top: 0; background: rgba(22,10,3,0.98); }
    .alm-cc-search input {
      width: 100%; padding: 7px 10px; background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff;
      font-size: 0.78rem; font-family: 'Manrope', sans-serif; outline: none; direction: rtl;
    }
    .alm-cc-search input::placeholder { color: rgba(255,255,255,0.3); }
    .alm-cc-item { display: flex; align-items: center; gap: 8px; padding: 8px 14px; cursor: pointer; font-size: 0.8rem; color: rgba(255,255,255,0.8); transition: background 0.15s; }
    .alm-cc-item:hover, .alm-cc-item.selected { background: rgba(254,214,91,0.1); color: #fed65b; }
    .alm-cc-item .cc-flag { font-size: 1.1rem; flex-shrink: 0; }
    .alm-cc-item .cc-name { flex: 1; }
    .alm-cc-item .cc-dial { font-weight: 700; direction: ltr; opacity: 0.7; font-size: 0.75rem; }
    .alm-phone-row .alm-modal-field-wrap { flex: 1; }
    .alm-phone-exists-msg {
      display: none; align-items: center; gap: 6px; margin-top: 7px; padding: 8px 11px;
      background: rgba(255,80,80,0.12); border: 1px solid rgba(255,80,80,0.25); border-radius: 10px;
      font-size: 0.76rem; color: rgba(255,160,160,0.95); line-height: 1.45;
    }
    .alm-phone-exists-msg.show { display: flex; }
    .alm-phone-exists-msg .ms { font-size: 16px; flex-shrink: 0; color: rgba(255,100,100,0.9); }
    .alm-phone-exists-msg span.goto { color: #fed65b; cursor: pointer; font-weight: 700; text-decoration: underline; white-space: nowrap; }
    #alm-reg-phone.exists { border-color: rgba(255,80,80,0.5) !important; }
    .alm-modal-success-icon {
      width: 64px; height: 64px; background: rgba(254,214,91,0.12);
      border: 2px solid rgba(254,214,91,0.4); border-radius: 50%;
      display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;
    }
    .alm-modal-success-icon span { color: #fed65b; font-size: 32px; font-variation-settings: 'FILL' 1, 'wght' 300, 'GRAD' 0, 'opsz' 24; }
    #alm-login-modal.compact { padding: 36px 38px 32px; }
    #alm-login-modal.compact .alm-modal-logo-area { display: none; }
    #alm-login-modal.compact .alm-modal-success-icon { width: 56px; height: 56px; margin-bottom: 14px; }
    #alm-login-modal.compact .alm-modal-success-icon span { font-size: 28px; }
    @media (max-width: 480px) { #alm-login-modal { padding: 28px 20px; } }
  `;
  document.head.appendChild(style);

  /* ─── Inject HTML ──────────────────────────────────────────── */
  var backdropEl = document.createElement('div');
  backdropEl.id = 'alm-modal-backdrop';
  backdropEl.setAttribute('role', 'dialog');
  backdropEl.setAttribute('aria-modal', 'true');
  backdropEl.innerHTML = `
  <div id="alm-login-modal">
    <button class="alm-modal-close-btn" id="alm-modal-close-btn" aria-label="إغلاق">
      <span class="material-symbols-outlined" style="font-size:17px;">close</span>
    </button>
    <div class="alm-modal-logo-area">
      <div class="alm-modal-logo-ring"><img src="logo.png" alt="شعار المصطفى"/></div>
      <h2>المصطفى</h2>
      <p>خبراء العسل الصافي</p>
      <div class="alm-modal-gold-divider"></div>
    </div>
    <div class="alm-modal-tabs" id="alm-modal-tabs">
      <div class="alm-modal-tab active" data-tab="login">تسجيل الدخول</div>
      <div class="alm-modal-tab" data-tab="register">إنشاء حساب جديد</div>
    </div>

    <!-- LOGIN -->
    <div class="alm-modal-panel active" id="alm-panel-login">
      <div class="alm-modal-field">
        <label for="alm-login-phone">رقم الهاتف</label>
        <div class="alm-phone-row">
          <button type="button" class="alm-cc-btn" id="alm-login-cc-btn" aria-haspopup="listbox" aria-expanded="false">
            <span class="flag" id="alm-login-cc-flag">🇸🇦</span>
            <span class="dial" id="alm-login-cc-dial">+966</span>
            <span class="chevron">expand_more</span>
            <div class="alm-cc-dropdown" id="alm-login-cc-dropdown" role="listbox">
              <div class="alm-cc-search"><input type="text" id="alm-login-cc-search" placeholder="ابحث عن دولة..." autocomplete="off"/></div>
              <div id="alm-login-cc-list"></div>
            </div>
          </button>
          <div class="alm-modal-field-wrap">
            <span class="alm-modal-field-icon material-symbols-outlined">phone_iphone</span>
            <input id="alm-login-phone" type="tel" placeholder="5XXXXXXXX" autocomplete="tel" dir="ltr"/>
          </div>
        </div>
      </div>
      <div class="alm-modal-field">
        <label for="alm-login-password">كلمة المرور</label>
        <div class="alm-modal-field-wrap">
          <span class="alm-modal-field-icon material-symbols-outlined">lock</span>
          <input id="alm-login-password" type="password" placeholder="••••••••" autocomplete="current-password"/>
        </div>
      </div>
      <button class="alm-modal-btn-gold" id="alm-btn-login-submit">دخول</button>
      <div class="alm-modal-forgot" id="alm-btn-forgot">نسيت كلمة المرور؟</div>
      <hr class="alm-modal-separator"/>
      <div class="alm-modal-footer">ليس لديك حساب؟ <span id="alm-btn-go-register">إنشاء حساب جديد</span></div>
    </div>

    <!-- FORGOT PASSWORD -->
    <div class="alm-modal-panel" id="alm-panel-forgot">
      <div id="alm-forgot-step-1">
        <div style="text-align:center;margin-bottom:20px;position:relative;z-index:1;">
          <div style="font-family:'Amiri',serif;color:#fff;font-size:1.1rem;font-weight:700;margin-bottom:6px;">استعادة كلمة المرور</div>
          <div style="font-size:0.75rem;color:rgba(255,255,255,0.35);line-height:1.5;">أدخل رقم هاتفك لإرسال رمز التحقق</div>
        </div>
        <div class="alm-modal-field">
          <label for="alm-forgot-phone">رقم الهاتف</label>
          <div class="alm-phone-row">
            <button type="button" class="alm-cc-btn" id="alm-forgot-cc-btn" aria-haspopup="listbox" aria-expanded="false">
              <span class="flag" id="alm-forgot-cc-flag">🇸🇦</span>
              <span class="dial" id="alm-forgot-cc-dial">+966</span>
              <span class="chevron">expand_more</span>
              <div class="alm-cc-dropdown" id="alm-forgot-cc-dropdown" role="listbox">
                <div class="alm-cc-search"><input type="text" id="alm-forgot-cc-search" placeholder="ابحث عن دولة..." autocomplete="off"/></div>
                <div id="alm-forgot-cc-list"></div>
              </div>
            </button>
            <div class="alm-modal-field-wrap">
              <span class="alm-modal-field-icon material-symbols-outlined">phone_iphone</span>
              <input id="alm-forgot-phone" type="tel" placeholder="5XXXXXXXX" autocomplete="tel" dir="ltr"/>
            </div>
          </div>
          <div id="alm-forgot-phone-hint" style="font-size:0.72rem;margin-top:5px;min-height:16px;"></div>
        </div>
        <button class="alm-modal-btn-gold" id="alm-btn-send-forgot-otp" disabled style="opacity:0.45;cursor:not-allowed;">إرسال رمز تغيير كلمة السر</button>
      </div>
      <div id="alm-forgot-step-2" style="display:none;">
        <div class="alm-modal-otp-hint">تم إرسال رمز التحقق إلى<br/><span class="hl" id="alm-forgot-otp-phone-display"></span></div>
        <div class="alm-otp-row" id="alm-forgot-otp-row">
          <input class="alm-otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الأول"/>
          <input class="alm-otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الثاني"/>
          <input class="alm-otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الثالث"/>
          <input class="alm-otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الرابع"/>
        </div>
        <div class="alm-modal-timer">انتهاء الرمز خلال <span class="hl" id="alm-forgot-otp-timer">1:30</span></div>
        <button class="alm-modal-btn-gold" id="alm-btn-verify-forgot-otp" style="margin-top:14px;">التحقق والمتابعة</button>
        <button class="alm-modal-btn-outline" id="alm-btn-resend-forgot-otp">إعادة إرسال الرمز</button>
      </div>
      <hr class="alm-modal-separator" style="margin-top:18px;"/>
      <div class="alm-modal-back-link" id="alm-btn-back-to-login">
        <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;margin-left:4px;">arrow_forward</span>
        العودة لتسجيل الدخول
      </div>
    </div>

    <!-- NEW PASSWORD -->
    <div class="alm-modal-panel" id="alm-panel-new-password">
      <div style="text-align:center;margin-bottom:22px;position:relative;z-index:1;">
        <div style="font-family:'Amiri',serif;color:#fff;font-size:1.1rem;font-weight:700;margin-bottom:6px;">تعيين كلمة مرور جديدة</div>
        <div style="font-size:0.75rem;color:rgba(255,255,255,0.35);line-height:1.5;">اختر كلمة مرور جديدة لحسابك</div>
      </div>
      <div class="alm-modal-field">
        <label for="alm-new-password">كلمة المرور الجديدة</label>
        <div class="alm-modal-field-wrap">
          <span class="alm-modal-field-icon material-symbols-outlined">lock</span>
          <input id="alm-new-password" type="password" placeholder="••••••••" autocomplete="new-password"/>
        </div>
      </div>
      <div class="alm-modal-field">
        <label for="alm-confirm-password">تأكيد كلمة المرور</label>
        <div class="alm-modal-field-wrap">
          <span class="alm-modal-field-icon material-symbols-outlined">lock_reset</span>
          <input id="alm-confirm-password" type="password" placeholder="••••••••" autocomplete="new-password"/>
        </div>
      </div>
      <div id="alm-msg-password-mismatch" style="display:none;text-align:center;margin-top:8px;font-size:0.75rem;color:rgba(255,120,120,0.8);">كلمتا المرور غير متطابقتين</div>
      <button class="alm-modal-btn-gold" id="alm-btn-save-new-password">حفظ كلمة المرور الجديدة</button>
    </div>

    <!-- REGISTER STEP 1 -->
    <div class="alm-modal-panel" id="alm-panel-register-1">
      <div class="alm-modal-field">
        <label for="alm-reg-name">الاسم الكامل</label>
        <div class="alm-modal-field-wrap">
          <span class="alm-modal-field-icon material-symbols-outlined">person</span>
          <input id="alm-reg-name" type="text" placeholder="أدخل اسمك" autocomplete="name"/>
        </div>
      </div>
      <div class="alm-modal-field">
        <label for="alm-reg-phone">رقم الهاتف</label>
        <div class="alm-phone-row">
          <button type="button" class="alm-cc-btn" id="alm-cc-btn" aria-haspopup="listbox" aria-expanded="false">
            <span class="flag" id="alm-cc-flag">🇸🇦</span>
            <span class="dial" id="alm-cc-dial">+966</span>
            <span class="chevron">expand_more</span>
            <div class="alm-cc-dropdown" id="alm-cc-dropdown" role="listbox">
              <div class="alm-cc-search"><input type="text" id="alm-cc-search-input" placeholder="ابحث عن دولة..." autocomplete="off"/></div>
              <div id="alm-cc-list"></div>
            </div>
          </button>
          <div class="alm-modal-field-wrap">
            <span class="alm-modal-field-icon material-symbols-outlined">phone_iphone</span>
            <input id="alm-reg-phone" type="tel" placeholder="5XXXXXXXX" autocomplete="tel" dir="ltr"/>
          </div>
        </div>
        <div class="alm-phone-exists-msg" id="alm-phone-exists-msg">
          <span class="ms">warning</span>
          <span>هذا الرقم مسجّل مسبقاً. <span class="goto" id="alm-phone-exists-goto">تسجيل الدخول</span></span>
        </div>
      </div>
      <div class="alm-modal-field">
        <label for="alm-reg-password">كلمة المرور</label>
        <div class="alm-modal-field-wrap">
          <span class="alm-modal-field-icon material-symbols-outlined">lock</span>
          <input id="alm-reg-password" type="password" placeholder="••••••••" autocomplete="new-password"/>
        </div>
      </div>
      <button class="alm-modal-btn-gold" id="alm-btn-send-otp">إرسال كود التأكيد</button>
      <hr class="alm-modal-separator"/>
      <div class="alm-modal-footer">لديك حساب؟ <span id="alm-btn-go-login">تسجيل الدخول</span></div>
    </div>

    <!-- REGISTER STEP 2 OTP -->
    <div class="alm-modal-panel" id="alm-panel-register-2">
      <div class="alm-modal-otp-hint">تم إرسال كود مكوّن من 4 أرقام إلى<br/><span class="hl" id="alm-otp-phone-display"></span></div>
      <div class="alm-otp-row" id="alm-otp-row">
        <input class="alm-otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الأول"/>
        <input class="alm-otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الثاني"/>
        <input class="alm-otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الثالث"/>
        <input class="alm-otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الرابع"/>
      </div>
      <div class="alm-modal-timer">انتهاء الكود خلال <span class="hl" id="alm-otp-timer">10:00</span></div>
      <button class="alm-modal-btn-gold" id="alm-btn-verify-otp" style="margin-top:14px;">تأكيد الكود</button>
      <button class="alm-modal-btn-outline" id="alm-btn-resend-otp">إعادة إرسال الكود</button>
      <div class="alm-modal-back-link" id="alm-btn-back-to-reg1">
        <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;margin-left:4px;">arrow_forward</span>
        تعديل رقم الهاتف
      </div>
    </div>

    <!-- SUCCESS -->
    <div class="alm-modal-panel" id="alm-panel-success">
      <div style="text-align:center;">
        <div class="alm-modal-success-icon"><span class="material-symbols-outlined">check_circle</span></div>
        <div style="font-family:'Amiri',serif;color:#fff;font-size:1.3rem;font-weight:700;margin-bottom:8px;">مرحباً بك في متجر المصطفى!</div>
        <div id="alm-success-name-line" style="font-size:0.82rem;color:rgba(255,255,255,0.4);margin-bottom:24px;"></div>
      </div>
      <button class="alm-modal-btn-gold" id="alm-btn-success-close">متابعة التسوق</button>
    </div>
  </div>
  `;
  document.body.appendChild(backdropEl);

  /* ─── Open / Close ─────────────────────────────────────────── */
  function openModal() {
    backdropEl.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeModal() {
    backdropEl.classList.remove('open');
    document.body.style.overflow = '';
  }

  /* ─── Panel switcher ───────────────────────────────────────── */
  function showPanel(name) {
    document.querySelectorAll('.alm-modal-panel').forEach(function(p) { p.classList.remove('active'); });
    var panel = document.getElementById('alm-panel-' + name);
    if (panel) panel.classList.add('active');
    var tabsEl = document.getElementById('alm-modal-tabs');
    var loginTab    = tabsEl.querySelector('[data-tab="login"]');
    var registerTab = tabsEl.querySelector('[data-tab="register"]');
    loginTab.classList.toggle('active', name === 'login');
    registerTab.classList.toggle('active', name === 'register-1' || name === 'register-2');
    tabsEl.style.display = (name === 'register-2' || name === 'success' || name === 'forgot' || name === 'new-password') ? 'none' : 'flex';
    var modal = document.getElementById('alm-login-modal');
    modal.classList.toggle('compact', name === 'success' || name === 'new-password');
  }

  /* ─── Country picker ───────────────────────────────────────── */
  var COUNTRIES = [
    {n:'السعودية',f:'🇸🇦',d:'+966'},{n:'مصر',f:'🇪🇬',d:'+20'},
    {n:'الإمارات',f:'🇦🇪',d:'+971'},{n:'الكويت',f:'🇰🇼',d:'+965'},
    {n:'قطر',f:'🇶🇦',d:'+974'},{n:'البحرين',f:'🇧🇭',d:'+973'},
    {n:'عُمان',f:'🇴🇲',d:'+968'},{n:'الأردن',f:'🇯🇴',d:'+962'},
    {n:'لبنان',f:'🇱🇧',d:'+961'},{n:'العراق',f:'🇮🇶',d:'+964'},
    {n:'سوريا',f:'🇸🇾',d:'+963'},{n:'اليمن',f:'🇾🇪',d:'+967'},
    {n:'ليبيا',f:'🇱🇾',d:'+218'},{n:'تونس',f:'🇹🇳',d:'+216'},
    {n:'الجزائر',f:'🇩🇿',d:'+213'},{n:'المغرب',f:'🇲🇦',d:'+212'},
    {n:'السودان',f:'🇸🇩',d:'+249'},{n:'تركيا',f:'🇹🇷',d:'+90'},
    {n:'باكستان',f:'🇵🇰',d:'+92'},{n:'الهند',f:'🇮🇳',d:'+91'},
    {n:'المملكة المتحدة',f:'🇬🇧',d:'+44'},{n:'ألمانيا',f:'🇩🇪',d:'+49'},
    {n:'فرنسا',f:'🇫🇷',d:'+33'},{n:'كندا',f:'🇨🇦',d:'+1'},
    {n:'الولايات المتحدة',f:'🇺🇸',d:'+1'},{n:'أستراليا',f:'🇦🇺',d:'+61'},
  ];
  var selectedCountry = COUNTRIES[0];

  function buildCcList(filter) {
    var list = document.getElementById('alm-cc-list');
    list.innerHTML = '';
    var q = (filter || '').trim();
    var filtered = q ? COUNTRIES.filter(function(c) { return c.n.includes(q) || c.d.includes(q); }) : COUNTRIES;
    filtered.forEach(function(c) {
      var div = document.createElement('div');
      div.className = 'alm-cc-item' + (c === selectedCountry ? ' selected' : '');
      div.setAttribute('role', 'option');
      div.innerHTML = '<span class="cc-flag">' + c.f + '</span><span class="cc-name">' + c.n + '</span><span class="cc-dial">' + c.d + '</span>';
      div.addEventListener('mousedown', function(e) {
        e.preventDefault();
        selectedCountry = c;
        document.getElementById('alm-cc-flag').textContent = c.f;
        document.getElementById('alm-cc-dial').textContent = c.d;
        document.getElementById('alm-reg-phone').placeholder = c.d === '+966' ? '5XXXXXXXX' : 'XXXXXXXXX';
        closeCcDropdown();
      });
      list.appendChild(div);
    });
  }
  function openCcDropdown() {
    document.getElementById('alm-cc-dropdown').classList.add('open');
    document.getElementById('alm-cc-btn').setAttribute('aria-expanded', 'true');
    document.getElementById('alm-cc-search-input').value = '';
    buildCcList('');
    setTimeout(function() { document.getElementById('alm-cc-search-input').focus(); }, 50);
  }
  function closeCcDropdown() {
    document.getElementById('alm-cc-dropdown').classList.remove('open');
    document.getElementById('alm-cc-btn').setAttribute('aria-expanded', 'false');
  }
  function getRegPhone() {
    var raw = document.getElementById('alm-reg-phone').value.trim().replace(/[\s\-().]+/g, '');
    return selectedCountry.d + raw.replace(/^0+/, '');
  }

  document.getElementById('alm-cc-btn').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('alm-cc-dropdown').classList.contains('open') ? closeCcDropdown() : openCcDropdown();
  });
  document.getElementById('alm-cc-search-input').addEventListener('input', function(e) { buildCcList(e.target.value); });
  document.addEventListener('click', function(e) {
    if (!document.getElementById('alm-cc-btn').contains(e.target)) closeCcDropdown();
    if (document.getElementById('alm-forgot-cc-btn') && !document.getElementById('alm-forgot-cc-btn').contains(e.target)) closeAlmForgotCcDropdown();
    if (document.getElementById('alm-login-cc-btn') && !document.getElementById('alm-login-cc-btn').contains(e.target)) closeAlmLoginCcDropdown();
  });
  buildCcList('');

  /* ─── Login CC picker ──────────────────────────────────────── */
  var almLoginSelectedCountry = COUNTRIES[0];
  function buildAlmLoginCcList(filter) {
    var list = document.getElementById('alm-login-cc-list');
    list.innerHTML = '';
    var q = (filter || '').trim();
    var filtered = q ? COUNTRIES.filter(function(c) { return c.n.includes(q) || c.d.includes(q); }) : COUNTRIES;
    filtered.forEach(function(c) {
      var div = document.createElement('div');
      div.className = 'alm-cc-item' + (c === almLoginSelectedCountry ? ' selected' : '');
      div.setAttribute('role', 'option');
      div.innerHTML = '<span class="cc-flag">' + c.f + '</span><span class="cc-name">' + c.n + '</span><span class="cc-dial">' + c.d + '</span>';
      div.addEventListener('mousedown', function(e) {
        e.preventDefault();
        almLoginSelectedCountry = c;
        document.getElementById('alm-login-cc-flag').textContent = c.f;
        document.getElementById('alm-login-cc-dial').textContent = c.d;
        document.getElementById('alm-login-phone').placeholder = c.d === '+966' ? '5XXXXXXXX' : 'XXXXXXXXX';
        closeAlmLoginCcDropdown();
      });
      list.appendChild(div);
    });
  }
  function openAlmLoginCcDropdown() {
    document.getElementById('alm-login-cc-dropdown').classList.add('open');
    document.getElementById('alm-login-cc-btn').setAttribute('aria-expanded', 'true');
    document.getElementById('alm-login-cc-search').value = '';
    buildAlmLoginCcList('');
    setTimeout(function() { document.getElementById('alm-login-cc-search').focus(); }, 50);
  }
  function closeAlmLoginCcDropdown() {
    document.getElementById('alm-login-cc-dropdown').classList.remove('open');
    document.getElementById('alm-login-cc-btn').setAttribute('aria-expanded', 'false');
  }
  document.getElementById('alm-login-cc-btn').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('alm-login-cc-dropdown').classList.contains('open') ? closeAlmLoginCcDropdown() : openAlmLoginCcDropdown();
  });
  document.getElementById('alm-login-cc-search').addEventListener('input', function(e) { buildAlmLoginCcList(e.target.value); });
  function getAlmLoginPhone() {
    var raw = document.getElementById('alm-login-phone').value.trim().replace(/[\s\-().]+/g, '');
    return almLoginSelectedCountry.d + raw.replace(/^0+/, '');
  }
  buildAlmLoginCcList('');

  /* ─── Phone exists check ───────────────────────────────────── */
  var phoneCheckTimer = null;
  function showPhoneExists(show) {
    document.getElementById('alm-phone-exists-msg').classList.toggle('show', show);
    document.getElementById('alm-reg-phone').classList.toggle('exists', show);
    document.getElementById('alm-btn-send-otp').disabled = show;
  }
  function checkPhoneExists() {
    var phone = getRegPhone();
    var local = document.getElementById('alm-reg-phone').value.trim().replace(/\D/g, '');
    if (local.length < 7) { showPhoneExists(false); return; }
    fetch('apis/users/check_phone.php?phone=' + encodeURIComponent(phone))
      .then(function(r) { return r.json(); })
      .then(function(d) { showPhoneExists(d.exists === true); })
      .catch(function() { showPhoneExists(false); });
  }
  document.getElementById('alm-reg-phone').addEventListener('input', function() {
    showPhoneExists(false);
    clearTimeout(phoneCheckTimer);
    phoneCheckTimer = setTimeout(checkPhoneExists, 600);
  });
  document.getElementById('alm-phone-exists-goto').addEventListener('click', function() {
    var loginPhone = document.getElementById('alm-login-phone');
    if (loginPhone) loginPhone.value = document.getElementById('alm-reg-phone').value.trim();
    showPanel('login');
  });

  /* ─── Tab switcher ─────────────────────────────────────────── */
  document.getElementById('alm-modal-tabs').addEventListener('click', function(e) {
    var tab = e.target.closest('[data-tab]');
    if (!tab) return;
    showPanel(tab.dataset.tab === 'login' ? 'login' : 'register-1');
  });
  document.getElementById('alm-btn-go-register').addEventListener('click', function() { showPanel('register-1'); });
  document.getElementById('alm-btn-go-login').addEventListener('click', function() { showPanel('login'); });

  /* ─── Forgot password ──────────────────────────────────────── */
  var forgotOtpInterval = null;
  document.getElementById('alm-btn-forgot').addEventListener('click', function() {
    document.getElementById('alm-forgot-step-1').style.display = 'block';
    document.getElementById('alm-forgot-step-2').style.display = 'none';
    document.getElementById('alm-forgot-phone').value = '';
    document.getElementById('alm-forgot-phone-hint').textContent = '';
    var sb = document.getElementById('alm-btn-send-forgot-otp');
    sb.disabled = true; sb.style.opacity = '0.45'; sb.style.cursor = 'not-allowed';
    showPanel('forgot');
  });
  document.getElementById('alm-btn-back-to-login').addEventListener('click', function() { showPanel('login'); });

  /* ─── Forgot CC picker ─────────────────────────────────────── */
  var almForgotSelectedCountry = COUNTRIES[0];
  function buildAlmForgotCcList(filter) {
    var list = document.getElementById('alm-forgot-cc-list');
    list.innerHTML = '';
    var q = (filter || '').trim();
    var filtered = q ? COUNTRIES.filter(function(c) { return c.n.includes(q) || c.d.includes(q); }) : COUNTRIES;
    filtered.forEach(function(c) {
      var div = document.createElement('div');
      div.className = 'alm-cc-item' + (c === almForgotSelectedCountry ? ' selected' : '');
      div.setAttribute('role', 'option');
      div.innerHTML = '<span class="cc-flag">' + c.f + '</span><span class="cc-name">' + c.n + '</span><span class="cc-dial">' + c.d + '</span>';
      div.addEventListener('mousedown', function(e) {
        e.preventDefault();
        almForgotSelectedCountry = c;
        document.getElementById('alm-forgot-cc-flag').textContent = c.f;
        document.getElementById('alm-forgot-cc-dial').textContent = c.d;
        document.getElementById('alm-forgot-phone').placeholder = c.d === '+966' ? '5XXXXXXXX' : 'XXXXXXXXX';
        closeAlmForgotCcDropdown();
        document.getElementById('alm-forgot-phone').dispatchEvent(new Event('input'));
      });
      list.appendChild(div);
    });
  }
  function openAlmForgotCcDropdown() {
    document.getElementById('alm-forgot-cc-dropdown').classList.add('open');
    document.getElementById('alm-forgot-cc-btn').setAttribute('aria-expanded', 'true');
    document.getElementById('alm-forgot-cc-search').value = '';
    buildAlmForgotCcList('');
    setTimeout(function() { document.getElementById('alm-forgot-cc-search').focus(); }, 50);
  }
  function closeAlmForgotCcDropdown() {
    document.getElementById('alm-forgot-cc-dropdown').classList.remove('open');
    document.getElementById('alm-forgot-cc-btn').setAttribute('aria-expanded', 'false');
  }
  document.getElementById('alm-forgot-cc-btn').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('alm-forgot-cc-dropdown').classList.contains('open') ? closeAlmForgotCcDropdown() : openAlmForgotCcDropdown();
  });
  document.getElementById('alm-forgot-cc-search').addEventListener('input', function(e) { buildAlmForgotCcList(e.target.value); });
  function getAlmForgotPhone() {
    var raw = document.getElementById('alm-forgot-phone').value.trim().replace(/[\s\-().]+/g, '');
    return almForgotSelectedCountry.d + raw.replace(/^0+/, '');
  }
  buildAlmForgotCcList('');

  /* ─── Dynamic phone check for forgot ──────────────────────── */
  (function() {
    var timer = null;
    var phoneEl = document.getElementById('alm-forgot-phone');
    var hintEl  = document.getElementById('alm-forgot-phone-hint');
    var sendBtn = document.getElementById('alm-btn-send-forgot-otp');
    function setReady(ok) {
      sendBtn.disabled = !ok;
      sendBtn.style.opacity = ok ? '' : '0.45';
      sendBtn.style.cursor  = ok ? '' : 'not-allowed';
    }
    phoneEl.addEventListener('input', function() {
      clearTimeout(timer);
      hintEl.textContent = '';
      setReady(false);
      var local = phoneEl.value.trim().replace(/\D/g, '');
      if (local.length < 7) return;
      hintEl.style.color = 'rgba(255,255,255,0.4)';
      hintEl.textContent = 'جارٍ التحقق...';
      timer = setTimeout(async function() {
        try {
          var phone = getAlmForgotPhone();
          var res  = await fetch('apis/users/check_phone.php?phone=' + encodeURIComponent(phone));
          var data = await res.json();
          if (data.exists) {
            hintEl.style.color = 'rgba(100,220,130,0.85)';
            hintEl.textContent = '✓ الرقم مرتبط بحساب';
            setReady(true);
          } else {
            hintEl.style.color = 'rgba(255,100,100,0.85)';
            hintEl.textContent = '✗ لا يوجد حساب مرتبط بهذا الرقم';
            setReady(false);
          }
        } catch { hintEl.textContent = ''; }
      }, 600);
    });
  })();

  function startForgotTimer() {
    clearInterval(forgotOtpInterval);
    var seconds = 600;
    var timerEl = document.getElementById('alm-forgot-otp-timer');
    timerEl.textContent = '10:00';
    forgotOtpInterval = setInterval(function() {
      seconds--;
      timerEl.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
      if (seconds <= 0) {
        clearInterval(forgotOtpInterval);
        timerEl.textContent = 'انتهت صلاحية الرمز';
        document.getElementById('alm-btn-verify-forgot-otp').disabled = true;
      }
    }, 1000);
  }

  var forgotOtpBoxes = Array.from(document.querySelectorAll('#alm-forgot-otp-row .alm-otp-box'));
  var almForgotPhone = '';
  forgotOtpBoxes.forEach(function(box, i) {
    box.addEventListener('input', function() {
      box.value = box.value.replace(/\D/g, '').slice(-1);
      if (box.value && i < forgotOtpBoxes.length - 1) forgotOtpBoxes[i + 1].focus();
    });
    box.addEventListener('keydown', function(e) { if (e.key === 'Backspace' && !box.value && i > 0) forgotOtpBoxes[i - 1].focus(); });
  });

  async function almSendForgotOtp() {
    var fullPhone = getAlmForgotPhone();
    if (!fullPhone || fullPhone.length < 8) { alert('يرجى إدخال رقم الهاتف'); return; }
    var btn = document.getElementById('alm-btn-send-forgot-otp');
    btn.disabled = true; btn.textContent = 'جارٍ الإرسال...';
    try {
      var res  = await fetch('apis/verify/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ phone: fullPhone }) });
      var data = await res.json();
      if (!res.ok) { alert(data.error || 'تعذّر إرسال الرمز'); return; }
      almForgotPhone = fullPhone;
      var raw = document.getElementById('alm-forgot-phone').value.trim();
      var masked = raw.slice(0, 3) + 'XX XXX X' + raw.slice(-2);
      document.getElementById('alm-forgot-otp-phone-display').textContent = masked;
      forgotOtpBoxes.forEach(function(b) { b.value = ''; });
      startForgotTimer();
      document.getElementById('alm-btn-verify-forgot-otp').disabled = false;
      document.getElementById('alm-forgot-step-1').style.display = 'none';
      document.getElementById('alm-forgot-step-2').style.display = 'block';
      forgotOtpBoxes[0].focus();
    } catch { alert('خطأ في الاتصال. يرجى المحاولة مجدداً.'); }
    finally { btn.disabled = false; btn.textContent = 'إرسال رمز تغيير كلمة السر'; }
  }
  document.getElementById('alm-btn-send-forgot-otp').addEventListener('click', almSendForgotOtp);

  document.getElementById('alm-btn-resend-forgot-otp').addEventListener('click', async function() {
    var btn = document.getElementById('alm-btn-resend-forgot-otp');
    btn.disabled = true; btn.textContent = 'جارٍ إعادة الإرسال...';
    try {
      var res  = await fetch('apis/verify/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ phone: almForgotPhone }) });
      var data = await res.json();
      if (!res.ok) { alert(data.error || 'تعذّر إعادة الإرسال'); return; }
      forgotOtpBoxes.forEach(function(b) { b.value = ''; });
      startForgotTimer();
      document.getElementById('alm-btn-verify-forgot-otp').disabled = false;
      forgotOtpBoxes[0].focus();
    } catch { alert('خطأ في الاتصال. يرجى المحاولة مجدداً.'); }
    finally { btn.disabled = false; btn.textContent = 'إعادة إرسال الرمز'; }
  });

  document.getElementById('alm-btn-verify-forgot-otp').addEventListener('click', async function() {
    var code = forgotOtpBoxes.map(function(b) { return b.value; }).join('');
    if (code.length < 4) { alert('يرجى إدخال الرمز المكوّن من 4 أرقام'); return; }
    var btn = document.getElementById('alm-btn-verify-forgot-otp');
    btn.disabled = true; btn.textContent = 'جارٍ التحقق...';
    try {
      var res  = await fetch('apis/verify/check.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ phone: almForgotPhone, code: code }) });
      var data = await res.json();
      if (!res.ok) { alert(data.error || 'الرمز غير صحيح'); btn.disabled = false; btn.textContent = 'التحقق والمتابعة'; return; }
      clearInterval(forgotOtpInterval);
      document.getElementById('alm-new-password').value = '';
      document.getElementById('alm-confirm-password').value = '';
      document.getElementById('alm-msg-password-mismatch').style.display = 'none';
      showPanel('new-password');
    } catch { alert('خطأ في الاتصال. يرجى المحاولة مجدداً.'); btn.disabled = false; btn.textContent = 'التحقق والمتابعة'; }
  });

  document.getElementById('alm-btn-save-new-password').addEventListener('click', async function() {
    var pw  = document.getElementById('alm-new-password').value;
    var cpw = document.getElementById('alm-confirm-password').value;
    var msg = document.getElementById('alm-msg-password-mismatch');
    if (pw.length < 6 || pw !== cpw) {
      msg.textContent = pw.length < 6 ? 'كلمة المرور يجب أن تكون 6 أحرف على الأقل' : 'كلمتا المرور غير متطابقتين';
      msg.style.display = 'block'; return;
    }
    msg.style.display = 'none';
    var btn = document.getElementById('alm-btn-save-new-password');
    btn.disabled = true; btn.textContent = 'جارٍ الحفظ...';
    try {
      var res  = await fetch('apis/users/reset_password.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ phone: almForgotPhone, password: pw }) });
      var data = await res.json();
      if (!res.ok) { alert(data.error || 'تعذّر تغيير كلمة المرور'); return; }
      setSession({ customer_id: data.customer_id, name: data.name || null, phone: data.phone, referral_code: data.referral_code || null, loggedAt: new Date().toISOString() });
      notifySessionChange();
      document.getElementById('alm-success-name-line').textContent = data.name ? 'أهلاً ' + data.name : 'تم تغيير كلمة المرور بنجاح';
      showPanel('success');
    } catch { alert('خطأ في الاتصال. يرجى المحاولة مجدداً.'); }
    finally { btn.disabled = false; btn.textContent = 'حفظ كلمة المرور الجديدة'; }
  });

  /* ─── Login ────────────────────────────────────────────────── */
  document.getElementById('alm-btn-login-submit').addEventListener('click', async function() {
    var phone    = getAlmLoginPhone();
    var password = document.getElementById('alm-login-password').value;
    var btn      = document.getElementById('alm-btn-login-submit');
    if (!document.getElementById('alm-login-phone').value.trim() || !password) { alert('يرجى إدخال رقم الهاتف وكلمة المرور'); return; }
    btn.disabled = true; btn.textContent = 'جارٍ التحقق...';
    try {
      var res  = await fetch('apis/users/login.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone: phone, password: password }),
      });
      var data = await res.json();
      if (!res.ok) { alert(data.error || 'رقم الهاتف أو كلمة المرور غير صحيحة'); return; }
      setSession({ customer_id: data.customer_id, name: data.name || null, phone: data.phone || phone, referral_code: data.referral_code || null, loggedAt: new Date().toISOString() });
      notifySessionChange();
      document.getElementById('alm-success-name-line').textContent = data.name ? 'أهلاً ' + data.name : '';
      if (data.profile_complete === false) { window.location.href = 'onboarding.html'; }
      else { showPanel('success'); }
    } catch { alert('خطأ في الاتصال. يرجى المحاولة مجدداً.'); }
    finally { btn.disabled = false; btn.textContent = 'دخول'; }
  });

  /* ─── Register Step 1 ──────────────────────────────────────── */
  document.getElementById('alm-btn-send-otp').addEventListener('click', async function() {
    var name     = document.getElementById('alm-reg-name').value.trim();
    var phone    = getRegPhone();
    var password = document.getElementById('alm-reg-password').value;
    var btn      = document.getElementById('alm-btn-send-otp');
    if (!phone) { alert('يرجى إدخال رقم الهاتف'); return; }
    if (!password || password.length < 6) { alert('كلمة المرور يجب أن تكون 6 أحرف على الأقل'); return; }
    btn.disabled = true; btn.textContent = 'جارٍ الإرسال...';
    try {
      var res  = await fetch('apis/verify/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ phone: phone }) });
      var data = await res.json();
      if (!res.ok) { alert(data.error || 'تعذّر إرسال الكود'); return; }
      var masked = phone.slice(0, Math.min(4, phone.length)) + 'XXXXXX' + phone.slice(-2);
      document.getElementById('alm-otp-phone-display').textContent = masked;
      document.querySelectorAll('#alm-otp-row .alm-otp-box').forEach(function(b) { b.value = ''; });
      startOtpTimer();
      document.getElementById('alm-btn-verify-otp').disabled = false;
      showPanel('register-2');
      document.querySelectorAll('#alm-otp-row .alm-otp-box')[0].focus();
    } catch { alert('خطأ في الاتصال. يرجى المحاولة مجدداً.'); }
    finally { btn.disabled = false; btn.textContent = 'إرسال كود التأكيد'; }
  });

  /* ─── OTP inputs ───────────────────────────────────────────── */
  var otpBoxes = Array.from(document.querySelectorAll('#alm-otp-row .alm-otp-box'));
  otpBoxes.forEach(function(box, i) {
    box.addEventListener('input', function() {
      box.value = box.value.replace(/\D/g, '').slice(-1);
      if (box.value && i < otpBoxes.length - 1) otpBoxes[i + 1].focus();
    });
    box.addEventListener('keydown', function(e) { if (e.key === 'Backspace' && !box.value && i > 0) otpBoxes[i - 1].focus(); });
  });
  if (otpBoxes[0]) otpBoxes[0].addEventListener('paste', function(e) {
    e.preventDefault();
    var text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
    otpBoxes.forEach(function(box, i) { box.value = text[i] || ''; });
    var last = Math.min(text.length, otpBoxes.length) - 1;
    if (last >= 0) otpBoxes[last].focus();
  });

  /* ─── OTP timer ────────────────────────────────────────────── */
  var otpInterval = null;
  function startOtpTimer() {
    clearInterval(otpInterval);
    var seconds = 600;
    var timerEl = document.getElementById('alm-otp-timer');
    timerEl.textContent = '10:00';
    otpInterval = setInterval(function() {
      seconds--;
      timerEl.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
      if (seconds <= 0) { clearInterval(otpInterval); timerEl.textContent = 'انتهت صلاحية الكود'; document.getElementById('alm-btn-verify-otp').disabled = true; }
    }, 1000);
  }

  /* ─── Resend OTP ───────────────────────────────────────────── */
  document.getElementById('alm-btn-resend-otp').addEventListener('click', async function() {
    var phone = getRegPhone();
    var btn   = document.getElementById('alm-btn-resend-otp');
    btn.disabled = true; btn.textContent = 'جارٍ إعادة الإرسال...';
    try {
      var res  = await fetch('apis/verify/', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ phone: phone }) });
      var data = await res.json();
      if (!res.ok) { alert(data.error || 'تعذّر إعادة الإرسال'); return; }
      otpBoxes.forEach(function(b) { b.value = ''; });
      startOtpTimer();
      document.getElementById('alm-btn-verify-otp').disabled = false;
      otpBoxes[0].focus();
    } catch { alert('خطأ في الاتصال. يرجى المحاولة مجدداً.'); }
    finally { btn.disabled = false; btn.textContent = 'إعادة إرسال الكود'; }
  });

  /* ─── Verify OTP + Register ────────────────────────────────── */
  document.getElementById('alm-btn-verify-otp').addEventListener('click', async function() {
    var code = otpBoxes.map(function(b) { return b.value; }).join('');
    if (code.length < 4) { alert('يرجى إدخال الكود المكوّن من 4 أرقام'); return; }
    var name     = document.getElementById('alm-reg-name').value.trim();
    var phone    = getRegPhone();
    var password = document.getElementById('alm-reg-password').value;
    var btn      = document.getElementById('alm-btn-verify-otp');
    btn.disabled = true; btn.textContent = 'جارٍ التحقق...';
    try {
      var vRes  = await fetch('apis/verify/check.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ phone: phone, code: code }) });
      var vData = await vRes.json();
      if (!vRes.ok) { alert(vData.error || 'الكود غير صحيح'); return; }
      var rRes  = await fetch('apis/users/register.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: name, phone: phone, password: password }) });
      var rData = await rRes.json();
      if (!rRes.ok) { alert(rData.error || 'تعذّر إنشاء الحساب'); return; }
      clearInterval(otpInterval);
      setSession({ customer_id: rData.customer_id, name: rData.name || name || null, phone: phone, referral_code: rData.referral_code || null, loggedAt: new Date().toISOString() });
      notifySessionChange();
      if (rData.welcome_gift) sessionStorage.setItem('show_welcome_gift', '1');
      window.location.href = 'onboarding.html';
    } catch { alert('خطأ في الاتصال. يرجى المحاولة مجدداً.'); }
    finally { btn.disabled = false; btn.textContent = 'تأكيد الكود'; }
  });

  document.getElementById('alm-btn-back-to-reg1').addEventListener('click', function() { clearInterval(otpInterval); showPanel('register-1'); });

  /* ─── Close handlers ───────────────────────────────────────── */
  document.getElementById('alm-modal-close-btn').addEventListener('click', closeModal);
  document.getElementById('alm-btn-success-close').addEventListener('click', closeModal);
  backdropEl.addEventListener('click', function(e) { if (e.target === backdropEl) closeModal(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && backdropEl.classList.contains('open')) closeModal(); });

  /* ─── Public API ───────────────────────────────────────────── */
  window.almAuth = {
    open: function(panel) {
      showPanel(panel === 'register' ? 'register-1' : 'login');
      openModal();
    },
    close: closeModal,
    isLoggedIn: function() { return !!getSession(); }
  };

  /* ─── Profile Dropdown (shared across all pages) ───────────── */
  // Only inject if index.html hasn't already defined its own #profile-dropdown
  if (!document.getElementById('profile-dropdown')) {
    var pdStyle = document.createElement('style');
    pdStyle.textContent = [
      '#alm-profile-dd{position:fixed;transform:translateY(-8px);width:230px;background:rgba(22,10,3,0.97);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.1);border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,0.55),inset 0 1px 0 rgba(255,255,255,0.07);padding:16px 16px 14px;opacity:0;pointer-events:none;transition:opacity 0.2s ease,transform 0.2s ease;z-index:600;direction:rtl;}',
      '#alm-profile-dd.open{opacity:1;pointer-events:auto;transform:translateY(0);}',
      '#alm-profile-dd::before{content:"";position:absolute;top:-6px;left:14px;transform:rotate(45deg);width:11px;height:11px;background:rgba(22,10,3,0.97);border-left:1px solid rgba(255,255,255,0.1);border-top:1px solid rgba(255,255,255,0.1);}',
      '.apd-header{display:flex;align-items:center;gap:10px;margin-bottom:12px;}',
      '.apd-avatar{width:40px;height:40px;border-radius:50%;flex-shrink:0;background:rgba(254,214,91,0.1);border:2px solid rgba(254,214,91,0.28);display:flex;align-items:center;justify-content:center;}',
      '.apd-avatar span{color:#fed65b;font-size:22px;font-variation-settings:"FILL" 1,"wght" 300,"GRAD" 0,"opsz" 24;}',
      '.apd-info{flex:1;min-width:0;}',
      '.apd-name{font-family:"Amiri",serif;font-size:0.95rem;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
      '.apd-phone{font-size:0.72rem;color:rgba(255,255,255,0.4);direction:ltr;text-align:right;margin-top:1px;}',
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
      '.apd-wallet{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:9px 10px;background:rgba(254,214,91,0.06);border-radius:8px;border:1px solid rgba(254,214,91,0.12);transition:background 0.2s,border-color 0.2s;}',
      '.apd-wallet:hover{background:rgba(254,214,91,0.1);border-color:rgba(254,214,91,0.2);}',
      '.apd-wallet-label{display:flex;align-items:center;gap:5px;font-size:0.71rem;color:rgba(255,255,255,0.4);}',
      '.apd-wallet-label .material-symbols-outlined{font-size:14px;color:rgba(254,214,91,0.6);}',
      '.apd-wallet-balance{font-family:"Manrope",sans-serif;font-size:0.82rem;font-weight:700;color:#fed65b;direction:ltr;}',
      '.apd-divider{border:none;border-top:1px solid rgba(255,255,255,0.07);margin:0 0 12px;}',
      '.apd-logout{width:100%;padding:10px 12px;background:rgba(220,50,50,0.1);border:1px solid rgba(220,50,50,0.2);border-radius:10px;display:flex;align-items:center;gap:8px;cursor:pointer;font-family:"Manrope",sans-serif;font-size:0.83rem;color:rgba(220,100,100,0.9);transition:background 0.2s,border-color 0.2s;box-sizing:border-box;}',
      '.apd-logout:hover{background:rgba(220,50,50,0.18);border-color:rgba(220,50,50,0.38);}',
      '.apd-logout .material-symbols-outlined{font-size:17px;}'
    ].join('');
    document.head.appendChild(pdStyle);

    var apdEl = document.createElement('div');
    apdEl.id = 'alm-profile-dd';
    apdEl.innerHTML =
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
            '<div class="apd-item-copy"><div class="apd-item-label">الشريحة</div><div class="apd-item-value" id="apd-segment">لم يتم تحديد الشريحة بعد</div></div>' +
          '</div>' +
        '</div>' +
        '<button class="apd-item" id="apd-btn-invite" type="button">' +
          '<div class="apd-item-main">' +
            '<span class="material-symbols-outlined">card_giftcard</span>' +
            '<div class="apd-item-copy"><div class="apd-item-label">كود الدعوة</div><div class="apd-item-value" id="apd-invite">-</div></div>' +
          '</div>' +
          '<div class="apd-item-action"><span class="material-symbols-outlined">open_in_new</span></div>' +
        '</button>' +
        '<button class="apd-item" id="apd-btn-address" type="button">' +
          '<div class="apd-item-main">' +
            '<span class="material-symbols-outlined">edit_location_alt</span>' +
            '<div class="apd-item-copy"><div class="apd-item-label">العنوان</div><div class="apd-item-value" id="apd-address">تعديل العنوان الحالي</div></div>' +
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
    document.body.appendChild(apdEl);

    var apdSegmentLabels = { 'مستهلك': 'مستهلك', 'جملة': 'جملة', 'corporate': 'جملة الجملة', 'consumer': 'مستهلك', 'wholesale': 'جملة' };

    function apdBuildAddress(s) {
      return [s.governorate, s.city, s.addressDetails || s.address].filter(Boolean).join('، ') || 'لم يتم تحديد العنوان بعد';
    }

    function apdPosition() {
      var btn = document.getElementById('btn-account');
      if (!btn) return;
      var r = btn.getBoundingClientRect();
      apdEl.style.top = (r.bottom + 14) + 'px';
      var left = r.left - apdEl.offsetWidth + r.width;
      apdEl.style.left = Math.max(8, left) + 'px';
    }

    function apdOpen() {
      var s = getSession();
      if (!s) return;
      document.getElementById('apd-name').textContent = s.name || 'مستخدم';
      document.getElementById('apd-phone').textContent = s.phone || '—';
      document.getElementById('apd-segment').textContent = apdSegmentLabels[s.segment] || s.segment || 'لم يتم تحديد الشريحة بعد';
      document.getElementById('apd-invite').textContent = s.referral_code || s.invitationCode || '…';
      document.getElementById('apd-address').textContent = apdBuildAddress(s);
      var bal = s.wallet != null ? Number(s.wallet) : 0;
      document.getElementById('apd-wallet').textContent = bal.toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ج.م';
      apdPosition();
      apdEl.classList.add('open');
    }

    function apdClose() { apdEl.classList.remove('open'); }

    // Account button handler
    var apdAccountBtn = document.getElementById('btn-account');
    if (apdAccountBtn) {
      apdAccountBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (getSession()) {
          apdEl.classList.contains('open') ? apdClose() : apdOpen();
        } else {
          showPanel('login');
          openModal();
        }
      });
      document.addEventListener('click', function(e) {
        if (!apdEl.contains(e.target) && e.target !== apdAccountBtn) apdClose();
      });
    }

    document.getElementById('apd-logout').addEventListener('click', function() {
      localStorage.removeItem('alm_session');
      apdClose();
      window.location.href = 'index.html';
    });

    document.getElementById('apd-btn-invite').addEventListener('click', function(e) {
      e.stopPropagation();
      var s = getSession();
      var code = s && (s.referral_code || s.invitationCode);
      if (code && navigator.clipboard) navigator.clipboard.writeText(code).catch(function(){});
      apdClose();
      window.location.href = 'index.html';
    });

    document.getElementById('apd-btn-address').addEventListener('click', function(e) {
      e.stopPropagation();
      apdClose();
      window.location.href = 'index.html';
    });

    document.addEventListener('alm:session-changed', function() {
      var iconBtn = document.getElementById('btn-account');
      if (!iconBtn) return;
      var icon = iconBtn.querySelector('.material-symbols-outlined');
      if (!icon) return;
      if (getSession()) {
        icon.textContent = 'account_circle';
        icon.style.fontVariationSettings = "'FILL' 1,'wght' 300,'GRAD' 0,'opsz' 24";
      } else {
        icon.textContent = 'person';
        icon.style.fontVariationSettings = "'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24";
      }
      apdClose();
    });
  }

})();
