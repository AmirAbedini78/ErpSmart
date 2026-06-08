---
id: notes
title: Notes Module
type: module
reads:
  - docs/ai/00-GOVERNANCE.md
  - docs/ai/01-PROJECT-MAP.md
depends_on:
  - docs/ai/02-domains/core.md
touches_code:
  - modules/Notes/app/Providers/NotesServiceProvider.php
  - modules/Notes/app/Providers/RouteServiceProvider.php
  - modules/Notes/routes/api.php
  - modules/Notes/app/Resources/Note.php
  - modules/Notes/app/Models/Note.php
smoke_checks:
  - php artisan module:list
  - php artisan route:list
  - open related UI page and check browser console/network
validation_queries:
  - آیا module در modules_statuses.json enabled است؟
  - آیا providerهای module در boot خطا نمی‌دهند؟
  - آیا routes/api.php یا routes/web.php endpoint لازم را ثبت کرده‌اند؟
  - آیا resources/js/app.js ماژول import و route/menu لازم را ثبت می‌کند؟
aliases:
  - Notes
---

# Notes Module

## Current State Summary
ماژول `Notes` یکی از ماژول‌های Concord/ErpSmart است. وضعیت و جزئیات دقیق باید هنگام هر تسک با کد validate شود.

## Key Files Map
### Providers
- `modules/Notes/app/Providers/NotesServiceProvider.php`
- `modules/Notes/app/Providers/RouteServiceProvider.php`

### Routes
- `modules/Notes/routes/api.php`

### Resources
- `modules/Notes/app/Resources/Note.php`

### Models
- `modules/Notes/app/Models/Note.php`

## File counts
{
  "app/Models": 1,
  "app/Http/Controllers": 0,
  "app/Http/Requests": 0,
  "app/Providers": 2,
  "app/Resources": 1,
  "app/Workflow": 0,
  "resources/js": 7,
  "database/migrations": 2,
  "database/seeders": 0,
  "routes": 1
}

## Development lifecycle
1. ابتدا `module.json` و Provider را بررسی کن.
2. route مربوط را پیدا کن.
3. اگر صفحه Vue است، از `resources/js/app.js` ماژول شروع کن.
4. اگر CRUD است، Resource و Model را بررسی کن.
5. Migration/Seeder را فقط برای همان module اجرا/بررسی کن.
6. بعد از تغییر، cache را clear کن و smoke check بگیر.

## Risks & Traps
- Module cache ممکن است وضعیت قدیمی را نگه دارد.
- برخی seedها داده پایه لازم برای Workflow/UI می‌سازند.
- اگر module provider موقع boot خطا بدهد، حتی `php artisan` هم fail می‌شود.

## History
- 2026-06-08 — Bootstrapped by project analysis. نیازمند تکمیل تدریجی هنگام توسعه واقعی.
