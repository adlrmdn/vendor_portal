# Subcon Official Report (Berita Acara) — Design

Status: approved by user, pending spec self-review sign-off
Date: 2026-09-22

## 1. Purpose

Add Process (`ADD-Was`/`ADD-Embro`/`ADD-Sab`/`ADD-Pleat`/...) operations currently
have no downstream sign-off/invoicing path of their own — the admin enters
good/reject qty and it syncs to D365 (`SubconAddProcessController`,
`SyncSubconAddProcessToD365`), full stop. There is no approval, no deduction
claim, and nothing feeds Invoice/Debit Note RPA.

This adds an **Official Report (Berita Acara)** per Add Process operation: a
lightweight two-signature (MD Production → Director) sign-off, modeled on the
existing QC/HO/Director inspection report but with the QC/Factory
Representative inspection stage removed entirely (Add Process has no physical
inspection step — the admin already reports good/reject directly).

## 2. Scope key

**One report per `(production_group, operation)`**, not per production group.

A production group can carry more than one Add Process operation (e.g. both
`ADD-Was` and `ADD-Embro`), each with its own AddPro-pool PO, vendor, and —
per the user — its own separate invoice later. Reports, approvals, and
deductions must stay independent per operation: approving/rejecting one must
never affect the other, and each closes independently once its own matched PO
is invoiced (mirroring the rule `SubconAddProcessController::submit()`
already enforces for reporting itself).

## 3. Storage

New local table, own migration, **not** the `qms` connection. Every row in
`qms.packaging_project_sessions` today is created by the external QC Console
app when an inspector starts an inspection; this report has no inspection and
must never originate a row there. A new locally-owned table avoids the column-
ownership contract entirely.

### `subcon_official_reports`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | boot-hook assigned, matches every other UUID model in this app |
| `production_group` | string | indexed |
| `operation` | string | raw D365 Operation value, e.g. `ADD-Was` |
| `status` | string | `pending_ho` \| `pending_director` \| `completed` — row is created at first send, never persisted in a pre-send "draft" state (see §5) |
| `revision_count` | unsigned int, default 0 | incremented every time Director rejects |
| `matched_po_number` | string, nullable | snapshot at send time |
| `matched_po_vendor_code` | string, nullable | snapshot at send time |
| `matched_po_vendor_name` | string, nullable | snapshot at send time |
| `cut_qty` | unsigned int | snapshot at send time (`cutQuantitiesForGroup()`'s total for this operation's sizes) |
| `order_qty` | unsigned int | snapshot at send time |
| `good_qty` | unsigned int | snapshot at send time (`SubconAddProcessReport` sum) |
| `reject_qty` | unsigned int | snapshot at send time |
| `sales_price` | decimal(15,2), nullable | prefilled from D365, admin-editable |
| `sales_price_source` | string, nullable | `d365` \| `manual` — informational, mirrors the fabric-price-hint pattern |
| `allowed_limit` | unsigned int | `floor(cut_qty * 0.01)` |
| `exceeding_reject_qty` | unsigned int | `max(0, reject_qty - allowed_limit)` |
| `penalty_price` | decimal(15,2) | `sales_price * 0.70` |
| `reject_penalty` | decimal(15,2) | `exceeding_reject_qty * penalty_price` |
| `additional_deduction` | decimal(15,2), default 0 | manual, admin/HO-editable |
| `remarks` | text, nullable | |
| `ho_approval_signature` | string, nullable | `"Digitally Signed: {name} <{email}>"` style, matching existing HO/Director rendering |
| `ho_approved_at` | timestamp, nullable | |
| `director_approval_signature` | string, nullable | |
| `director_approved_at` | timestamp, nullable | |
| `director_decline_reason` | text, nullable | cleared on next send |
| `created_at`/`updated_at` | timestamp | |

Unique index on `(production_group, operation)`.

No separate revisions/history table. Audit trail reuses `SubconJobLog`
(`job_type = "official_report:{operation}"`), the same mechanism
`SyncSubconAddProcessToD365` already logs through — consistent with how the
rest of this module keeps history, and avoids a bespoke schema for something
`SubconJobLog` already does.

## 4. Workflow states

```
(admin sends, row created) --> pending_ho --(HO approves)--> pending_director --(Director approves)--> completed
                                    ^                              |
                                    |                              v
                                    +--------(Director rejects)----+
                                        (revision_count++, decline_reason set)
```

Before the first send there is no row at all — the deduction/remarks fields
the admin edits on the Add Process page (§7) are plain form inputs, not a
persisted draft; the row is created directly in `pending_ho` on send.

- **pending_ho**: sent to MD Production, awaiting their approval. A
  Director-rejected report also lands here (that's the "revision" state — no
  separate `needs_revision` status; `revision_count > 0` is what
  distinguishes a first-time send from a resend in the UI).
- **pending_director**: MD Production approved, sent to Director.
- **completed**: Director approved. Terminal. RPA queued (§10).

Director decline never returns to QC (there is no QC stage here) and never
skips straight back to Director — it always goes through MD Production again,
mirroring the existing chain's "Director reject → stage 2a (MD Production),
not QC" rule.

## 5. Gating to send (create/resend)

The "Send Report" action (and the resend action after a decline) is blocked
unless **both**:

1. **Cutting is approved.**
   - CMT (a `SubconOrder` exists for this `production_group`):
     `$cmtOrder->workflow_stage` is **not** `SubconOrder::STAGE_CUTTING` and
     **not** `SubconOrder::STAGE_CUTTING_REVIEW`. Any later stage
     (`gramasi`, `gramasi_review`, `waiting_distribution`, `labels`,
     `completed`) qualifies.
   - In-house (no `SubconOrder`): `SubconProductionService::cutQuantitiesForGroup($prg)`
     returns at least one row with a non-null good qty for a size this
     operation's lines cover — i.e. the D365 cut-qty job sync isn't blank.
2. **Not yet invoiced**: the operation's own matched AddPro PO (via
   `matchAddProcessPoForOperation()`) is not `Invoiced` — same rule
   `SubconAddProcessController::submit()` already enforces for reporting.

Both checks are re-verified server-side on the send/resend POST (not just
hidden in the UI), same defense-in-depth pattern
`SubconAddProcessController::submit()` uses today.

## 6. Delivery mechanism

Laravel **signed routes** (`signed:relative` middleware), same pattern as
`SubconApprovalController` (fabric tolerance / cutting / gramasi links) —
**not** the QC/HO/Director controller's random-UUID bearer-token scheme. That
scheme exists specifically because the QC Console (an external app) generates
its own emails and Laravel can't sign a URL it didn't create; here,
vendor_portal originates every email itself, so a signed route is simpler and
needs no extra token column/index.

Every mutating action follows the same prefetch-safe GET-renders/POST-mutates
split as the rest of this app's email links (mail-scanner prefetch
protection) — a GET here only ever renders a confirm/edit form.

Routes (all under `subcon.official-report.*`):

| Route | Method | Purpose |
|---|---|---|
| `subcon.official-report.ho-approve` | GET | signed; renders the HO approval form (editable deduction/remarks) |
| `subcon.official-report.ho-approve.submit` | POST | signed; applies HO approval, emails Director |
| `subcon.official-report.director-approve` | GET | signed; renders read-only Director review |
| `subcon.official-report.director-approve.submit` | POST | signed; applies Director approval, queues RPA (§10) |
| `subcon.official-report.director-decline` | GET | signed; renders decline-reason form |
| `subcon.official-report.director-decline.submit` | POST | signed; applies decline, routes back to `pending_ho`, emails HO |

There is no HO-decline route — only the Director can decline (§4); MD
Production's only actions are approve-and-forward (first send) or
revise-and-resend (after a Director decline), both via the same
`ho-approve`/`ho-approve.submit` pair.

In-app equivalents live on the existing admin-gated `/subcon/admin/*` group
(auth + `role:admin,subcon_admin`), reusing the same underlying
approve/decline methods the signed routes call — same dual-entry-point
pattern the cutting gate already uses (§ "Cutting gate" in CLAUDE.md).

Recipient emails reuse **existing** settings — `qc_ho_approver_email` (MD
Production) and `qc_director_approver_email` (Director) — rather than new
`Setting` keys, since these are the same two roles the QC/HO/Director chain
already emails.

## 7. Admin UI

- **Entry point**: a "Send Report" button on each operation's card on the
  Add Process page (`resources/views/subcon/admin/add-process/index.blade.php`),
  next to the existing per-operation meta (Job Transaction / PO / Vendor /
  Description). Only rendered when §5's gates pass; before any row exists the
  admin edits deduction/remarks inline and clicks "Send Report" to create it;
  once a row exists the card instead shows its live status (pending HO /
  pending Director / completed).
- **Report Validation tab** (`SubconAdminController::reportValidations()` /
  `report-validations.blade.php`): union in `subcon_official_reports` rows
  where `status = 'pending_ho'` and `revision_count > 0` (i.e. a Director
  rejection awaiting MD Production's revision), alongside the existing
  qms-session rows. A small type badge ("QC/HO/Director" vs. "Add Process")
  distinguishes the two; each row's action link routes to the right form.
  Two different backing tables, one page — same "union sources, one row
  shape" pattern `addProcessPurchaseOrdersForGroup()` already uses.
- **Director Approvals tab** (`directorApprovals()` /
  `director-approvals.blade.php`): same union, for `status = 'pending_director'`
  rows.
- No new nav item, no new list page.

## 8. Deduction formula

Per-operation port of `QcReportPdfService::calculateDeductions()`'s formula
shape (itself a 1:1 port of the QC Console's `calculations.ts`), scoped to
this one operation's own figures rather than a cross-stage sum:

```
cut_qty          = cutQuantitiesForGroup(prg) total for this operation's sizes
reject_qty       = SubconAddProcessReport sum of reject_qty for this (prg, operation)
allowed_limit    = floor(cut_qty * 0.01)
exceeding_reject = max(0, reject_qty - allowed_limit)
penalty_price    = sales_price * 0.70
reject_penalty   = exceeding_reject * penalty_price
total_deduction  = reject_penalty + additional_deduction
```

No "barang hilang" (lost items) term — that concept belongs to the main
garment QC inspection (`session.report_lines.barang_hilang`), which this
report has no equivalent of.

`sales_price` is fetched via a new small method (mirroring the QC Console's
Rust `fetch_odata_sales_price`): D365 OData `ReleasedProductMastersV2`,
filter `ItemNumber eq '{PLMId}'`, field `SalesPrice`. Best-effort — on fetch
failure, or when the production group has no PLMId at all (standalone/
in-house groups can be PLM-less per `addProcessPurchaseOrdersForGroup()`'s
docblock), the field is left blank and the admin fills it manually before
sending; `sales_price_source` records which happened. Once set, it stays
editable on every subsequent revision — same resilience pattern the existing
fabric-price prefill already uses.

`additional_deduction` and `remarks` are editable by the admin (before/at
first send) and by MD Production (on the HO approval form, including on a
revision after a Director decline) — never by the Director, whose review is
read-only, matching the existing Director stage's "no recomputation" contract.

## 9. Report content (PDF)

Reuses the app's existing DomPDF infra (`barryvdh/laravel-dompdf`), new
template under `resources/views/subcon/pdf/official-report.blade.php`.

- Title: **Official Report (Berita Acara)** only — no "2nd Final Inspection"
  line, since there is no inspection.
- **1. Overview**: Vendor (matched PO's vendor), PO Number (matched PO),
  Article Name, Qty Order, Available Qty, Delivery Plan, Season, PLM ID,
  Production Group. Drops the inspection-only fields (Inspection Date, Total
  Store, Store Inspected) that appear on the QC report this is modeled on.
- **2. Production Yield Matrix**: Order Qty, Cut Qty, Good Qty, Reject Qty —
  this operation only, no per-stage grid (Add Process tracks one reject
  figure per operation, not six).
- **3. Deduction & Remarks**: the formula breakdown from §8 (cut qty, allowed
  limit, exceeding qty, penalty price, reject penalty, additional deduction,
  total) plus the remarks text.
- **4. Conclusions**: exactly two blocks — **APPROVED BY** (HO signature,
  "MPG HO - MD Production") and **AUTHORIZED BY** (Director signature,
  "Awaiting Authorization" until signed) — reusing the same
  digitally-signed rendering style as the existing HO/Director PDF blocks. No
  QC/Factory Representative block.

## 10. On Director approval — RPA queueing

New `RpaQueueService` methods, parallel in shape to `queueInvoice()`/
`queueDeduction()` but **not sharing code with them** (different entity
scheme, no qms `$project` object to read from):

- `queueOfficialReportInvoice(SubconOfficialReport $report, string $signedDoc)`
  → `rpa_type = 'invoice_addprocess'`, `entity_id = "{production_group}:{operation}"`.
  `amount = unit_price × good_qty`, where `unit_price` is the matched AddPro
  PO line's own price (`LineAmount ÷ quantity` off `vsm.po_lines`, **never**
  `PurchasePrice`/`po_items.unit_price` — same per-unit-price principle
  `SubconProductionService::resolveFabricPricing()` already applies to
  fabric). `resolveAddProcessLineNumber()` currently returns only the line
  number; it gains a sibling (or an extra return field) that also resolves
  this unit price for the same matched line. Same signed-doc S3 offload
  pattern (`offloadSignedDocToS3`) as the existing invoice queueing.
- `queueOfficialReportDeduction(SubconOfficialReport $report, string $signedDoc)`
  → `rpa_type = 'deduction_addprocess'`, same entity scheme, only queued when
  `total_deduction > 0` (mirrors the existing "only queued when > 0" rule).

Both are best-effort/never-throwing, same contract as every other
`RpaQueueService` method. **Out of scope for this change**: the
automaton-side listeners that would consume `invoice_addprocess`/
`deduction_addprocess` (mirroring `addprocess_listener.py`'s relationship to
`goods_receive_addprocess`) live in a separate repo
(`~/automaton/pw_service/...`) and are not part of this plan — rows will
queue but sit unconsumed until that listener exists. This is called out
explicitly so it isn't mistaken for a bug during testing.

## 11. Error handling

- All state-transition writes wrapped in `DB::transaction()`, matching every
  other mutation in this module.
- Gating checks (§5) re-verified server-side on every send/resend POST, not
  just hidden client-side.
- D365 sales-price fetch: try/catch, never blocks sending — see §8.
- RPA queueing: try/catch + `report()`, never blocks the Director-approve
  response — see §10, matches `queueAddProcessGoodsReceive()`'s contract.
- Email sending: try/catch, never blocks the state transition — matches
  `SubconApprovalRequestMailable`/`SubconStageStatusMailable`'s existing
  "never blocks the submit/approval" contract.

## 12. Testing

- Feature tests for: gating (blocked when cutting not approved / in-house cut
  qty blank / PO invoiced), the full happy path (send → HO approve → Director
  approve → RPA rows queued), Director decline → revision → resend →
  approve, and the deduction formula against known inputs (including the
  zero-reject / zero-deduction case and the PLM-less sales-price-blank case).
- No changes needed to the existing `--filter=Subcon` suite's baseline; new
  tests are additive.

## 13. Out of scope (explicitly)

- The automaton-side RPA listeners for `invoice_addprocess`/
  `deduction_addprocess` (separate repo, separate change).
- Any change to the existing QC/HO/Director inspection workflow itself
  (`QcApprovalController`) — this is a parallel, independent flow that only
  *shares UI real estate* on two admin tabs (§7).
- A "barang hilang" (lost items) equivalent for Add Process.
- Splitting the HO step into a two-stage "Review & Approve" /
  "Validate & Send" pair like the main workflow's Material-Flow-gated split —
  there is no equivalent gate here, so one HO approval step suffices.
