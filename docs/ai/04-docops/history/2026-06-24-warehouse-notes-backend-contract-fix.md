# 2026-06-24 - Warehouse Notes backend contract fix

## Context

After fixing the frontend resource path, Notes requests correctly targeted:

```text
/api/warehouses/{id}/notes?page=1&per_page=15&timeline=1
```

However, listing notes returned 500 and creating a note returned 422.

## Diagnosis

The Warehouse model had the forward `notes()` morph relation and `HasTimeline`, but the Notes model did not have the inverse `warehouses()` relation required by the Core association/timeline flow.

Core record tabs and note creation synchronize associations through the associated resource relation name. For Warehouse this relation name is `warehouses`.

## Change

`WarehouseServiceProvider` now dynamically registers:

```php
Note::resolveRelationUsing('warehouses', function (Note $note): MorphToMany {
    return $note->morphedByMany(Warehouse::class, 'noteable')->withTimestamps();
});
```

This avoids editing the Notes core module while giving Note records the inverse relation needed for Warehouse associations.

## Validation

1. Clear optimized/cache state.
2. Restart app/nginx.
3. Confirm `$note->warehouses()` resolves to a MorphToMany relation.
4. Open Warehouse detail and click Notes.
5. Confirm GET notes returns 200.
6. Add a non-empty note and confirm POST returns 201.

## Builder rule

Notes-enabled generated modules must include the full two-sided polymorphic relation contract:

- Subject model: forward relation to notes.
- Associated Note model: inverse relation using the subject resource `associateableName()`.
- Frontend detail resource: valid `path`, `notes`, and `notes_count` keys.
