(function () {
  // CSS-level: disable double-tap zoom on all interactive & general elements
  var style = document.createElement('style');
  style.textContent = '* { touch-action: manipulation; }';
  document.head.appendChild(style);

  // JS-level: block double-tap on elements that aren't inputs (inputs need native behavior)
  var lastTap = 0;
  document.addEventListener('touchend', function (e) {
    if (e.target.matches('input, textarea, select')) return;
    var now = Date.now();
    if (now - lastTap < 320) e.preventDefault();
    lastTap = now;
  }, { passive: false });
})();
