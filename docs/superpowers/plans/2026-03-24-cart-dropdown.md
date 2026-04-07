# Cart Dropdown Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** إضافة dropdown للسلة أسفل أيقونة الحقيبة في `index.html`، يقرأ من `localStorage` المُحدَّث تلقائياً من `products.html`.

**Architecture:** تعديل ملفين — `products.html` يحفظ السلة في localStorage عند كل تغيير، و`index.html` يضيف CSS + HTML wrapper + JavaScript للـ dropdown. لا يوجد build step، الصفحة تُفتح مباشرة في المتصفح.

**Tech Stack:** HTML, Tailwind CSS (CDN), Vanilla JavaScript, localStorage.

---

## File Map

| الملف | التعديل |
|-------|---------|
| `products.html` | إضافة دالة `saveCart()` + استدعاؤها في موضعين |
| `index.html` | CSS في `<style>` + HTML wrapper لأيقونة الحقيبة + `<script>` جديد قبل `</body>` |

---

## Task 1: إضافة `saveCart()` في `products.html`

**Files:**
- Modify: `products.html:922` (قرب `renderCart`)
- Modify: `products.html:952` (inc/dec/remove handler)
- Modify: `products.html:995` (add-to-cart callback)

- [ ] **Step 1: أضف تعريف `saveCart()` بعد سطر 922 (بعد بداية `renderCart`)**

ابحث عن:
```js
  // --- Render cart ---
  function renderCart() {
```

أضف `saveCart()` قبله مباشرة:
```js
  // --- Persist cart ---
  function saveCart() {
    localStorage.setItem('alm_cart', JSON.stringify(cartItems));
  }

  // --- Render cart ---
  function renderCart() {
```

- [ ] **Step 2: أضف `saveCart()` في handler أزرار inc/dec/remove**

ابحث عن (سطر ~951-952):
```js
    else if (btn.dataset.action === 'remove') cartItems.splice(idx,1);
    renderCart();
  });
```

غيّر إلى:
```js
    else if (btn.dataset.action === 'remove') cartItems.splice(idx,1);
    renderCart();
    saveCart();
  });
```

- [ ] **Step 3: أضف `saveCart()` في add-to-cart callback**

ابحث عن (سطر ~994-997):
```js
        if (ex) ex.qty++; else cartItems.push({ name, img, price, qty:1 });
        renderCart();
        showToast(name);
```

غيّر إلى:
```js
        if (ex) ex.qty++; else cartItems.push({ name, img, price, qty:1 });
        renderCart();
        saveCart();
        showToast(name);
```

- [ ] **Step 4: تحقق بصرياً**

افتح `products.html` في المتصفح، أضف أي منتج للسلة، ثم افتح الـ DevTools → Application → Local Storage → تحقق أن مفتاح `alm_cart` يظهر ويحتوي على JSON صحيح لبيانات المنتج.

---

## Task 2: إضافة CSS للـ Dropdown في `index.html`

**Files:**
- Modify: `index.html` — داخل `<style>` الموجود (قبل `</style>`)

- [ ] **Step 1: أضف CSS الـ dropdown قبل `</style>`**

ابحث عن `</style>` الأول في الـ `<head>` (سطر ~580) وأضف قبله:

```css
        /* ===== Cart Dropdown ===== */
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
        .cart-dd-empty { text-align: center; padding: 28px 16px 24px; }
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

---

## Task 3: تحديث HTML أيقونة الحقيبة في `index.html`

**Files:**
- Modify: `index.html:810`

- [ ] **Step 1: استبدل زر الحقيبة الحالي بـ wrapper يحتوي الـ dropdown**

ابحث عن (سطر 810):
```html
<button class="text-primary hover:text-secondary transition-all scale-110"><span class="material-symbols-outlined" data-icon="shopping_bag">shopping_bag</span></button>
```

استبدله بـ:
```html
<div class="relative" id="cart-icon-wrapper">
  <button id="btn-cart" aria-expanded="false" class="text-primary hover:text-secondary transition-all scale-110 relative">
    <span class="material-symbols-outlined" data-icon="shopping_bag">shopping_bag</span>
    <span id="cart-badge" style="display:none"
      class="absolute -top-1 -left-1 w-4 h-4 rounded-full bg-red-600 text-white text-[10px] font-bold items-center justify-center font-body">
    </span>
  </button>
  <div id="cart-dropdown" aria-hidden="true">
    <div id="cart-dd-body"></div>
  </div>
</div>
```

- [ ] **Step 2: تحقق بصرياً**

افتح `index.html` في المتصفح — أيقونة الحقيبة تظهر كما كانت، لا تغيير مرئي بعد. تحقق أن الـ console لا يحتوي أخطاء.

---

## Task 4: إضافة JavaScript للـ Dropdown في `index.html`

**Files:**
- Modify: `index.html:1762` (قبل `</body>`)

- [ ] **Step 1: أضف `<script>` جديد قبل `</body>` مباشرة**

ابحث عن السطر الأخير:
```html
</body>
```

أضف قبله:
```html
<script>
// ===== Cart Dropdown =====
(function () {
  function readCart() {
    try { return JSON.parse(localStorage.getItem('alm_cart')) || []; } catch { return []; }
  }

  function toArabicPrice(n) {
    if (isNaN(n)) return '\u2014';
    return n.toLocaleString('ar-EG') + ' \u062c.\u0645';
  }

  function safeSrc(url) {
    return (typeof url === 'string' && url.startsWith('https://')) ? url : '';
  }

  function buildCartRow(item) {
    var row = document.createElement('div');
    row.className = 'cart-dd-row';
    var img = document.createElement('img');
    img.className = 'cart-dd-img';
    img.src = safeSrc(item.img);
    img.alt = '';
    var info = document.createElement('div');
    info.style.cssText = 'flex:1;min-width:0';
    var name = document.createElement('div');
    name.className = 'cart-dd-name';
    name.textContent = item.name;
    var price = document.createElement('div');
    price.className = 'cart-dd-price';
    price.textContent = toArabicPrice(item.price);
    var qty = document.createElement('div');
    qty.className = 'cart-dd-qty';
    qty.textContent = '\u00d7 ' + item.qty;
    info.append(name, price, qty);
    row.append(img, info);
    return row;
  }

  function renderCartDropdown() {
    var items = readCart();
    var body = document.getElementById('cart-dd-body');
    var badge = document.getElementById('cart-badge');
    var totalQty = items.reduce(function(s, i) { return s + i.qty; }, 0);
    badge.textContent = totalQty > 9 ? '9+' : String(totalQty);
    badge.style.display = totalQty > 0 ? 'flex' : 'none';
    body.textContent = '';
    if (items.length === 0) {
      var empty = document.createElement('div');
      empty.className = 'cart-dd-empty';
      var icon = document.createElement('span');
      icon.className = 'cart-dd-empty-icon material-symbols-outlined';
      icon.textContent = 'shopping_bag';
      var msg = document.createElement('p');
      msg.className = 'cart-dd-empty-text';
      msg.textContent = '\u0627\u0644\u0633\u0644\u0629 \u0641\u0627\u0631\u063a\u0629';
      var shopBtn = document.createElement('a');
      shopBtn.className = 'cart-dd-shop-btn';
      shopBtn.href = 'products.html';
      shopBtn.textContent = '\u0627\u0644\u0630\u0647\u0627\u0628 \u0644\u0644\u062a\u0633\u0648\u0642';
      empty.append(icon, msg, shopBtn);
      body.appendChild(empty);
      return;
    }
    var header = document.createElement('div');
    header.className = 'cart-dd-header';
    var headerIcon = document.createElement('span');
    headerIcon.className = 'material-symbols-outlined';
    headerIcon.style.fontSize = '18px';
    headerIcon.textContent = 'shopping_bag';
    var headerTitle = document.createElement('span');
    headerTitle.textContent = '\u0633\u0644\u0629 \u0627\u0644\u062a\u0633\u0648\u0642';
    header.append(headerIcon, headerTitle);
    var list = document.createElement('div');
    list.className = 'cart-dd-list';
    items.forEach(function(item) { list.appendChild(buildCartRow(item)); });
    var footer = document.createElement('div');
    footer.className = 'cart-dd-footer-inner';
    var totalRow = document.createElement('div');
    totalRow.className = 'cart-dd-total';
    var totalLabel = document.createElement('span');
    totalLabel.className = 'cart-dd-total-label';
    totalLabel.textContent = '\u0627\u0644\u0625\u062c\u0645\u0627\u0644\u064a';
    var totalValue = document.createElement('span');
    totalValue.className = 'cart-dd-total-value';
    var total = items.reduce(function(s, i) { return s + i.price * i.qty; }, 0);
    totalValue.textContent = toArabicPrice(total);
    totalRow.append(totalLabel, totalValue);
    var checkoutBtn = document.createElement('button');
    checkoutBtn.className = 'cart-dd-checkout-btn';
    checkoutBtn.textContent = '\u0625\u062a\u0645\u0627\u0645 \u0627\u0644\u0637\u0644\u0628';
    checkoutBtn.addEventListener('click', function() { window.location.href = 'checkout.html'; });
    footer.append(totalRow, checkoutBtn);
    body.append(header, list, footer);
  }

  function closeCartDropdown() {
    cartDropdown.classList.remove('open');
    cartDropdown.setAttribute('aria-hidden', 'true');
    cartBtn.setAttribute('aria-expanded', 'false');
  }

  var cartBtn = document.getElementById('btn-cart');
  var cartDropdown = document.getElementById('cart-dropdown');
  var wrapper = document.getElementById('cart-icon-wrapper');

  cartBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    var isOpen = cartDropdown.classList.contains('open');
    if (!isOpen) renderCartDropdown();
    cartDropdown.classList.toggle('open');
    cartDropdown.setAttribute('aria-hidden', String(isOpen));
    cartBtn.setAttribute('aria-expanded', String(!isOpen));
  });

  document.addEventListener('click', function(e) {
    if (!wrapper.contains(e.target)) closeCartDropdown();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && cartDropdown.classList.contains('open')) closeCartDropdown();
  });

  renderCartDropdown();
})();
</script>
```

- [ ] **Step 2: تحقق بصرياً — السلة فارغة**

افتح `index.html` في المتصفح:
1. أيقونة الحقيبة لا تحمل badge (السلة فارغة أو لا توجد في localStorage)
2. اضغط على الأيقونة → ينسدل dropdown بأيقونة حقيبة + "السلة فارغة" + زر "الذهاب للتسوق"
3. اضغط على "الذهاب للتسوق" → تنتقل لـ `products.html`
4. اضغط Escape → يُغلق الـ dropdown
5. اضغط خارج الـ dropdown → يُغلق

- [ ] **Step 3: تحقق بصرياً — السلة بها منتجات**

1. افتح `products.html`، أضف 2-3 منتجات للسلة
2. تحقق في DevTools → Local Storage أن `alm_cart` يحتوي على المنتجات
3. ارجع لـ `index.html`
4. يظهر badge على الأيقونة بعدد الوحدات
5. اضغط الأيقونة → يظهر الـ dropdown بقائمة المنتجات + الإجمالي + زر "إتمام الطلب"
6. اضغط "إتمام الطلب" → تنتقل لـ `checkout.html`
