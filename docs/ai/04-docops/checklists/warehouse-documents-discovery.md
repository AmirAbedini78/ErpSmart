# Warehouse Documents / Attachments Discovery Checklist

## Run discovery

```bash
bash patches/probe_warehouse_documents_contract.sh
```

## Required evidence before implementation

- [ ] `storage/app/warehouse-documents-contract-report.md` exists.
- [ ] Existing resource profile pages with document/media/file tabs were identified.
- [ ] Backend Resource contract for files/documents was identified.
- [ ] Required model trait or relation was identified.
- [ ] API routes for list/upload/delete were identified.
- [ ] Pivot or media table schema was inspected.
- [ ] Decision recorded: generic attachments/media OR CRM documents.
- [ ] Notes lessons were carried into the design.

## Validation after implementation

- [ ] Warehouse detail page loads without console errors.
- [ ] Upload/list request returns 200/201.
- [ ] Refresh keeps uploaded file visible.
- [ ] Delete/detach request works with correct permissions.
- [ ] Non-authorized user cannot upload/delete if permission requires it.
- [ ] SmartDocs, task_state and RAG manifest updated.
