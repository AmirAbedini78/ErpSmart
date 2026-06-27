# Checklist: Warehouse Attachments Delete Action

## Goal

Make the Warehouse attachments tab behave like Core resource detail pages, including the remove/delete X for attached files.

## Preconditions

- Attachments tab exists.
- Upload succeeds.
- Files persist after refresh/navigation.
- `Warehouse` model implements `Resourceable` contract.
- `Warehouse` resource implements `Mediable`.

## Apply

```bash
unzip -o warehouse_attachments_delete_action_fix_files.zip
docker compose exec app php patches/apply_warehouse_attachments_delete_action_fix.php
docker compose exec app php patches/verify_warehouse_attachments_delete_action_fix.php
```

## Build

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan cache:clear
sudo rm -f public/hot
docker compose exec node npm run build
docker compose restart app nginx
```

## Manual test

1. Open `/warehouses/{id}`.
2. Open Attachments tab.
3. Confirm existing files are visible.
4. Confirm each media item shows the delete/remove X.
5. Delete a test file.
6. Navigate away and return.
7. Confirm the deleted file is gone.

## If delete fails

Capture:

```bash
docker compose exec app sh -lc 'tail -n 120 $(ls -t storage/logs/laravel-*.log | head -1)'
docker compose exec app php artisan route:list | grep -E "media|warehouses"
```

Also capture Network request:

```text
DELETE /api/warehouses/{id}/media/{media}
```
