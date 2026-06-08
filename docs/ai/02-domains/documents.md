---
id: documents
title: Documents Module
type: module
reads:
  - docs/ai/00-GOVERNANCE.md
  - docs/ai/01-PROJECT-MAP.md
depends_on:
  - docs/ai/02-domains/core.md
touches_code:
  - modules/Documents/app/Providers/DocumentsServiceProvider.php
  - modules/Documents/app/Providers/RouteServiceProvider.php
  - modules/Documents/routes/api.php
  - modules/Documents/routes/web.php
  - modules/Documents/app/Resources/Document.php
  - modules/Documents/app/Resources/DocumentTemplate.php
  - modules/Documents/app/Resources/DocumentType.php
  - modules/Documents/app/Models/Document.php
  - modules/Documents/app/Models/DocumentSigner.php
  - modules/Documents/app/Models/DocumentTemplate.php
  - modules/Documents/app/Models/DocumentType.php
  - modules/Documents/app/Http/Controllers/DocumentController.php
  - modules/Documents/app/Http/Controllers/Api/DocumentAcceptController.php
  - modules/Documents/app/Http/Controllers/Api/DocumentStateController.php
  - modules/Documents/app/Workflow/Triggers/DocumentStatusChanged.php
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
  - Documents
---

# Documents Module

## Current State Summary
ماژول `Documents` یکی از ماژول‌های Concord/ErpSmart است. وضعیت و جزئیات دقیق باید هنگام هر تسک با کد validate شود.

## Key Files Map
### Providers
- `modules/Documents/app/Providers/DocumentsServiceProvider.php`
- `modules/Documents/app/Providers/RouteServiceProvider.php`

### Routes
- `modules/Documents/routes/api.php`
- `modules/Documents/routes/web.php`

### Resources
- `modules/Documents/app/Resources/Document.php`
- `modules/Documents/app/Resources/DocumentTemplate.php`
- `modules/Documents/app/Resources/DocumentType.php`

### Models
- `modules/Documents/app/Models/Document.php`
- `modules/Documents/app/Models/DocumentSigner.php`
- `modules/Documents/app/Models/DocumentTemplate.php`
- `modules/Documents/app/Models/DocumentType.php`

### Workflow
- `modules/Documents/app/Workflow/Triggers/DocumentStatusChanged.php`

## File counts
{
  "app/Models": 4,
  "app/Http/Controllers": 3,
  "app/Http/Requests": 0,
  "app/Providers": 2,
  "app/Resources": 3,
  "app/Workflow": 1,
  "resources/js": 26,
  "database/migrations": 5,
  "database/seeders": 1,
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
