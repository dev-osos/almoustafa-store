# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Multi-page Arabic (RTL) luxury honey store for "المصطفى" (Al Moustafa). Static HTML frontend with a PHP API backend and MySQL database.

## Running Locally

```bash
php -S localhost:8000
```

No build step, no dependencies to install. Requires a MySQL database if testing the visitor-tracking API.

## Deployment

Pushing to `main` or `master` triggers a GitHub Actions workflow (`.github/workflows/deploy.yml`) that deploys via FTP to `ftp.almoustafa.site` into `/store/`. `push.sh` automates commit + pull + push in one step.

## Architecture

### Frontend (static HTML)
- **Pages**: `index.html`, `products.html`, `collections.html`, `checkout.html`, `reviews.html`, `contact.html`, `onboarding.html`, `powerd-by.html`
- **Styling**: Tailwind CSS via CDN with a custom Material Design 3 color theme (primary `#3c0004`, secondary `#735c00`) defined inline in each page's `<script id="tailwind-config">`
- **Fonts**: Amiri (headlines), Manrope (body/labels) via Google Fonts; Material Symbols Outlined + Bootstrap Icons
- **Layout**: RTL (`dir="rtl"`), Arabic text throughout
- **Service Worker**: `sw.js` — cache-first for static assets, network-first for navigation, falls back to `index.html`

### Backend (PHP API)
All API files live in `apis/` and share a layered bootstrap pattern:

| File | Role |
|------|------|
| `bootstrap.php` | Loads `.env` from project root; exposes `api_env()`, `api_env_bool()` |
| `db.php` | Singleton PDO connection via `api_pdo()`; reads config from env vars |
| `response.php` | CORS headers, JSON helpers (`api_ok()`, `api_error()`), method guard |
| `v_id.php` | Visitor identification: reads/issues `v_id` cookie, persists to DB |

Each endpoint starts with `api_response_init()` then `api_require_method([...])`.
never use "SELECT *" use optemized queries

### Database
Single table `visitors` (schema in `sql/schema.sql`): tracks unique visitors by `v_id` (format `v_[32 hex chars]`) with IP, user agent, hit count.

### Visitor Tracking Flow
1. `js/v_id_bootstrap.js` checks for `v_id` cookie; if missing, POSTs to `apis/v_id.php` at idle time
2. `v_id.php` generates or refreshes the visitor record and sets the cookie

## Environment Variables

Create a `.env` file at project root (excluded from deployments):

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=store
DB_USER=root
DB_PASS=

# CORS — disabled by default; set to an origin or * to enable
API_CORS_ALLOW_ORIGIN=

# Visitor ID cookie
V_ID_COOKIE_NAME=v_id
V_ID_COOKIE_DAYS=365
V_ID_COOKIE_SAMESITE=Lax   # Lax | Strict | None
V_ID_COOKIE_HTTPONLY=false
```

## Color Theme

Material Design 3 tokens used as Tailwind classes (e.g. `bg-primary`, `text-on-surface`, `bg-surface-container`). Primary: deep red `#3c0004`. Secondary: gold `#735c00`. All tokens are extended in `tailwind.config` inside each HTML page.
