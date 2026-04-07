(function () {
  'use strict';

  var STORAGE_KEY = 'almoustafa_cookie_consent';

  if (localStorage.getItem(STORAGE_KEY) === 'accepted') return;

  var banner = document.createElement('div');
  banner.id = 'cookie-consent-banner';
  banner.setAttribute('role', 'dialog');
  banner.setAttribute('aria-modal', 'false');
  banner.setAttribute('aria-label', 'إشعار الخصوصية وملفات تعريف الارتباط');
  banner.innerHTML = [
    '<div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px;">',
      '<span style="font-size:22px;line-height:1;flex-shrink:0;margin-top:2px;">🍪</span>',
      '<div>',
        '<p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#3c0004;font-family:\'Amiri\',serif;">خصوصيتك تهمنا</p>',
        '<p style="margin:0;font-size:13px;line-height:1.6;color:#564241;font-family:\'Manrope\',sans-serif;">',
          'نستخدم ملفات تعريف الارتباط (Cookies) وبياناتك لتحسين تجربتك وتذكّر تفضيلاتك. ',
          'باستمرارك في استخدام الموقع فأنت توافق على ذلك.',
        '</p>',
      '</div>',
    '</div>',
    '<div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">',
      '<button id="cookie-accept-btn" style="',
        'background:#3c0004;color:#fff;border:none;border-radius:6px;',
        'padding:9px 22px;font-size:13px;font-family:\'Manrope\',sans-serif;',
        'font-weight:700;cursor:pointer;transition:background 0.2s;',
      '">أوافق</button>',
      '<button id="cookie-decline-btn" style="',
        'background:transparent;color:#897270;border:1.5px solid #dcc0be;border-radius:6px;',
        'padding:8px 18px;font-size:13px;font-family:\'Manrope\',sans-serif;',
        'font-weight:600;cursor:pointer;transition:all 0.2s;',
      '">لاحقاً</button>',
    '</div>',
  ].join('');

  Object.assign(banner.style, {
    position: 'fixed',
    bottom: '20px',
    right: '20px',
    left: '20px',
    maxWidth: '480px',
    marginLeft: 'auto',
    background: '#fdf9f0',
    border: '1.5px solid #dcc0be',
    borderRadius: '14px',
    padding: '18px 20px',
    boxShadow: '0 8px 32px rgba(60,0,4,0.13)',
    zIndex: '9999',
    direction: 'rtl',
    opacity: '0',
    transform: 'translateY(16px)',
    transition: 'opacity 0.4s ease, transform 0.4s ease',
  });

  document.body.appendChild(banner);

  requestAnimationFrame(function () {
    requestAnimationFrame(function () {
      banner.style.opacity = '1';
      banner.style.transform = 'translateY(0)';
    });
  });

  function dismiss() {
    banner.style.opacity = '0';
    banner.style.transform = 'translateY(16px)';
    setTimeout(function () {
      if (banner.parentNode) banner.parentNode.removeChild(banner);
    }, 400);
  }

  document.getElementById('cookie-accept-btn').addEventListener('click', function () {
    localStorage.setItem(STORAGE_KEY, 'accepted');
    dismiss();
  });

  document.getElementById('cookie-decline-btn').addEventListener('click', function () {
    dismiss();
  });

  var acceptBtn = document.getElementById('cookie-accept-btn');
  acceptBtn.addEventListener('mouseenter', function () { this.style.background = '#5d0e12'; });
  acceptBtn.addEventListener('mouseleave', function () { this.style.background = '#3c0004'; });
})();
