# Module Builder Activities Contract

Status: canonical

For CRM-style resources that need related activities, follow the first-party ConcordCRM pattern used by Contacts, Companies, and Deals.

## Required backend contract

1. The resource model must use `Modules\Activities\Concerns\HasActivities`.
2. `Modules\Activities\Models\Activity` must expose a concrete `morphedByMany` relation for the resource plural name, for example `warehouses()`.
3. The resource class should include `CreateRelatedActivityAction::make()->onlyInline()` before edit/delete actions.
4. The resource `displayQuery()` should eager load `activities` and expose `incomplete_activities_for_user_count`.
5. If the related create flow uses `via_resource`, the module service provider must allow `via_resource=warehouses` and validate `via_resource_id`.

## Required frontend contract

1. Use `RecordTabActivity.vue` as the tab label/count component.
2. Use `RecordTabActivityPanel.vue` as the panel.
3. Pass `resourceName`, `resourceId`, `resource`, and `scroll-element="#main"`.
4. Normalize the resource shape so `activities` is always an array and `incomplete_activities_for_user_count` is always numeric.

## Do not use

- Do not create a custom activities table for each module.
- Do not create custom Warehouse-only activity UI unless the first-party panel cannot support the need.
- Do not bypass the `activityables` pivot contract.