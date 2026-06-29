# Warehouse Activities Detail 500 Safe Fix

Status: applied

This patch keeps the final Activities integration aligned with first-party ConcordCRM resources while removing risky eager-loading/count assumptions from `GET /api/warehouses/{id}`.

Canonical contract:

- Warehouse model uses `Modules\Activities\Concerns\HasActivities`.
- `Activity` model exposes `warehouses()` through the shared `activityables` morph pivot.
- Warehouse Resource exposes `CreateRelatedActivityAction::make()->onlyInline()`.
- Warehouse detail uses `ActivitiesTab` / `ActivitiesTabPanel` and lets the tab lazy-load activity records.
- Warehouse detail payload must stay safe even when `activities` are not eager loaded.

Do not force-load activities from the Warehouse detail endpoint unless the exact first-party display query contract is verified from Contacts/Companies/Deals.
