<?php

namespace App\Console\Commands;

use App\Models\PcFabricItem;
use App\Models\PoItem;
use Illuminate\Console\Command;

class PcImportPoFabricItems extends Command
{
    protected $signature = 'pc:import-po-items';

    protected $description = 'Populate pc_fabric_items master table with distinct fabric items from existing po_items';

    public function handle()
    {
        $this->info('Scanning po_items for distinct fabric items...');

        $poItems = PoItem::query()
            ->select(['item_number', 'description', 'unit', 'fabric_type'])
            ->whereNotNull('item_number')
            ->where('item_number', '!=', '')
            ->distinct('item_number')
            ->get();

        $this->info("Found {$poItems->count()} distinct item(s) in po_items.");

        $created = 0;
        foreach ($poItems as $item) {
            $existing = PcFabricItem::where('item_number', $item->item_number)->first();
            if (! $existing) {
                PcFabricItem::create([
                    'item_number' => $item->item_number,
                    'item_name' => $item->description ?: $item->item_number,
                    'uom_code' => $item->unit ?: 'MTR',
                    'weave' => $item->fabric_type,
                    'is_active' => true,
                    'synced_at' => now(),
                ]);
                $created++;
            }
        }

        $total = PcFabricItem::count();
        $this->info("Created {$created} new item(s). Total pc_fabric_items: {$total}.");

        return Command::SUCCESS;
    }
}
