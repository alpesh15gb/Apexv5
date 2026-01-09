<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Location;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;

class SyncEtimetrack extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:etimetrack';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync master data from Etimetracklite MSSQL database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Sync from Etimetracklite...');

        try {
            DB::connection('sqlsrv')->getPdo();
            $this->info('Connected to MSSQL successfully.');
        } catch (\Exception $e) {
            $this->error('Could not connect to MSSQL: ' . $e->getMessage());
            return 1;
        }

        $this->syncCompanies();
        $this->syncDepartments(); // Handles Branch/Location intermediate creation
        $this->syncEmployees();

        $this->info('Sync completed successfully.');
        return 0;
    }

    private function syncCompanies()
    {
        $this->info('Syncing Companies...');
        $mssqlCompanies = DB::connection('sqlsrv')->table('Companies')->get();

        foreach ($mssqlCompanies as $source) {
            $code = $source->CompanyId; // Use ID as code since no CompanyCode exists
            $name = trim(($source->CompanyFName ?? '') . ' ' . ($source->CompanySName ?? ''));

            Company::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name ?: 'Company ' . $code,
                    'address' => $source->CompanyAddress ?? null,
                ]
            );
        }
        $this->info('Companies synced: ' . count($mssqlCompanies));
    }

    private function syncDepartments()
    {
        $this->info('Syncing Departments (and creating default Branch/Location)...');
        // Assuming Departments are linked to Companies in MSSQL via CompanyId
        $mssqlDepartments = DB::connection('sqlsrv')->table('Departments')->get();

        foreach ($mssqlDepartments as $source) {
            // 1. Find the Parent Company in Local DB
            // We need to fetch the Source Company Code to match. 
            // If MSSQL Departments table has CompanyId, we assume we can fetch that Company.

            // Departments don't have CompanyId - infer from first Employee using this Department
            $sampleEmployee = DB::connection('sqlsrv')->table('Employees')
                ->where('DepartmentId', $source->DepartmentId)
                ->first();

            if (!$sampleEmployee) {
                $this->warn("Skipping Department {$source->DepartmentId}: No employees found to infer company.");
                continue;
            }

            $company = Company::where('code', $sampleEmployee->CompanyId)->first();

            if (!$company) {
                $this->warn("Skipping Department {$source->DepartmentId}: Local Company not found.");
                continue;
            }

            // 2. Ensure Default Branch exists for this Company
            $branch = Branch::firstOrCreate(
                ['code' => $company->code . '-BR-MAIN', 'company_id' => $company->id],
                ['name' => 'Main Branch', 'address' => $company->address]
            );

            // 3. Ensure Default Location exists for this Branch
            $location = Location::firstOrCreate(
                ['code' => $company->code . '-LOC-MAIN', 'branch_id' => $branch->id],
                ['name' => 'Main Location', 'address' => $company->address]
            );

            // 4. Upsert Department
            $deptName = trim(($source->DepartmentFName ?? '') . ' ' . ($source->DepartmentSName ?? ''));
            Department::updateOrCreate(
                ['code' => $source->DepartmentId],
                [
                    'name' => $deptName ?: 'Department ' . $source->DepartmentId,
                    'location_id' => $location->id
                ]
            );
        }
        $this->info('Departments synced: ' . count($mssqlDepartments));
    }

    private function syncEmployees()
    {
        $this->info('Syncing Employees...');
        $mssqlEmployees = DB::connection('sqlsrv')->table('Employees')->get();

        foreach ($mssqlEmployees as $source) {
            // Find Department
            $sourceDept = DB::connection('sqlsrv')->table('Departments')
                ->where('DepartmentId', $source->DepartmentId)
                ->first();

            $deptId = null;
            if ($sourceDept) {
                $localDept = Department::where('code', $sourceDept->DepartmentId)->first();
                $deptId = $localDept?->id;
            }

            // Skip if no department found (department_id is required)
            if (!$deptId) {
                $this->warn("Skipping Employee {$source->EmployeeCode}: No department mapping found.");
                continue;
            }

            // Create/Update Employee
            Employee::updateOrCreate(
                ['device_emp_code' => $source->EmployeeCode],
                [
                    'name' => $source->EmployeeName,
                    'is_active' => $source->RecordStatus == 1, // 1=Active, others inactive
                    'department_id' => $deptId,
                    'email' => $source->Email ?? null,
                    'joining_date' => $source->DOJ ?? null,
                ]
            );
        }
        $this->info('Employees synced: ' . count($mssqlEmployees));
    }
}
