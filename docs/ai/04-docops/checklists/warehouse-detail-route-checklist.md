# Warehouse Detail Route Checklist

Use this checklist after every custom detail-page capability is added.

## Required checks

- [ ] `/warehouses` opens index.
- [ ] `/warehouses/create` opens create form.
- [ ] `/warehouses/{id}` opens detail page.
- [ ] Top Edit button from detail opens `/warehouses/{id}/edit`.
- [ ] Edit form loads the selected record.
- [ ] Saving edit updates the correct record.
- [ ] Returning from edit does not accidentally reset to index unless intended.
- [ ] Notes tab still loads after route changes.
- [ ] Attachments tab still loads after route changes.
- [ ] Uploaded attachments remain visible after refresh/revisit.
- [ ] Attachment delete action remains visible and functional for authorized users.

## Builder implication

Route stability is a base capability. Feature toggles such as Notes, Attachments, Timeline, Activities, Calls, Emails, Documents and Changelog must not override or break the base route contract.
