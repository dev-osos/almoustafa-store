# تصميم Cart Dropdown في index.html

**التاريخ:** 2026-03-24
**الملفات المستهدفة:** `index.html`, `products.html`

---

## الهدف

عند الضغط على أيقونة الحقيبة في `index.html`، ينسدل dropdown أسفلها يعرض محتويات السلة المحفوظة في `localStorage`. إذا كانت السلة فارغة يظهر رسالة مع زر للذهاب للتسوق.

---

## التغييرات المطلوبة

### 1. `products.html` — حفظ السلة تلقائياً

**المشكلة الحالية:** `localStorage.setItem('alm_cart', ...)` يُستدعى فقط عند الضغط على "إتمام الطلب" (سطر ~1014).

**الحل:** إضافة دالة `saveCart()` وإضافتها في موضعين محددين:

```js
function saveCart() {
  localStorage.setItem('alm_cart', JSON.stringify(cartItems));
}
```

**الموضع الأول — بعد سطر 996 (داخل callback الإضافة للسلة):**

```js
// قبل:
flyToCart(btn, () => {
  const ex = cartItems.find(i => i.name === name);
  if (ex) ex.qty++; else cartItems.push({ name, img, price, qty:1 });
  renderCart();
  showToast(name);
});

// بعد:
flyToCart(btn, () => {
  const ex = cartItems.find(i => i.name === name);
  if (ex) ex.qty++; else cartItems.push({ name, img, price, qty:1 });
  renderCart();
  saveCart();
  showToast(name);
});
```

**الموضع الثاني — بعد سطر 952 (داخل listener أزرار inc/dec/remove):**

```js
// قبل:
    else if (btn.dataset.action === 'remove') cartItems.splice(idx,1);
    renderCart();

// بعد:
    else if (btn.dataset.action === 'remove') cartItems.splice(idx,1);
    renderCart();
    saveCart();
```

---

### 2. `index.html` — Cart Dropdown

#### أ) HTML

أوجد الزر الحالي لأيقونة الحقيبة (يحمل `data-icon="shopping_bag"`) واستبدل الـ `<button>` الخاص به بهذا الكود كاملاً:

```html
<div class="relative" id="cart-icon-wrapper">
  <button id="btn-cart" aria-expanded="false"
    class="text-primary hover:text-secondary transition-all scale-110 relative">
    <span class="material-symbols-outlined" data-icon="shopping_bag">shopping_bag</span>
    <span id="cart-badge"
      style="display:none"
      class="absolute -top-1 -left-1 w-4 h-4 rounded-full bg-red-600
             text-white text-[10px] font-bold items-center justify-center font-body">
    </span>
  </button>
  <div id="cart-dropdown" aria-hidden="true">
    <div id="cart-dd-body"></div>
  </div>
</div>
```

**ملاحظة:** الـ badge يستخدم `style="display:none"` بدلاً من Tailwind `hidden` لتفادي تعارض JIT مع class `flex` المضاف ديناميكياً.

#### ب) CSS (إضافة في `<style>` بـ `index.html`)

```css
#cart-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  left: 0;
  width: 320px;
  background: #fdf9f0;
  border: 1px solid rgba(60,0,4,0.1);
  border-radius: 18px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15);
  z-index: 300;
  direction: rtl;
  overflow: hidden;
  opacity: 0;
  pointer-events: none;
  transform: translateY(-8px);
  transition: opacity 0.2s ease, transform 0.2s ease;
}
#cart-dropdown.open {
  opacity: 1;
  pointer-events: auto;
  transform: translateY(0);
}
.cart-dd-header {
  padding: 14px 18px 10px;
  border-bottom: 1px solid rgba(60,0,4,0.07);
  font-family: 'Amiri', serif;
  font-size: 1rem;
  font-weight: 700;
  color: #3c0004;
  display: flex;
  align-items: center;
  gap: 6px;
}
.cart-dd-list {
  max-height: 260px;
  overflow-y: auto;
  padding: 8px 16px;
}
.cart-dd-list::-webkit-scrollbar { width: 3px; }
.cart-dd-list::-webkit-scrollbar-thumb { background: rgba(60,0,4,0.15); border-radius: 3px; }
.cart-dd-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid rgba(60,0,4,0.05);
}
.cart-dd-row:last-child { border-bottom: none; }
.cart-dd-img {
  width: 44px; height: 44px; border-radius: 8px;
  object-fit: cover; flex-shrink: 0;
  border: 1px solid rgba(60,0,4,0.08);
}
.cart-dd-name { font-family: 'Amiri', serif; font-size: 0.88rem; font-weight: 700; color: #3c0004; }
.cart-dd-price { font-family: 'Manrope', sans-serif; font-size: 0.75rem; color: #735c00; font-weight: 700; }
.cart-dd-qty { font-family: 'Manrope', sans-serif; font-size: 0.72rem; color: rgba(60,0,4,0.45); margin-top: 2px; }
.cart-dd-footer-inner {
  padding: 12px 16px 14px;
  border-top: 1px solid rgba(60,0,4,0.07);
}
.cart-dd-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}
.cart-dd-total-label { font-family: 'Amiri', serif; font-size: 0.82rem; color: rgba(60,0,4,0.5); }
.cart-dd-total-value { font-family: 'Amiri', serif; font-size: 1.1rem; font-weight: 700; color: #3c0004; }
.cart-dd-checkout-btn {
  width: 100%; padding: 11px;
  background: linear-gradient(135deg, #3c0004, #5d0e12);
  color: #fff; border: none; border-radius: 10px;
  font-family: 'Amiri', serif; font-size: 0.95rem; font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(60,0,4,0.2);
  transition: transform 0.15s, box-shadow 0.15s;
}
.cart-dd-checkout-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(60,0,4,0.3); }
.cart-dd-empty {
  text-align: center;
  padding: 28px 16px 24px;
}
.cart-dd-empty-icon { font-size: 40px; display: block; margin-bottom: 8px; color: rgba(60,0,4,0.2); }
.cart-dd-empty-text { font-family: 'Amiri', serif; font-size: 0.95rem; color: rgba(60,0,4,0.4); margin-bottom: 14px; }
.cart-dd-shop-btn {
  display: inline-block;
  padding: 9px 22px;
  border: 1.5px solid rgba(60,0,4,0.2);
  border-radius: 30px;
  font-family: 'Amiri', serif;
  font-size: 0.88rem;
  font-weight: 700;
  color: #3c0004;
  text-decoration: none;
  transition: background 0.2s, border-color 0.2s;
}
.cart-dd-shop-btn:hover { background: rgba(60,0,4,0.05); border-color: rgba(60,0,4,0.4); }
```

#### ج) JavaScript

**أمان:** كل البيانات الديناميكية تُحقن عبر `textContent` و DOM methods. لصور المنتجات (`img.src`)، يُتحقق من أن الـ URL يبدأ بـ `https://` قبل التعيين لتفادي URLs مشبوهة.

**موضع السكريبت:** يُضاف داخل `<script>` الموجود في نهاية `<body>` (قبل `</script>` الختامي)، بعد بقية كود الصفحة.

```js
// ===== Cart Dropdown =====
(function () {
  function readCart() {
    try { return JSON.parse(localStorage.getItem('alm_cart')) || []; } catch { return []; }
  }

  function toArabicPrice(n) {
    if (isNaN(n)) return '—';
    return n.toLocaleString('ar-EG') + ' ج.م';
  }

  function safeSrc(url) {
    return (typeof url === 'string' && url.startsWith('https://')) ? url : '';
  }

  function buildCartRow(item) {
    const row = document.createElement('div');
    row.className = 'cart-dd-row';

    const img = document.createElement('img');
    img.className = 'cart-dd-img';
    img.src = safeSrc(item.img);
    img.alt = '';

    const info = document.createElement('div');
    info.style.cssText = 'flex:1;min-width:0';

    const name = document.createElement('div');
    name.className = 'cart-dd-name';
    name.textContent = item.name;

    const price = document.createElement('div');
    price.className = 'cart-dd-price';
    price.textContent = toArabicPrice(item.price);

    const qty = document.createElement('div');
    qty.className = 'cart-dd-qty';
    qty.textContent = '× ' + item.qty;

    info.append(name, price, qty);
    row.append(img, info);
    return row;
  }

  function renderCartDropdown() {
    const items = readCart();
    const body = document.getElementById('cart-dd-body');
    const badge = document.getElementById('cart-badge');

    const totalQty = items.reduce((s, i) => s + i.qty, 0);

    // Badge (inline style بدلاً من Tailwind class)
    if (totalQty > 0) {
      badge.textContent = totalQty > 9 ? '9+' : String(totalQty);
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }

    body.textContent = '';

    if (items.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'cart-dd-empty';

      const icon = document.createElement('span');
      icon.className = 'cart-dd-empty-icon material-symbols-outlined';
      icon.textContent = 'shopping_bag';

      const msg = document.createElement('p');
      msg.className = 'cart-dd-empty-text';
      msg.textContent = 'السلة فارغة';

      const shopBtn = document.createElement('a');
      shopBtn.className = 'cart-dd-shop-btn';
      shopBtn.href = 'products.html';
      shopBtn.textContent = 'الذهاب للتسوق';

      empty.append(icon, msg, shopBtn);
      body.appendChild(empty);
      return;
    }

    // Header
    const header = document.createElement('div');
    header.className = 'cart-dd-header';
    const headerIcon = document.createElement('span');
    headerIcon.className = 'material-symbols-outlined';
    headerIcon.style.fontSize = '18px';
    headerIcon.textContent = 'shopping_bag';
    const headerTitle = document.createElement('span');
    headerTitle.textContent = 'سلة التسوق';
    header.append(headerIcon, headerTitle);

    // List
    const list = document.createElement('div');
    list.className = 'cart-dd-list';
    items.forEach(item => list.appendChild(buildCartRow(item)));

    // Footer
    const footer = document.createElement('div');
    footer.className = 'cart-dd-footer-inner';

    const totalRow = document.createElement('div');
    totalRow.className = 'cart-dd-total';
    const totalLabel = document.createElement('span');
    totalLabel.className = 'cart-dd-total-label';
    totalLabel.textContent = 'الإجمالي';
    const totalValue = document.createElement('span');
    totalValue.className = 'cart-dd-total-value';
    const total = items.reduce((s, i) => s + i.price * i.qty, 0);
    totalValue.textContent = toArabicPrice(total);
    totalRow.append(totalLabel, totalValue);

    const checkoutBtn = document.createElement('button');
    checkoutBtn.className = 'cart-dd-checkout-btn';
    checkoutBtn.textContent = 'إتمام الطلب';
    checkoutBtn.addEventListener('click', () => { window.location.href = 'checkout.html'; });

    footer.append(totalRow, checkoutBtn);
    body.append(header, list, footer);
  }

  function closeCartDropdown() {
    cartDropdown.classList.remove('open');
    cartDropdown.setAttribute('aria-hidden', 'true');
    cartBtn.setAttribute('aria-expanded', 'false');
  }

  const cartBtn = document.getElementById('btn-cart');
  const cartDropdown = document.getElementById('cart-dropdown');
  const wrapper = document.getElementById('cart-icon-wrapper');

  cartBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const isOpen = cartDropdown.classList.contains('open');
    if (!isOpen) renderCartDropdown();
    cartDropdown.classList.toggle('open');
    cartDropdown.setAttribute('aria-hidden', String(isOpen));
    cartBtn.setAttribute('aria-expanded', String(!isOpen));
  });

  // إغلاق عند الضغط خارج الـ dropdown
  document.addEventListener('click', (e) => {
    if (!wrapper.contains(e.target)) closeCartDropdown();
  });

  // إغلاق عند Escape (مماثل للـ modals الأخرى في الصفحة)
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && cartDropdown.classList.contains('open')) closeCartDropdown();
  });

  // تحديث الـ badge عند تحميل الصفحة
  renderCartDropdown();
})();
```

---

## السلوك المتوقع

| الحالة | السلوك |
|--------|--------|
| السلة فارغة | لا badge، dropdown يعرض "السلة فارغة" + "الذهاب للتسوق" |
| السلة بها منتجات | badge بعدد الوحدات، dropdown: قائمة + إجمالي + "إتمام الطلب" |
| badge > 9 وحدة | يُعرض "9+" |
| الضغط خارج الـ dropdown | يُغلق |
| Escape | يُغلق |
| تحميل الصفحة | badge يُحدَّث فوراً من localStorage |
| URL صورة غير https:// | img.src فارغ (صورة مكسورة بدلاً من request خارجية مجهولة) |

---

## الملفات المتأثرة

| الملف | التعديل |
|-------|---------|
| `products.html` | إضافة `saveCart()` في موضعين محددين (سطر ~996 وسطر ~952) |
| `index.html` | CSS + HTML wrapper للأيقونة (يستبدل الـ button الحالي) + JavaScript في نهاية `<body>` |
