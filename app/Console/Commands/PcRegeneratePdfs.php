<?php

namespace App\Console\Commands;

use App\Models\PcContract;
use App\Services\PcContractPdfService;
use Illuminate\Console\Command;

class PcRegeneratePdfs extends Command
{
    protected $signature = 'pc:regenerate-pdfs {--contract= : Contract number or ID to regenerate}';

    protected $description = 'Re-render and persist frozen PDF document(s) using the latest template layout.';

    public function handle(PcContractPdfService $pdfService): int
    {
        $contractIdentifier = $this->option('contract');

        $query = PcContract::query();

        if ($contractIdentifier) {
            $query->where(function ($q) use ($contractIdentifier) {
                $q->where('contract_number', $contractIdentifier)
                  ->orWhere('id', $contractIdentifier);
            });
        } else {
            $query->whereNotNull('confirmed_at')
                  ->orWhereNotNull('approval_decision_at')
                  ->orWhereHas('files', function ($q) {
                      $q->where('file_type', 'contract_pdf');
                  });
        }

        $contracts = $query->get();

        if ($contracts->isEmpty()) {
            $this->warn('No contracts matched for regeneration.');
            return Command::SUCCESS;
        }

        $this->info("Found {$contracts->count()} contract(s) to regenerate.");

        foreach ($contracts as $contract) {
            $this->line("Regenerating PDF for: {$contract->contract_number} ({$contract->id})...");
            try {
                $file = $pdfService->generate($contract);
                $this->info("  -> Stored: {$file->path} ({$file->size_bytes} bytes)");
            } catch (\Throwable $e) {
                $this->error("  -> Failed: {$e->getMessage()}");
            }
        }

        $this->info('Regeneration complete.');

        return Command::SUCCESS;
    }
}
