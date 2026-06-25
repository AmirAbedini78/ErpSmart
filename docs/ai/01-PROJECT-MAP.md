# ERPSMART AI Project Map

## Warehouse module canonical path

Warehouse is the current canonical template module for future ERP module generation.

Capability sequence:

1. CRUD
2. Resource UI
3. Permissions
4. Boolean fields and model normalization
5. Custom fields
6. Import / Export
7. Clone / Delete actions
8. Notes integration
9. Generic Media / Attachments integration
10. Activities / Timeline integration
11. Audit / History
12. Inventory-specific features such as locations and stock movements

## Important architecture notes

- Concord/ErpSmart resources expose reusable capabilities through contracts such as `WithResourceRoutes`, `Tableable`, `Importable`, `Exportable`, `Mediable`.
- Do not implement module features ad-hoc when Core already provides a contract.
- For record-tab features, inspect Core backend and frontend contracts before coding.
- Notes and media integration lessons must be included in RAG context for future module generation.
