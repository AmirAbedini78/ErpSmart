# Warehouse detail stability validation

- [ ] Run `docker compose exec node npm run build`.
- [ ] Run `docker compose exec app php artisan optimize:clear`.
- [ ] Restart app and nginx.
- [ ] Open `/warehouses`.
- [ ] Click an existing Warehouse record.
- [ ] Confirm detail page renders without SPA freeze.
- [ ] Confirm Console no longer repeats `FieldInlineEdit` render loop warnings.
- [ ] Confirm missing i18n warnings for `back_to_warehouses`, `active`, and `inactive` are gone.
- [ ] Open Notes tab.
- [ ] Create a note and refresh.
