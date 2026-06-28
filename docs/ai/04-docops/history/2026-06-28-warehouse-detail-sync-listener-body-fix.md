# Warehouse Detail Sync Listener Body Fix

Status: applied

The Warehouse detail page listener for `floating-resource-updated` was canonicalized after the import/export helper was corrected.

The listener now:

1. Checks that the updated resource name matches `warehouses`.
2. Checks that the updated resource id matches the current detail route id.
3. Synchronizes the returned payload resource immediately.
4. Fetches the latest detail record and rehydrates detail fields.

This is the canonical post-floating-edit detail synchronization behavior for future CRM-style modules.
