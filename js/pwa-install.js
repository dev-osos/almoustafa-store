/**
 * PWA Install Prompt — متجر المصطفى
 * - Desktop: triggers native beforeinstallprompt
 * - Mobile/Tablet (iOS & Android): opens a how-to modal with browser-specific steps
 * - يظهر بعد 10 ثوانٍ · لا يظهر مجدداً لمدة يوم إذا رُفض · لا يظهر أبداً بعد التثبيت
 */
(function () {
  'use strict';

  const STORAGE_KEY   = 'almoustafa_pwa_prompt';
  const DISMISS_DAYS  = 1;
  const SHOW_DELAY_MS = 10000;

  // ── device / browser detection ────────────────────────────
  const ua        = navigator.userAgent || '';
  const isIOS     = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const isAndroid = /android/i.test(ua);
  const isMobile  = isIOS || isAndroid || /mobile|tablet/i.test(ua);
  const isSamsungBrowser = /SamsungBrowser/i.test(ua);
  const isFirefoxAndroid = isAndroid && /firefox/i.test(ua);
  const isChrome  = /chrome|crios/i.test(ua) && !/edg|opr\//i.test(ua);
  const isSafari  = /safari/i.test(ua) && !isChrome;
  const isEdge    = /edg\//i.test(ua);

  // ── helpers ───────────────────────────────────────────────
  function shouldShow() {
    if (window.matchMedia('(display-mode: standalone)').matches) return false;
    if (window.navigator.standalone === true) return false;
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return true;
    try {
      const { action, ts } = JSON.parse(raw);
      if (action === 'installed') return false;
      if (action === 'dismissed') return (Date.now() - ts) / 86400000 >= DISMISS_DAYS;
    } catch (_) {}
    return true;
  }

  function saveDismissed() { localStorage.setItem(STORAGE_KEY, JSON.stringify({ action: 'dismissed', ts: Date.now() })); }
  function saveInstalled()  { localStorage.setItem(STORAGE_KEY, JSON.stringify({ action: 'installed',  ts: Date.now() })); }

  // ── build install steps for each platform ─────────────────
  function getInstallSteps() {
    if (isIOS) {
      if (!isSafari) {
        return {
          title: 'ثبّت التطبيق على جهازك',
          note: 'افتح هذه الصفحة في متصفح Safari أولاً',
          steps: [
            { icon: '🧭', text: 'افتح <strong>Safari</strong> وانتقل إلى هذا الموقع' },
            { icon: '⬆️', text: 'اضغط على أيقونة <strong>المشاركة</strong> (المربع والسهم) في شريط التنقل' },
            { icon: '➕', text: 'اختر <strong>إضافة إلى الشاشة الرئيسية</strong>' },
            { icon: '✅', text: 'اضغط <strong>إضافة</strong> للتأكيد' },
          ]
        };
      }
      return {
        title: 'ثبّت التطبيق على جهازك',
        steps: [
          { icon: '⬆️', text: 'اضغط على أيقونة <strong>المشاركة</strong> (المربع والسهم) أسفل الشاشة' },
          { icon: '🔽', text: 'مرّر القائمة لأسفل واختر <strong>إضافة إلى الشاشة الرئيسية</strong>' },
          { icon: '✅', text: 'اضغط <strong>إضافة</strong> في الزاوية العلوية اليمنى' },
        ]
      };
    }

    if (isAndroid) {
      if (isSamsungBrowser) {
        return {
          title: 'ثبّت التطبيق على جهازك',
          steps: [
            { icon: '⋮', text: 'اضغط على <strong>القائمة</strong> (ثلاث نقاط) في أسفل المتصفح' },
            { icon: '➕', text: 'اختر <strong>إضافة صفحة إلى</strong> ثم <strong>الشاشة الرئيسية</strong>' },
            { icon: '✅', text: 'اضغط <strong>إضافة</strong> للتأكيد' },
          ]
        };
      }
      if (isFirefoxAndroid) {
        return {
          title: 'ثبّت التطبيق على جهازك',
          steps: [
            { icon: '⋮', text: 'اضغط على <strong>القائمة</strong> (ثلاث نقاط) أعلى المتصفح' },
            { icon: '➕', text: 'اختر <strong>تثبيت</strong> أو <strong>إضافة إلى الشاشة الرئيسية</strong>' },
            { icon: '✅', text: 'اضغط <strong>إضافة</strong> للتأكيد' },
          ]
        };
      }
      if (isEdge) {
        return {
          title: 'ثبّت التطبيق على جهازك',
          steps: [
            { icon: '⋮', text: 'اضغط على <strong>القائمة</strong> (ثلاث نقاط) أسفل الشاشة' },
            { icon: '📱', text: 'اختر <strong>إضافة إلى الهاتف</strong>' },
            { icon: '✅', text: 'اضغط <strong>تثبيت</strong> للتأكيد' },
          ]
        };
      }
      // Default Chrome/Android
      return {
        title: 'ثبّت التطبيق على جهازك',
        steps: [
          { icon: '⋮', text: 'اضغط على <strong>القائمة</strong> (ثلاث نقاط) في أعلى المتصفح' },
          { icon: '📲', text: 'اختر <strong>إضافة إلى الشاشة الرئيسية</strong> أو <strong>تثبيت التطبيق</strong>' },
          { icon: '✅', text: 'اضغط <strong>تثبيت</strong> أو <strong>إضافة</strong> للتأكيد' },
        ]
      };
    }

    return null; // desktop — use native prompt
  }

  // ── inject styles ─────────────────────────────────────────
  function injectStyles() {
    const style = document.createElement('style');
    style.textContent = `
      /* ── Banner ── */
      #pwa-install-banner {
        position: fixed;
        bottom: 0; left: 0; right: 0;
        z-index: 9999;
        display: flex;
        justify-content: center;
        padding: 0 0 env(safe-area-inset-bottom, 0);
        pointer-events: none;
        transform: translateY(110%);
        transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
      }
      #pwa-install-banner.show {
        transform: translateY(0);
        pointer-events: auto;
      }
      .pwa-card {
        width: 100%;
        max-width: 480px;
        margin: 0 12px 16px;
        background: linear-gradient(145deg, #2a0003 0%, #3c0004 60%, #4a0e10 100%);
        border: 1px solid rgba(254,214,91,0.2);
        border-radius: 24px;
        box-shadow: 0 -2px 0 rgba(254,214,91,0.15) inset, 0 24px 60px rgba(0,0,0,0.5), 0 8px 24px rgba(60,0,4,0.4);
        overflow: hidden;
        direction: rtl;
        position: relative;
      }
      .pwa-card::before {
        content: '';
        position: absolute;
        top: 0; left: 15%; right: 15%;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(254,214,91,0.6), rgba(254,214,91,0.9), rgba(254,214,91,0.6), transparent);
      }
      .pwa-card::after {
        content: '';
        position: absolute;
        top: -80px; left: 50%;
        transform: translateX(-50%);
        width: 300px; height: 160px;
        background: radial-gradient(ellipse, rgba(254,214,91,0.07) 0%, transparent 70%);
        pointer-events: none;
      }
      .pwa-inner {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px 18px 18px 14px;
        position: relative;
        z-index: 1;
      }
      .pwa-icon-wrap {
        width: 54px; height: 54px;
        border-radius: 14px;
        background: rgba(254,214,91,0.1);
        border: 1px solid rgba(254,214,91,0.25);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
      }
      .pwa-icon-wrap img {
        width: 42px; height: 42px;
        object-fit: contain;
        filter: drop-shadow(0 2px 6px rgba(0,0,0,0.4));
      }
      .pwa-text { flex: 1; min-width: 0; }
      .pwa-title {
        font-family: 'Amiri', serif;
        font-size: 16px; font-weight: 700;
        color: #fdf9f0; line-height: 1.3; margin-bottom: 3px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      }
      .pwa-sub {
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        color: rgba(253,249,240,0.5);
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
      }
      .pwa-actions { display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; }
      .pwa-btn-install {
        display: inline-flex; align-items: center; gap: 6px;
        background: linear-gradient(135deg, #fed65b, #e9c349);
        color: #3c0004;
        border: none; border-radius: 30px;
        padding: 9px 18px;
        font-family: 'Amiri', serif; font-size: 14px; font-weight: 700;
        cursor: pointer; white-space: nowrap;
        box-shadow: 0 4px 16px rgba(254,214,91,0.35), 0 1px 0 rgba(255,255,255,0.3) inset;
        transition: transform 0.15s, box-shadow 0.15s;
      }
      .pwa-btn-install:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(254,214,91,0.45); }
      .pwa-btn-install:active { transform: scale(0.96); }
      .pwa-btn-install svg { width: 14px; height: 14px; fill: #3c0004; }
      .pwa-btn-dismiss {
        background: none; border: none;
        color: rgba(253,249,240,0.35);
        font-family: 'Manrope', sans-serif; font-size: 11px;
        cursor: pointer; text-align: center; padding: 2px 6px;
        transition: color 0.2s; line-height: 1;
      }
      .pwa-btn-dismiss:hover { color: rgba(253,249,240,0.6); }
      .pwa-hex-deco {
        position: absolute; left: 14px; top: 50%;
        transform: translateY(-50%);
        display: flex; flex-direction: column; gap: 4px;
        opacity: 0.06; pointer-events: none;
      }
      .pwa-hex-row { display: flex; gap: 4px; }
      .pwa-hex {
        width: 8px; height: 9px;
        background: #fed65b;
        clip-path: polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);
      }

      /* ── Modal ── */
      #pwa-modal-overlay {
        position: fixed; inset: 0;
        z-index: 10000;
        background: rgba(0,0,0,0.65);
        backdrop-filter: blur(4px);
        display: flex; align-items: flex-end; justify-content: center;
        opacity: 0; pointer-events: none;
        transition: opacity 0.3s ease;
        padding: 0 0 env(safe-area-inset-bottom, 0);
      }
      #pwa-modal-overlay.show { opacity: 1; pointer-events: auto; }
      .pwa-modal {
        width: 100%; max-width: 500px;
        margin: 0 0 0;
        background: linear-gradient(170deg, #2a0003 0%, #3c0004 100%);
        border: 1px solid rgba(254,214,91,0.2);
        border-radius: 28px 28px 0 0;
        direction: rtl;
        overflow: hidden;
        position: relative;
        transform: translateY(40px);
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
      }
      #pwa-modal-overlay.show .pwa-modal { transform: translateY(0); }
      .pwa-modal::before {
        content: '';
        position: absolute;
        top: 0; left: 20%; right: 20%;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(254,214,91,0.7), rgba(254,214,91,1), rgba(254,214,91,0.7), transparent);
      }
      .pwa-modal-handle {
        width: 40px; height: 4px;
        background: rgba(254,214,91,0.3);
        border-radius: 2px;
        margin: 14px auto 0;
      }
      .pwa-modal-header {
        display: flex; align-items: center; gap: 12px;
        padding: 16px 20px 12px;
        border-bottom: 1px solid rgba(254,214,91,0.1);
        position: relative; z-index: 1;
      }
      .pwa-modal-icon-wrap {
        width: 48px; height: 48px;
        border-radius: 12px;
        background: rgba(254,214,91,0.1);
        border: 1px solid rgba(254,214,91,0.25);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; overflow: hidden;
      }
      .pwa-modal-icon-wrap img { width: 36px; height: 36px; object-fit: contain; }
      .pwa-modal-title {
        font-family: 'Amiri', serif;
        font-size: 18px; font-weight: 700;
        color: #fdf9f0; line-height: 1.3;
        flex: 1;
      }
      .pwa-modal-note {
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        color: rgba(253,249,240,0.5);
        margin-top: 2px;
      }
      .pwa-modal-close {
        background: rgba(253,249,240,0.08);
        border: none; border-radius: 50%;
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        color: rgba(253,249,240,0.5);
        flex-shrink: 0;
        transition: background 0.2s, color 0.2s;
      }
      .pwa-modal-close:hover { background: rgba(253,249,240,0.15); color: #fdf9f0; }
      .pwa-modal-close svg { width: 16px; height: 16px; fill: currentColor; }
      .pwa-modal-body { padding: 16px 20px 24px; position: relative; z-index: 1; }
      .pwa-modal-steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
      .pwa-modal-step {
        display: flex; align-items: flex-start; gap: 14px;
      }
      .pwa-step-num {
        width: 32px; height: 32px;
        background: rgba(254,214,91,0.12);
        border: 1px solid rgba(254,214,91,0.3);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        font-family: 'Manrope', sans-serif;
        font-size: 11px; font-weight: 700;
        color: #fed65b;
      }
      .pwa-step-emoji {
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; flex-shrink: 0;
      }
      .pwa-step-text {
        font-family: 'Manrope', sans-serif;
        font-size: 13.5px;
        color: rgba(253,249,240,0.85);
        line-height: 1.6;
        padding-top: 5px;
      }
      .pwa-step-text strong { color: #fdf9f0; }
      .pwa-modal-divider {
        height: 1px;
        background: rgba(254,214,91,0.08);
        margin: 16px 0 0;
      }
    `;
    document.head.appendChild(style);
  }

  // ── build banner ──────────────────────────────────────────
  function buildBanner() {
    const banner = document.createElement('div');
    banner.id = 'pwa-install-banner';
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', 'تثبيت التطبيق');
    banner.innerHTML = `
      <div class="pwa-card">
        <div class="pwa-hex-deco">
          <div class="pwa-hex-row"><div class="pwa-hex"></div><div class="pwa-hex"></div></div>
          <div class="pwa-hex-row" style="margin-right:6px"><div class="pwa-hex"></div><div class="pwa-hex"></div></div>
          <div class="pwa-hex-row"><div class="pwa-hex"></div><div class="pwa-hex"></div></div>
        </div>
        <div class="pwa-inner">
          <div class="pwa-icon-wrap">
            <img src="logo.png" alt="شعار المصطفى" loading="lazy"/>
          </div>
          <div class="pwa-text">
            <div class="pwa-title">ثبّت تطبيق المصطفى</div>
            <div class="pwa-sub">وصول فوري · يضاف الي الواجهة · بدون متجر تطبيقات</div>
          </div>
          <div class="pwa-actions">
            <button class="pwa-btn-install" id="pwa-btn-install">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 9h-4V3H9v6H5l7 7 7-7zm-8 2V5h2v6h1.17L12 13.17 9.83 11H11zm-6 7h14v2H5v-2z"/></svg>
              تثبيت
            </button>
            <button class="pwa-btn-dismiss" id="pwa-btn-dismiss">لاحقاً</button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(banner);
    return banner;
  }

  // ── build how-to modal (mobile) ───────────────────────────
  function buildModal(stepsData) {
    const overlay = document.createElement('div');
    overlay.id = 'pwa-modal-overlay';

    const stepsHTML = stepsData.steps.map((s) => `
      <li class="pwa-modal-step">
        <div class="pwa-step-emoji">${s.icon}</div>
        <div class="pwa-step-text">${s.text}</div>
      </li>
    `).join('');

    overlay.innerHTML = `
      <div class="pwa-modal" role="dialog" aria-modal="true" aria-label="خطوات تثبيت التطبيق">
        <div class="pwa-modal-handle"></div>
        <div class="pwa-modal-header">
          <div class="pwa-modal-icon-wrap">
            <img src="logo.png" alt="شعار المصطفى" loading="lazy"/>
          </div>
          <div style="flex:1">
            <div class="pwa-modal-title">${stepsData.title}</div>
            ${stepsData.note ? `<div class="pwa-modal-note">${stepsData.note}</div>` : ''}
          </div>
          <button class="pwa-modal-close" id="pwa-modal-close" aria-label="إغلاق">
            <svg viewBox="0 0 24 24"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
          </button>
        </div>
        <div class="pwa-modal-body">
          <ul class="pwa-modal-steps">${stepsHTML}</ul>
          <div class="pwa-modal-divider"></div>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    // Close on backdrop click
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeModal();
    });
    document.getElementById('pwa-modal-close').addEventListener('click', closeModal);

    return overlay;
  }

  function closeModal() {
    const overlay = document.getElementById('pwa-modal-overlay');
    if (overlay) {
      overlay.classList.remove('show');
      setTimeout(() => overlay.remove(), 350);
    }
    saveDismissed();
    hideBanner();
  }

  // ── main ──────────────────────────────────────────────────
  let deferredPrompt = null;

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
  });

  window.addEventListener('appinstalled', () => {
    saveInstalled();
    hideBanner();
  });

  function hideBanner() {
    const b = document.getElementById('pwa-install-banner');
    if (b) {
      b.classList.remove('show');
      setTimeout(() => b.remove(), 600);
    }
  }

  function init() {
    if (!shouldShow()) return;

    injectStyles();
    const banner = buildBanner();

    const timer = setTimeout(() => {
      if (window.matchMedia('(display-mode: standalone)').matches) return;
      banner.classList.add('show');
    }, SHOW_DELAY_MS);

    document.getElementById('pwa-btn-install').addEventListener('click', async () => {
      if (!isMobile && deferredPrompt) {
        // ── Desktop: native install ──
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        deferredPrompt = null;
        if (outcome === 'accepted') {
          saveInstalled();
        } else {
          saveDismissed();
        }
        hideBanner();
      } else if (isMobile && deferredPrompt) {
        // ── Android Chrome with prompt support ──
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        deferredPrompt = null;
        if (outcome === 'accepted') {
          saveInstalled();
        } else {
          saveDismissed();
        }
        hideBanner();
      } else {
        // ── Mobile without native prompt (iOS, Firefox, etc.) — show how-to modal ──
        const stepsData = getInstallSteps();
        if (stepsData) {
          hideBanner();
          clearTimeout(timer);
          const modal = buildModal(stepsData);
          requestAnimationFrame(() => {
            requestAnimationFrame(() => modal.classList.add('show'));
          });
        } else {
          saveDismissed();
          hideBanner();
        }
      }
    });

    document.getElementById('pwa-btn-dismiss').addEventListener('click', () => {
      saveDismissed();
      hideBanner();
      clearTimeout(timer);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
