---
id: deals
title: Deals Module
type: module
reads:
  - docs/ai/00-GOVERNANCE.md
  - docs/ai/01-PROJECT-MAP.md
depends_on:
  - docs/ai/02-domains/core.md
touches_code:
  - modules/Deals/app/Providers/DealsServiceProvider.php
  - modules/Deals/app/Providers/RouteServiceProvider.php
  - modules/Deals/routes/api.php
  - modules/Deals/routes/channels.php
  - modules/Deals/app/Resources/Deal.php
  - modules/Deals/app/Resources/DealImport.php
  - modules/Deals/app/Resources/DealTable.php
  - modules/Deals/app/Resources/LostReason.php
  - modules/Deals/app/Resources/Pipeline.php
  - modules/Deals/app/Resources/PipelineStage.php
  - modules/Deals/app/Models/Deal.php
  - modules/Deals/app/Models/LostReason.php
  - modules/Deals/app/Models/Pipeline.php
  - modules/Deals/app/Models/Stage.php
  - modules/Deals/app/Models/StageHistory.php
  - modules/Deals/app/Http/Controllers/Api/DealBoardController.php
  - modules/Deals/app/Http/Controllers/Api/DealStatusController.php
  - modules/Deals/app/Http/Controllers/Api/PipelineStageController.php
  - modules/Deals/app/Workflow/Actions/MarkAssociatedDealsAsLost.php
  - modules/Deals/app/Workflow/Actions/MarkAssociatedDealsAsWon.php
  - modules/Deals/app/Workflow/Triggers/DealCreated.php
  - modules/Deals/app/Workflow/Triggers/DealStageChanged.php
  - modules/Deals/app/Workflow/Triggers/DealStatusChanged.php
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
  - Deals
---

# Deals Module

## Current State Summary
ماژول `Deals` یکی از ماژول‌های Concord/ErpSmart است. وضعیت و جزئیات دقیق باید هنگام هر تسک با کد validate شود.

## Key Files Map
### Providers
- `modules/Deals/app/Providers/DealsServiceProvider.php`
- `modules/Deals/app/Providers/RouteServiceProvider.php`

### Routes
- `modules/Deals/routes/api.php`
- `modules/Deals/routes/channels.php`

### Resources
- `modules/Deals/app/Resources/Deal.php`
- `modules/Deals/app/Resources/DealImport.php`
- `modules/Deals/app/Resources/DealTable.php`
- `modules/Deals/app/Resources/LostReason.php`
- `modules/Deals/app/Resources/Pipeline.php`
- `modules/Deals/app/Resources/PipelineStage.php`

### Models
- `modules/Deals/app/Models/Deal.php`
- `modules/Deals/app/Models/LostReason.php`
- `modules/Deals/app/Models/Pipeline.php`
- `modules/Deals/app/Models/Stage.php`
- `modules/Deals/app/Models/StageHistory.php`

### Workflow
- `modules/Deals/app/Workflow/Actions/MarkAssociatedDealsAsLost.php`
- `modules/Deals/app/Workflow/Actions/MarkAssociatedDealsAsWon.php`
- `modules/Deals/app/Workflow/Triggers/DealCreated.php`
- `modules/Deals/app/Workflow/Triggers/DealStageChanged.php`
- `modules/Deals/app/Workflow/Triggers/DealStatusChanged.php`

## File counts
{
  "app/Models": 5,
  "app/Http/Controllers": 3,
  "app/Http/Requests": 0,
  "app/Providers": 2,
  "app/Resources": 6,
  "app/Workflow": 5,
  "resources/js": 37,
  "database/migrations": 7,
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
