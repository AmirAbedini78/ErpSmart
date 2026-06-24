# 2026-06-23 — Warehouse Notes Tab 404 Fix

## Summary

Clicking the Warehouse `Notes` tab returned 404 after the initial Notes integration.

The root cause was that Warehouse had the `notes()` morph relation and frontend Notes tab, but the model did not use the Core `HasTimeline` trait. The generic association endpoint used by `RecordTabNotePanel` runs in timeline mode and rejects resources that are not timeline subjects.

## File changed

```text
modules/Warehouse/app/Models/Warehouse.php
```

## Fix

Added:

```php
use Modules\Core\Common\Timeline\HasTimeline;
```

and changed model traits to:

```php
use HasTimeline,
    Resourceable;
```

## Why this matters

For Core Notes integration, relation-only is insufficient. The model must also be registered as a timeline subject through the `HasTimeline` trait.

## Validation

1. Clear cache.
2. Open `/warehouses/{id}`.
3. Click `Notes`.
4. Confirm it no longer redirects to 404.
5. Add a note and refresh.

## Builder rule

The Module Builder must treat Notes as a multi-layer capability:

```text
HasTimeline trait
notes() relation
frontend record tab
provide() resource sync hooks
SmartDocs/RAG manifest
cache clear step
```
