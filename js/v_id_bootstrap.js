(function () {
  'use strict';

  function hasVidCookie() {
    return /(?:^|;\s*)v_id=/.test(document.cookie || '');
  }

  if (hasVidCookie()) {
    return;
  }

  function initVisitorId() {
    fetch('apis/v_id.php', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Content-Type': 'application/json' },
      body: '{}',
      keepalive: true
    }).catch(function () {
      // Silent fail: next page load will retry.
    });
  }

  if ('requestIdleCallback' in window) {
    window.requestIdleCallback(initVisitorId, { timeout: 2000 });
  } else {
    setTimeout(initVisitorId, 0);
  }
})();
