<?php

namespace App\Console\Commands;

use App\Services\DesknetSyncService;
use Illuminate\Console\Command;

class DesknetDiagnoseApp extends Command
{
    protected $signature = 'desknet:diagnose-app
                            {app_id=12 : Desknet app ID to diagnose}
                            {--view= : Optional view_id to use}
                            {--limit=3 : Number of records to dump}';

    protected $description = 'Dump raw Desknet AppSuite record structure for mapping fields';

    public function handle(DesknetSyncService $service): int
    {
        $appId = (int) $this->argument('app_id');
        $viewId = $this->option('view') ? (int) $this->option('view') : null;
        $limit = (int) $this->option('limit');

        $this->info("Diagnosing Desknet app_id={$appId}" . ($viewId ? " view_id={$viewId}" : '') . "...");

        try {
            $result = $service->diagnoseApp($appId, $limit, $viewId);

            $this->info("Fetched {$result['record_count']} record(s).");
            $this->newLine();

            foreach ($result['records'] as $record) {
                $this->info("Record #{$record['index']} (list view):");
                $this->line(json_encode($record['sample'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->newLine();
            }

            // Fetch full detail for the first record
            if (!empty($result['records'])) {
                $firstDataId = $result['records'][0]['sample']['data_id']['val'] ?? null;
                if ($firstDataId) {
                    $this->info("Fetching full detail for data_id={$firstDataId}...");
                    $this->newLine();
                    try {
                        $detail = $service->diagnoseRecordDetail($appId, (string) $firstDataId);
                        $this->info("Full detail record:");
                        $this->line(json_encode($detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    } catch (\Throwable $e) {
                        $this->error("Could not fetch detail: {$e->getMessage()}");
                    }
                    $this->newLine();
                }
            }

            $this->warn('Check storage/logs/laravel.log for the same output.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Diagnosis failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
