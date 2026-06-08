---
id: calls
title: Calls Module
type: module
reads:
  - docs/ai/00-GOVERNANCE.md
  - docs/ai/01-PROJECT-MAP.md
depends_on:
  - docs/ai/02-domains/core.md
touches_code:
  - modules/Calls/app/Providers/CallsServiceProvider.php
  - modules/Calls/app/Providers/RouteServiceProvider.php
  - modules/Calls/app/Providers/VoIPServiceProvider.php
  - modules/Calls/routes/api.php
  - modules/Calls/routes/web.php
  - modules/Calls/app/Resources/Call.php
  - modules/Calls/app/Resources/CallOutcome.php
  - modules/Calls/app/Models/Call.php
  - modules/Calls/app/Models/CallOutcome.php
  - modules/Calls/app/Http/Controllers/Api/TwilioAppController.php
  - modules/Calls/app/Http/Controllers/Api/TwilioController.php
  - modules/Calls/app/Http/Controllers/Api/TwilioSettingsController.php
  - modules/Calls/app/Http/Controllers/Api/VoIPController.php
  - modules/Calls/app/Workflow/Triggers/MissedIncomingCall.php
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
  - Calls
---

# Calls Module

## Current State Summary
ماژول `Calls` یکی از ماژول‌های Concord/ErpSmart است. وضعیت و جزئیات دقیق باید هنگام هر تسک با کد validate شود.

## Key Files Map
### Providers
- `modules/Calls/app/Providers/CallsServiceProvider.php`
- `modules/Calls/app/Providers/RouteServiceProvider.php`
- `modules/Calls/app/Providers/VoIPServiceProvider.php`

### Routes
- `modules/Calls/routes/api.php`
- `modules/Calls/routes/web.php`

### Resources
- `modules/Calls/app/Resources/Call.php`
- `modules/Calls/app/Resources/CallOutcome.php`

### Models
- `modules/Calls/app/Models/Call.php`
- `modules/Calls/app/Models/CallOutcome.php`

### Workflow
- `modules/Calls/app/Workflow/Triggers/MissedIncomingCall.php`

## File counts
{
  "app/Models": 2,
  "app/Http/Controllers": 4,
  "app/Http/Requests": 0,
  "app/Providers": 3,
  "app/Resources": 2,
  "app/Workflow": 1,
  "resources/js": 26,
  "database/migrations": 3,
  "database/seeders": 0,
  "routes": 2
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
