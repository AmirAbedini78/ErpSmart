---
id: contacts
title: Contacts Module
type: module
reads:
  - docs/ai/00-GOVERNANCE.md
  - docs/ai/01-PROJECT-MAP.md
depends_on:
  - docs/ai/02-domains/core.md
touches_code:
  - modules/Contacts/app/Providers/ContactsServiceProvider.php
  - modules/Contacts/app/Providers/RouteServiceProvider.php
  - modules/Contacts/routes/api.php
  - modules/Contacts/routes/channels.php
  - modules/Contacts/app/Resources/Company.php
  - modules/Contacts/app/Resources/Contact.php
  - modules/Contacts/app/Resources/ContactTable.php
  - modules/Contacts/app/Resources/Industry.php
  - modules/Contacts/app/Resources/Source.php
  - modules/Contacts/app/Models/Company.php
  - modules/Contacts/app/Models/Contact.php
  - modules/Contacts/app/Models/Industry.php
  - modules/Contacts/app/Models/Phone.php
  - modules/Contacts/app/Models/Source.php
  - modules/Contacts/app/Workflow/Triggers/CompanyCreated.php
  - modules/Contacts/app/Workflow/Triggers/ContactCreated.php
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
  - Contacts
---

# Contacts Module

## Current State Summary
ماژول `Contacts` یکی از ماژول‌های Concord/ErpSmart است. وضعیت و جزئیات دقیق باید هنگام هر تسک با کد validate شود.

## Key Files Map
### Providers
- `modules/Contacts/app/Providers/ContactsServiceProvider.php`
- `modules/Contacts/app/Providers/RouteServiceProvider.php`

### Routes
- `modules/Contacts/routes/api.php`
- `modules/Contacts/routes/channels.php`

### Resources
- `modules/Contacts/app/Resources/Company.php`
- `modules/Contacts/app/Resources/Contact.php`
- `modules/Contacts/app/Resources/ContactTable.php`
- `modules/Contacts/app/Resources/Industry.php`
- `modules/Contacts/app/Resources/Source.php`

### Models
- `modules/Contacts/app/Models/Company.php`
- `modules/Contacts/app/Models/Contact.php`
- `modules/Contacts/app/Models/Industry.php`
- `modules/Contacts/app/Models/Phone.php`
- `modules/Contacts/app/Models/Source.php`

### Workflow
- `modules/Contacts/app/Workflow/Triggers/CompanyCreated.php`
- `modules/Contacts/app/Workflow/Triggers/ContactCreated.php`

## File counts
{
  "app/Models": 5,
  "app/Http/Controllers": 0,
  "app/Http/Requests": 0,
  "app/Providers": 2,
  "app/Resources": 5,
  "app/Workflow": 2,
  "resources/js": 24,
  "database/migrations": 8,
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
