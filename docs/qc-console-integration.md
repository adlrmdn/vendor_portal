# QC Console ⇄ Vendor Portal — Integration Setup (handoff)

Single reference for the portal↔QC-Console integration: architecture, DB config,
schema, routes, the approval flow, and the fabric-lines publish/pull contract.
Send this to the console-side team/session. Portal side is implemented as of
**2026-07-06**; **console action items are in §8**.

---

## 1. Architecture
- **Integration method: shared database (Method B), NOT an API.** The portal and
  the QC Console (Tauri/Windows app, separate repo) talk through a shared
  PostgreSQL **QMS database** on the same AWS server.
- The **console generates the approval emails** (so the portal cannot HMAC-sign
  URLs). It writes a random-UUID bearer token onto its session row and puts it in
  the link; the portal validates the token and writes decisions back to rows the
  console polls (~8s).
- The portal is the **host/provider**: it renders the approval pages and now also
  **publishes fabric consumption data** the console pulls.

## 2. Connection config (portal side)
Connection name **`qms`** in `config/database.php`, driven by env:

```
QMS_DB_URL=            # optional full DSN; else the parts below
QMS_DB_HOST=
QMS_DB_PORT=5432
QMS_DB_DATABASE=qms
QMS_DB_USERNAME=
QMS_DB_PASSWORD=
# driver=pgsql, search_path=public, sslmode=prefer (hardcoded)
```
Base URL used in the console's links (`WEB_SERVICE_URL` on their side):
**`https://vendor-portal.megaperintis.co.id`**

## 3. Routes (portal endpoints the console links to)
| Route name | Method | Path | Purpose |
|---|---|---|---|
| `qc.approve` | GET | `/qc/approve/{token}` | Stage-1 factory/vendor confirm (writes signature) |
| `qc.reject` | GET | `/qc/reject/{token}` | Stage-1 reject |
| `qc.ho-approve` | GET | `/qc/ho-approve/{token}` | Stage-2 HO form (calc+approval) |
| `qc.ho-approve.submit` | POST | `/qc/ho-approve/{token}` | Stage-2 HO commit |
| `qc.ho-decline` | GET | `/qc/ho-decline/{token}` | Stage-2 HO reject |

All resolve the row via `approval_token` (uuid), `Str::isUuid()`-guarded → bad
token returns a friendly page, never a 500.

## 4. Approval flow & column ownership — `packaging_project_sessions`
Stage 1 (`approve`) writes the signature, then chains an email to the HO approver.
Stage 2 (`hoApprove`) publishes fabric lines (§5), writes deduction lines, then
the HO signature — all guarded, never fatal.

**Column ownership (do not violate):**
| Column | Owner | Notes |
|---|---|---|
| `factory_representative` | **Console** | inspector's manual name — portal NEVER writes it |
| `approval_signature` | Console (portal writes) | `Digitally Signed: <email> [UTC+07:00: …]` |
| `ho_approval_signature` | Console (portal writes) | `Digitally Signed:` / `Rejected:` prefix branches |
| `approval_token`, `approval_email`, `approved_by`, `approved_at`, `approval_source`, `approval_status` | **Portal** | added by portal migration `2026_07_01_000001`, nullable, guarded |

Console detects: approval via `approval_status='approved'` + `approval_signature`;
rejection via `approval_status='rejected'`; HO decision via the `ho_approval_signature` prefix.

## 5. Fabric-lines publish/pull — `packaging_project_fabric_lines`
**Why:** the cutting report (consumption figures) is ready BEFORE the console's
project exists. So the portal publishes; the console pulls by `production_group`.

**Portal migration `2026_07_06_000003`** (qms conn, guarded, additive) makes the
console's table publishable:
| Change | Detail |
|---|---|
| add `production_group` (indexed, nullable) | **the pull key** |
| add `overconsumption`, `fabric_price`, `deduction` (nullable) | figures it couldn't store before |
| **relax `project_id` → NULLABLE** | `ALTER … DROP NOT NULL` — lets a row be *staged* pre-project |

**Row lifecycle (portal writes via `SubconFabricLinePublisher::publish`):**
1. **Cutting approval** → upsert per fabric, key `(production_group, label)`,
   `project_id = NULL` (staged). `created_by = 'web_portal'`.
2. **HO approval** → re-publish (figures may be revised) and **set `project_id`**.
   `project_id` is only ever SET, never nulled → a console-set value survives.

`retur_kain` (portal) → **`return_kain`** (QMS). NOT-NULL numerics coalesced to 0.

## 6. Email routing (portal `Setting` keys)
- Stage-2 HO email recipients: `qc_ho_approver_email`, **empty-aware fallback** to
  `subcon_cutting_approver_email` (a blank saved value returns `''`, not a default).
- Editable in **Subcon Admin → Workflow settings** ("Final Email Address(es)").

## 7. Portal migrations to apply on the QMS DB
Run against the `qms` connection (portal already scopes them):
- `2026_07_01_000001_add_qc_approval_columns_to_packaging_sessions` — approval/token/audit cols.
- `2026_07_06_000003_add_publish_keys_to_packaging_fabric_lines` — publish keys + relax project_id.

Both are guarded (`hasColumn`/`hasTable`) and re-runnable.

## 8. ✅ Console-side action items
1. **Read fabric lines by production_group** when starting a packaging project:
   ```sql
   SELECT * FROM packaging_project_fabric_lines WHERE production_group = :pg;
   ```
2. **Adopt** them to your project so your existing read-by-`project_id` PDF path
   keeps working unchanged:
   ```sql
   UPDATE packaging_project_fabric_lines
   SET project_id = :project_id
   WHERE production_group = :pg AND project_id IS NULL;
   ```
3. Continue reading `overconsumption` / `fabric_price` / `deduction` if you want
   them on the QMS side (now available).
4. **⚠️ Deploy ordering:** the portal has STOPPED the old `project_id`-only insert
   at HO time. Ship this pull+adopt **before/with** the portal deploy, or fabric
   lines won't link to projects created in the gap.

## 9. Portal-side guarantees
- Every QMS write is `hasTable`/`hasColumn`-guarded + try/catch → a missing
  column/table or unreachable QMS is logged and skipped, never blocks an approval.
- Publishing runs before the HO signature so the console sees fabric lines by the
  time the signature triggers PDF regen.
- The portal creates **no** console-owned tables; it only adds guarded nullable
  columns to existing ones.
