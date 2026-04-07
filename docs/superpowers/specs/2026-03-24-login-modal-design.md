# Login Modal — Design Spec
**Date:** 2026-03-24
**Project:** المصطفى — Arabic luxury honey store
**File scope:** `index.html` (actual file on disk; CLAUDE.md references `index.php` but the project has migrated to `.html`)

---

## Overview

Add a login/register modal triggered by the person icon button in the navbar. The modal appears only when no active session exists (tracked via `localStorage`). It follows the existing site's MD3 color theme, RTL Arabic layout, and luxury dark-glass aesthetic.

---

## Prerequisites (explicit steps before JS wiring)

1. Add `id="btn-account"` to the existing person-icon `<button>` at line 350 of `index.html`.
2. Add `id="login-modal"` to the modal root `<div>` appended to `<body>`.

---

## Visual Design

- **Style:** Dark glass — `rgba(60,0,4,0.82)` background + `backdrop-filter: blur(24px)` (spec is authoritative; mockup blur value of 20px is illustrative only)
- **Border:** `1px solid rgba(255,255,255,0.1)` + `inset 0 1px 0 rgba(255,255,255,0.12)` box-shadow
- **Border-radius:** `28px`
- **Ambient glow:** Gold radial gradient top-center `rgba(254,214,91,0.22)`
- **z-index:** Backdrop `z-[200]`, modal panel `z-[201]` — sits above navbar (`z-50`) and skeleton overlay (`z-[100]`)
- **Logo:** `src="logo.png"` (same relative path as navbar) in a `76×76px` circular ring with `2px solid rgba(254,214,91,0.32)` border, centered at top
- **Store name:** "المصطفى" — Amiri serif, white
- **Gold divider line** below subtitle
- **Fonts:** Amiri (headings/buttons), Manrope (inputs/labels) — both already loaded
- **Color values:** primary `#3c0004`; gold accent `#fed65b` — this is the `secondary-container` token in the existing Tailwind config (not `secondary` which is `#735c00`)
- **Mobile (< 480px):** modal spans full width with `margin: 0 16px`, padding reduced to `28px 20px`

---

## Structure & States

### Tab 1 — Login
- Phone number field — no country code prefix (Egypt-only site)
- Password field
- **Validation:** no client-side validation; "دخول" button is always enabled (simulated auth — any input accepted)
- "دخول" gold button → writes `alm_session`, closes modal, updates icon
- "نسيت كلمة المرور؟" link → renders inline message `"هذه الخاصية ستكون متاحة قريبًا"` below the link; no redirect
- Footer link → switches to Register tab

### Tab 2 — Register, Step 1: Enter details
- Full name field
- Phone number field — no country code prefix
- Password field
- **Validation:** no client-side validation; button always enabled (simulated auth)
- "إرسال كود التأكيد" gold button → transitions to Step 2; previously entered field values are **retained** in case user navigates back

### Tab 2 — Register, Step 2: Verify OTP
- Masked phone number display (e.g., `05XX XXX X34`)
- 4 individual `<input>` boxes — `maxlength="1"`, `inputmode="numeric"`, `type="text"`
  - **Auto-advance:** valid digit → focus moves to next box
  - **Backspace:** if current box is empty → focus moves to previous box
  - **Paste:** pasting a 4-digit string distributes digits across all 4 boxes automatically
- Countdown timer — starts at `1:30`, counts down every second
  - On expiry: label changes to `"انتهت صلاحية الكود"` and "تأكيد الكود" button is **disabled**
- "تأكيد الكود" gold button — disabled when timer expired; accepts any 4 digits (no real SMS)
- "إعادة إرسال الكود" outline button — **always enabled**; resets timer to `1:30`, re-enables confirm button, clears OTP boxes
- "تعديل رقم الهاتف" link → returns to Step 1 with previously entered fields **retained**

### Success State
- Gold checkmark icon
- Heading: "مرحباً بك في المصطفى!"
- If `alm_session.name !== null` (Register path): render name via `.textContent` in a sub-line
- If `alm_session.name === null` (Login path): sub-line omitted
- "متابعة التسوق" gold button → closes modal

---

## Session Management

- **Storage key:** `alm_session` in `localStorage`
- **Value shape:** `{ name: string | null, phone: string, loggedAt: ISO8601 }`
  - `name` is `null` for the Login path (name not collected at login)
- **On page load:** if `alm_session` exists → change icon glyph from `person` to `account_circle` with `font-variation-settings: 'FILL' 1` (i.e., filled icon); clicking `#btn-account` is a **no-op** when session exists
- **On success:** write `alm_session`, update icon to filled `account_circle`, close modal
- **No TTL** — session persists indefinitely; logout is out of scope for this feature
- **XSS rule:** every value read from `alm_session` and rendered into the DOM must use `.textContent` — never `innerHTML`

---

## Trigger & Backdrop

- `#btn-account` click + no active session → open modal
- `#btn-account` click + session exists → no-op
- Click on backdrop (outside modal panel) → close
- Click `✕` button → close
- `Escape` key → close

---

## Animations

- **Open:** `opacity 0→1` + `scale(0.95)→scale(1)`, `0.3s cubic-bezier(0.4,0,0.2,1)`
- **Close:** reverse, `0.2s ease-in`
- **Tab switch:** content fades `0→1` over `0.2s`
- **OTP boxes:** border highlight on focus; `scale(1.05)` on fill

---

## Implementation Notes

- Modal HTML appended just before `</body>` in `index.html`
- Modal CSS appended to the existing `<style>` block
- Modal JS (event listeners + state machine) appended **inside** the existing `DOMContentLoaded` callback in the existing `<script>` block — do not add a new `DOMContentLoaded` wrapper
- All changes inline — no new files created
