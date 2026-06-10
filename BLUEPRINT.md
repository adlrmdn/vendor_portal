# Vendor Portal Blueprint

## Overview
The **Vendor Portal** is a web-based application designed to streamline communication and workflow between the company (Admin) and its suppliers (Vendors). It facilitates the management of Purchase Orders (POs), item processing (rolls management), and the generation of packing slips.

## Architecture
- **Framework**: Laravel 11.x
- **Language**: PHP 8.2+
- **Frontend**: Blade Templates + Bootstrap 5
- **Database**: PostgreSQL (Production) / SQLite (Dev/Docker)
- **Containerization**: Docker + Docker Compose

## Core Entities & Data Model

### 1. Vendor (`vendors`)
Represents a supplier entity.
- **Key Fields**: `id` (UUID), `name`, `vendor_code`, `contact_info`.
- **Relationships**: Has many `User` accounts, has many `PurchaseOrder`.

### 2. User (`users`)
authentication entity for accessing the portal.
- **Roles**:
    - `admin`: Internal company staff with full access to all vendors and POs.
    - `vendor`: Supplier staff restricted to their own organization's data.
- **Key Fields**: `role` (enum: 'admin', 'vendor'), `vendor_id` (nullable, linked for vendor users).

### 3. Purchase Order (`purchase_orders`)
The central document managed in the system.
- **Key Fields**: `po_number`, `order_date`, `delivery_date`, `status` (pending, processing, completed, cancelled).
- **Relationships**: Belongs to a `Vendor`, has many `PoItem`.

### 4. PO Item (`po_items`)
Specific line items within a Purchase Order.
- **Key Fields**: `item_number`, `description`, `quantity`, `unit` (YD, M, KG), `status`.
- **Relationships**: Belongs to a `PurchaseOrder`, has many `Roll`.

### 5. Roll (`rolls`)
Represents physical rolls of fabric or material delivered against a PO Item.
- **Key Fields**: `roll_number`, `length_yd` / `length_m`, `qr_code_path`, `is_printed`.
- **Relationships**: Belongs to a `PoItem`.

### 6. Packing Slip (`packing_slips`)
Generated document for shipping.
- **Key Fields**: `slip_number`, `pdf_path`.
- **Relationships**: Belongs to a `PurchaseOrder`.

## Key Workflows

### 1. Authentication & Routing
- Login is handled via standard Laravel Auth.
- **Role-Based Redirection**:
    - Admins are redirected to `/admin/dashboard`.
    - Vendors are redirected to `/vendor/dashboard`.
- Middleware ensures users cannot access unauthorized routes.

### 2. Purchase Order Management
- **Admins**: Can view all POs, filter by vendor/status, update delivery dates, and status.
- **Vendors**: Can view only their assigned POs.

### 3. Item Processing (Roll Management)
- Vendors "process" items by breaking down the total quantity into individual "Rolls".
- Each roll is assigned a unique number and can have QR codes generated.
- Once rolls are defined, the item status moves to 'processing' or 'completed'.

### 4. Packing Slips
- Once items are processed, vendors can generate a Packing Slip (PDF).
- This serves as the shipping document.

## Directory Structure
- `app/Models`: Eloquent models (User, Vendor, PurchaseOrder, etc.).
- `app/Http/Controllers`: Application logic (AdminController, VendorController).
- `database/migrations`: Database schema definitions.
- `resources/views`: Blade templates organized by role (`admin/`, `vendor/`) and layout.
- `routes/web.php`: Route definitions separating Admin and Vendor scopes.

## Technology Stack Extensions
- **DomPDF**: Used for generating PDF packing slips.
- **SimpleQRCode**: Used for generating QR codes for rolls.
- **Boostrap 5**: UI Framework (Note: explicit `custom-pagination` view is used to ensure compatibility).
