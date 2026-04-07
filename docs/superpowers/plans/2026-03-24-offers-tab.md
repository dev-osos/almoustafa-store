# Offers Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** إضافة تبويب "العروض" في شريط الفلاتر بـ `products.html` يُظهر المنتجات ذات السعر المخفّض.

**Architecture:** تعديل ملف HTML واحد فقط — إضافة CSS للتبويب الجديد، تمييز 6 منتجات بـ `data-discount="true"` مع تحديث عرض أسعارها، وتعديل `applyFilters()` لدعم فلتر العروض.

**Tech Stack:** HTML, Tailwind CSS (CDN), Vanilla JavaScript — ملف واحد بدون build step.

---

## File Map

| الملف | التعديل |
|-------|---------|
| `products.html` | CSS (سطر 53) + HTML تبويب (سطر 292) + 6 بطاقات منتجات + JS (سطرا 783 و816) |

---

## Task 1: إضافة CSS لتبويب العروض

**Files:**
- Modify: `products.html:53`

- [ ] **Step 1: أضف CSS بعد سطر 53 مباشرةً** (بعد `.cat-tab.active{...}`)

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

- [ ] **Step 2: تحقق بصرياً في المتصفح**

افتح `products.html` في المتصفح — لا يوجد بعد تبويب العروض، لكن تأكد أن الصفحة لا تزال تعمل بدون أخطاء في الـ console.

---

## Task 2: إضافة زر تبويب العروض في HTML

**Files:**
- Modify: `products.html:292`

- [ ] **Step 1: أضف زر التبويب بعد سطر 292** (بعد زر "المكسرات")

```html
      <button class="cat-tab offers-tab" data-cat="offers"><i class="bi bi-tag-fill"></i> العروض</button>
```

الموقع الدقيق — بعد هذا السطر:
```html
      <button class="cat-tab" data-cat="nuts"><i class="bi bi-triangle-fill text-secondary" style="font-size:10px"></i>المكسرات</button>
```

- [ ] **Step 2: تحقق بصرياً**

في المتصفح: يظهر تبويب "العروض" باللون الأحمر الخفيف. عند hover يتغير اللون. عند الضغط عليه يصبح active بخلفية حمراء خفيفة. لا تختفي المنتجات بعد لأن منطق الفلترة لم يُحدَّث بعد.

---

## Task 3: تمييز عسل الزهور البرية بخصم 20%

**Files:**
- Modify: `products.html:352-373`

- [ ] **Step 1: أضف `data-discount="true"` وحدّث عرض السعر والشارة**

ابحث عن البطاقة التي تبدأ بـ:
```html
      <div class="product-card" data-cat="honey" data-name="عسل مزيج الزهور البرية">
```

**غيّر السطر الأول إلى:**
```html
      <div class="product-card" data-cat="honey" data-name="عسل مزيج الزهور البرية" data-discount="true">
```

**غيّر الشارة من:**
```html
          <span class="product-badge badge-new">جديد</span>
```
**إلى:**
```html
          <span class="product-badge badge-sale">خصم ٢٠٪</span>
```

**غيّر عرض السعر من:**
```html
            <span class="font-headline text-xl text-primary font-bold">١٤٠ ج.م</span>
```
**إلى:**
```html
            <div class="flex flex-col gap-0.5">
              <span class="font-headline text-xl font-bold" style="color:#c0392b">١١٢ ج.م</span>
              <span class="font-headline text-sm text-on-surface-variant line-through">١٤٠ ج.م</span>
            </div>
```

**تحذير:** الـ span الخاص بالسعر المشطوب يجب أن يحمل `text-sm` وليس `text-xl` — الكود يستخدم `.font-headline.text-xl` لقراءة السعر عند الإضافة للسلة.

---

## Task 4: تمييز عسل المانوكا (موجود بالفعل كـ badge-sale)

**Files:**
- Modify: `products.html:402`

- [ ] **Step 1: أضف `data-discount="true"` فقط — لا تغيّر الأسعار**

ابحث عن:
```html
      <div class="product-card" data-cat="honey" data-name="عسل المانوكا">
```
**غيّر إلى:**
```html
      <div class="product-card" data-cat="honey" data-name="عسل المانوكا" data-discount="true">
```

الشارة والأسعار (٣٢٠/٤٠٠ ج.م) موجودة بالفعل بشكل صحيح — لا تعدّل عليها.

---

## Task 5: تمييز غذاء ملكات النحل بخصم 20%

**Files:**
- Modify: `products.html:466-487`

- [ ] **Step 1: أضف `data-discount="true"` وحدّث عرض السعر والشارة**

ابحث عن:
```html
      <div class="product-card" data-cat="derivatives" data-name="غذاء ملكات النحل">
```

**غيّر السطر الأول إلى:**
```html
      <div class="product-card" data-cat="derivatives" data-name="غذاء ملكات النحل" data-discount="true">
```

**غيّر الشارة من:**
```html
          <span class="product-badge badge-best">مميز</span>
```
**إلى:**
```html
          <span class="product-badge badge-sale">خصم ٢٠٪</span>
```

**غيّر عرض السعر من:**
```html
            <span class="font-headline text-xl text-primary font-bold">٢٢٠ ج.م</span>
```
**إلى:**
```html
            <div class="flex flex-col gap-0.5">
              <span class="font-headline text-xl font-bold" style="color:#c0392b">١٧٦ ج.م</span>
              <span class="font-headline text-sm text-on-surface-variant line-through">٢٢٠ ج.م</span>
            </div>
```

---

## Task 6: تمييز كريم العسل المرطب بخصم 25%

**Files:**
- Modify: `products.html:526-547`

- [ ] **Step 1: أضف `data-discount="true"` وحدّث عرض السعر والشارة**

ابحث عن:
```html
      <div class="product-card" data-cat="beauty" data-name="كريم العسل المرطب">
```

**غيّر السطر الأول إلى:**
```html
      <div class="product-card" data-cat="beauty" data-name="كريم العسل المرطب" data-discount="true">
```

**غيّر الشارة من:**
```html
          <span class="product-badge badge-new">جديد</span>
```
**إلى:**
```html
          <span class="product-badge badge-sale">خصم ٢٥٪</span>
```

**غيّر عرض السعر من:**
```html
            <span class="font-headline text-xl text-primary font-bold">١٨٠ ج.م</span>
```
**إلى:**
```html
            <div class="flex flex-col gap-0.5">
              <span class="font-headline text-xl font-bold" style="color:#c0392b">١٣٥ ج.م</span>
              <span class="font-headline text-sm text-on-surface-variant line-through">١٨٠ ج.م</span>
            </div>
```

---

## Task 7: تمييز تمر سكري مع عسل بخصم 20%

**Files:**
- Modify: `products.html:610-631`

- [ ] **Step 1: أضف `data-discount="true"` وحدّث عرض السعر والشارة**

ابحث عن:
```html
      <div class="product-card" data-cat="dates" data-name="تمر سكري مع عسل">
```

**غيّر السطر الأول إلى:**
```html
      <div class="product-card" data-cat="dates" data-name="تمر سكري مع عسل" data-discount="true">
```

**غيّر الشارة من:**
```html
          <span class="product-badge badge-new">جديد</span>
```
**إلى:**
```html
          <span class="product-badge badge-sale">خصم ٢٠٪</span>
```

**غيّر عرض السعر من:**
```html
            <span class="font-headline text-xl text-primary font-bold">١٣٠ ج.م</span>
```
**إلى:**
```html
            <div class="flex flex-col gap-0.5">
              <span class="font-headline text-xl font-bold" style="color:#c0392b">١٠٤ ج.م</span>
              <span class="font-headline text-sm text-on-surface-variant line-through">١٣٠ ج.م</span>
            </div>
```

---

## Task 8: تمييز مكسرات بالعسل بخصم 20%

**Files:**
- Modify: `products.html:648-669`

- [ ] **Step 1: أضف `data-discount="true"` وحدّث عرض السعر والشارة**

ابحث عن:
```html
      <div class="product-card" data-cat="nuts" data-name="مكسرات بالعسل">
```

**غيّر السطر الأول إلى:**
```html
      <div class="product-card" data-cat="nuts" data-name="مكسرات بالعسل" data-discount="true">
```

**غيّر الشارة من:**
```html
          <span class="product-badge badge-best">الأكثر مبيعاً</span>
```
**إلى:**
```html
          <span class="product-badge badge-sale">خصم ٢٠٪</span>
```

**غيّر عرض السعر من:**
```html
            <span class="font-headline text-xl text-primary font-bold">١٩٥ ج.م</span>
```
**إلى:**
```html
            <div class="flex flex-col gap-0.5">
              <span class="font-headline text-xl font-bold" style="color:#c0392b">١٥٦ ج.م</span>
              <span class="font-headline text-sm text-on-surface-variant line-through">١٩٥ ج.م</span>
            </div>
```

---

## Task 9: تحديث منطق الفلترة وسلوك التمرير في JavaScript

**Files:**
- Modify: `products.html:783`
- Modify: `products.html:816-822`

- [ ] **Step 1: تحديث `applyFilters()` — سطر 783**

ابحث عن:
```js
      const catMatch = activeCat === 'all' || card.dataset.cat === activeCat;
```

**غيّر إلى:**
```js
      const catMatch = activeCat === 'all'
        || (activeCat === 'offers'
            ? card.dataset.discount === 'true'
            : card.dataset.cat === activeCat);
```

- [ ] **Step 2: تحديث سلوك التمرير عند الضغط على تبويب العروض — سطرا 817-821**

ابحث عن:
```js
    if (cat !== 'all') {
      const sec = document.getElementById(cat);
      if (sec) setTimeout(() => sec.scrollIntoView({ behavior: 'smooth' }), 80);
    } else {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
```

**غيّر إلى:**
```js
    if (cat === 'all' || cat === 'offers') {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
      const sec = document.getElementById(cat);
      if (sec) setTimeout(() => sec.scrollIntoView({ behavior: 'smooth' }), 80);
    }
```

- [ ] **Step 3: تحقق بصرياً في المتصفح**

1. افتح `products.html`
2. اضغط تبويب "العروض" — يجب أن تظهر 6 منتجات فقط (من 5 أقسام مختلفة)
3. الصفحة تتمرر للأعلى عند الضغط
4. عداد النتائج يُظهر "٦ منتج"
5. الأقسام الفارغة (شمع العسل، حبوب اللقاح، إلخ) تختفي
6. البحث بالنص يعمل مع فلتر العروض معاً
7. اضغط "الكل" — تعود جميع المنتجات
8. أضف منتج مخفّض للسلة — تحقق أن السعر المحفوظ هو السعر الجديد (الأحمر) وليس المشطوب

---

## ملاحظات التنفيذ

- لا يوجد build step — افتح الملف مباشرة في المتصفح
- الـ console في المتصفح (F12) يجب أن يكون خالياً من الأخطاء
- أرقام الأسطر المذكورة تقريبية — استخدم البحث عن النص الدقيق
