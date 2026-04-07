# تصميم قسم العروض — تبويب الفلاتر في products.html

**التاريخ:** 2026-03-24
**الملف المستهدف:** `products.html`

---

## الهدف

إضافة تبويب "العروض" في شريط فلاتر الفئات في `products.html`، يُصفّي المنتجات التي عليها سعر مخفّض.

---

## المكونات

### 1. زر التبويب

إضافة زر جديد في `#cat-tabs` بجوار "المكسرات":

```html
<button class="cat-tab offers-tab" data-cat="offers">
  <i class="bi bi-tag-fill"></i> العروض
</button>
```

**ملاحظة:** لا تُضف `text-red-600` على أيقونة التبويب مباشرة — لون الأيقونة يتحكم فيه CSS المذكور أدناه.

---

### 2. CSS — إدراج بعد سطر 53 مباشرة (بعد `.cat-tab.active`)

```css
.cat-tab.offers-tab { color: rgba(192,57,43,0.6); }
.cat-tab.offers-tab:hover { color: #c0392b; background: rgba(192,57,43,0.05); }
.cat-tab.offers-tab.active {
  color: #c0392b;
  background: rgba(192,57,43,0.1);
  border-color: rgba(192,57,43,0.3);
}
.cat-tab.offers-tab.active i { color: #c0392b; }
```

---

### 3. المنتجات المخفّضة

إضافة `data-discount="true"` على الـ `product-card` للمنتجات التالية (6 منتجات من فئات مختلفة):

| المنتج | data-cat | السعر الأصلي (من HTML) | السعر بعد الخصم | نسبة الخصم |
|--------|----------|----------------------|----------------|------------|
| عسل مزيج الزهور البرية | honey | ١٤٠ ج.م | ١١٢ ج.م | ٢٠٪ |
| عسل المانوكا | honey | ٤٠٠ ج.م | ٣٢٠ ج.م | ٢٠٪ (موجود في HTML) |
| غذاء ملكات النحل | derivatives | ٢٢٠ ج.م | ١٧٦ ج.م | ٢٠٪ |
| كريم العسل المرطب | beauty | ١٨٠ ج.م | ١٣٥ ج.م | ٢٥٪ |
| تمر سكري مع عسل | dates | ١٣٠ ج.م | ١٠٤ ج.م | ٢٠٪ |
| مكسرات بالعسل | nuts | ١٩٥ ج.م | ١٥٦ ج.م | ٢٠٪ |

**ملاحظة:** عسل المانوكا يحمل `badge-sale: خصم ٢٠٪` مسبقًا مع أسعار (٤٠٠ ج.م → ٣٢٠ ج.م) في الـ HTML. لا تُغيّر أسعاره ولا شارته — فقط أضف `data-discount="true"` له.

**تحديث عرض السعر في الـ HTML للمنتجات المخفّضة:**

```html
<div class="flex flex-col gap-0.5">
  <span class="font-headline text-xl font-bold" style="color:#c0392b">١١٢ ج.م</span>
  <span class="font-headline text-sm text-on-surface-variant line-through">١٤٠ ج.م</span>
</div>
```

**تحذير مهم للتنفيذ:** الدالة `parsePrice()` في السكريبت تستخدم المحدد `.font-headline.text-xl` لقراءة سعر المنتج عند الإضافة للسلة. يجب التأكد من أن الـ span المشطوب (السعر القديم) **لا يحمل** كلاس `text-xl` — يُستخدم `text-sm` فقط.

**إضافة/تحديث شارة الخصم على الصورة:**

```html
<span class="product-badge badge-sale">خصم ٢٠٪</span>
```

لا تُزل الشارة الموجودة إن كانت `badge-sale` على عسل المانوكا، فقط حدّث نصّها إن لزم.

---

### 4. منطق الفلترة

تعديل `applyFilters()` في السكريبت (سطر 783):

```js
// قبل
const catMatch = activeCat === 'all' || card.dataset.cat === activeCat;

// بعد
const catMatch = activeCat === 'all'
  || (activeCat === 'offers'
      ? card.dataset.discount === 'true'
      : card.dataset.cat === activeCat);
```

---

### 5. سلوك التمرير (Scroll) عند تفعيل تبويب العروض

تعديل معالج النقر على التبويبات (سطر 816-822):

```js
// قبل
const cat = tab.dataset.cat;
if (cat !== 'all') {
  const sec = document.getElementById(cat);
  if (sec) setTimeout(() => sec.scrollIntoView({ behavior: 'smooth' }), 80);
} else {
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// بعد
const cat = tab.dataset.cat;
if (cat === 'all' || cat === 'offers') {
  window.scrollTo({ top: 0, behavior: 'smooth' });
} else {
  const sec = document.getElementById(cat);
  if (sec) setTimeout(() => sec.scrollIntoView({ behavior: 'smooth' }), 80);
}
```

**السبب:** تبويب العروض لا يملك قسمًا مخصصًا بـ id="offers"، لذا يتصرف مثل تبويب "الكل" في التمرير للأعلى.

---

## السلوك المتوقع

- عند الضغط على "العروض": تظهر فقط المنتجات التي `data-discount="true"` من أي فئة، والصفحة تتمرر للأعلى
- الأقسام (honey, derivatives...) التي لا تحتوي على منتجات مخفّضة تختفي تلقائيًا — هذا سلوك مقصود
- عناوين الأقسام الظاهرة (مثل "العسل") داخل عرض العروض أمر مقصود ومقبول
- البحث بالنص يعمل بالتوازي مع فلتر العروض
- عداد النتائج يتحدث تلقائيًا
- الرابط المباشر `#offers` غير مدعوم (قرار مقصود — لا توجد متطلبات لحملات تسويقية حالياً)

---

## الملفات المتأثرة

- `products.html` فقط — لا تغييرات على ملفات أخرى
