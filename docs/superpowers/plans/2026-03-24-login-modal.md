# Login Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a dark glass login/register modal to `index.html`, triggered by the navbar person-icon button, with phone-based auth, OTP verification flow, and localStorage session management.

**Architecture:** All code is added inline to the single `index.html` file — CSS appended to the existing `<style>` block (ends line 123), modal HTML inserted before `</body>` (line 620), and JS added inside the existing `DOMContentLoaded` callback (before its closing `}` at line 618). No new files are created.

**Tech Stack:** Vanilla HTML/CSS/JS, Tailwind CSS CDN (already loaded), Material Symbols Outlined (already loaded), Amiri + Manrope fonts (already loaded), `localStorage` for session persistence.

---

### Task 1: Add `id` attributes and prepare HTML anchor points

**Files:**
- Modify: `index.html:350` (add id to person button)

- [ ] **Step 1: Add `id="btn-account"` to the person-icon button**

Find this line (line 350):
```html
<button class="text-primary hover:text-secondary transition-all scale-110"><span class="material-symbols-outlined" data-icon="person">person</span></button>
```
Replace with:
```html
<button id="btn-account" class="text-primary hover:text-secondary transition-all scale-110"><span class="material-symbols-outlined" data-icon="person">person</span></button>
```

- [ ] **Step 2: Verify in browser**

Open `index.html` in browser. Open DevTools console, run:
```js
document.getElementById('btn-account')
```
Expected: returns the button element (not null).

---

### Task 2: Add modal CSS to the existing `<style>` block

**Files:**
- Modify: `index.html:123` (append CSS before closing `</style>`)

- [ ] **Step 1: Append modal CSS inside the existing `<style>` block, just before `</style>` (line 123)**

```css
        /* ========== LOGIN MODAL ========== */
        #modal-backdrop {
            position: fixed; inset: 0;
            background: rgba(28,28,23,0.72);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 200;
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
            opacity: 0; pointer-events: none;
            transition: opacity 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        #modal-backdrop.open {
            opacity: 1; pointer-events: auto;
        }
        #login-modal {
            position: relative;
            z-index: 201;
            width: 100%; max-width: 420px;
            background: rgba(60,0,4,0.82);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 28px;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 40px 100px rgba(0,0,0,0.65),
                        inset 0 1px 0 rgba(255,255,255,0.12);
            padding: 44px 38px 40px;
            overflow: hidden;
            transform: scale(0.95);
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        #modal-backdrop.open #login-modal {
            transform: scale(1);
        }
        #login-modal::before {
            content: '';
            position: absolute; top: -70px; left: 50%;
            transform: translateX(-50%);
            width: 280px; height: 200px;
            background: radial-gradient(ellipse, rgba(254,214,91,0.22) 0%, transparent 68%);
            pointer-events: none;
        }
        #login-modal::after {
            content: '';
            position: absolute; bottom: -50px; right: -50px;
            width: 180px; height: 180px;
            background: radial-gradient(circle, rgba(254,214,91,0.08) 0%, transparent 65%);
            border-radius: 50%; pointer-events: none;
        }
        .modal-close-btn {
            position: absolute; top: 16px; left: 16px;
            width: 34px; height: 34px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.06);
            border-radius: 50%; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,0.5);
            transition: background 0.2s;
            z-index: 2;
        }
        .modal-close-btn:hover { background: rgba(255,255,255,0.14); color: #fff; }
        .modal-logo-area {
            display: flex; flex-direction: column; align-items: center;
            margin-bottom: 28px; position: relative; z-index: 1;
        }
        .modal-logo-ring {
            width: 76px; height: 76px; border-radius: 50%;
            border: 2px solid rgba(254,214,91,0.32);
            display: flex; align-items: center; justify-content: center;
            background: rgba(254,214,91,0.07);
            margin-bottom: 12px;
            box-shadow: 0 0 28px rgba(254,214,91,0.12);
        }
        .modal-logo-ring img { width: 54px; height: 54px; object-fit: contain; border-radius: 50%; }
        .modal-logo-area h2 {
            font-family: 'Amiri', serif; color: #fff;
            font-size: 1.5rem; font-weight: 700;
        }
        .modal-logo-area p { color: rgba(255,255,255,0.4); font-size: 0.78rem; margin-top: 4px; }
        .modal-gold-divider {
            width: 44px; height: 2px;
            background: linear-gradient(90deg, transparent, #fed65b, transparent);
            border-radius: 2px; margin: 9px auto 0;
        }
        .modal-tabs {
            display: flex; background: rgba(255,255,255,0.06);
            border-radius: 12px; padding: 4px; margin-bottom: 26px;
            position: relative; z-index: 1;
        }
        .modal-tab {
            flex: 1; text-align: center; padding: 9px 0;
            font-family: 'Amiri', serif; font-size: 0.88rem; font-weight: 700;
            color: rgba(255,255,255,0.35); border-radius: 9px; cursor: pointer;
            transition: all 0.2s;
        }
        .modal-tab.active {
            background: rgba(254,214,91,0.15); color: #fed65b;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }
        .modal-panel { display: none; position: relative; z-index: 1; }
        .modal-panel.active { display: block; animation: modalFadeIn 0.2s ease-out; }
        @keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal-field { margin-bottom: 15px; }
        .modal-field label {
            display: block; font-size: 0.73rem; font-weight: 600;
            color: rgba(255,255,255,0.4); margin-bottom: 7px; letter-spacing: 0.05em;
        }
        .modal-field-wrap { position: relative; }
        .modal-field input {
            width: 100%;
            padding: 13px 16px 13px 44px;
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            background: rgba(255,255,255,0.06);
            color: #fff; font-family: 'Manrope', sans-serif; font-size: 0.87rem;
            outline: none; transition: border-color 0.2s, background 0.2s;
        }
        .modal-field input:focus {
            border-color: rgba(254,214,91,0.5);
            background: rgba(255,255,255,0.09);
        }
        .modal-field input::placeholder { color: rgba(255,255,255,0.22); }
        .modal-field-icon {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.28); font-size: 19px; pointer-events: none;
        }
        .modal-btn-gold {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, #fed65b 0%, #f5c400 100%);
            color: #3c0004; border: none; border-radius: 12px;
            font-family: 'Amiri', serif; font-size: 1rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.04em; margin-top: 6px;
            box-shadow: 0 4px 20px rgba(254,214,91,0.28);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .modal-btn-gold:hover { transform: translateY(-1px); box-shadow: 0 6px 28px rgba(254,214,91,0.4); }
        .modal-btn-gold:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }
        .modal-btn-outline {
            width: 100%; padding: 13px; background: transparent;
            color: rgba(255,255,255,0.5);
            border: 1.5px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            font-family: 'Amiri', serif; font-size: 0.92rem; font-weight: 700;
            cursor: pointer; margin-top: 8px; transition: border-color 0.2s, color 0.2s;
        }
        .modal-btn-outline:hover { border-color: rgba(255,255,255,0.25); color: #fff; }
        .modal-forgot {
            text-align: center; margin-top: 14px;
            font-size: 0.77rem; color: rgba(254,214,91,0.6); cursor: pointer;
        }
        .modal-forgot:hover { color: #fed65b; }
        .modal-inline-msg {
            text-align: center; margin-top: 8px;
            font-size: 0.75rem; color: rgba(255,255,255,0.4);
            display: none;
        }
        .modal-separator { border: none; border-top: 1px solid rgba(255,255,255,0.07); margin: 16px 0; }
        .modal-footer {
            text-align: center; font-size: 0.78rem; color: rgba(255,255,255,0.3);
        }
        .modal-footer span { color: #fed65b; font-weight: 700; cursor: pointer; }
        .modal-footer span:hover { text-decoration: underline; }
        /* OTP */
        .otp-row { display: flex; gap: 8px; justify-content: center; margin-bottom: 14px; }
        .otp-box {
            width: 56px; height: 60px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 12px; text-align: center;
            font-family: 'Manrope', sans-serif;
            font-size: 1.4rem; font-weight: 700; color: #fed65b;
            outline: none; caret-color: #fed65b;
            transition: border-color 0.2s, background 0.2s;
        }
        .otp-box:focus { border-color: rgba(254,214,91,0.55); background: rgba(254,214,91,0.08); }
        .modal-otp-hint { text-align: center; font-size: 0.75rem; color: rgba(255,255,255,0.35); margin-bottom: 18px; line-height: 1.5; }
        .modal-otp-hint .hl { color: rgba(254,214,91,0.75); }
        .modal-timer { text-align: center; font-size: 0.75rem; color: rgba(255,255,255,0.35); margin-bottom: 6px; }
        .modal-timer .hl { color: #fed65b; font-weight: 700; }
        .modal-back-link { text-align: center; font-size: 0.78rem; color: rgba(255,255,255,0.3); cursor: pointer; margin-top: 6px; }
        .modal-back-link:hover { color: rgba(255,255,255,0.6); }
        /* Success */
        .modal-success-icon {
            width: 64px; height: 64px;
            background: rgba(254,214,91,0.12);
            border: 2px solid rgba(254,214,91,0.4);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .modal-success-icon span { color: #fed65b; font-size: 32px; font-variation-settings: 'FILL' 1, 'wght' 300, 'GRAD' 0, 'opsz' 24; }
        /* Mobile */
        @media (max-width: 480px) {
            #login-modal { padding: 28px 20px; }
        }
```

- [ ] **Step 2: Verify no CSS syntax errors**

Open `index.html` in browser. DevTools Console should show zero errors.

---

### Task 3: Add modal HTML before `</body>`

**Files:**
- Modify: `index.html:620` (insert HTML before `</body>`)

- [ ] **Step 1: Insert modal HTML just before `</body>` (line 620)**

```html
<!-- ========== LOGIN MODAL ========== -->
<div id="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-title">
  <div id="login-modal">

    <!-- Close button -->
    <button class="modal-close-btn" id="modal-close-btn" aria-label="إغلاق">
      <span class="material-symbols-outlined" style="font-size:17px;">close</span>
    </button>

    <!-- Logo -->
    <div class="modal-logo-area">
      <div class="modal-logo-ring">
        <img src="logo.png" alt="شعار المصطفى"/>
      </div>
      <h2 id="modal-title">المصطفى</h2>
      <p>خبراء العسل الصافي</p>
      <div class="modal-gold-divider"></div>
    </div>

    <!-- Tabs -->
    <div class="modal-tabs" id="modal-tabs">
      <div class="modal-tab active" data-tab="login">تسجيل الدخول</div>
      <div class="modal-tab" data-tab="register">حساب جديد</div>
    </div>

    <!-- ===== LOGIN PANEL ===== -->
    <div class="modal-panel active" id="panel-login">
      <div class="modal-field">
        <label for="login-phone">رقم الهاتف</label>
        <div class="modal-field-wrap">
          <span class="modal-field-icon material-symbols-outlined">phone_iphone</span>
          <input id="login-phone" type="tel" placeholder="01XXXXXXXXX" autocomplete="tel"/>
        </div>
      </div>
      <div class="modal-field">
        <label for="login-password">كلمة المرور</label>
        <div class="modal-field-wrap">
          <span class="modal-field-icon material-symbols-outlined">lock</span>
          <input id="login-password" type="password" placeholder="••••••••" autocomplete="current-password"/>
        </div>
      </div>
      <button class="modal-btn-gold" id="btn-login-submit">دخول</button>
      <div class="modal-forgot" id="btn-forgot">نسيت كلمة المرور؟</div>
      <div class="modal-inline-msg" id="msg-forgot">هذه الخاصية ستكون متاحة قريبًا</div>
      <hr class="modal-separator"/>
      <div class="modal-footer">ليس لديك حساب؟ <span id="btn-go-register">إنشاء حساب جديد</span></div>
    </div>

    <!-- ===== REGISTER STEP 1 PANEL ===== -->
    <div class="modal-panel" id="panel-register-1">
      <div class="modal-field">
        <label for="reg-name">الاسم الكامل</label>
        <div class="modal-field-wrap">
          <span class="modal-field-icon material-symbols-outlined">person</span>
          <input id="reg-name" type="text" placeholder="أدخل اسمك" autocomplete="name"/>
        </div>
      </div>
      <div class="modal-field">
        <label for="reg-phone">رقم الهاتف</label>
        <div class="modal-field-wrap">
          <span class="modal-field-icon material-symbols-outlined">phone_iphone</span>
          <input id="reg-phone" type="tel" placeholder="01XXXXXXXXX" autocomplete="tel"/>
        </div>
      </div>
      <div class="modal-field">
        <label for="reg-password">كلمة المرور</label>
        <div class="modal-field-wrap">
          <span class="modal-field-icon material-symbols-outlined">lock</span>
          <input id="reg-password" type="password" placeholder="••••••••" autocomplete="new-password"/>
        </div>
      </div>
      <button class="modal-btn-gold" id="btn-send-otp">إرسال كود التأكيد</button>
      <hr class="modal-separator"/>
      <div class="modal-footer">لديك حساب؟ <span id="btn-go-login">تسجيل الدخول</span></div>
    </div>

    <!-- ===== REGISTER STEP 2 — OTP PANEL ===== -->
    <div class="modal-panel" id="panel-register-2">
      <div class="modal-otp-hint">
        تم إرسال كود مكوّن من 4 أرقام إلى<br/>
        <span class="hl" id="otp-phone-display"></span>
      </div>
      <div class="otp-row" id="otp-row">
        <input class="otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الأول"/>
        <input class="otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الثاني"/>
        <input class="otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الثالث"/>
        <input class="otp-box" type="text" inputmode="numeric" maxlength="1" aria-label="الرقم الرابع"/>
      </div>
      <div class="modal-timer">انتهاء الكود خلال <span class="hl" id="otp-timer">1:30</span></div>
      <button class="modal-btn-gold" id="btn-verify-otp" style="margin-top:14px;">تأكيد الكود</button>
      <button class="modal-btn-outline" id="btn-resend-otp">إعادة إرسال الكود</button>
      <div class="modal-back-link" id="btn-back-to-reg1">
        <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;margin-left:4px;">arrow_forward</span>
        تعديل رقم الهاتف
      </div>
    </div>

    <!-- ===== SUCCESS PANEL ===== -->
    <div class="modal-panel" id="panel-success">
      <div style="text-align:center;">
        <div class="modal-success-icon">
          <span class="material-symbols-outlined">check_circle</span>
        </div>
        <div style="font-family:'Amiri',serif;color:#fff;font-size:1.3rem;font-weight:700;margin-bottom:8px;">
          مرحباً بك في المصطفى!
        </div>
        <div id="success-name-line" style="font-size:0.82rem;color:rgba(255,255,255,0.4);margin-bottom:24px;"></div>
      </div>
      <button class="modal-btn-gold" id="btn-success-close">متابعة التسوق</button>
    </div>

  </div>
</div>
```

- [ ] **Step 2: Verify modal renders**

Open `index.html` in browser. Open DevTools console, run:
```js
document.getElementById('login-modal')
```
Expected: returns the modal div element.

---

### Task 4: Add JavaScript inside existing `DOMContentLoaded` callback

**Files:**
- Modify: `index.html:618` (add JS before the closing `});` of `DOMContentLoaded`)

- [ ] **Step 1: Append the following JS block inside the `DOMContentLoaded` callback, just before its closing `});` (currently at line 618)**

```js
        // ========== LOGIN MODAL ==========

        // --- Session helpers ---
        function getSession() {
            try { return JSON.parse(localStorage.getItem('alm_session')); } catch { return null; }
        }
        function setSession(data) {
            localStorage.setItem('alm_session', JSON.stringify(data));
        }
        function updateAccountIcon() {
            const btn = document.getElementById('btn-account');
            if (!btn) return;
            const iconSpan = btn.querySelector('.material-symbols-outlined');
            if (!iconSpan) return;
            if (getSession()) {
                iconSpan.textContent = 'account_circle';
                iconSpan.style.fontVariationSettings = "'FILL' 1, 'wght' 300, 'GRAD' 0, 'opsz' 24";
            } else {
                iconSpan.textContent = 'person';
                iconSpan.style.fontVariationSettings = "'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24";
            }
        }

        // Apply icon state on load
        updateAccountIcon();

        // --- Modal open/close ---
        const backdrop = document.getElementById('modal-backdrop');

        function openModal() {
            backdrop.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            backdrop.classList.remove('open');
            document.body.style.overflow = '';
        }

        document.getElementById('btn-account').addEventListener('click', () => {
            if (getSession()) return; // no-op when logged in
            showPanel('login');
            openModal();
        });

        document.getElementById('modal-close-btn').addEventListener('click', closeModal);
        document.getElementById('btn-success-close').addEventListener('click', closeModal);

        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) closeModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && backdrop.classList.contains('open')) closeModal();
        });

        // --- Panel switcher ---
        function showPanel(name) {
            document.querySelectorAll('.modal-panel').forEach(p => p.classList.remove('active'));
            const panel = document.getElementById('panel-' + name);
            if (panel) panel.classList.add('active');

            // Update tabs visibility
            const tabsEl = document.getElementById('modal-tabs');
            const loginTab = tabsEl.querySelector('[data-tab="login"]');
            const registerTab = tabsEl.querySelector('[data-tab="register"]');
            const isLoginTab = name === 'login';
            const isRegisterTab = name === 'register-1' || name === 'register-2';
            loginTab.classList.toggle('active', isLoginTab);
            registerTab.classList.toggle('active', isRegisterTab);
            // Hide tabs on OTP and success screens
            tabsEl.style.display = (name === 'register-2' || name === 'success') ? 'none' : 'flex';
        }

        // Tab clicks
        document.getElementById('modal-tabs').addEventListener('click', (e) => {
            const tab = e.target.closest('[data-tab]');
            if (!tab) return;
            showPanel(tab.dataset.tab === 'login' ? 'login' : 'register-1');
        });

        document.getElementById('btn-go-register').addEventListener('click', () => showPanel('register-1'));
        document.getElementById('btn-go-login').addEventListener('click', () => showPanel('login'));

        // --- Forgot password ---
        document.getElementById('btn-forgot').addEventListener('click', () => {
            const msg = document.getElementById('msg-forgot');
            msg.style.display = 'block';
        });

        // --- Login submit ---
        document.getElementById('btn-login-submit').addEventListener('click', () => {
            const phone = document.getElementById('login-phone').value.trim();
            const password = document.getElementById('login-password').value;
            setSession({ name: null, phone: phone, loggedAt: new Date().toISOString() });
            updateAccountIcon();
            showPanel('success');
            // success-name-line stays empty for login path (name is null)
            document.getElementById('success-name-line').textContent = '';
        });

        // --- Register Step 1 — send OTP ---
        document.getElementById('btn-send-otp').addEventListener('click', () => {
            const phone = document.getElementById('reg-phone').value.trim();
            // Display masked phone in OTP panel
            const masked = phone.length >= 4
                ? phone.slice(0, 3) + 'XX XXX X' + phone.slice(-2)
                : phone;
            document.getElementById('otp-phone-display').textContent = masked;
            // Clear OTP boxes
            document.querySelectorAll('.otp-box').forEach(box => { box.value = ''; });
            // Reset timer
            startOtpTimer();
            document.getElementById('btn-verify-otp').disabled = false;
            showPanel('register-2');
            document.querySelectorAll('.otp-box')[0].focus();
        });

        // --- OTP inputs: auto-advance, backspace, paste ---
        const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));
        otpBoxes.forEach((box, i) => {
            box.addEventListener('input', () => {
                // Keep only last char and ensure it's a digit
                box.value = box.value.replace(/\D/g, '').slice(-1);
                if (box.value && i < otpBoxes.length - 1) {
                    otpBoxes[i + 1].focus();
                }
            });
            box.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !box.value && i > 0) {
                    otpBoxes[i - 1].focus();
                }
            });
        });

        // Paste handler on first box distributes digits
        otpBoxes[0].addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            otpBoxes.forEach((box, i) => {
                box.value = text[i] || '';
            });
            const lastFilled = Math.min(text.length, otpBoxes.length) - 1;
            if (lastFilled >= 0) otpBoxes[lastFilled].focus();
        });

        // --- OTP countdown timer ---
        let otpInterval = null;
        function startOtpTimer() {
            clearInterval(otpInterval);
            let seconds = 90;
            const timerEl = document.getElementById('otp-timer');
            timerEl.textContent = '1:30';
            otpInterval = setInterval(() => {
                seconds--;
                const m = Math.floor(seconds / 60);
                const s = String(seconds % 60).padStart(2, '0');
                timerEl.textContent = m + ':' + s;
                if (seconds <= 0) {
                    clearInterval(otpInterval);
                    timerEl.textContent = 'انتهت صلاحية الكود';
                    document.getElementById('btn-verify-otp').disabled = true;
                }
            }, 1000);
        }

        // --- Resend OTP ---
        document.getElementById('btn-resend-otp').addEventListener('click', () => {
            document.querySelectorAll('.otp-box').forEach(box => { box.value = ''; });
            startOtpTimer();
            document.getElementById('btn-verify-otp').disabled = false;
            otpBoxes[0].focus();
        });

        // --- Verify OTP submit ---
        document.getElementById('btn-verify-otp').addEventListener('click', () => {
            const code = otpBoxes.map(b => b.value).join('');
            if (code.length < 4) return; // must fill all boxes
            clearInterval(otpInterval);
            const name = document.getElementById('reg-name').value.trim();
            const phone = document.getElementById('reg-phone').value.trim();
            setSession({ name: name || null, phone: phone, loggedAt: new Date().toISOString() });
            updateAccountIcon();
            // Show name in success if provided
            const nameLine = document.getElementById('success-name-line');
            if (name) {
                nameLine.textContent = name;
            } else {
                nameLine.textContent = '';
            }
            showPanel('success');
        });

        // --- Back to register step 1 ---
        document.getElementById('btn-back-to-reg1').addEventListener('click', () => {
            clearInterval(otpInterval);
            showPanel('register-1');
        });
```

- [ ] **Step 2: Test login flow in browser**

1. Open `index.html`, click the person icon → modal should open with animation
2. Enter any phone + password, click "دخول" → success screen appears
3. Close modal → person icon changes to filled `account_circle`
4. Click icon again → nothing happens (no-op)
5. Refresh page → icon should still be filled (localStorage persists)

- [ ] **Step 3: Test register flow in browser**

1. Reload page; open DevTools > Application > LocalStorage → delete `alm_session`
2. Click person icon → modal opens on login tab
3. Switch to "حساب جديد" tab
4. Fill name, phone, password → click "إرسال كود التأكيد"
5. OTP screen appears with masked phone, timer starts counting down
6. Type any 4 digits in the boxes → auto-advance works
7. Click "تأكيد الكود" → success screen with name shown
8. Close → icon filled

- [ ] **Step 4: Test OTP edge cases**

1. Let timer expire (90s) → confirm button becomes disabled
2. Click "إعادة إرسال الكود" → timer resets, confirm re-enabled, boxes cleared
3. Click "تعديل رقم الهاتف" → returns to register step 1 with fields still filled
4. Paste `1234` into first OTP box → digits distribute across all 4 boxes
5. In first OTP box, press Backspace → nothing (already first box)
6. In second box, press Backspace while empty → focus moves to first box

- [ ] **Step 5: Test modal dismiss**

1. Open modal → click backdrop area outside modal → closes
2. Open modal → press ESC → closes
3. Open modal → click ✕ button → closes

---

### Task 5: Clean up mockup files

**Files:**
- Delete: `login-mockup.html`, `login-mockup-final.html`, `login-mockup-v2.html`

- [ ] **Step 1: Delete mockup files**

```bash
rm /Users/osama/Desktop/store/login-mockup.html \
   /Users/osama/Desktop/store/login-mockup-final.html \
   /Users/osama/Desktop/store/login-mockup-v2.html
```

- [ ] **Step 2: Final visual check**

Open `index.html`. Verify:
- Page loads normally with skeleton animation
- Person icon shows correctly in navbar
- Clicking icon opens glass modal with logo, tabs, and gold button
- All text is correct Arabic RTL
- No console errors
