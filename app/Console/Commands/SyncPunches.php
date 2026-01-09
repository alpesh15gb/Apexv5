<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PunchImportService;

class SyncPunches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-punches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync punch logs from external MSSQL database';

    /**
     * Execute the console command.
     */
    public function handle(PunchImportService $service)
    {
        $this->info('Starting punch sync...');

        try {
            $service->importPunches();
            $this->info('Punch sync completed successfully.');
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
