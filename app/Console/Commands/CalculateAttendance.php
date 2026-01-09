<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceService;
use Carbon\Carbon;

class CalculateAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-attendance {date? : The date to calculate for (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate daily attendance based on punch logs';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceService $service)
    {
        $date = $this->argument('date') ?? Carbon::today()->toDateString();

        $this->info("Calculating attendance for: {$date}");

        try {
            $service->calculateDailyAttendance($date);
            $this->info('Attendance calculation completed successfully.');
        } catch (\Exception $e) {
            $this->error('Calculation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
