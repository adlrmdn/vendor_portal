# Vendor Portal: Technical Design & Architecture

## Executive Summary
The **Vendor Portal** is a high-integrity procurement and logistics management system built on Laravel 12.x. Its primary objective is to digitize the "Roll Processing" workflow, enabling vendors to decompose bulk Purchase Orders (POs) into traceable, QR-coded material units (Rolls). It manages the entire lifecycle from PO ingestion to shipping document generation, enforcing strict data isolation and complex re-sequencing logic.

---

## 1. System Architecture
The application is built on **PHP 8.2+** and **Laravel 12.x**, following a standard MVC pattern with specialized service-layer logic for roll sequencing and PDF generation.

### 1.1 Multi-Tenant Isolation
The system enforces strict data isolation at the database level. Every `User` is associated with a `vendor_id` UUID. A global middleware scopes all Eloquent queries to the authenticated user's vendor, ensuring that suppliers can only interact with their own Purchase Orders and Roll data.

### 1.2 Technology Stack
- **Backend Framework**: Laravel 12.x.
- **Frontend Layer**: Blade Templates, Bootstrap 5, and Vite.
- **Primary Database**: PostgreSQL (for POs, Items, and Rolls).
- **External Integration**: Read-only connection to the `people_function` database for employee/approver validation.
- **PDF/QR Engines**: `barryvdh/laravel-dompdf` for Packing Slips and `SimpleSoftwareIO/QrCode` for roll labeling.

---

## 2. Core Business Logic & Workflows

### 2.1 The Roll Processing Engine
The core complexity resides in [`VendorController@saveItemRolls`](file:///home/ubuntu/webservice/vendor_portal/app/Http/Controllers/VendorController.php), which handles the transformation of a `PoItem` into individual `Roll` entities.
- **Atomic Transactions**: All operations are wrapped in `DB::beginTransaction()` to ensure consistency.
- **Re-sequencing Logic**:
    1. **Deletions**: First removes rolls marked for deletion and their associated S3/storage QR codes.
    2. **Indexing**: Re-indexes all remaining and new rolls to ensure a continuous `sequence` integer.
    3. **Naming**: Generates a `roll_number` using the format: `sprintf("%s-%s-%03d", $po, $item, $nextSequence)`.
- **Unit Column Mapping**: To avoid conversion errors, the system populates unit-specific columns in the [`Roll`](file:///home/ubuntu/webservice/vendor_portal/app/Models/Roll.php) model:
    - `YD` -> `length_yd`
    - `M` -> `length_m`
    - `KG` -> `weight`

### 2.2 Data Import & Heuristic Parsing
Suppliers can batch-upload roll data via [`VendorController@uploadRollsData`](file:///home/ubuntu/webservice/vendor_portal/app/Http/Controllers/VendorController.php).
- **PDF Parsing**: Uses a regex-based heuristic to extract data from raw PDF text: `/([A-Z0-9_-]+)?\s*(\d+(?:\.\d+)?)$/`.
- **Excel Parsing**: Uses `Laravel Excel` to extract `internal_id` and `quantity` from row-based structures.

### 2.3 Tolerance Amendments & Approvals
Procurement variances are managed via the **Tolerance Amendment Subsystem**:
- **Approval Resolution**: Queries the external `employees` table for the badge number stored in `Setting::getValue('Approval')`.
- **State Machine**: Requests move through `pending`, `approved`, and finally `implemented` when the partial shipment is split via `PoItem@splitToPartialShipment`.
- **Fallback**: If no approver email is found, notifications revert to the `admin_notification_email` defined in settings.

---

## 3. Advanced Features & Infrastructure

### 3.1 QR Labeling Subsystem
Each roll generates a unique JSON-encoded QR code via [`Roll.php`](file:///home/ubuntu/webservice/vendor_portal/app/Models/Roll.php):
- **Encoded Schema**: `{"roll_number": "...", "po_number": "...", "item_number": "...", "quantity": "...", "unit": "..."}`.
- **Storage**: Saved as PNGs in `storage/app/public/qrcodes/` for rendering on labels.

### 3.2 Packing Slip Consolidation
The system allows "Quick Generation" for all `completed` items in a PO.
- **Aggregation**: It creates a [`PackingSlip`](file:///home/ubuntu/webservice/vendor_portal/app/Models/PackingSlip.php) record, storing a `jsonb` array of item IDs.
- **Print Tracking**: Marks all associated rolls as `is_printed`, updates `printed_at`, and increments the `printed_count` on the slip.

---

## 4. Technical Reference (Path Map)

| Component | Responsibility | File Path |
| :--- | :--- | :--- |
| **Logic Entry** | Roll & PDF Controller | [`VendorController.php`](file:///home/ubuntu/webservice/vendor_portal/app/Http/Controllers/VendorController.php) |
| **Model** | QR & Sequencing | [`Roll.php`](file:///home/ubuntu/webservice/vendor_portal/app/Models/Roll.php) |
| **Model** | Tolerance/Shipment Logic | [`PoItem.php`](file:///home/ubuntu/webservice/vendor_portal/app/Models/PoItem.php) |
| **Schema** | PostgreSQL Migration | [`0001_data_schema.php`](file:///home/ubuntu/webservice/vendor_portal/database/migrations/0001_data_schema.php) |
| **Template** | PDF Blade View | [`packing-slip.blade.php`](file:///home/ubuntu/webservice/vendor_portal/resources/views/vendor/pdf/packing-slip.blade.php) |

---
*Internal Technical Manual - Version 1.1*
