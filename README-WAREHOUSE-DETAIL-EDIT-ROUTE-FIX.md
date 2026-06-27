# Warehouse Detail Edit Route Fix

This patch fixes the Warehouse detail page Edit button so it opens the edit form instead of returning to the index.

## Apply

```bash
unzip -o warehouse_detail_edit_route_fix_files.zip
docker compose exec app php patches/apply_warehouse_detail_edit_route_fix.php
```

## Verify

```bash
docker compose exec app php patches/verify_warehouse_detail_edit_route_fix.php
```

## Clear and build

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan cache:clear
sudo rm -f public/hot
docker compose exec node npm run build
docker compose restart app nginx
```

## Test

Open `/warehouses/{id}` and click the top Edit button. It should open `/warehouses/{id}/edit` and load the edit form for the same record.
