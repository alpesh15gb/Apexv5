<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sync punches every 15 minutes
        $schedule->command('app:sync-punches')->everyFifteenMinutes();

        // Calculate attendance daily at midnight (or frequently if real-time needed)
        // For real-time updates, we can run it hourly or every time punches are synced
        $schedule->command('app:calculate-attendance')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
