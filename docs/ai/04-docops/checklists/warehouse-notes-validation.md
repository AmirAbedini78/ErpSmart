# Warehouse Notes Validation Checklist

## Preconditions

- Warehouse CRUD is working.
- User has permission to view Warehouse records.
- Notes module is enabled.
- Frontend build is current.

## Commands

```bash
docker compose exec app php artisan optimize:clear
sudo rm -f public/hot
docker compose exec node npm run build
docker compose restart app nginx
```

## UI validation

1. Open `/warehouses`.
2. Click a Warehouse record.
3. Confirm the detail page has a `Details` tab and a `Notes` tab.
4. Open the `Notes` tab.
5. Add a note.
6. Confirm it appears immediately.
7. Refresh the page.
8. Confirm the note is still present.
9. Search inside notes if existing notes are present.
10. Open browser console and confirm there are no errors about:

```text
RecordTabNote
RecordTabNotePanel
synchronizeResource
incrementResourceCount
/api/warehouses/{id}/notes
```

## Backend validation

In Tinker:

```php
$warehouse = \Modules\Warehouse\Models\Warehouse::first();
method_exists($warehouse, 'notes');
$warehouse->notes()->count();
```

Expected:

```text
true
integer count
```

## Troubleshooting

### Notes tab appears but create fails

Check browser console and Network. If injection errors appear, verify `WarehousesView.vue` provides:

```text
synchronizeResource
incrementResourceCount
decrementResourceCount
```

### Notes tab loads but no notes persist

Check that the model relation uses:

```php
return $this->morphToMany(Note::class, 'noteable')->withTimestamps();
```

### 404 on notes endpoint

Check that the Core Resource engine sees Warehouse as a first-class resource and that `resource.path` in the detail response points to the correct `/api/warehouses/{id}` path.


## Path contract validation

- [ ] Open Network tab before clicking Notes.
- [ ] Click Notes.
- [ ] Confirm the request is `/api/warehouses/{id}/notes?page=1&per_page=15&timeline=1`.
- [ ] Confirm the request is not `/api/undefined/notes`.


## Backend association contract validation

Run after applying the provider fix:

```php
$warehouse = \Modules\Warehouse\Models\Warehouse::first();
$note = new \Modules\Notes\Models\Note;

$warehouse->notes()->getRelated() instanceof \Modules\Notes\Models\Note;
$note->warehouses()->getRelated() instanceof \Modules\Warehouse\Models\Warehouse;
```

Expected result: both return `true`.

Network validation:

- [ ] `GET /api/warehouses/{id}/notes?page=1&per_page=15&timeline=1` returns 200.
- [ ] `POST /api/notes?via_resource=warehouses&via_resource_id={id}` returns 201 when `body` is not empty.
- [ ] Refreshing `/warehouses/{id}` keeps the created note visible.


## POST validation fix

- [ ] `Innoclapps::resourceByName('warehouses')->isAssociateable()` returns true.
- [ ] `POST /api/notes?via_resource=warehouses&via_resource_id={id}` does not return `The selected via resource is invalid.`.
- [ ] The POST request body includes a non-empty `body` and `warehouses: [{id}]` or `warehouses: [id]` depending on the Core form serializer.
- [ ] The created note appears after refreshing the Warehouse detail page.
