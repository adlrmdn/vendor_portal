# Subcon Vendor Portal — Material Flow / Material Return Development Notes

Reference for the "material returned to us by the subcon vendor" feature
built **2026-09-03**: attachments + quantity reconciliation on the vendor
portal, validated by a new **Material Flow** tab in `value_stream_ops`,
gating MD Production's "Validate & Send Approval" step. Portal side and
`value_stream_ops` side are both implemented as of this date.

---

## 1. Scope & terminology

Material the **subcon vendor returns to us** (unused/excess cut fabric or
accessory/trim) — **NOT** material we return to our own upstream fabric
supplier, which is a separate, unbuilt flow. This distinction is stamped
into every table/model/disk name touched by this feature (`material_prod_return`
S3 prefix, docblocks saying "to us" explicitly) — if a real return-to-supplier
feature is ever built, give it its own tables, don't extend these.

## 2. Architecture

- **Cross-repo, shared database (same pattern as the `qms` QC Console integration).**
  vendor_portal and `value_stream_ops` (separate repo) both connect to a `wms`
  Postgres database on the same shared AWS server as `vsm`/`qms`. **`wms`
  already existed in production** before this feature — it backs the
  handheld "Goods Receiver" scanner app's supplier goods-receipt flow
  (`good_receipt_headers`/`good_receipt_lines`, schema owned by that app's
  own Rust backend, source not in this workspace). This feature adds three
  new, independent tables to that same database — never touches the Receiver
  app's own tables.
- **Files**: S3 bucket `rpa-lake`, prefix `automaton/material_prod_return/`
  (disk `material_prod_return` in both repos' `config/filesystems.php`).
  Reuses the same EC2-instance-role access as the existing `rpa_lake` disk —
  no AWS credentials needed in `.env`.
- vendor_portal **owns and migrates** the shared `wms` tables (same
  ownership precedent as `packaging_project_remarks` in `qms`);
  `value_stream_ops` only ever updates `status`/`checked_*`/`qty_actual`.

## 3. Data model (`wms` database)

| Table | Owner (writes) | Purpose |
|---|---|---|
| `material_return_tasks` | vendor_portal creates; value_stream_ops flips to `checked` | One row per "Send to Material Flow" dispatch |
| `material_return_attachments` | vendor_portal | Delivery-note files (PDF/JPG/PNG) |
| `material_return_lines` | vendor_portal creates/updates `qty_declared`; value_stream_ops fills `qty_actual` | Per-item declared vs. actual quantity (fabric + accessory), upserted by `(order_id, item_type, label)` — a snapshot, not an append-only log |

`SubconProductionService::accessoryLinesForPo()` mirrors `fabricLinesForPo()`
but keeps the PCS-unit lines that method deliberately skips — real D365 item
names used as suggestions. Data quality varies (a PCS line on a fabric-pool
PO isn't always a true accessory — sometimes a pre-cut knit panel), so
free-typed rows are always allowed alongside the suggestions.

## 4. Gate: `QcApprovalController::hoSendApproval`

`MaterialReturnService::isReturnCheckPending($order)` blocks "Validate &
Send Approval" (Report Validation → Director) if:
- a dispatched task exists and isn't `checked`, **or**
- any attachment/line exists that was never linked to a task at all
  (`task_id IS NULL`).

The second condition is deliberate and closes a real incident (below):
attaching/declaring does **not** auto-dispatch to Material Flow — "Send to
Material Flow" stays a manual button press by MD Production — but an
un-dispatched declaration still blocks Director-send by itself, so nothing
can silently slip through un-checked.

**2026-09-03 incident (fixed same day):** the original design required an
explicit dispatch before anything blocked. MD Production attached a note to
a real order (`MPG/PO/2606/01342`) without pressing "Send to Material Flow"
→ no task existed → Validate & Send went through for real, notifying the
Director. Order was manually reverted (`ho_validation_signature` reset to
null, same shape as `directorDecline()`) and the gate logic above was added.

Window helpers on `SubconOrder`:
- `materialReturnVendorWindowOpen()` — any time up to Report Validation being sent.
- `materialReturnAdminWindowOpen()` — Final Approval through Report Validation only.

## 5. UI structure (as of the last redesign this session)

Three separate concerns, three separate partials — don't merge them back
into one card (an earlier version did, and had to be split back apart
after "why is there dual?" feedback):

- **Material Reconciliation** (`subcon/partials/fabric-reconciliation.blade.php`,
  renamed from "Fabric Reconciliation") — two table groups, **Fabric**
  (unchanged: Short Roll / Sisa Kain / Kepala Kain / Retur Kain) and
  **Accessory** (new: item + Qty in PCS, same "unit shown under value"
  visual pattern). Both editable together at the cutting-report stage,
  submitted in the same form (`fabrics_recon[]` / `accessories_recon[]`),
  persisted via the SAME upsert-by-label + prune-on-resubmit pattern.
  Fabric → `SubconFabricReconciliation`; Accessory → `MaterialReturnLine`
  (item_type=accessory). This is the vendor's only editable entry point for
  accessory quantities — it is intentionally **not** duplicated into the
  Final-Approval-stage material-return card.
- **Delivery Note Attachment** (`subcon/partials/delivery-note-attachment.blade.php`)
  — just the file upload + list. Placed under Blister/Sack Capacity on the
  admin page, under Blister Capacity on the vendor page, after Remarks on
  both.
- **Material Flow** (`subcon/partials/material-return.blade.php`, now
  minimal) — just the status badge + "Send to Material Flow" dispatch
  button (admin/HO only). Renders nothing if there's no task and no
  dispatch route.

Same three includes appear on: `subcon/vendor/orders/show.blade.php`,
`subcon/admin/orders/show.blade.php`, `qc/ho-approval-form.blade.php` (the
token-gated Final Approval / Report Validation page, reachable both in-app
and via the no-login emailed link — controller actions have `*Signed`
variants in `QcApprovalController` for that path).

## 6. value_stream_ops side

New **Material Flow** nav tab (`MaterialFlowController`), badge-driven
pending count (same pattern as subcon-admin's Director tab badge — no new
notification/push mechanism, this codebase has none). Per task: lists
attachments (vendor/MD-Prod attribution) and lines; "Mark as Checked"
requires an actual qty for **every** line first — this is the "corrective
measure": inventory's real count, pre-filled with the declared value but
freely editable/correctable, not just a confirm-yes/no.

## 7. Known gaps / not yet built

- Admin has no editable accessory-declaration surface of their own —
  `consumption-input.blade.php` (admin's separate fabric+consumption form,
  used at cutting-approval and again at ho-approval-form) does not yet have
  an Accessory group. Only the vendor-side `fabric-reconciliation.blade.php`
  does. Add if/when asked.
- `SubconAdminController::saveMaterialReturnLines` /
  `QcApprovalController::saveMaterialReturnLinesSigned` (+ their routes)
  are now unreachable dead code — the UI that called them (accessory
  declaration inside the material-return card) was removed when accessories
  moved into Material Reconciliation. Left in place rather than deleted
  given time constraints; safe to remove in a follow-up pass.

---

## 8. Backup guard — NOT YET IMPLEMENTED, notes only

**Do not build this now — this section is planning/documentation only,
raised so it isn't forgotten.**

**The gap:** per `vendor-portal-db-infra-recovery` (memory), several
hand-entered, non-D365-resyncable tables have **no backup path except
Estuary Flow CDC**, which requires a human with an Estuary dashboard
account — nothing on-box (no `flowctl`, no creds) can query or restore from
it programmatically. Confirmed list: `settings`, `rolls`,
`subcon_cutting_reports`, `subcon_fabric_reconciliations`,
`packing_slips`, `tolerance_amendment_requests`, `notifications`. This
feature adds `material_return_lines`/`tasks`/`attachments` (in `wms`, a
different database with its own unknown backup posture — not yet verified)
to that same category of risk.

**Why this matters (concrete incident, 2026-09-03):** while manually testing
this feature via `php artisan tinker`, a `SubconFabricReconciliation::updateOrCreate()`
call meant to seed test data landed on a **real, pre-existing row** for
order `MPG/PO/2608/01328` (label: "WOVEN 80% POLYESTER 20% COTTON
TC45XT75/116X78 90 GSM PLAIN SOLID SOFT TC DACRON WHITE OFF WHITE (YD)")
and silently overwrote real `short_roll`/`sisa_kain`/`kepala_kain`/`retur_kain`
values with fabricated test numbers. No backup existed to recover the
original figures — the row was reset to 0 (an honest placeholder, not a
guess) and the order's `remarks` flagged for vendor re-entry. See git
history / session notes around 2026-09-03 for the full incident.

**Candidate approaches (unevaluated, pick one when this is prioritized):**
1. **Scheduled `pg_dump`** of the primary DB (or just the no-resync-path
   tables) to S3 — closes the "no local backup, no RDS snapshot" gap
   directly. Needs a retention policy and a restore runbook.
2. **App-level audit/history table** (e.g. an `audit_log` capturing
   before/after JSON on every `UPDATE` to the sensitive tables via Eloquent
   model events) — cheaper to query for a single-row recovery like the
   incident above, doesn't require restoring a whole dump.
3. **Soft-guard on destructive test patterns** — e.g. a documented/enforced
   convention (or artisan command) for seeding test data against an
   **isolated fake order** rather than a real `SubconOrder`, so manual
   verification during development can't collide with production rows at
   all. Process fix, not a DB feature — cheapest, but relies on discipline
   rather than a safety net.

Any of these — or a combination — would have prevented or made trivially
recoverable the 2026-09-03 incident. None are built yet.
