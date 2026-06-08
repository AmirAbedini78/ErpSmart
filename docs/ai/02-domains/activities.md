---
id: activities
title: Activities Module
type: module
reads:
  - docs/ai/00-GOVERNANCE.md
  - docs/ai/01-PROJECT-MAP.md
depends_on:
  - docs/ai/02-domains/core.md
touches_code:
  - modules/Activities/app/Providers/ActivitiesServiceProvider.php
  - modules/Activities/app/Providers/RouteServiceProvider.php
  - modules/Activities/routes/api.php
  - modules/Activities/routes/web.php
  - modules/Activities/app/Resources/Activity.php
  - modules/Activities/app/Resources/ActivityType.php
  - modules/Activities/app/Models/Activity.php
  - modules/Activities/app/Models/ActivityType.php
  - modules/Activities/app/Models/Calendar.php
  - modules/Activities/app/Models/Guest.php
  - modules/Activities/app/Http/Controllers/OAuthCalendarController.php
  - modules/Activities/app/Http/Controllers/OutlookCalendarWebhookController.php
  - modules/Activities/app/Http/Controllers/Api/ActivityStateController.php
  - modules/Activities/app/Http/Controllers/Api/CalendarOAuthController.php
  - modules/Activities/app/Workflow/Actions/CreateActivityAction.php
  - modules/Activities/app/Workflow/Actions/DeleteAssociatedActivities.php
  - modules/Activities/app/Workflow/Actions/MarkAssociatedActivitiesAsComplete.php
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
  - Activities
---

# Activities Module

## Current State Summary
ماژول `Activities` یکی از ماژول‌های Concord/ErpSmart است. وضعیت و جزئیات دقیق باید هنگام هر تسک با کد validate شود.

## Key Files Map
### Providers
- `modules/Activities/app/Providers/ActivitiesServiceProvider.php`
- `modules/Activities/app/Providers/RouteServiceProvider.php`

### Routes
- `modules/Activities/routes/api.php`
- `modules/Activities/routes/web.php`

### Resources
- `modules/Activities/app/Resources/Activity.php`
- `modules/Activities/app/Resources/ActivityType.php`

### Models
- `modules/Activities/app/Models/Activity.php`
- `modules/Activities/app/Models/ActivityType.php`
- `modules/Activities/app/Models/Calendar.php`
- `modules/Activities/app/Models/Guest.php`

### Workflow
- `modules/Activities/app/Workflow/Actions/CreateActivityAction.php`
- `modules/Activities/app/Workflow/Actions/DeleteAssociatedActivities.php`
- `modules/Activities/app/Workflow/Actions/MarkAssociatedActivitiesAsComplete.php`

## File counts
{
  "app/Models": 4,
  "app/Http/Controllers": 4,
  "app/Http/Requests": 0,
  "app/Providers": 2,
  "app/Resources": 2,
  "app/Workflow": 3,
  "resources/js": 34,
  "database/migrations": 8,
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
