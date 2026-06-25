# Resource Documents / Attachments Discovery

## Purpose
This document defines the discovery-first protocol for adding file/document capability to generated ERP resources.

Warehouse is the template resource. The goal is not just to make Warehouse upload files; the goal is to learn the reusable Core contract so future Builder-generated modules can safely enable attachments.

## Important distinction
Concord/ErpSmart has two nearby concepts:

| Concept | Meaning | ERP Warehouse fit |
|---|---|---|
| Documents module | Business documents such as proposals, quotes, agreements, sending/signature, brands, products and tracking. | Use only if the warehouse must participate in CRM document workflow. |
| Media / Attachments | Generic uploaded files attached to a record. | Preferred for warehouse photos, permits, leases, inventory sheets and internal evidence files. |

The public Concord CRM documentation describes Documents as proposal/agreement/quote style business documents with sending, signing and tracking behavior, while module customization is expected to follow the installed source code because modules extend the application codebase. Therefore, local source discovery is mandatory before implementation.

## Discovery protocol
Before generating code, run:

```bash
bash patches/probe_warehouse_documents_contract.sh
```

The generated report is:

```text
storage/app/warehouse-documents-contract-report.md
```

The AI agent must inspect this report and decide:

1. Does Core expose a `Mediable` Resource contract?
2. Which model trait is required for media/attachments?
3. Which frontend record-tab component is used by existing resources?
4. Which route pattern loads/uploads/deletes files?
5. Is the correct domain feature generic media or CRM Documents?
6. Does any pivot table require real inverse relation methods like Notes?
7. Are permissions needed for view/upload/delete attachments?

## Expected implementation candidates

### Candidate A — Generic attachments/media
Use this if Core exposes media endpoints and components.

Likely generated pieces:

```text
Warehouse Resource implements Mediable
Warehouse model uses required media trait
WarehousesView imports Core/Media record tab components
Warehouse docs record upload/delete permissions and routes
SmartDocs records validation matrix
```

### Candidate B — CRM Documents association
Use this only if existing Contacts/Companies/Deals profile pages associate `Document` records through resource association panels.

Likely generated pieces:

```text
Warehouse model documents() relation
Document model warehouses() concrete inverse relation
Warehouse Resource associateable contract
Warehouse detail tab for associated documents
Document validation accepts via_resource=warehouses
```

## Do not do

- Do not guess component names.
- Do not add `withTimestamps()` unless the pivot schema has timestamps.
- Do not use `resolveRelationUsing()` for write-synced associations.
- Do not mix CRM Documents and generic attachments in one step.
- Do not build a UI tab before confirming the API route and backend contract.

## RAG tags

```text
warehouse documents discovery
resource attachments contract
mediable resource contract
core record tab components
documents vs media distinction
notes integration lessons
pivot timestamp rule
concrete inverse relation rule
```
