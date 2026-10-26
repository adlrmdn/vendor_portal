<?php

namespace App\Console\Commands;

use App\Services\PcAnalysisExportService;
use Illuminate\Console\Command;

class PcExportDailyAnalysis extends Command
{
    protected $signature = 'pc:export-analysis';

    protected $description = 'Generate daily preliminary contract analysis CSVs and manifest per Spec v4 Section 11';

    public function handle(PcAnalysisExportService $exportService): int
    {
        $this->info('Starting PC daily analysis export...');

        try {
            $result = $exportService->generateExport();

            $this->info("Successfully exported {$result['contracts_count']} contract(s) and {$result['lines_count']} line(s).");
            $this->info("Files written to analysis/latest/ and analysis/archive/{$result['date']}/");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to export daily analysis: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('PC Daily Analysis Export Failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
