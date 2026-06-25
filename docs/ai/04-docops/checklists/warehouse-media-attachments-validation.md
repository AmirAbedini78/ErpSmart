# Warehouse Media / Attachments Validation Checklist

## Goal

Validate that Warehouse supports generic file attachments through the Core Resource media pipeline.

## Pre-check

```bash
php -l modules/Warehouse/app/Models/Warehouse.php
php -l modules/Warehouse/app/Resources/Warehouse.php
```

## Backend contract

```bash
php patches/verify_warehouse_media_attachments_step.php
```

Expected:

- `resource_is_mediable` = true
- `model_uses_has_media` = true
- `model_has_media_relation` = true
- `model_has_get_media_directory` = true
- `model_has_get_media_tags` = true

## Frontend build

```bash
sudo rm -f public/hot
npm run build
```

If running inside Docker:

```bash
docker compose exec node npm run build
```

## UI validation

1. Open `/warehouses`.
2. Open an existing Warehouse detail page.
3. Confirm tabs include Details, Notes, Attachments.
4. Open Attachments.
5. Upload a small test file.
6. Confirm Network request uses a Warehouse resource media route, not a custom ad-hoc upload route.
7. Refresh the page and confirm the uploaded file remains attached.
8. Delete the attachment if the UI supports deletion and confirm it disappears after refresh.

## Failure capture

If upload fails, capture:

```bash
tail -n 120 $(ls -t storage/logs/laravel-*.log | head -1)
php artisan route:list | grep -E "media|warehouses"
```

Also capture browser Network:

- Request URL
- Request Method
- Status Code
- Payload/FormData
- Response/Preview

## Known decisions

- This step is for generic media/attachments.
- CRM Documents are intentionally not attached to Warehouse yet.
- Notes rules from the previous checkpoint remain valid and must not be reverted.
