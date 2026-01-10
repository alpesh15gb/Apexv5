<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\PunchLog;
use Illuminate\Support\Facades\DB;

class AuditPunches extends Command
{
    protected $signature = 'app:audit-punches {--fix : Auto-fix mislinked punches}';
    protected $description = 'Audits and fixes incorrectly linked punches (e.g. HO012 linked to HO/004)';

    public function handle()
    {
        $this->info("--- Auditing Punch Links ---");

        // 1. Get all unique combinations of code + linked ID
        $links = PunchLog::whereNotNull('employee_id')
            ->select('device_emp_code', 'employee_id')
            ->distinct()
            ->get();

        $mislinkedCount = 0;
        $fixableCount = 0;

        foreach ($links as $link) {
            $employee = Employee::find($link->employee_id);

            if (!$employee) {
                $this->error("Punch code '{$link->device_emp_code}' linked to NON-EXISTENT ID {$link->employee_id}");
                continue;
            }

            // Check if this link makes sense
            // Normalize both to compare
            $punchCode = trim($link->device_emp_code);
            $empCode = trim($employee->device_emp_code);

            // Simple strict check first
            $match = ($punchCode === $empCode);

            // Loose check (ignore HO/ vs HO, leading zeros)
            if (!$match) {
                $pNum = preg_replace('/[^0-9]/', '', $punchCode);
                $eNum = preg_replace('/[^0-9]/', '', $empCode);
                if (intval($pNum) == intval($eNum) && $pNum !== '') {
                    $match = true;
                }
            }

            if (!$match) {
                // It's a MISMATCH!
                // Example: Punch HO012 linked to Emp HO/004

                // Find who SHOULD own it
                $correctOwner = $this->findCorrectOwner($punchCode);

                $ownerInfo = $correctOwner ? "Should be: {$correctOwner->name} ({$correctOwner->device_emp_code})" : "NO CORRECT OWNER FOUND";

                // Count how many punches are affected
                $count = PunchLog::where('device_emp_code', $punchCode)
                    ->where('employee_id', $link->employee_id)
                    ->count();

                $this->warn("MISMATCH: Code '{$punchCode}' is linked to '{$employee->name}' ({$empCode})");
                $this->info("    -> Affected Punches: {$count}");
                $this->info("    -> {$ownerInfo}");

                $mislinkedCount += $count;

                if ($correctOwner && $this->option('fix')) {
                    PunchLog::where('device_emp_code', $punchCode)
                        ->where('employee_id', $link->employee_id)
                        ->update(['employee_id' => $correctOwner->id]);
                    $this->info("    [FIXED] Re-linked to {$correctOwner->name}");
                    $fixableCount += $count;
                }
            }
        }

        $this->info("--------------------------------");
        $this->info("Total Mislinked Punches Found: {$mislinkedCount}");
        if ($this->option('fix')) {
            $this->info("Total Fixed: {$fixableCount}");
            $this->info("Don't forget to run recalculate-attendance!");
        } else {
            $this->info("Run with --fix to apply changes.");
        }

        return 0;
    }

    private function findCorrectOwner($code)
    {
        // Logic similar to PunchImportService
        // 1. Strict
        $emp = Employee::where('device_emp_code', $code)->first();
        if ($emp)
            return $emp;

        // 2. HO/ mismatch
        // HO012 -> HO/012
        if (stripos($code, 'HO') === 0 && strpos($code, '/') === false) {
            $number = preg_replace('/[^0-9]/', '', $code);
            $emp = Employee::where('device_emp_code', 'HO/' . $number)
                ->orWhere('device_emp_code', 'HO/' . str_pad($number, 3, '0', STR_PAD_LEFT))
                ->first();
            if ($emp)
                return $emp;
        }

        // 3. Numeric match
        $normalizedId = ltrim($code, '0');
        $emp = Employee::where('device_emp_code', $normalizedId)
            ->orWhere('device_emp_code', 'HO/' . str_pad($normalizedId, 3, '0', STR_PAD_LEFT))
            ->orWhere('device_emp_code', 'MIPA' . $normalizedId)
            ->orWhere('device_emp_code', intval($code))
            ->first();

        return $emp;
    }
}
