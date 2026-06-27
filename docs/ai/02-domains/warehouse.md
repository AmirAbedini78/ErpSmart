# Warehouse Domain Notes

Warehouse is the canonical ERP template module used to discover and document reusable module-builder capabilities.

## Current Capabilities

- CRUD
- Permissions
- Custom fields
- Import / Export
- Clone / Delete row actions
- Notes tab
- Attachments / Media tab
- Attachment delete action
- Stable detail → edit route navigation

## Detail Page Contract

The Warehouse detail page is custom and must keep stable navigation:

- Detail: `/warehouses/{id}`
- Edit: `/warehouses/{id}/edit`

Custom detail capabilities must not break the top Edit action.
