<?php

namespace App\Console\Commands;

use App\Services\DesknetSyncService;
use Illuminate\Console\Command;

class SyncDesknet extends Command
{
    protected $signature = 'desknet:sync
                            {--type=all : Sync type: all, staff, project_codes}
                            {--user= : User ID who triggered the sync}';

    protected $description = 'Sync data from Desknet NEO AppSuite (staff list, project codes, departments)';

    public function handle(DesknetSyncService $service): int
    {
        $type = $this->option('type');
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $triggerType = $userId ? 'manual' : 'scheduled';

        $this->info("Starting Desknet sync ({$type})...");

        try {
            if ($type === 'staff') {
                $log = $service->syncStaff($userId, $triggerType);
                $this->printResult('Staff', $log);
            } elseif ($type === 'project_codes') {
                $log = $service->syncProjectCodes($userId, $triggerType);
                $this->printResult('Project Codes', $log);
            } else {
                $results = $service->syncAll($userId, $triggerType);
                $this->printResult('Staff', $results['staff']);
                $this->printResult('Project Codes', $results['project_codes']);
            }
        } catch (\Throwable $e) {
            $this->error("Sync failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        $this->info('Sync completed.');
        return Command::SUCCESS;
    }

    protected function printResult(string $label, $log): void
    {
        if ($log->status === 'success') {
            $this->info("  [{$label}] Created: {$log->records_created}, Updated: {$log->records_updated}, Deactivated: {$log->records_deactivated}");
        } else {
            $this->error("  [{$label}] FAILED: {$log->error_message}");
        }
    }
}
