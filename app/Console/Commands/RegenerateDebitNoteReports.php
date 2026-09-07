<?php

namespace App\Console\Commands;

use App\Services\RpaQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegenerateDebitNoteReports extends Command
{
    protected $signature = 'rpa:regenerate-debit-notes
                            {--id=* : Specific rpa_queues row id(s) to regenerate}
                            {--all : Every non-completed deduction row (status pending/waiting/failed)}
                            {--include-test : Also process rows whose entity_id starts with "TEST" (excluded by default)}
                            {--dry-run : List which rows would be touched, write nothing}';

    protected $description = 'Re-render the debit-note-combined + original report documents for queued deduction rows (RpaQueueService::regenerateDeductionDocs), without a full Director re-approval';

    public function handle(RpaQueueService $rpa): int
    {
        // dompdf on a defect-photo-heavy report can exceed the default CLI
        // 128M limit (confirmed exhausting it during testing) — this command
        // exists specifically to make ad-hoc `-d memory_limit=...` tinker
        // runs unnecessary, so raise it here rather than documenting a flag.
        ini_set('memory_limit', '1G');

        $ids = array_map('intval', $this->option('id'));

        if (empty($ids) && ! $this->option('all')) {
            $this->error('Pass --id=<row> (repeatable) or --all.');

            return Command::FAILURE;
        }

        $query = DB::connection('rpa')->table('rpa_queues')->where('rpa_type', 'deduction');

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereIn('status', ['pending', 'waiting', 'failed']);
        }

        $rows = $query->get(['id', 'entity_id', 'status']);

        if (! $this->option('include-test')) {
            $rows = $rows->reject(fn ($r) => str_starts_with((string) $r->entity_id, 'TEST'));
        }

        if ($rows->isEmpty()) {
            $this->info('Nothing to regenerate.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['id', 'status', 'entity_id'], $rows->map(fn ($r) => [$r->id, $r->status, $r->entity_id])->all());
            $this->info("Dry run: {$rows->count()} row(s) would be regenerated. Nothing written.");

            return Command::SUCCESS;
        }

        $ok = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $success = $rpa->regenerateDeductionDocs((int) $row->id);
            if ($success) {
                $ok++;
                $this->info("#{$row->id} ({$row->entity_id}) regenerated");
            } else {
                $failed++;
                $this->warn("#{$row->id} ({$row->entity_id}) skipped — see log for reason");
            }
        }

        $this->info("Done: {$ok} regenerated, {$failed} skipped.");

        return $failed > 0 && $ok === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
