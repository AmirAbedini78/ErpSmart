---
id: users
title: Users Module
type: module
reads:
  - docs/ai/00-GOVERNANCE.md
  - docs/ai/01-PROJECT-MAP.md
depends_on:
  - docs/ai/02-domains/core.md
touches_code:
  - modules/Users/app/Providers/RouteServiceProvider.php
  - modules/Users/app/Providers/UsersServiceProvider.php
  - modules/Users/routes/api.php
  - modules/Users/routes/channels.php
  - modules/Users/routes/web.php
  - modules/Users/app/Resources/User.php
  - modules/Users/app/Models/Team.php
  - modules/Users/app/Models/User.php
  - modules/Users/app/Models/UserInvitation.php
  - modules/Users/app/Http/Controllers/UserInvitationAcceptController.php
  - modules/Users/app/Http/Controllers/Api/IssueTokenController.php
  - modules/Users/app/Http/Controllers/Api/NotificationController.php
  - modules/Users/app/Http/Controllers/Api/PersonalAccessTokenController.php
  - modules/Users/app/Http/Controllers/Api/ProfileController.php
  - modules/Users/app/Http/Controllers/Api/TeamController.php
  - modules/Users/app/Http/Controllers/Api/UserAvatarController.php
  - modules/Users/app/Http/Controllers/Api/UserInvitationController.php
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
  - Users
---

# Users Module

## Current State Summary
ماژول `Users` یکی از ماژول‌های Concord/ErpSmart است. وضعیت و جزئیات دقیق باید هنگام هر تسک با کد validate شود.

## Key Files Map
### Providers
- `modules/Users/app/Providers/RouteServiceProvider.php`
- `modules/Users/app/Providers/UsersServiceProvider.php`

### Routes
- `modules/Users/routes/api.php`
- `modules/Users/routes/channels.php`
- `modules/Users/routes/web.php`

### Resources
- `modules/Users/app/Resources/User.php`

### Models
- `modules/Users/app/Models/Team.php`
- `modules/Users/app/Models/User.php`
- `modules/Users/app/Models/UserInvitation.php`

## File counts
{
  "app/Models": 3,
  "app/Http/Controllers": 8,
  "app/Http/Requests": 3,
  "app/Providers": 2,
  "app/Resources": 1,
  "app/Workflow": 0,
  "resources/js": 16,
  "database/migrations": 7,
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
