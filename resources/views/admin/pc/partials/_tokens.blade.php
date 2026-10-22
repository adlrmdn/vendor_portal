{{--
    Shared design tokens + components for the PC (Preliminary Contract)
    module. `@include`d once near the top of each top-level PC page/section
    (admin/pc/index, contracts/{index,create,edit,show}, vendor/pc/*) — not
    from a partial that's included many times per page (e.g. line-row).

    Design rationale: PC replaces a paper contract process, so the palette
    leans toward a ledger/dossier rather than a generic SaaS dashboard —
    warm paper background, indigo (a literal textile/denim reference) as
    the accent instead of a generic brand blue, muted "stamp" colors for
    status instead of stock Bootstrap red/yellow/green. The stage tracker
    is the one bespoke device (the workflow really is a 4-step sequence,
    so encoding it as connected segments is informative, not decorative);
    everything else — tables, forms — stays plain and quiet around it.
--}}
<style>
    :root {
        --pc-ink: #20262E;
        --pc-ink-soft: #596273;
        --pc-paper: #F7F3EA;
        --pc-paper-line: #E8E0CC;
        --pc-indigo: #2E4A66;
        --pc-indigo-soft: #E8EEF3;
        --pc-amber: #A9752C;
        --pc-amber-soft: #F3E9D7;
        --pc-forest: #3C6652;
        --pc-forest-soft: #E3EDE7;
        --pc-brick: #8B4C3F;
        --pc-brick-soft: #F1E3DF;
        --pc-thread: #D9CBA8;
        --pc-mono: ui-monospace, "SF Mono", "Cascadia Code", Menlo, Consolas, monospace;
    }

    .pc-shell {
        background: var(--pc-paper);
        border: 1px solid var(--pc-paper-line);
        border-radius: 6px;
        padding: 1.5rem;
        color: var(--pc-ink);
    }
    @media (max-width: 480px) {
        .pc-shell { padding: 0.85rem; }
        .pc-table th, .pc-table td { padding: 0.5rem 0.5rem; font-size: 0.85rem; }
    }

    /* Ledger-style tab row: underline, not filled pills — reads as folder
       dividers rather than the generic rounded-pill tab bar. */
    .pc-ledger-tabs {
        display: flex;
        gap: 1.75rem;
        border-bottom: 1px solid var(--pc-paper-line);
        margin-bottom: 1.75rem;
        padding: 0;
        list-style: none;
        overflow-x: auto;
    }
    .pc-ledger-tabs .nav-link,
    .pc-ledger-tabs a.nav-link {
        background: none !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0.65rem 0.1rem !important;
        color: var(--pc-ink-soft) !important;
        font-weight: 500;
        font-size: 0.92rem;
        white-space: nowrap;
        border-bottom: 2px solid transparent !important;
    }
    /* Below ~700px, 6 tabs in a single nowrap/scroll strip hide most of
       them off-screen with no hint they're there (the "where did Contracts
       go" bug) — wrap to a second/third row instead, so every tab stays
       visible and tappable without a hidden horizontal swipe. */
    @media (max-width: 700px) {
        .pc-ledger-tabs {
            flex-wrap: wrap;
            overflow-x: visible;
            gap: 0.25rem 1.1rem;
            row-gap: 0.4rem;
        }
        .pc-ledger-tabs .nav-link,
        .pc-ledger-tabs a.nav-link {
            font-size: 0.85rem;
            padding: 0.5rem 0.1rem !important;
        }
    }

    .pc-ledger-tabs .nav-link.active,
    .pc-ledger-tabs a.nav-link.active {
        color: var(--pc-indigo) !important;
        border-bottom-color: var(--pc-indigo) !important;
        font-weight: 600;
    }

    .pc-h1 { font-size: 1.5rem; font-weight: 600; color: var(--pc-ink); margin: 0; }
    .pc-meta { color: var(--pc-ink-soft); font-size: 0.85rem; }
    .pc-mono { font-family: var(--pc-mono); letter-spacing: -0.01em; }

    .pc-card {
        background: #fff;
        border: 1px solid var(--pc-paper-line);
        border-radius: 6px;
    }
    .pc-card > .pc-card-head {
        padding: 0.85rem 1.1rem;
        border-bottom: 1px solid var(--pc-paper-line);
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--pc-ink);
    }
    .pc-card > .pc-card-body { padding: 1.1rem; }

    /* Stage tracker: the one bespoke device. Draft / Vendor Review /
       Approval / Confirmed as connected segments — filled = past/current,
       outlined = not reached yet. Cancelled/superseded render as a single
       flat strip instead (there's no "progress" to show for a dead end). */
    .pc-stage-tracker { display: flex; align-items: center; gap: 0; }
    .pc-stage-tracker .seg {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.78rem;
        color: var(--pc-ink-soft);
        white-space: nowrap;
    }
    .pc-stage-tracker .dot {
        width: 9px; height: 9px; border-radius: 50%;
        border: 2px solid var(--pc-thread);
        background: #fff;
        flex-shrink: 0;
    }
    .pc-stage-tracker .seg.done .dot,
    .pc-stage-tracker .seg.current .dot {
        border-color: var(--pc-indigo);
        background: var(--pc-indigo);
    }
    .pc-stage-tracker .seg.current { color: var(--pc-indigo); font-weight: 600; }
    .pc-stage-tracker .seg.done { color: var(--pc-ink); }
    .pc-stage-tracker .bar {
        flex: 1;
        height: 1px;
        background: var(--pc-thread);
        margin: 0 0.35rem;
    }
    .pc-stage-tracker .seg.done + .bar,
    .pc-stage-tracker .bar.done { background: var(--pc-indigo); }

    /* Four nowrap labels + connecting bars don't fit an iPhone-width card at
       the full font size — drop to a centered, wrapping label under each
       dot instead of truncating or forcing the strip to scroll. */
    @media (max-width: 480px) {
        .pc-stage-tracker .seg {
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.68rem;
            white-space: normal;
            text-align: center;
        }
        .pc-stage-tracker .bar { margin: 0 0.15rem; align-self: flex-start; margin-top: 4px; }
    }
    .pc-stage-tracker.pc-stage-flat {
        background: var(--pc-brick-soft);
        color: var(--pc-brick);
        padding: 0.4rem 0.75rem;
        border-radius: 4px;
        font-weight: 600;
        font-size: 0.82rem;
        display: inline-flex;
    }
    .pc-stage-tracker.pc-stage-flat.is-superseded {
        background: #ECECEC;
        color: var(--pc-ink-soft);
    }

    .pc-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.2rem 0.6rem;
        border-radius: 4px;
        font-size: 0.78rem;
        font-weight: 600;
    }
    .pc-pill-draft { background: #ECECEC; color: var(--pc-ink-soft); }
    .pc-pill-waiting { background: var(--pc-amber-soft); color: var(--pc-amber); }
    .pc-pill-confirmed { background: var(--pc-forest-soft); color: var(--pc-forest); }
    .pc-pill-dead { background: var(--pc-brick-soft); color: var(--pc-brick); }
    .pc-pill-neutral { background: var(--pc-indigo-soft); color: var(--pc-indigo); }

    /* Solid/outline action-button variants carrying the module's own
       forest (approve/accept) and brick (reject/cancel) colors. These
       replace one-off inline `style="background:..."` on buttons — an
       inline style wins over any :hover rule in the stylesheet, including
       Bootstrap's own, so those buttons could never get a hover state and
       just sat dead/unresponsive to the cursor. */
    .pc-btn-forest {
        background: var(--pc-forest); border-color: var(--pc-forest); color: #fff;
    }
    .pc-btn-forest:hover, .pc-btn-forest:focus {
        background: #2f5443; border-color: #2f5443; color: #fff;
    }
    .pc-btn-outline-brick {
        background: transparent; border-color: var(--pc-brick); color: var(--pc-brick);
    }
    .pc-btn-outline-brick:hover, .pc-btn-outline-brick:focus {
        background: var(--pc-brick); border-color: var(--pc-brick); color: #fff;
    }
    .pc-text-brick { color: var(--pc-brick); }

    .pc-stat {
        border-left: 3px solid var(--pc-indigo);
        padding: 0.15rem 0 0.15rem 0.85rem;
    }
    .pc-stat .n { font-size: 1.6rem; font-weight: 600; color: var(--pc-ink); font-family: var(--pc-mono); line-height: 1; }
    .pc-stat .l { font-size: 0.8rem; color: var(--pc-ink-soft); }

    /* A non-zero "waiting on someone" count is a to-do, not trivia — give
       it the amber accent so it reads as something to act on rather than
       blending into the same neutral indigo as every other tally. */
    .pc-stat-actionable { border-left-color: var(--pc-amber); }
    .pc-stat-actionable .n { color: var(--pc-amber); }

    .pc-table { width: 100%; border-collapse: collapse; }
    .pc-table th {
        text-align: left;
        font-size: 0.76rem;
        font-weight: 600;
        color: var(--pc-ink-soft);
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid var(--pc-paper-line);
    }
    .pc-table td {
        padding: 0.65rem 0.75rem;
        border-bottom: 1px solid var(--pc-paper-line);
        font-size: 0.9rem;
        vertical-align: middle;
    }
    .pc-table tbody tr:hover { background: var(--pc-paper); }
    .pc-table .num { font-family: var(--pc-mono); text-align: right; }

    /* Caps an unpaginated reference table (e.g. Master Data's add-as-you-go
       lists) so one growing list can't push every other card on the page
       far down the scroll — the table scrolls internally instead. */
    .pc-table-scroll { max-height: 280px; overflow-y: auto; }
    .pc-table-scroll .pc-table thead th { position: sticky; top: 0; background: #fff; }

    .pc-btn-primary {
        background: var(--pc-indigo); border-color: var(--pc-indigo); color: #fff;
    }
    .pc-btn-primary:hover { background: #253c54; border-color: #253c54; color: #fff; }
</style>
