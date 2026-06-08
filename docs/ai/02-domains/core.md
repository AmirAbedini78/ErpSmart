---
id: core
title: Core Module
type: module
reads:
  - docs/ai/00-GOVERNANCE.md
  - docs/ai/01-PROJECT-MAP.md
depends_on:
  - docs/ai/02-domains/core.md
touches_code:
  - modules/Core/app/Providers/BootstrapServiceProvider.php
  - modules/Core/app/Providers/CoreServiceProvider.php
  - modules/Core/app/Providers/OAuthServiceProvider.php
  - modules/Core/app/Providers/PurifierServiceProvider.php
  - modules/Core/app/Providers/ReCaptchaServiceProvider.php
  - modules/Core/app/Providers/RouteServiceProvider.php
  - modules/Core/routes/api.php
  - modules/Core/routes/web.php
  - modules/Core/app/Models/CacheModel.php
  - modules/Core/app/Models/Changelog.php
  - modules/Core/app/Models/Country.php
  - modules/Core/app/Models/CustomField.php
  - modules/Core/app/Models/CustomFieldOption.php
  - modules/Core/app/Models/Dashboard.php
  - modules/Core/app/Models/DataView.php
  - modules/Core/app/Models/DataViewUserConfig.php
  - modules/Core/app/Http/Controllers/ApiController.php
  - modules/Core/app/Http/Controllers/MediaViewController.php
  - modules/Core/app/Http/Controllers/OAuthController.php
  - modules/Core/app/Http/Controllers/PrivacyPolicy.php
  - modules/Core/app/Http/Controllers/ScriptController.php
  - modules/Core/app/Http/Controllers/ServeApplication.php
  - modules/Core/app/Http/Controllers/StyleController.php
  - modules/Core/app/Http/Controllers/SynchronizationGoogleWebhookController.php
  - modules/Core/app/Workflow/Action.php
  - modules/Core/app/Workflow/ActionsCollection.php
  - modules/Core/app/Workflow/HasWorkflowTriggers.php
  - modules/Core/app/Workflow/ProcessWorkflowAction.php
  - modules/Core/app/Workflow/Trigger.php
  - modules/Core/app/Workflow/WorkflowEventsSubscriber.php
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
  - Core
---

# Core Module

## Current State Summary
ماژول `Core` یکی از ماژول‌های Concord/ErpSmart است. وضعیت و جزئیات دقیق باید هنگام هر تسک با کد validate شود.

## Key Files Map
### Providers
- `modules/Core/app/Providers/BootstrapServiceProvider.php`
- `modules/Core/app/Providers/CoreServiceProvider.php`
- `modules/Core/app/Providers/OAuthServiceProvider.php`
- `modules/Core/app/Providers/PurifierServiceProvider.php`
- `modules/Core/app/Providers/ReCaptchaServiceProvider.php`
- `modules/Core/app/Providers/RouteServiceProvider.php`

### Routes
- `modules/Core/routes/api.php`
- `modules/Core/routes/web.php`

### Resources

### Models
- `modules/Core/app/Models/CacheModel.php`
- `modules/Core/app/Models/Changelog.php`
- `modules/Core/app/Models/Country.php`
- `modules/Core/app/Models/CustomField.php`
- `modules/Core/app/Models/CustomFieldOption.php`
- `modules/Core/app/Models/Dashboard.php`
- `modules/Core/app/Models/DataView.php`
- `modules/Core/app/Models/DataViewUserConfig.php`
- `modules/Core/app/Models/Import.php`
- `modules/Core/app/Models/MailableTemplate.php`
- `modules/Core/app/Models/Media.php`
- `modules/Core/app/Models/Meta.php`
- `modules/Core/app/Models/Model.php`
- `modules/Core/app/Models/ModelVisibilityGroup.php`
- `modules/Core/app/Models/ModelVisibilityGroupDependent.php`
- `modules/Core/app/Models/OAuthAccount.php`
- `modules/Core/app/Models/Patch.php`
- `modules/Core/app/Models/PendingMedia.php`
- `modules/Core/app/Models/Permission.php`
- `modules/Core/app/Models/PinnedTimelineSubject.php`

### Workflow
- `modules/Core/app/Workflow/Action.php`
- `modules/Core/app/Workflow/ActionsCollection.php`
- `modules/Core/app/Workflow/HasWorkflowTriggers.php`
- `modules/Core/app/Workflow/ProcessWorkflowAction.php`
- `modules/Core/app/Workflow/Trigger.php`
- `modules/Core/app/Workflow/WorkflowEventsSubscriber.php`
- `modules/Core/app/Workflow/Workflows.php`
- `modules/Core/app/Workflow/Actions/WebhookAction.php`

## File counts
{
  "app/Models": 27,
  "app/Http/Controllers": 57,
  "app/Http/Requests": 20,
  "app/Providers": 6,
  "app/Resources": 0,
  "app/Workflow": 8,
  "resources/js": 390,
  "database/migrations": 29,
  "database/seeders": 3,
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
