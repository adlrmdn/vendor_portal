{{--
    Modernized design tokens + components for the PC (Preliminary Contract) module.
    Aligned with Vendor Portal global design system (Bootstrap 5, Segoe UI, clean SaaS aesthetics).
--}}
<style>
    :root {
        --pc-ink: #1e293b;
        --pc-ink-soft: #64748b;
        --pc-border: #e2e8f0;
        --pc-bg-subtle: #f8fafc;
        --pc-primary: #0d6efd;
        --pc-primary-hover: #0b5ed7;
        --pc-primary-soft: #e7f1ff;
        --pc-success: #198754;
        --pc-success-soft: #d1e7dd;
        --pc-warning: #ffc107;
        --pc-warning-soft: #fff3cd;
        --pc-danger: #dc3545;
        --pc-danger-soft: #f8d7da;
        --pc-info: #0dcaf0;
        --pc-info-soft: #cff4fc;
        --pc-purple: #6f42c1;
        --pc-purple-soft: #e2d9f3;
    }

    .pc-shell {
        background: transparent;
        color: var(--pc-ink);
        padding: 0;
    }

    /* Modern Tabs Navigation matching Vendor Portal */
    .pc-ledger-tabs {
        display: flex;
        gap: 0.5rem;
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 1.5rem;
        padding: 0;
        list-style: none;
        overflow-x: auto;
    }
    .pc-ledger-tabs .nav-link,
    .pc-ledger-tabs a.nav-link {
        background: transparent !important;
        border: none !important;
        border-radius: 0.375rem 0.375rem 0 0 !important;
        padding: 0.75rem 1.25rem !important;
        color: var(--pc-ink-soft) !important;
        font-weight: 500;
        font-size: 0.95rem;
        white-space: nowrap;
        position: relative;
        bottom: -2px;
        border-bottom: 3px solid transparent !important;
        transition: all 0.2s ease;
    }
    .pc-ledger-tabs .nav-link:hover,
    .pc-ledger-tabs a.nav-link:hover {
        color: var(--pc-primary) !important;
        background-color: rgba(13, 110, 253, 0.04) !important;
    }
    .pc-ledger-tabs .nav-link.active,
    .pc-ledger-tabs a.nav-link.active {
        color: var(--pc-primary) !important;
        border-bottom-color: var(--pc-primary) !important;
        font-weight: 600;
        background-color: transparent !important;
    }

    .pc-h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        letter-spacing: -0.01em;
    }
    .pc-meta {
        color: #64748b;
        font-size: 0.875rem;
    }
    .pc-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        letter-spacing: -0.02em;
    }

    /* Modern Card Styles with crisp, visible borders */
    .pc-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        margin-bottom: 1.25rem;
    }
    .pc-card > .pc-card-head {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #cbd5e1;
        font-weight: 600;
        font-size: 1rem;
        color: #0f172a;
        background-color: #ffffff;
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
    }
    .pc-card > .pc-card-body {
        padding: 1.25rem;
    }

    /* Distinct Nested Line Item Card */
    .line-row.pc-card {
        background: #f8fafc;
        border: 1px solid #cbd5e1 !important;
        border-left: 4px solid #0d6efd !important;
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 1rem;
    }
    .line-row.pc-card:hover {
        border-color: #94a3b8 !important;
        border-left-color: #0b5ed7 !important;
    }

    /* Form Controls & TomSelect Crisp Borders */
    .form-control, .form-select {
        border: 1px solid #ced4da !important;
    }
    .form-control:focus, .form-select:focus {
        border-color: #86b7fe !important;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    }
    .ts-wrapper.form-select,
    .ts-wrapper.form-control {
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        height: auto !important;
        background: transparent !important;
    }
    .ts-wrapper .ts-control,
    .ts-wrapper.form-select .ts-control,
    .ts-wrapper.form-control .ts-control,
    .ts-control {
        border: 1px solid #ced4da !important;
        border-radius: 0.375rem !important;
        min-height: 38px !important;
        height: 38px !important;
        padding: 0.375rem 2.25rem 0.375rem 0.75rem !important;
        background-color: #ffffff !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.75rem center !important;
        background-size: 16px 12px !important;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    }
    .ts-wrapper.focus .ts-control,
    .ts-control.focus {
        border-color: #86b7fe !important;
        outline: 0 !important;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    }
    .ts-dropdown {
        font-size: 0.85rem !important;
        z-index: 1055 !important;
        border: 1px solid #ced4da !important;
        border-radius: 0.375rem !important;
        box-shadow: 0 4px 14px rgba(0,0,0,0.12) !important;
        max-height: 220px !important;
    }
    .ts-dropdown .ts-dropdown-content {
        max-height: 220px !important;
        overflow-y: auto !important;
        padding: 4px 0 !important;
    }
    .ts-dropdown .option {
        padding: 5px 10px !important;
        font-size: 0.84rem !important;
        line-height: 1.35 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    .ts-dropdown .option.active {
        background-color: #e7f1ff !important;
        color: #0f172a !important;
    }

    /* Pipeline Interactive Stat Card */
    .pc-stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-left: 4px solid #cbd5e1;
        border-radius: 0.5rem;
        padding: 1rem 1.15rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        text-decoration: none !important;
        color: inherit !important;
        display: block;
        height: 100%;
    }
    .pc-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    .pc-stat-card .stat-num {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.1;
        color: #0f172a;
    }
    .pc-stat-card .stat-label {
        font-size: 0.825rem;
        font-weight: 500;
        color: #64748b;
        margin-top: 0.25rem;
    }

    .pc-stat-draft { border-left-color: #64748b; }
    .pc-stat-pending { border-left-color: #f59e0b; }
    .pc-stat-approved { border-left-color: #6366f1; }
    .pc-stat-sent { border-left-color: #0ea5e9; }
    .pc-stat-confirmed { border-left-color: #10b981; }
    .pc-stat-queried { border-left-color: #ef4444; }

    /* Modern Table */
    .pc-table {
        width: 100%;
        border-collapse: collapse;
    }
    .pc-table th {
        text-align: left;
        font-size: 0.78rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        background-color: #f8fafc;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .pc-table td {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.92rem;
        color: #1e293b;
        vertical-align: middle;
    }
    .pc-table tbody tr:hover {
        background-color: #f8fafc;
    }
    .pc-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Badges & Status Pills */
    .pc-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.28rem 0.65rem;
        border-radius: 9999px;
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1;
    }
    .pc-pill::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .pc-pill-draft { background: #f1f5f9; color: #475569; }
    .pc-pill-draft::before { background: #94a3b8; }
    .pc-pill-waiting { background: #fef3c7; color: #b45309; }
    .pc-pill-waiting::before { background: #f59e0b; }
    .pc-pill-confirmed { background: #d1fae5; color: #047857; }
    .pc-pill-confirmed::before { background: #10b981; }
    .pc-pill-dead { background: #fee2e2; color: #b91c1c; }
    .pc-pill-dead::before { background: #ef4444; }
    .pc-pill-neutral { background: #e0e7ff; color: #4338ca; }
    .pc-pill-neutral::before { background: #6366f1; }

    /* Modern Stage Tracker */
    .pc-stage-tracker {
        display: flex;
        align-items: center;
        width: 100%;
    }
    .pc-stage-tracker .seg {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.825rem;
        color: #94a3b8;
        font-weight: 500;
    }
    .pc-stage-tracker .dot {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: 2px solid #cbd5e1;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        font-weight: bold;
        flex-shrink: 0;
        color: #94a3b8;
    }
    .pc-stage-tracker .seg.done .dot {
        border-color: #0d6efd;
        background: #0d6efd;
        color: #ffffff;
    }
    .pc-stage-tracker .seg.current .dot {
        border-color: #0d6efd;
        background: #ffffff;
        color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2);
    }
    .pc-stage-tracker .seg.current { color: #0d6efd; font-weight: 600; }
    .pc-stage-tracker .seg.done { color: #1e293b; font-weight: 500; }
    .pc-stage-tracker .bar {
        flex: 1;
        height: 2px;
        background: #e2e8f0;
        margin: 0 0.5rem;
    }
    .pc-stage-tracker .bar.done {
        background: #0d6efd;
    }

    /* Action Buttons */
    .pc-btn-primary {
        background-color: var(--pc-primary);
        border-color: var(--pc-primary);
        color: #fff;
        font-weight: 500;
    }
    .pc-btn-primary:hover {
        background-color: var(--pc-primary-hover);
        border-color: var(--pc-primary-hover);
        color: #fff;
    }
    .pc-btn-forest {
        background-color: var(--pc-success);
        border-color: var(--pc-success);
        color: #fff;
    }
    .pc-btn-forest:hover {
        background-color: #157347;
        border-color: #157347;
        color: #fff;
    }
    .pc-btn-outline-brick {
        background: transparent;
        border-color: var(--pc-danger);
        color: var(--pc-danger);
    }
    .pc-btn-outline-brick:hover {
        background: var(--pc-danger);
        color: #fff;
    }
</style>
