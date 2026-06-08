---
id: mailclient
title: MailClient Module
type: module
reads:
  - docs/ai/00-GOVERNANCE.md
  - docs/ai/01-PROJECT-MAP.md
depends_on:
  - docs/ai/02-domains/core.md
touches_code:
  - modules/MailClient/app/Providers/MailClientServiceProvider.php
  - modules/MailClient/app/Providers/RouteServiceProvider.php
  - modules/MailClient/routes/api.php
  - modules/MailClient/routes/channels.php
  - modules/MailClient/routes/web.php
  - modules/MailClient/app/Resources/EmailMessage.php
  - modules/MailClient/app/Resources/IncomingMessageTable.php
  - modules/MailClient/app/Resources/OutgoingMessageTable.php
  - modules/MailClient/app/Models/EmailAccount.php
  - modules/MailClient/app/Models/EmailAccountFolder.php
  - modules/MailClient/app/Models/EmailAccountMessage.php
  - modules/MailClient/app/Models/EmailAccountMessageAddress.php
  - modules/MailClient/app/Models/EmailAccountMessageFolder.php
  - modules/MailClient/app/Models/EmailAccountMessageHeader.php
  - modules/MailClient/app/Models/MessageLinksClick.php
  - modules/MailClient/app/Models/PredefinedMailTemplate.php
  - modules/MailClient/app/Http/Controllers/MailTrackerController.php
  - modules/MailClient/app/Http/Controllers/OAuthEmailAccountController.php
  - modules/MailClient/app/Http/Controllers/Api/EmailAccountConnectionTestController.php
  - modules/MailClient/app/Http/Controllers/Api/EmailAccountController.php
  - modules/MailClient/app/Http/Controllers/Api/EmailAccountMessagesController.php
  - modules/MailClient/app/Http/Controllers/Api/EmailAccountMessageTagController.php
  - modules/MailClient/app/Http/Controllers/Api/EmailAccountPrimaryStateController.php
  - modules/MailClient/app/Http/Controllers/Api/EmailAccountSync.php
  - modules/MailClient/app/Workflow/Actions/ResourcesSendEmailToField.php
  - modules/MailClient/app/Workflow/Actions/SendEmailAction.php
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
  - MailClient
---

# MailClient Module

## Current State Summary
ماژول `MailClient` یکی از ماژول‌های Concord/ErpSmart است. وضعیت و جزئیات دقیق باید هنگام هر تسک با کد validate شود.

## Key Files Map
### Providers
- `modules/MailClient/app/Providers/MailClientServiceProvider.php`
- `modules/MailClient/app/Providers/RouteServiceProvider.php`

### Routes
- `modules/MailClient/routes/api.php`
- `modules/MailClient/routes/channels.php`
- `modules/MailClient/routes/web.php`

### Resources
- `modules/MailClient/app/Resources/EmailMessage.php`
- `modules/MailClient/app/Resources/IncomingMessageTable.php`
- `modules/MailClient/app/Resources/OutgoingMessageTable.php`

### Models
- `modules/MailClient/app/Models/EmailAccount.php`
- `modules/MailClient/app/Models/EmailAccountFolder.php`
- `modules/MailClient/app/Models/EmailAccountMessage.php`
- `modules/MailClient/app/Models/EmailAccountMessageAddress.php`
- `modules/MailClient/app/Models/EmailAccountMessageFolder.php`
- `modules/MailClient/app/Models/EmailAccountMessageHeader.php`
- `modules/MailClient/app/Models/MessageLinksClick.php`
- `modules/MailClient/app/Models/PredefinedMailTemplate.php`
- `modules/MailClient/app/Models/ScheduledEmail.php`

### Workflow
- `modules/MailClient/app/Workflow/Actions/ResourcesSendEmailToField.php`
- `modules/MailClient/app/Workflow/Actions/SendEmailAction.php`

## File counts
{
  "app/Models": 9,
  "app/Http/Controllers": 15,
  "app/Http/Requests": 3,
  "app/Providers": 2,
  "app/Resources": 3,
  "app/Workflow": 2,
  "resources/js": 52,
  "database/migrations": 11,
  "database/seeders": 0,
  "routes": 3
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
