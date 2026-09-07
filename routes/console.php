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
