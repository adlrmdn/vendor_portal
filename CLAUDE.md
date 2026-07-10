# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Development
```bash
composer run setup       # First-time setup: install deps, generate key, migrate, build frontend
composer run dev         # Run all dev processes concurrently (server + queue + log tail + vite)
php artisan serve        # Run Laravel dev server only
npm run dev              # Run Vite frontend only
```

### Testing
```bash
composer run test                              # Clear config cache, then run full test suite
php artisan test                               # Run all tests
php artisan test --filter TestClassName        # Run a single test class
php artisan test --filter testMethodName       # Run a single test method
```
Tests use SQLite in-memory (`:memory:`), sync queue, and array drivers — no external services needed.

### Code Style
```bash
./vendor/bin/pint        # Laravel Pint (PHP code style fixer)
```

### Database & Cache
```bash
php artisan migrate                     # Run pending migrations
php artisan migrate --seed              # Migrate and seed
php artisan optimize:clear              # Clear all caches
php artisan view:clear                  # Clear compiled Blade views
```

### Docker
PHP runs **inside the container** — there is no host `php`/`composer`. Run artisan/PHP via Docker:
```bash
docker-compose up -d --build            # Build and start (serves on port 8080)
docker-compose exec app php artisan migrate --seed
docker-compose exec -T app php -l <file> # Lint a PHP file
docker-compose logs -f app
```

### D365 Sync (ETL)
```bash
php artisan d365:sync-orders                                  # Pull fabric POs (PurchPoolId Fab-Local/Fab-Import)
php artisan d365:sync-subcon-orders --company=mpg             # Pull subcon vendors' POs into subcon work orders
php artisan d365:sync-subcon-orders --dry-run --vendor=V0246  # Report only, single vendor
```
`--company` is effectively required for subcon: vendor account codes are company-scoped in D365 (the same code maps to different legal vendors across `mpg`/`mpr`).

## Architecture

### Stack
- **Backend**: Laravel 12.x / PHP 8.2+
- **Frontend**: Blade templates + Bootstrap 5, compiled with Vite + Tailwind CSS 4
- **Database**: PostgreSQL (production), SQLite (dev/Docker default)
- **External DB**: Read-only `people_function` connection (PostgreSQL) for employee/approver lookups
- **PDF**: `barryvdh/laravel-dompdf`
- **QR Codes**: `SimpleSoftwareIO/QrCode` — stored as PNGs in `storage/app/public/qrcodes/`
- **Excel/PDF import**: `maatwebsite/excel` + `smalot/pdfparser`

### Role-Based Access
The app spans **two business domains** — *fabric* (the original vendor portal) and *subcon* (subcontractor CMT work orders). Roles live directly on `User.role` (no Spatie layer despite the package being installed):
- `admin` / `fabric_admin` → fabric admin
- `fabric_vendor` → fabric vendor
- `subcon_admin` → subcon admin
- `subcon_vendor` → subcon vendor

`Vendor.type` (enum `fabric|subcon`) classifies vendors per domain. `User` exposes helpers (`isFabricAdmin()`, `isSubconVendor()`, `hasFabricAccess()`, `hasSubconAccess()`, etc.). The `CheckRole` middleware (alias: `role`) enforces access. `/`, `/home` route each role to its dashboard via `match`. Routes are split into **four** prefix groups in `routes/web.php`:
- `/admin/*` → `AdminController`, `/vendor/*` → `VendorController` (fabric)
- `/subcon/admin/*` → `SubconAdminController`, `/subcon/vendor/*` → `SubconVendorController`

Within a domain, the admin/vendor controllers mirror each other's actions. Admin views give cross-vendor visibility; vendor routes are implicitly scoped to the authenticated user's `vendor_id`.

### Data Model
```
Vendor (UUID PK)
  └── User (role: admin|vendor, vendor_id nullable for admins)
  └── PurchaseOrder (status: pending|processing|completed|cancelled)
        └── PoItem (UUID PK, unit: YD|M|KG, status, underdelivery%, overdelivery%)
              └── Roll (UUID PK, roll_number, sequence, length_yd|length_m|weight)
        └── PackingSlip (pdf_path, item_ids jsonb)

Vendor (type: fabric|subcon)
  └── SubconOrder (order_number = D365 PO#, status, job_trans_status, distribution_id)
        └── SubconOrderItem (item_number, quantity, unit default PCS)
        └── SubconCuttingReport (prod_id, size, cutting_qty, gramasi)  # one row per production line
```
All primary keys are UUIDs (`$incrementing = false`). UUIDs are assigned in each model's `creating` boot hook.

### Roll Processing Engine
`VendorController@saveItemRolls` is the most complex method in the app. Key invariants:
- **Roll number format**: `{PO_NUMBER}-{ITEM_NUMBER}-{sequence:03d}`
- **Unit-to-column mapping**: `YD` → `length_yd`, `M` → `length_m`, `KG` → `weight`
- **Re-sequencing**: on save, first deletes marked rolls + their QR files, then re-indexes remaining rolls to maintain a continuous `sequence`
- **Transactions**: all mutations are wrapped in `DB::beginTransaction()`

### Tolerance & Partial Shipment Flow
1. Vendor submits a tolerance amendment or partial shipment request → stored in `ToleranceAmendmentRequest`
2. Email sent to approver resolved from `Setting::getValue('Approval')`, which is a badge number looked up in the external `people_function.employees` table. Falls back to `Setting::getValue('admin_notification_email')`
3. Approver clicks a **signed URL** → `ApprovalController@approveAmendment` / `declineAmendment`
4. On approval + vendor-triggered implementation → `PoItem@splitToPartialShipment`:
   - Creates a "shadow" item with batch suffix `-P2` (increments if collision) holding the remaining quantity
   - Updates the current item's quantity to what was delivered and marks it `completed`

### Tolerance Calculation
`PoItem` groups all items sharing the same `(po_id, item_number)` as a logical unit. Min/max limits are calculated globally across all sibling items, then subtract what other completed siblings already delivered. `Setting::getValue('default_underdelivery', 3.00)` and `default_overdelivery` apply when no per-item tolerance is set.

### D365 / External Read-Only Databases
Beyond the local app DB, the app reads from external connections (configured in `config/database.php`, credentials in env):
- **`people_function`** — employee/approver lookups (fabric tolerance flow).
- **`vsm`** — read-only garment production master, used by `SubconProductionService`. **Joins are on `PLMId` / `ProductionGroup` only, never `dataAreaId`** — these tables live under inconsistent legal entities (`mpg`/`mpr`/`mti`), so joining on company would return nothing.

Two artisan ETL commands pull confirmed POs from the D365 OData API (OAuth client-credentials, token cached) and materialise them locally:
- `SyncD365Orders` (`d365:sync-orders`) — fabric POs → `purchase_orders` + `po_items`.
- `SyncD365SubconOrders` (`d365:sync-subcon-orders`) — subcon vendors' POs → `subcon_orders` + `subcon_order_items`. The D365 PO number is used verbatim as the work-order number.

Both follow the same shape: fetch headers (filtered by pool/vendor + Confirmed + recency), validate against existing local rows, then insert in a transaction.

### Subcon Module
`SubconProductionService::forPo($poNumber)` resolves a subcon CMT PO to its garment production detail in `vsm`: the PO line only carries a generic "Item Jasa CMT" service item, so the real garment is reached via `po_lines.PLMId → plm_activity.ProductionGroup → production_group_lines` (per-size). Returns `[]` (best-effort) if VSM is unreachable or the PO has no PLM link.

**Staged approval workflow** (`SubconOrder.workflow_stage`): the vendor portal is gated through `cutting → cutting_review → gramasi → gramasi_review → waiting_distribution → labels → completed`. Stage 1 the vendor submits the **cutting report** (qty/size) for approval (`submitCuttingReport`); on approval the "original mechanism" starts (D365 cutting-qty sync). Stage 2 the vendor enters **gramasi (kg) + a single `blister_capacity`** (one value across all sizes, stored on the order) and submits (`submitGramasi`); gramasi syncs to D365 on save. On gramasi approval, label printing unlocks and the vendor can `completeOrder`. Approvals run through `SubconApprovalController`, reachable **both** in-app (subcon admin Approve/Reject panel) and via **signed-URL links in the approval email** (`subcon.approve`/`subcon.decline`, `{order}/{gate}` where gate ∈ `cutting|gramasi`). `printPackagingLabels` and the stage forms are all gated by `SubconOrder` helpers (`canEditCutting/canEditGramasi/canPrintLabels/canComplete`). The shared `subcon/partials/production-detail` partial takes a `$mode` of `cutting|gramasi|view` to control which column is editable.

**Cutting gate = "calculate + approve" (merged).** The cutting approval is *not* a bare approve: the approver enters per-fabric **consumption** (Fabric Sent + Cons. Plan) and the portal derives, per the agreed spreadsheet schema:
- `cutt_plan = ROUNDDOWN(fabric_sent / consumption_plan, 0)`
- `actual_consumption = (fabric_sent − retur_kain) / total cutting qty` — **only `retur_kain`** is subtracted; the other three waste columns (short_roll/sisa_kain/kepala_kain) are still captured/stored but no longer reduce consumption
- `overconsumption = (actual_consumption − consumption_plan) / consumption_plan` (stored as a ratio; the unrounded actual_consumption is used, so e.g. 1.2325 → 2.71%)

- `deduction` (IDR) = `max(0, actual_consumption − 1.03 × consumption_plan) × total cutting qty × fabric_price` — charged **only when overconsumption > 3%** (the 3% is a tolerance; under-consuming is never charged)

The **four waste columns (Short Roll / Sisa Kain / Kepala Kain / Retur Kain) are vendor-entered on the cutting report but overridable by the approver** on the approval form — a submitted waste value wins, otherwise the stored value is kept. All figures are snapshotted onto `subcon_fabric_reconciliations` (upsert by `(order_id, label)`, writing the waste columns too now that they're editable). Display uses min-2/max-4 decimals for the two **consumption** figures (Cons. Plan, Actual Cons.) and fixed 2 decimals for everything else (money/waste columns).

**Fabric price + currency** (`SubconProductionService::resolveFabricPricing`): the deduction needs an IDR unit price per fabric. The price must be **per unit metre/yard/kg**, so it is always `LineAmount ÷ quantity` — **never `PurchasePrice`/`po_items.unit_price`**, which D365 quotes per a price-unit basis (1/100/1000) and over-states. It is resolved **local-first, then direct**: local `po_items` (`total_price ÷ quantity`) + `purchase_orders.currency` (already synced by the fabric portal — fast, no external call) is checked first; only fabrics with no local match trigger a **best-effort, cached** direct D365 fetch (`D365JobTransactionService::fetchFabricPricing` → `PurchaseOrderLinesV2`, unit price = `Σ LineAmount ÷ Σ OrderedPurchaseQuantity`, `CurrencyCode` off the line); the VSM-derived `LineAmount ÷ qty` (no currency) is the final fallback. VSM (`po_lines`/`po_headers`) exposes **no currency**, so the resolved price's currency is shown as a hint and the field stays **editable** — the admin converts a non-IDR source to IDR by hand (no FX-rate source exists in the app). The price prefill is overridden by the admin's saved value. Persisting consumption + advancing the stage is **one atomic transaction** — there is no standalone "save consumption" step. Both entry points carry it:
- **No-login signed email link** — the cutting email's Approve link opens `subcon/cutting-approval-form` (`approveSigned` renders it for gate `cutting`); the form POSTs to the signed `subcon.approve.cutting.submit` (`approveCuttingSubmit`). This preserves the old HO-style no-login consumption+approve UX, relocated to the cutting gate.
- **In-app admin panel** — the order-view Approve button submits the consumption inputs (`approveInApp`, gate `cutting`). The **Approvals tab** deep-links cutting to the order page instead of one-click approving (Reject stays one-click; gramasi keeps one-click Approve).

The shared calc/persist lives in `SubconConsumptionService::persist()`; `applyCuttingApproval()` in `SubconApprovalController` wraps it + the stage transition. The **final/HO stage is approve-only** — `QcApprovalController@hoApprove` *reads* the snapshot (`SubconProductionService::fabricLinesWithData()`) and pushes it to the QMS-owned `packaging_project_fabric_lines` (never re-enters or recomputes it). The `consumption-input` partial is inputs-only (no `<form>`); callers wrap it in the approving form.

**Emails** (`SubconApprovalRequestMailable` → approver, `SubconStageStatusMailable` → vendor): SMTP, `from: rpa@megaperintis.co.id`, sent synchronously (try/catch — never blocks the submit/approval). Recipients resolve via `Setting` `subcon_approver_email` / `subcon_notification_email`, both **defaulting to `adil.ramadhan@megaperintis.co.id` for testing**. NOTE: the SMTP account is a Gmail (`rpa.megaperintis@gmail.com`); the `rpa@megaperintis.co.id` From only survives if it is a verified "Send mail as" alias on that account.

**Cutting report → D365 job-transaction sync** (`D365JobTransactionService`): only *changed* rows are synced. `D365JobTransactionService` `startJobs()` for the production group then `syncReportToD365()` PATCHes `JobTransactionLinesDetails` over OData. D365 failures are caught and surfaced as a soft warning — the local save always succeeds. (`SubconOrder.job_trans_status` is the older sync state machine, superseded for gating by `workflow_stage`.)

**Packaging labels** (`printPackagingLabels` in both subcon controllers): requires `SubconOrder.distribution_id`. Fetches D365 DTLines (`fetchDTLines`) + warehouses (`fetchWarehouses`), groups by `PackingCode`/`StoreID`, multiplies qty × per-size `gramasi` (from local cutting reports, default 157.5) for box weights, then streams a DomPDF (`subcon/pdf/packaging-labels`). Note: D365 DTLine rows are sparse — access keys with `??`, never `?:`, and sanitise `order_number` (contains `/`) before using it as a download filename.

### Settings
`Setting::getValue($key, $default)` is the single lookup point for runtime configuration (e.g. `default_underdelivery`, `default_overdelivery`, `Approval`, `admin_notification_email`). Managed via the Admin settings UI.

### Views Layout
- `resources/views/layouts/admin.blade.php` — admin shell
- `resources/views/layouts/vendor.blade.php` — vendor shell
- `resources/views/admin/` and `resources/views/vendor/` — fabric role-scoped pages
- `resources/views/subcon/{admin,vendor}/` — subcon role-scoped pages; `resources/views/subcon/pdf/` — DomPDF templates (packaging labels); `resources/views/subcon/partials/production-detail.blade.php` — shared VSM production detail
- `resources/views/vendor/pdf/packing-slip.blade.php` — DomPDF template for packing slips
- `resources/views/custom-pagination.blade.php` — overrides default Bootstrap pagination to fix SVG icon rendering
