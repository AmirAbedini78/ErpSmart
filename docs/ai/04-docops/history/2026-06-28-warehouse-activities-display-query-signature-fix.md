# Warehouse Activities Display Query Signature Fix

Status: canonical runtime fix

## Problem

The Activities integration introduced a custom `Warehouse::displayQuery(Builder $query, ResourceRequest $request)` method. Core's `Resource::displayQuery()` signature has no arguments, so PHP raised a fatal compatibility error before `/api/warehouses/{id}` could be served.

An earlier variant also attempted to eager-load `activities` inside `displayQuery`, which made Warehouse detail depend on an activity relation during first render. The Activities tab already lazy-loads its data, so detail should stay lightweight.

## Fix

- Normalize Warehouse resource to `public function displayQuery(): Builder`.
- Return `parent::displayQuery()` without force-loading activities.
- Keep `HasActivities` explicitly visible in the Warehouse model.
- Keep a safe `incomplete_activities_for_user_count` accessor for the tab badge.

## Contract

For CRM-style module builders:

- Do not override `displayQuery()` with request/query parameters.
- Do not force eager-load Activities for the base detail endpoint.
- Use `HasActivities` on the model.
- Use `ActivitiesTab` and `ActivitiesTabPanel` in the record view.
- Use `CreateRelatedActivityAction::make()->onlyInline()` for the resource action.