# Warehouse Media / Attachments Checklist

## Backend

- [x] Warehouse model uses `HasMedia`.
- [x] Warehouse model implements `ResourceableContract`.
- [x] Warehouse resource implements `Mediable`.
- [x] `/api/{resource}/{resourceId}/media` route is available through Core.
- [x] Upload succeeds without pivot changelog type errors.
- [x] Warehouse record response includes `media` after reload/navigation.

## Frontend

- [x] Attachments tab is visible on Warehouse detail.
- [x] `ResourceMediaPanel` receives panel metadata.
- [x] `record`, `resource`, `resourceName`, and `resourceId` context are provided.
- [x] `media` is normalized to an array.
- [ ] User validates that uploaded file remains visible after navigating away and returning.

## Test commands

```bash
docker compose exec app php patches/verify_warehouse_media_persistence_fix.php
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan cache:clear
docker compose restart app nginx
```
