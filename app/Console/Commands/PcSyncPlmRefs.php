<?php

namespace App\Console\Commands;

use App\Models\PcPlmRef;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PC PLM ID reference feed — read from the `vsm` connection's plm_activity
 * table (NOT a new D365 OData call; reuses the same read-only source
 * already proven in SubconProductionService::forPo()). Additive upsert
 * only, never deletes. Must run at least hourly (PC spec 6.2 — a PLM ID
 * created this morning must be pickable this morning).
 *
 * Joins on PLMId/ProductionGroup only, never dataAreaId — plm_activity and
 * production_group_lines live under inconsistent legal entities (see
 * SubconProductionService's class docblock / CLAUDE.md).
 *
 * OPEN ITEM (flagged in the Phase 1 plan, not resolved here): only
 * PLMId, ProductionGroup, ArticleCode, ArticleName, Brand, Colour,
 * GroupName, PLMActivityStatus, Season, World, Department, Category,
 * SubCategory are confirmed plm_activity columns by any existing caller.
 * `lot`, `supplier_name` and `planned_qty` (PC spec's data dictionary lists
 * these as "if D365 has it") have no confirmed source anywhere in this
 * codebase — they are left null below rather than guessed, which the spec
 * already tolerates. Confirm against the real vsm schema before assuming
 * they're unavailable.
 */
class PcSyncPlmRefs extends Command
{
    protected $signature = 'pc:sync-plm-refs';

    protected $description = 'Sync the PC PLM ID reference from the vsm plm_activity table (additive upsert only)';

    public function handle()
    {
        $this->info('Starting PC PLM ref sync...');

        $activities = DB::connection('vsm')->table('plm_activity')
            ->whereNotNull('PLMId')
            ->get(['PLMId', 'ProductionGroup', 'ArticleCode', 'ArticleName', 'Brand', 'Season']);

        $this->info('Fetched '.$activities->count().' PLM activity record(s).');

        if ($activities->isEmpty()) {
            $this->info('No PLM refs to sync.');

            return Command::SUCCESS;
        }

        $groupIds = $activities->pluck('ProductionGroup')->filter()->unique()->all();

        // Prefer production_group_lines.SearchName over plm_activity.ArticleName
        // when available — ArticleName is captured once at PLM-activity-creation
        // time and never refreshed, so it can go stale after a D365-side rename
        // (same freshness caveat SubconProductionService documents).
        $freshNameByGroup = empty($groupIds)
            ? collect()
            : DB::connection('vsm')->table('production_group_lines')
                ->whereIn('ProductionGroup', $groupIds)
                ->whereNotNull('SearchName')
                ->where('SearchName', '!=', '')
                ->orderBy('ProductionGroup')
                ->pluck('SearchName', 'ProductionGroup');

        $now = now();
        $synced = 0;
        foreach ($activities as $a) {
            $articleName = $freshNameByGroup->get($a->ProductionGroup) ?: $a->ArticleName;

            PcPlmRef::updateOrCreate(['plm_id' => $a->PLMId], [
                'article_name' => $articleName,
                'brand' => $a->Brand,
                'season' => $a->Season,
                'lot' => null, // see class docblock — no confirmed source
                'supplier_name' => null, // see class docblock — no confirmed source
                'planned_qty' => null, // see class docblock — no confirmed source
                'synced_at' => $now,
            ]);
            $synced++;
        }

        $this->info("Synced {$synced} PLM ref(s).");

        return Command::SUCCESS;
    }
}
