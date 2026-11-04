<style>
    /* =========================================================
       Apple HIG Design System for Vendor Portal
       Shared across Admin and Vendor dashboards and tables
       ========================================================= */
    :root {
        --apple-blue: #0071e3;
        --apple-blue-hover: #0077ed;
        --apple-blue-light: rgba(0, 113, 227, 0.08);
        --apple-text: #1d1d1f;
        --apple-text-secondary: #636366;
        --apple-border: #d2d2d7;
        --apple-border-subtle: #e5e5ea;
        --apple-surface: #ffffff;
        --apple-surface-alt: #f5f5f7;
        --apple-radius-sm: 8px;
        --apple-radius-md: 12px;
        --apple-radius-lg: 14px;
        --apple-radius-pill: 9999px;
    }

    .apple-po-wrapper {
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        color: var(--apple-text);
        background-color: #f5f5f7;
    }

    /* Page Header */
    .apple-section-eyebrow {
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--apple-text-secondary);
    }

    .apple-bullet {
        font-size: 0.7rem;
        color: var(--apple-text-secondary);
    }

    .apple-page-title {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: var(--apple-text);
    }

    .apple-count-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        background: #ffffff;
        border: 1px solid var(--apple-border);
        border-radius: var(--apple-radius-pill);
        font-size: 0.8rem;
        color: var(--apple-text-secondary);
        font-weight: 500;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }

    .apple-count-num {
        font-weight: 700;
        color: var(--apple-text);
        font-variant-numeric: tabular-nums;
    }

    .apple-filter-active-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        background: rgba(0, 113, 227, 0.1);
        color: var(--apple-blue);
        border-radius: var(--apple-radius-pill);
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .apple-filter-active-pill:hover {
        background: rgba(0, 113, 227, 0.18);
        color: var(--apple-blue-hover);
    }

    /* Segmented Control Bar (macOS / iPadOS style) */
    .apple-segmented-bar {
        display: inline-flex;
        align-items: center;
        background: #e3e3e8;
        padding: 3px;
        border-radius: 10px;
        border: 1px solid #d2d2d7;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
        list-style: none;
        margin-bottom: 0;
        gap: 2px;
    }

    .apple-segmented-bar .nav-item {
        display: flex;
        align-items: center;
    }

    .apple-segment-item {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 6px 15px;
        font-size: 0.82rem;
        font-weight: 500;
        color: #636366;
        text-decoration: none;
        border-radius: 8px;
        border: none;
        background: transparent;
        transition: all 0.16s cubic-bezier(0.16, 1, 0.3, 1);
        white-space: nowrap;
        cursor: pointer;
        line-height: 1.25;
    }

    .apple-segment-item i {
        font-size: 0.85rem;
        color: #86868b;
        transition: color 0.16s ease;
    }

    .apple-segment-item:hover {
        color: #1d1d1f;
    }

    .apple-segment-item:hover i {
        color: #1d1d1f;
    }

    .apple-segment-item.active {
        background: #ffffff !important;
        color: #1d1d1f !important;
        font-weight: 600;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12), 0 0.5px 1px rgba(0, 0, 0, 0.06);
    }

    .apple-segment-item.active i {
        color: #0071e3 !important; /* Apple blue accent on active tab icon */
    }

    .apple-segment-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        display: inline-block;
    }

    .dot-pending { background-color: #ff9f0a; }
    .dot-processing { background-color: #0071e3; }
    .dot-completed { background-color: #34c759; }
    .dot-cancelled { background-color: #8e8e93; }

    /* Apple Cards */
    .apple-card {
        background: var(--apple-surface);
        border: 1px solid var(--apple-border) !important;
        border-radius: var(--apple-radius-lg);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 4px 16px rgba(0, 0, 0, 0.02);
    }

    .apple-card-header {
        background: #fafafc;
        border-bottom: 1px solid var(--apple-border-subtle);
        padding: 13px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-top-left-radius: var(--apple-radius-lg);
        border-top-right-radius: var(--apple-radius-lg);
    }

    .apple-card-title {
        font-size: 0.93rem;
        font-weight: 600;
        color: var(--apple-text);
        letter-spacing: -0.01em;
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .apple-card-action-shelf {
        background: #f5f5f7;
        border-top: 1px solid var(--apple-border);
        padding: 12px 18px 14px 18px;
        border-bottom-left-radius: 17px;
        border-bottom-right-radius: 17px;
    }

    .apple-shelf-eyebrow {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #86868b;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .apple-shelf-eyebrow i {
        font-size: 0.72rem;
        color: var(--apple-blue);
    }

    .apple-shelf-input {
        background: #ffffff !important;
        border: 1px solid #d2d2d7 !important;
        border-radius: 8px !important;
        font-size: 0.82rem !important;
        font-weight: 500 !important;
        color: var(--apple-text) !important;
        height: 34px !important;
        padding: 6px 11px !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease !important;
    }

    .apple-shelf-input:focus {
        border-color: #0071e3 !important;
        box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.18) !important;
        outline: none !important;
    }

    .apple-shelf-input::placeholder {
        color: #86868b !important;
        font-weight: 400 !important;
    }

    .apple-shelf-btn {
        height: 34px !important;
        border-radius: 8px !important;
        padding: 0 16px !important;
        font-size: 0.82rem !important;
        font-weight: 600 !important;
        letter-spacing: -0.01em !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        white-space: nowrap !important;
    }

    /* Apple-style sleek scrollbars */
    .table-responsive::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .table-responsive::-webkit-scrollbar-track {
        background: transparent;
    }
    .table-responsive::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 9999px;
    }
    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.35);
    }
    .table-responsive {
        scrollbar-width: thin;
        scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
    }

    /* Apple Inset Key-Value Table */
    .apple-kv-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .apple-kv-table tr {
        transition: background-color 0.12s ease;
    }

    .apple-kv-table tr:hover {
        background-color: #fbfbfd;
    }

    .apple-kv-table td {
        padding: 11px 20px;
        vertical-align: middle;
        font-size: 0.86rem;
        border-bottom: 1px solid var(--apple-border-subtle);
    }

    .apple-kv-table tr:last-child td {
        border-bottom: none;
    }

    .apple-kv-table td.apple-kv-label {
        width: 36%;
        color: var(--apple-text-secondary);
        font-weight: 500;
        font-size: 0.83rem;
    }

    .apple-kv-table td.apple-kv-value {
        color: var(--apple-text);
        font-weight: 600;
    }

    .apple-inset-box {
        background: #f5f5f7;
        border: 1px solid var(--apple-border);
        border-radius: var(--apple-radius-md);
        padding: 14px 18px;
    }

    .apple-filter-panel {
        padding: 18px 20px;
        overflow: visible !important;
        position: relative;
        z-index: 30;
    }

    /* Spotlight Search Bar */
    .apple-search-container {
        position: relative;
        display: flex;
        align-items: center;
    }

    .apple-search-icon {
        position: absolute;
        left: 1.25rem;
        color: #86868b;
        font-size: 1.05rem;
        pointer-events: none;
        transition: color 0.15s ease;
    }

    .apple-search-input {
        width: 100%;
        height: 48px;
        padding-left: 3.1rem;
        padding-right: 7.5rem;
        background: var(--apple-surface-alt);
        border: 1px solid transparent;
        border-radius: var(--apple-radius-md);
        font-size: 0.95rem;
        color: var(--apple-text);
        font-weight: 400;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.03);
    }

    .apple-search-input::placeholder {
        color: #86868b;
    }

    .apple-search-input:focus {
        background: #ffffff;
        border-color: rgba(0, 113, 227, 0.4);
        outline: none;
        box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.12), inset 0 1px 2px rgba(0, 0, 0, 0.02);
    }

    .apple-search-container:focus-within .apple-search-icon {
        color: var(--apple-blue);
    }

    .apple-search-clear {
        position: absolute;
        right: 6.8rem;
        color: #86868b;
        font-size: 1.05rem;
        cursor: pointer;
        transition: color 0.15s ease;
    }

    .apple-search-clear:hover {
        color: var(--apple-text);
    }

    .apple-search-btn {
        position: absolute;
        right: 5px;
        height: 38px;
        padding: 0 18px;
        border-radius: calc(var(--apple-radius-md) - 3px);
    }

    /* Form Fields */
    .apple-field-label {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--apple-text-secondary);
        margin-bottom: 5px;
        display: block;
    }

    .apple-input,
    select.apple-select {
        height: 38px;
        border-radius: var(--apple-radius-sm);
        border: 1px solid #d2d2d7;
        background-color: #fafafc;
        font-size: 0.86rem;
        color: var(--apple-text);
        padding: 6px 12px;
        transition: all 0.15s ease;
    }

    .apple-input:focus,
    select.apple-select:focus {
        background-color: #ffffff;
        border-color: var(--apple-blue);
        box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.12);
        outline: none;
    }

    /* Apple Buttons */
    .apple-btn-primary {
        background: var(--apple-blue);
        color: #ffffff;
        border: 1px solid #0064ca;
        border-radius: var(--apple-radius-sm);
        box-shadow: 0 1px 2px rgba(0, 113, 227, 0.25);
        font-size: 0.85rem;
        font-weight: 600;
        height: 36px;
        padding: 0 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .apple-btn-primary:hover {
        background: var(--apple-blue-hover);
        border-color: #005bb5;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 113, 227, 0.3);
    }

    .apple-btn-secondary {
        background: #ffffff;
        color: var(--apple-text);
        border: 1px solid var(--apple-border);
        border-radius: var(--apple-radius-sm);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        font-size: 0.85rem;
        font-weight: 500;
        height: 36px;
        padding: 0 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .apple-btn-secondary:hover {
        background: #f5f5f7;
        border-color: #bcbcc0;
        color: var(--apple-text);
    }

    .apple-btn-success {
        background: #34c759;
        color: #ffffff;
        border: 1px solid #28a745;
        border-radius: var(--apple-radius-sm);
        box-shadow: 0 1px 2px rgba(52, 199, 89, 0.25);
        font-size: 0.85rem;
        font-weight: 600;
        height: 36px;
        padding: 0 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .apple-btn-success:hover {
        background: #30b753;
        border-color: #24963e;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(52, 199, 89, 0.3);
    }

    .apple-btn-pdf {
        background: #ffffff;
        color: #d70015;
        border: 1px solid #ffb3b8;
        border-radius: var(--apple-radius-sm);
        box-shadow: 0 1px 2px rgba(215, 0, 21, 0.06);
        font-size: 0.85rem;
        font-weight: 500;
        height: 36px;
        padding: 0 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .apple-btn-pdf:hover {
        background: #fff5f5;
        border-color: #ff3b30;
        color: #c90013;
    }

    .apple-btn-danger {
        background: #ffffff;
        color: #d70015;
        border: 1px solid #ffb3b8;
        border-radius: var(--apple-radius-sm);
        box-shadow: 0 1px 2px rgba(215, 0, 21, 0.06);
        font-size: 0.85rem;
        font-weight: 500;
        height: 36px;
        padding: 0 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .apple-btn-danger:hover {
        background: #fff5f5;
        border-color: #ff3b30;
        color: #c90013;
    }

    .apple-btn-ghost {
        background: #f2f2f7;
        color: #636366;
        border: 1px solid #d2d2d7;
        border-radius: var(--apple-radius-sm);
        height: 38px;
        width: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .apple-btn-ghost:hover {
        background: #e5e5ea;
        color: var(--apple-text);
    }

    /* Meta Bar & Per Page */
    .apple-meta-summary {
        font-size: 0.83rem;
        color: var(--apple-text-secondary);
    }

    .apple-perpage-label {
        font-size: 0.82rem;
        color: var(--apple-text-secondary);
    }

    .apple-perpage-select {
        height: 30px;
        border-radius: 6px;
        border: 1px solid #d2d2d7;
        background: #ffffff;
        font-size: 0.82rem;
        font-weight: 500;
        padding: 0 8px;
        color: var(--apple-text);
        cursor: pointer;
    }

    /* Table Design */
    .apple-table-container {
        overflow: hidden;
    }

    .apple-table-container .table-responsive {
        max-height: none !important;
        overflow-y: visible !important;
    }

    /* Apple-styled TomSelect */
    .ts-wrapper,
    .ts-wrapper.apple-select,
    .ts-wrapper.form-select,
    .ts-wrapper.form-control {
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        height: auto !important;
        min-height: 0 !important;
        background: transparent !important;
        overflow: visible !important;
        position: relative !important;
    }

    .apple-filter-panel .ts-control,
    .ts-control {
        border-radius: var(--apple-radius-sm) !important;
        border: 1px solid #d2d2d7 !important;
        background-color: #fafafc !important;
        font-size: 0.86rem !important;
        min-height: 38px !important;
        height: 38px !important;
        padding-left: 12px !important;
        transition: all 0.15s ease !important;
    }

    .apple-filter-panel .ts-wrapper.focus .ts-control,
    .ts-wrapper.focus .ts-control {
        background-color: #ffffff !important;
        border-color: var(--apple-blue) !important;
        box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.14) !important;
    }

    .apple-filter-panel .ts-dropdown,
    .ts-dropdown {
        border-radius: var(--apple-radius-md) !important;
        border: 1px solid #d2d2d7 !important;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.14), 0 2px 6px rgba(0, 0, 0, 0.06) !important;
        background: #ffffff !important;
        overflow: hidden !important;
        z-index: 1060 !important;
    }

    .apple-table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .apple-table thead th {
        background: #f5f5f7;
        color: var(--apple-text-secondary);
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 12px 16px;
        border-bottom: 1px solid var(--apple-border);
        white-space: nowrap;
        vertical-align: middle;
    }

    .apple-table-row {
        transition: background-color 0.15s ease;
    }

    .apple-table-row:hover {
        background-color: #fbfbfd;
    }

    .apple-table tbody td {
        padding: 14px 16px;
        vertical-align: middle;
        border-bottom: 1px solid var(--apple-border-subtle);
        font-size: 0.875rem;
    }

    /* PO Column Styling */
    .apple-po-number {
        font-weight: 600;
        color: var(--apple-text);
        text-decoration: none;
        font-size: 0.93rem;
        letter-spacing: -0.01em;
        transition: color 0.15s ease;
    }

    .apple-po-number:hover {
        color: var(--apple-blue);
    }

    .apple-copy-btn {
        background: transparent;
        border: none;
        color: #86868b;
        font-size: 0.78rem;
        padding: 2px 5px;
        border-radius: 4px;
        cursor: pointer;
        opacity: 0.5;
        transition: all 0.15s ease;
    }

    .apple-table-row:hover .apple-copy-btn {
        opacity: 1;
    }

    .apple-copy-btn:hover {
        color: var(--apple-blue);
        background: rgba(0, 113, 227, 0.08);
    }

    .apple-sub-tag {
        display: inline-flex;
        align-items: center;
        padding: 2px 7px;
        border-radius: 5px;
        font-size: 0.74rem;
        font-weight: 500;
        background: #f2f2f7;
        color: #48484a;
    }

    .apple-vendor-info {
        font-size: 0.78rem;
        color: #636366;
        max-width: 250px;
    }

    /* Items Column — No Scrollbar, Clean Stack */
    .apple-items-stack {
        display: flex;
        flex-direction: column;
        gap: 3px;
        max-width: 280px;
    }

    .apple-item-chip-box {
        background: #fbfbfd;
        border: 1px solid #eef0f3;
        border-radius: 7px;
        padding: 5px 9px;
        transition: border-color 0.15s ease;
    }

    .apple-table-row:hover .apple-item-chip-box {
        border-color: #dcdcde;
    }

    .apple-item-num-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 6px;
    }

    .apple-item-number {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--apple-text);
        letter-spacing: -0.01em;
    }

    .apple-item-style {
        font-size: 0.74rem;
        color: #86868b;
        margin-top: 1px;
    }

    .apple-micro-status {
        font-size: 0.68rem;
        font-weight: 600;
        padding: 1px 6px;
        border-radius: 4px;
        text-transform: capitalize;
    }

    .apple-micro-status.pending {
        background: rgba(255, 159, 10, 0.12);
        color: #c96500;
    }

    .apple-micro-status.completed,
    .apple-micro-status.processed {
        background: rgba(52, 199, 89, 0.12);
        color: #1a8738;
    }

    .apple-micro-status.processing {
        background: rgba(0, 113, 227, 0.1);
        color: #0071e3;
    }

    .apple-more-items-pill {
        background: #f2f2f7;
        color: #48484a;
        border: 1px solid #e5e5ea;
        border-radius: var(--apple-radius-pill);
        padding: 3px 10px;
        font-size: 0.74rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .apple-more-items-pill:hover {
        background: #e5e5ea;
        color: var(--apple-text);
    }

    .apple-total-badge {
        font-size: 0.72rem;
        color: var(--apple-text-secondary);
    }

    .apple-items-dropdown {
        border-radius: var(--apple-radius-md);
        border: 1px solid rgba(0, 0, 0, 0.1);
        width: 320px;
        max-height: 280px;
        overflow-y: auto;
    }

    .apple-dropdown-title {
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--apple-text-secondary);
        letter-spacing: 0.05em;
    }

    /* Timeline Stack */
    .apple-timeline-stack {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 0.8rem;
    }

    .apple-date-line {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .apple-date-caption {
        font-size: 0.72rem;
        color: var(--apple-text-secondary);
        width: 52px;
        font-weight: 500;
    }

    .apple-date-val {
        color: var(--apple-text);
        font-weight: 500;
        font-variant-numeric: tabular-nums;
    }

    .apple-date-badge.overdue {
        display: inline-flex;
        align-items: center;
        padding: 2px 7px;
        border-radius: 5px;
        background: rgba(255, 59, 48, 0.1);
        color: #d70015;
        font-weight: 600;
        font-size: 0.76rem;
        font-variant-numeric: tabular-nums;
    }

    /* Item Fulfillment Pill */
    .apple-fulfillment-count {
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--apple-text);
    }

    .apple-batch-pill {
        display: inline-block;
        font-size: 0.7rem;
        background: #f2f2f7;
        color: #636366;
        padding: 1px 6px;
        border-radius: 4px;
        margin-left: 2px;
    }

    .apple-pill-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 9px;
        border-radius: var(--apple-radius-pill);
        font-size: 0.74rem;
        font-weight: 600;
    }

    .apple-pill-status.pill-warning {
        background: rgba(255, 159, 10, 0.12);
        color: #b25000;
    }

    .apple-pill-status.pill-success {
        background: rgba(52, 199, 89, 0.12);
        color: #15803d;
    }

    .status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }

    .dot-warning { background-color: #ff9f0a; }
    .dot-success { background-color: #34c759; }

    /* Amount */
    .apple-amount-box {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-end;
    }

    .apple-currency {
        font-size: 0.68rem;
        font-weight: 600;
        color: var(--apple-text-secondary);
        letter-spacing: 0.04em;
    }

    .apple-amount-value {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--apple-text);
        font-variant-numeric: tabular-nums;
    }

    /* Status Capsule (Apple HIG style) */
    .apple-status-capsule {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: var(--apple-radius-pill);
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: -0.01em;
    }

    .apple-status-capsule .capsule-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }

    .capsule-pending {
        background: rgba(255, 159, 10, 0.12);
        color: #b45309;
    }
    .capsule-pending .capsule-dot { background-color: #ff9f0a; }

    .capsule-processing {
        background: rgba(0, 113, 227, 0.1);
        color: #0071e3;
    }
    .capsule-processing .capsule-dot { background-color: #0071e3; }

    .capsule-completed {
        background: rgba(52, 199, 89, 0.12);
        color: #15803d;
    }
    .capsule-completed .capsule-dot { background-color: #34c759; }

    .capsule-cancelled {
        background: rgba(142, 142, 147, 0.15);
        color: #636366;
    }
    .capsule-cancelled .capsule-dot { background-color: #8e8e93; }

    /* Action Buttons */
    .apple-action-btn {
        width: 32px;
        height: 32px;
        border-radius: var(--apple-radius-sm);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.82rem;
        border: 1px solid transparent;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .apple-action-view {
        background: rgba(0, 113, 227, 0.08);
        color: var(--apple-blue);
        border-color: rgba(0, 113, 227, 0.15);
    }

    .apple-action-view:hover {
        background: var(--apple-blue);
        color: #ffffff;
        border-color: var(--apple-blue);
        transform: scale(1.05);
    }

    .apple-action-slip {
        background: rgba(52, 199, 89, 0.1);
        color: #15803d;
        border-color: rgba(52, 199, 89, 0.2);
    }

    .apple-action-slip:hover {
        background: #34c759;
        color: #ffffff;
        border-color: #34c759;
        transform: scale(1.05);
    }

    /* Empty State */
    .apple-empty-state {
        padding: 40px 20px;
    }

    .apple-empty-icon-circle {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #f2f2f7;
        color: #86868b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .apple-empty-title {
        font-weight: 700;
        font-size: 1.15rem;
        color: var(--apple-text);
        margin-bottom: 6px;
    }

    .apple-empty-desc {
        font-size: 0.88rem;
        color: var(--apple-text-secondary);
        max-width: 380px;
        margin: 0 auto 18px;
    }

    /* Modal Styling */
    .apple-modal {
        border-radius: var(--apple-radius-lg);
        border: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        overflow: hidden;
    }

    .apple-modal-title {
        font-weight: 700;
        font-size: 1.15rem;
        color: var(--apple-text);
    }

    .apple-modal-order-box {
        background: #fbfbfd;
        border: 1px solid #eef0f3;
        border-radius: var(--apple-radius-md);
        padding: 12px 14px;
    }

    /* Apple Copy Toast */
    .apple-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: rgba(29, 29, 31, 0.92);
        color: #ffffff;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        padding: 10px 18px;
        border-radius: var(--apple-radius-pill);
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        display: none;
        align-items: center;
        z-index: 9999;
        animation: appleFadeIn 0.2s ease-out;
    }

    @keyframes appleFadeIn {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Apple Stat Cards */
    .apple-stat-card {
        background: #ffffff;
        border: 1px solid var(--apple-border);
        border-radius: var(--apple-radius-lg);
        padding: 20px 22px;
        box-shadow: 0 2px 12px -2px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        height: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .apple-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.08), 0 2px 6px rgba(0, 0, 0, 0.04);
        border-color: rgba(0, 0, 0, 0.12);
    }

    .apple-stat-title {
        font-size: 0.78rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--apple-text-secondary);
        margin-bottom: 6px;
    }

    .apple-stat-value {
        font-size: 1.85rem;
        font-weight: 700;
        color: var(--apple-text);
        letter-spacing: -0.025em;
        font-variant-numeric: tabular-nums;
        line-height: 1.1;
    }

    .apple-stat-icon-wrap {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .icon-wrap-blue { background: rgba(0, 113, 227, 0.1); color: var(--apple-blue); }
    .icon-wrap-amber { background: rgba(255, 159, 10, 0.12); color: #ff9f0a; }
    .icon-wrap-green { background: rgba(52, 199, 89, 0.12); color: #34c759; }
    .icon-wrap-indigo { background: rgba(88, 86, 214, 0.1); color: #5856d6; }

    /* Header Card Link */
    .apple-header-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--apple-blue);
        text-decoration: none;
        padding: 5px 12px;
        border-radius: var(--apple-radius-pill);
        background: var(--apple-blue-light);
        transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .apple-header-link:hover {
        background: rgba(0, 113, 227, 0.16);
        color: var(--apple-blue-hover);
        transform: translateX(1px);
    }

    /* Per Page Selector Pill */
    .apple-per-page-container {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #f5f5f7;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: var(--apple-radius-pill);
        padding: 3px 10px 3px 12px;
        transition: all 0.15s ease;
    }
    .apple-per-page-container:hover {
        background: #eef0f3;
        border-color: rgba(0, 0, 0, 0.14);
    }
    .apple-per-page-label {
        font-size: 0.78rem;
        font-weight: 500;
        color: var(--apple-text-secondary);
        white-space: nowrap;
        margin: 0;
    }
    .apple-per-page-select {
        height: 24px;
        border: none;
        background: transparent;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--apple-text);
        cursor: pointer;
        padding: 0 16px 0 2px;
        min-width: 44px;
        outline: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2386868b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 2px center;
        background-size: 10px 8px;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
    }
    .apple-per-page-select:focus {
        outline: none;
    }

    /* Stepper & Stage Tracker */
    .apple-stepper-wrap {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        background: #ffffff;
        border: 1px solid var(--apple-border);
        border-radius: var(--apple-radius-pill);
        padding: 5px 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }
    .apple-step-node {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 2px 6px;
        font-size: 0.8rem;
        font-weight: 500;
        color: #86868b;
        border-radius: var(--apple-radius-pill);
    }
    .apple-step-node.done {
        color: var(--apple-text);
    }
    .apple-step-node.active {
        color: var(--apple-blue);
        font-weight: 700;
        background: var(--apple-blue-light);
    }
    .apple-step-circle {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        font-weight: 700;
    }
    .apple-step-node.done .apple-step-circle {
        background: var(--apple-blue);
        color: #ffffff;
    }
    .apple-step-node.active .apple-step-circle {
        background: #ffffff;
        color: var(--apple-blue);
        border: 2px solid var(--apple-blue);
    }
    .apple-step-node.upcoming .apple-step-circle {
        background: #f2f2f7;
        color: #86868b;
        border: 1px solid rgba(0, 0, 0, 0.08);
    }
    .apple-step-chevron {
        font-size: 0.65rem;
        color: #c7c7cc;
        margin: 0 1px;
    }

    /* Module Navigation Bar */
    .apple-module-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #ebebf0;
    }
    .apple-module-tabs {
        display: inline-flex;
        align-items: center;
        background: #e5e5ea;
        padding: 3px;
        border-radius: var(--apple-radius-md);
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
    }
    .apple-module-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        font-size: 0.83rem;
        font-weight: 500;
        color: #636366;
        text-decoration: none;
        border-radius: calc(var(--apple-radius-md) - 3px);
        transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        white-space: nowrap;
    }
    .apple-module-tab:hover {
        color: var(--apple-text);
    }
    .apple-module-tab.active {
        background: #ffffff;
        color: var(--apple-text);
        font-weight: 600;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
    }

    /* Back Button */
    .apple-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--apple-text);
        text-decoration: none;
        padding: 6px 14px;
        background: #ffffff;
        border: 1px solid var(--apple-border);
        border-radius: var(--apple-radius-sm);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        transition: all 0.15s ease;
    }
    .apple-back-btn:hover {
        background: #f5f5f7;
        border-color: #bcbcc0;
        color: var(--apple-text);
    }
</style>

<script>
    function copyPoNumber(poNumber, btn) {
        if (!navigator.clipboard) {
            const temp = document.createElement('textarea');
            temp.value = poNumber;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
        } else {
            navigator.clipboard.writeText(poNumber);
        }

        const toast = document.getElementById('appleToast');
        const toastText = document.getElementById('appleToastText');
        if (toast && toastText) {
            toastText.textContent = 'Copied ' + poNumber;
            toast.style.display = 'inline-flex';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 2000);
        }

        if (btn) {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-check text-success';
                setTimeout(() => {
                    icon.className = 'far fa-copy';
                }, 1500);
            }
        }
    }
</script>
