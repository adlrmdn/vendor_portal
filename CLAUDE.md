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
```bash
docker-compose up -d --build            # Build and start (serves on port 8080)
docker-compose exec app php artisan migrate --seed
docker-compose logs -f app
```

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
Two roles live directly on `User.role` (enum: `admin`, `vendor`). There is no Spatie permission layer in use despite the package being installed. The `CheckRole` middleware (alias: `role`) enforces access. Routes are split into two prefix groups in `routes/web.php`:
- `/admin/*` → `AdminController`
- `/vendor/*` → `VendorController`

Both controllers mirror each other's roll-processing and packing-slip actions. The admin view gives cross-vendor visibility; vendor routes are implicitly scoped to the authenticated user's `vendor_id`.

### Data Model
```
Vendor (UUID PK)
  └── User (role: admin|vendor, vendor_id nullable for admins)
  └── PurchaseOrder (status: pending|processing|completed|cancelled)
        └── PoItem (UUID PK, unit: YD|M|KG, status, underdelivery%, overdelivery%)
              └── Roll (UUID PK, roll_number, sequence, length_yd|length_m|weight)
        └── PackingSlip (pdf_path, item_ids jsonb)
```
All primary keys are UUIDs (`$incrementing = false`).

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

### Settings
`Setting::getValue($key, $default)` is the single lookup point for runtime configuration (e.g. `default_underdelivery`, `default_overdelivery`, `Approval`, `admin_notification_email`). Managed via the Admin settings UI.

### Views Layout
- `resources/views/layouts/admin.blade.php` — admin shell
- `resources/views/layouts/vendor.blade.php` — vendor shell
- `resources/views/admin/` and `resources/views/vendor/` — role-scoped pages
- `resources/views/vendor/pdf/packing-slip.blade.php` — DomPDF template for packing slips
- `resources/views/custom-pagination.blade.php` — overrides default Bootstrap pagination to fix SVG icon rendering
