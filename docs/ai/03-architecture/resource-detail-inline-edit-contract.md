# Resource Detail Inline Edit Contract

For custom ERP resource detail pages, the Edit action must not rely on a nested
index route unless the parent/child route and RouterView contract are confirmed.

Warehouse uses a stable inline-edit modal pattern:

- Detail route stays on `/warehouses/{id}`.
- The top Edit button opens the existing update form component inline.
- The update component accepts `recordId` and `redirectOnClose` props.
- After update, the detail page synchronizes the resource and refetches the record.

This is important for the future module builder: `edit_from_detail` should be a
base detail capability that can be generated either as inline modal or as route
navigation, based on the selected UI pattern.
