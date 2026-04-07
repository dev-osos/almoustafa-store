# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Single-page Arabic (RTL) luxury honey store landing page for "المصطفى" (Al Moustafa). The entire site lives in one file: `index.php`.

## Running Locally

Serve with any PHP or static server, e.g.:
```
php -S localhost:8000
```
No build step, no dependencies to install.

## Architecture

- **Single file**: `index.php` — contains all HTML, CSS, and JavaScript inline
- **Styling**: Tailwind CSS loaded via CDN (`cdn.tailwindcss.com`) with a custom Material Design 3 color theme defined in an inline `tailwind.config`
- **Fonts**: Amiri (serif, headlines) and Manrope (sans-serif, body/labels) via Google Fonts; Material Symbols Outlined for icons; Bootstrap Icons for social/nav icons
- **Layout direction**: RTL (`dir="rtl"` on `<html>`)
- **Language**: All user-facing text is Arabic

## Key Patterns

- **Skeleton loading**: A full-screen overlay (`#loading-overlay`) with `.skeleton` pulse animations, dismissed after 1.5s timeout revealing `#main-content`
- **Scroll reveal**: Sections use `.reveal-on-scroll` class, activated by an `IntersectionObserver` (threshold 0.15) that adds `.active` class
- **Animations**: `fadeIn` and `slideDown` keyframes applied to hero section elements
- **Product cards**: Hover-triggered add-to-cart buttons with opacity/translate transitions
- **All images**: Hosted externally on `lh3.googleusercontent.com`

## Color Theme

Custom tokens follow Material Design 3 naming (e.g., `primary`, `on-primary`, `surface-container`, `secondary-container`). Primary is deep red (#3c0004), secondary is gold (#735c00).
