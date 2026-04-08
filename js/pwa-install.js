/**
 * PWA Install Prompt — متجر المصطفى
 * - يظهر بعد 20 ثانية من التصفح
 * - لا يظهر مجدداً لمدة 7 أيام إذا رفضه المستخدم
 * - لا يظهر أبداً إذا ثبّته المستخدم
 */
(function () {
  'use strict';

  const STORAGE_KEY = 'almoustafa_pwa_prompt';
  const DISMISS_DAYS = 1;
  const SHOW_DELAY_MS = 10000; // 10 seconds

  // ── helpers ──────────────────────────────────────────────
  function shouldShow() {
    // Already installed as standalone
    if (window.matchMedia('(display-mode: standalone)').matches) return false;
    if (window.navigator.standalone === true) return false;

    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return true;

    try {
      const { action, ts } = JSON.parse(raw);
      if (action === 'installed') return false;
      if (action === 'dismissed') {
        const days = (Date.now() - ts) / 86400000;
        return days >= DISMISS_DAYS;
      }
    } catch (_) {}
    return true;
  }

  function saveDismissed() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ action: 'dismissed', ts: Date.now() }));
  }

  function saveInstalled() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ action: 'installed', ts: Date.now() }));
  }

  // ── inject styles ────────────────────────────────────────
  function injectStyles() {
    const style = document.createElement('style');
    style.textContent = `
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
        box-shadow:
          0 -2px 0 rgba(254,214,91,0.15) inset,
          0 24px 60px rgba(0,0,0,0.5),
          0 8px 24px rgba(60,0,4,0.4);
        overflow: hidden;
        direction: rtl;
        position: relative;
      }
      /* Gold shimmer top border */
      .pwa-card::before {
        content: '';
        position: absolute;
        top: 0; left: 15%; right: 15%;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(254,214,91,0.6), rgba(254,214,91,0.9), rgba(254,214,91,0.6), transparent);
      }
      /* Ambient glow */
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
      .pwa-text {
        flex: 1;
        min-width: 0;
      }
      .pwa-title {
        font-family: 'Amiri', serif;
        font-size: 16px;
        font-weight: 700;
        color: #fdf9f0;
        line-height: 1.3;
        margin-bottom: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
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
      .pwa-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex-shrink: 0;
      }
      .pwa-btn-install {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, #fed65b, #e9c349);
        color: #3c0004;
        border: none;
        border-radius: 30px;
        padding: 9px 18px;
        font-family: 'Amiri', serif;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        box-shadow: 0 4px 16px rgba(254,214,91,0.35), 0 1px 0 rgba(255,255,255,0.3) inset;
        transition: transform 0.15s, box-shadow 0.15s;
      }
      .pwa-btn-install:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 24px rgba(254,214,91,0.45);
      }
      .pwa-btn-install:active { transform: scale(0.96); }
      .pwa-btn-install svg {
        width: 14px; height: 14px;
        fill: #3c0004;
      }
      .pwa-btn-dismiss {
        background: none;
        border: none;
        color: rgba(253,249,240,0.35);
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        cursor: pointer;
        text-align: center;
        padding: 2px 6px;
        transition: color 0.2s;
        line-height: 1;
      }
      .pwa-btn-dismiss:hover { color: rgba(253,249,240,0.6); }
      /* Honeycomb decorative dots */
      .pwa-hex-deco {
        position: absolute;
        left: 14px; top: 50%;
        transform: translateY(-50%);
        display: flex; flex-direction: column; gap: 4px;
        opacity: 0.06;
        pointer-events: none;
      }
      .pwa-hex-row { display: flex; gap: 4px; }
      .pwa-hex {
        width: 8px; height: 9px;
        background: #fed65b;
        clip-path: polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);
      }
    `;
    document.head.appendChild(style);
  }

  // ── build DOM ────────────────────────────────────────────
  function buildBanner() {
    const banner = document.createElement('div');
    banner.id = 'pwa-install-banner';
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', 'تثبيت التطبيق');
    banner.innerHTML = `
      <div class="pwa-card">
        <!-- decorative hexagons -->
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
            <div class="pwa-sub">وصول فوري · يعمل بدون إنترنت · بدون متجر تطبيقات</div>
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

  // ── main logic ───────────────────────────────────────────
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

    // Show after delay
    const timer = setTimeout(() => {
      // Final check — maybe user installed in the meantime
      if (window.matchMedia('(display-mode: standalone)').matches) return;
      banner.classList.add('show');
    }, SHOW_DELAY_MS);

    // Install button
    document.getElementById('pwa-btn-install').addEventListener('click', async () => {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        deferredPrompt = null;
        if (outcome === 'accepted') {
          saveInstalled();
        } else {
          saveDismissed();
        }
      } else {
        // Fallback for browsers that don't support beforeinstallprompt (e.g. iOS Safari)
        saveDismissed();
      }
      hideBanner();
    });

    // Dismiss button
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
