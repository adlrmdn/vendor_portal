{{--
    Single shared stylesheet for every Daily Production page (the vendor
    board and the admin board) — pushed once per page via @push('styles')
    so the whole feature reads as one cohesive design instead of drifting
    apart. Never edit these rules per-page; edit here.
--}}
<style>
    .dp-toolbar-label {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #94a3b8;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .code-pill {
        background-color: #f1f5f9;
        color: #475569;
        font-family: var(--bs-font-monospace);
        padding: 2px 7px;
        border-radius: 6px;
        font-size: 0.78rem;
        border: 1px solid #e2e8f0;
    }
    /* Order number / PRG pill, when they link to the work order page —
       plain link-blue so it's obvious they're clickable, not just styled
       text (see style-progress-rows.blade.php). */
    .dp-order-link {
        color: #0d6efd;
        text-decoration: none;
    }
    .dp-order-link:hover {
        color: #0a58ca;
        text-decoration: underline;
    }
    a.dp-prg-link {
        color: #0d6efd;
        background-color: #eaf2ff;
        border-color: #cfe2ff;
        text-decoration: none;
    }
    a.dp-prg-link:hover {
        color: #0a58ca;
        background-color: #dde9ff;
        text-decoration: underline;
    }
    .dp-save-btn {
        font-size: 1.05rem;
        font-weight: 700;
        padding: 12px 28px;
    }

    /* layouts/app.blade.php sets a SITE-WIDE `.table-responsive { max-height:
       500px; }` rule. Combined with Bootstrap's own `overflow-x: auto` on
       that same class, the CSS spec forces overflow-y into an implicit
       scroll container too — silently clipping every table on the page to
       500px tall with no visible scrollbar affordance. Harmless for a
       short table; actively hides rows on this one, which is often taller.
       `!important` is deliberate: this overrides an unrelated global rule
       for this feature's tables only, not a specificity accident. */
    .dp-table-wrap {
        max-height: none !important;
    }

    /* Each style gets its own card + own table (see
       style-progress-rows.blade.php) so its Cutting ratio columns can be
       exclusive to that style — Cutting is a group of columns (one per
       ratio/marker + a read-only Total) with a faint tint and a right
       border separating it from the flat stages. */
    .dp-style-card {
        border: 1px solid #dee2e6;
    }
    .dp-style-header {
        border-bottom: 2px solid #0d6efd;
        background: #eef2f7;
    }
    .dp-style-header.is-closed {
        border-bottom-color: #adb5bd;
    }
    .dp-table {
        table-layout: fixed;
        width: 100%;
    }
    .dp-table thead th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        background: #f8fafc;
        /* NOT nowrap: under table-layout:fixed, a forced-nowrap header
           ("Finishing & Packing") overflows past its column's actual width
           and drags the whole table wider than its container — this was
           silently cutting off the rightmost column. Letting labels wrap to
           two lines is the standard fix for narrow data-table columns. */
        white-space: normal;
        word-break: break-word;
        line-height: 1.25;
        padding: 10px 12px;
    }
    .dp-table td {
        padding: 12px;
        vertical-align: middle;
    }
    .dp-table .dp-total-row td {
        background: #fbfcfd;
        border-top: 1px solid #dee2e6;
    }
    .dp-table tfoot td {
        background: #fbfcfd;
        border-top: 1px solid #dee2e6;
    }
    .dp-table .dp-size-row:hover td {
        background: #f8fbff;
    }
    .dp-table .dp-size-row.is-closed input {
        background: #f8f9fa;
    }
    .dp-sofar {
        font-size: 0.72rem;
        color: #6b7280;
        margin-top: 2px;
        /* Same reasoning as thead th above — "1,710 of 1,710 made" can be
           wider than a narrow column; let it wrap rather than overflow. */
        white-space: normal;
        line-height: 1.2;
    }
    .dp-input {
        border: 1.5px solid #cbd5e1;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 0.35rem 0.4rem;
    }
    .dp-input.dp-has-value {
        background: #eefbf1;
        border-color: #86d99c;
        color: #1e7a37;
    }
    .dp-input:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.2);
        background: #fff;
        color: #212529;
    }
    .dp-cutting-total {
        border-right: 2px solid #cbd5e1 !important;
        background: #fbfcfd;
    }
    .dp-cutting-toggle {
        border: none;
        background: transparent;
        color: inherit;
        padding: 0 0 0 6px;
        line-height: 1;
    }
    /* Finishing & Packing's own Finishing/QC/Packing group — same faint
       tint language as the Cutting group, no border accent because (unlike
       Cutting's ratio columns + Total) there's no combined total column
       marking the group's end; the colspan header above already makes the
       grouping clear. */
    .dp-finishing-cell {
        background: #fbfcfd;
    }
</style>
