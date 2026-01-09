<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shift;
use App\Models\Employee;

class AssignDefaultShift extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:assign-default-shift';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a default shift and assign to all employees without a shift';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Create Default Shift if not exists
        $shift = Shift::firstOrCreate(
            ['code' => 'GEN'],
            [
                'name' => 'General Shift',
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'break_duration' => 60,
                'grace_period' => 15, // 15 mins grace
            ]
        );

        $this->info("Using Shift: {$shift->name} ({$shift->start_time} - {$shift->end_time})");

        // 2. Assign to employees without shift
        $updated = Employee::whereNull('shift_id')->update(['shift_id' => $shift->id]);

        $this->info("Assigned default shift to {$updated} employees.");

        return Command::SUCCESS;
    }
}
