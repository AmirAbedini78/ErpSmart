# Warehouse Notes Validation Checklist

1. Open `/warehouses/{id}` and verify `GET /api/warehouses/{id}` returns 200.
2. Open Notes tab and verify `GET /api/warehouses/{id}/notes?page=1&per_page=15&timeline=1` returns 200.
3. Create a note with a non-empty body.
4. Verify `POST /api/notes?via_resource=warehouses&via_resource_id={id}` returns 200 or 201.
5. Verify a row exists in `notes`.
6. Verify a row exists in `noteables` with `noteable_type` for Warehouse and `noteable_id={id}`.
7. Confirm `noteables` has no timestamp columns before enabling `withTimestamps()`.
