<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Fabric POs — pinned to mpg for the same reason as the subcon job below:
// vendor account codes are company-scoped in D365 (V0246 = KNK in mpg but
// PUMA CAT in mpr), so leaving --company on its implicit default would
// silently break if that default ever changes.
Schedule::command('d365:sync-orders --company=mpg')->hourly();

// Subcon work orders — KNK (V0246) lives in the mpg legal entity.
// --company is mandatory to avoid the cross-company vendor-code collision
// (V0246 = PUMA CAT in mpr). Offset to avoid overlapping the fabric run.
Schedule::command('d365:sync-subcon-orders --company=mpg --days=180')->hourlyAt(30);

// Safety net for the QC Console completion-guard bug (see
// ReconcileProjectCompletion docblock) — the source fix is pushed but not yet
// rebuilt/redistributed, so this catches and self-heals any recurrence until
// it is.
Schedule::command('subcon:reconcile-project-completion --fix')->everyFifteenMinutes();

// Keeps the Report Validation / Director Approval backlog's D365 response
// cache warm (see WarmQcApprovalCaches docblock) — WarmQcHoApprovalCache
// already fires once when the approval email goes out, but its 1h TTL
// routinely lapses before an admin works through a multi-item backlog.
// Every 30 minutes stays comfortably inside that TTL.
Schedule::command('qc:warm-approval-caches')->everyThirtyMinutes();

// PC module — vendor enrichment (update-only, existing fabric vendors) and
// fabric-item master feed. Not time-critical, so a lighter cadence than the
// PO syncs above is fine (see each command's docblock for the still-open
// D365 field-name verification items — confirm those before relying on
// this schedule in production).
Schedule::command('pc:enrich-vendors --company=mpg')->everyFourHours();
Schedule::command('pc:sync-fabric-items --company=mpg')->everyFourHours();

// PC module — PLM id feed, read from the vsm plm_activity table (not a
// D365 call). The PC spec requires same-day freshness, so this runs
// hourly; the PC admin sync screen also offers a manual "refresh now" for
// anything more urgent.
Schedule::command('pc:sync-plm-refs')->hourly();

// PC module — Daily Analysis Export (Spec v4 Section 11)
// Generates pc_contract_lines.csv, pc_contracts.csv, manifest.json daily at 06:00 WIB
Schedule::command('pc:export-analysis')->dailyAt('06:00')->timezone('Asia/Jakarta');
