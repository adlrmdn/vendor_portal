<?php

namespace App\Console\Commands;

use App\Models\Roll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeRollNumbers extends Command
{
    protected $signature = 'rolls:normalize-numbers
                            {--dry-run : Count what would change, write nothing}';

    protected $description = 'Rewrite roll_number to the standard form (Roll::qrSafeName) for rows still carrying the raw "/"-bearing PO number';

    public function handle()
    {
        $rolls = Roll::all(['id', 'roll_number']);
        $toUpdate = $rolls->filter(fn (Roll $r) => Roll::qrSafeName($r->roll_number) !== $r->roll_number);

        if ($toUpdate->isEmpty()) {
            $this->info('Every roll_number is already in standard form.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry run: {$toUpdate->count()} roll(s) would be renamed.");
            $this->table(
                ['roll_number', '-> new'],
                $toUpdate->take(5)->map(fn (Roll $r) => [$r->roll_number, Roll::qrSafeName($r->roll_number)])->all()
            );

            return Command::SUCCESS;
        }

        $renamed = 0;
        DB::transaction(function () use ($toUpdate, &$renamed) {
            foreach ($toUpdate as $roll) {
                $roll->roll_number = Roll::qrSafeName($roll->roll_number);
                $roll->save();
                $renamed++;
            }
        });

        $this->info("Renamed {$renamed} roll_number(s) to standard form.");

        return Command::SUCCESS;
    }
}
