<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaves = [
            [
                'name' => 'Casual Leave',
                'code' => 'CL',
                'days_allowed' => 12,
                'is_paid' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Sick Leave',
                'code' => 'SL',
                'days_allowed' => 10,
                'is_paid' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Privilege Leave',
                'code' => 'PL',
                'days_allowed' => 15,
                'is_paid' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Leave Without Pay',
                'code' => 'LWP',
                'days_allowed' => 0,
                'is_paid' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($leaves as $leave) {
            // Update or create based on code
            DB::table('leave_types')->updateOrInsert(
                ['code' => $leave['code']],
                $leave
            );
        }
    }
}
