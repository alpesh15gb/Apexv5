<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class RecalculateAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:recalculate-attendance {days=30 : Number of days to look back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate attendance for the last X days for all employees';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceService $service)
    {
        $days = $this->argument('days');
        $start = Carbon::today()->subDays($days);
        $end = Carbon::today();

        $this->info("Recalculating attendance from {$start->toDateString()} to {$end->toDateString()}...");

        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            $this->info("Processing: " . $date->toDateString());
            $service->calculateDailyAttendance($date->toDateString());
        }

        $this->info('Recalculation complete.');
        return Command::SUCCESS;
    }
}
