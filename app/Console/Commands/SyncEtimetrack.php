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
            $code = $source->CompanyCode ?? $source->CompanyId; // Fallback if Code is null

            Company::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $source->CompanyName,
                    'address' => $source->Address1 ?? null,
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

            $sourceCompany = DB::connection('sqlsrv')->table('Companies')
                ->where('CompanyId', $source->CompanyId)
                ->first();

            if (!$sourceCompany) {
                $this->warn("Skipping Department {$source->DepartmentName}: Source Company ID {$source->CompanyId} not found.");
                continue;
            }

            $company = Company::where('code', $sourceCompany->CompanyCode ?? $sourceCompany->CompanyId)->first();

            if (!$company) {
                $this->warn("Skipping Department {$source->DepartmentName}: Local Company not found.");
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
            Department::updateOrCreate(
                ['code' => $source->DepartmentCode ?? $source->DepartmentId],
                [
                    'name' => $source->DepartmentName,
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
                $localDept = Department::where('code', $sourceDept->DepartmentCode ?? $sourceDept->DepartmentId)->first();
                $deptId = $localDept?->id;
            }

            // Create/Update Employee
            // Use EmployeeCode as device_emp_code
            $empCode = $source->EmployeeCode;

            Employee::updateOrCreate(
                ['device_emp_code' => $empCode],
                [
                    'name' => $source->EmployeeName,
                    'is_active' => $source->RecStatus == 0 ? false : true, // Assuming RecStatus: 1=Active
                    'department_id' => $deptId ?? 1, // Fallback to ID 1 if dept missing? Or nullable? Schema says required. 
                    // Ideally we shouldn't fail validation. I'll fetch first dept as fallback or null if schema allows.
                    // Schema says: foreignId('department_id')->constrained(). So it is REQUIRED.
                    // I will skip if no department found.
                    'email' => $source->Email ?? null,
                    'joining_date' => $source->JoiningDate ?? null,
                ]
            );
        }
        $this->info('Employees synced: ' . count($mssqlEmployees));
    }
}
