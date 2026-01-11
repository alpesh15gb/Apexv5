# ApexV5 Employee Import Script (Standalone)
# Syncs Employees directly from Etimetracklite1 to ApexV5

#region Configuration
$MSSQL_SERVER = "localhost"
$MSSQL_USERNAME = "essl"
$MSSQL_PASSWORD = "Keystone@456"

# Database Name
$DB_NAME = "hikcentral"

$APEXV5_API_URL = "https://ho.apextime.in/api/employees/sync" # New Endpoint
$APEXV5_API_TOKEN = "Keystone@456" # UPDATE THIS

# Security
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
[System.Net.ServicePointManager]::Expect100Continue = $false
#endregion

function Write-Log {
    param($Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$timestamp] $Message"
}

function Get-Employees {
    $connStr = "Server=$MSSQL_SERVER;Database=$DB_NAME;User Id=$MSSQL_USERNAME;Password=$MSSQL_PASSWORD;TrustServerCertificate=True;"
    $employees = @()

    try {
        $conn = New-Object System.Data.SqlClient.SqlConnection($connStr)
        $conn.Open()
        Write-Log "Connected to $DB_NAME"

        # HikCentral - Extract unique employees from Logs
        $query = @"
SELECT DISTINCT person_id as EmployeeCode, person_name as EmployeeName, card_no as EmployeeRFIDNumber, emp_dept as DepartmentName
FROM HikvisionLogs
WHERE person_name IS NOT NULL AND person_name <> ''
"@

        $cmd = New-Object System.Data.SqlClient.SqlCommand($query, $conn)
        $reader = $cmd.ExecuteReader()
        
        while ($reader.Read()) {
            $employees += @{
                device_emp_code = [string]$reader["EmployeeCode"]
                name            = [string]$reader["EmployeeName"]
                card_no         = [string]$reader["EmployeeRFIDNumber"]
                department      = [string]$reader["DepartmentName"]
            }
        }
        $reader.Close()
        $conn.Close()
    }
    catch { Write-Log "Error querying Employees: $_" }
    
    return $employees
}

function Send-Employees {
    param($Employees)
    
    if ($Employees.Count -eq 0) { Write-Log "No employees found."; return }
    
    Write-Log "Found $($Employees.Count) employees via SQL."
    
    # Batch Send
    $batchSize = 50
    for ($i = 0; $i -lt $Employees.Count; $i += $batchSize) {
        $batch = $Employees[$i..[Math]::Min($i + $batchSize - 1, $Employees.Count - 1)]
        $batchNum = ($i / $batchSize) + 1
        
        try {
            $body = @{ employees = $batch; token = $APEXV5_API_TOKEN } | ConvertTo-Json -Depth 3
            
            $resp = Invoke-RestMethod -Uri $APEXV5_API_URL -Method POST -Headers @{ "Authorization" = "Bearer $APEXV5_API_TOKEN" } -Body $body -ContentType "application/json" -TimeoutSec 30
            
            Write-Log "  Batch ${batchNum}: SUCCESS - $($resp.imported) Imported / $($resp.updated) Updated / $($resp.failed) Failed"
            
            if ($resp.errors) {
                foreach ($err in $resp.errors) {
                    Write-Log "    FAIL: $err"
                }
            }
        }
        catch {
            Write-Log "  Batch ${batchNum}: ERROR - $_"
        }
        
        # DEBUG: Print sample of first batch to verify structure
        if ($i -eq 0) {
            Write-Log "  DEBUG PAYLOAD: $($batch[0] | ConvertTo-Json -Depth 2)"
        }

        Start-Sleep -Milliseconds 500
    }
}

# Main
Write-Log "=== ApexV5 Employee Sync Started ==="
$emps = Get-Employees
Send-Employees -Employees $emps
Write-Log "=== Finished ==="
