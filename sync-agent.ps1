# ApexV5 Local Sync Agent (Dual Mode)
# Syncs from BOTH Etimetracklite1 AND HikCentral to ApexV5 Server

#region Configuration
$MSSQL_SERVER = "localhost"
$MSSQL_USERNAME = "essl"
$MSSQL_PASSWORD = "Keystone@456"

# Database Names
$DB_ETIME = "Etimetracklite1"
$DB_HIK = "hikcentral"

$APEXV5_API_URL = "https://ho.apextime.in/api/punches/import"
$APEXV5_API_TOKEN = "secret-token"

# Sync settings
$DAYS_TO_SYNC = 90
$BATCH_SIZE = 50 # Increased to 50 to reduce total request count

# Security
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
[System.Net.ServicePointManager]::Expect100Continue = $false
#endregion

#region Functions
function Write-Log {
    param($Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$timestamp] $Message"
}

# --- Legacy Etimetracklite1 Logic ---
function Get-MonthlyTableName {
    param([DateTime]$Date)
    $month = $Date.Month
    $year = $Date.Year
    return "DeviceLogs_${month}_${year}"
}

function Get-EtimeLogs {
    param([DateTime]$StartDate, [DateTime]$EndDate)
    
    $connStr = "Server=$MSSQL_SERVER;Database=$DB_ETIME;User Id=$MSSQL_USERNAME;Password=$MSSQL_PASSWORD;TrustServerCertificate=True;"
    $allLogs = @()

    try {
        $conn = New-Object System.Data.SqlClient.SqlConnection($connStr)
        $conn.Open()
        Write-Log "Connected to $DB_ETIME"

        $currentDate = $StartDate
        while ($currentDate -le $EndDate) {
            $tableName = Get-MonthlyTableName -Date $currentDate
            
            $checkCmd = New-Object System.Data.SqlClient.SqlCommand("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$tableName'", $conn)
            if ($checkCmd.ExecuteScalar() -gt 0) {
                Write-Log "  Querying $tableName..."
                $query = @"
SELECT d.UserId, d.LogDate, d.Direction, d.AttDirection, d.C1, d.DeviceId, 
       u.EmployeeRFIDNumber as CardNo, u.EmployeeCode as Badgenumber, u.EmployeeName
FROM $tableName d
LEFT JOIN Employees u ON CAST(d.UserId AS NVARCHAR(50)) = CAST(u.EmployeeId AS NVARCHAR(50))
WHERE d.LogDate >= @Start AND d.LogDate <= @End
ORDER BY d.LogDate ASC
"@
                $cmd = New-Object System.Data.SqlClient.SqlCommand($query, $conn)
                $cmd.Parameters.AddWithValue("@Start", $StartDate) | Out-Null
                $cmd.Parameters.AddWithValue("@End", $EndDate) | Out-Null
                $reader = $cmd.ExecuteReader()
                while ($reader.Read()) {
                    # Normalize Direction
                    $dir = "in"
                    if ($reader["C1"] -match "out" -or $reader["AttDirection"] -match "out" -or $reader["Direction"] -match "out") { $dir = "out" }

                    $allLogs += [PSCustomObject]@{
                        Source    = "Etime"
                        UserId    = [string]$reader["UserId"]
                        LogDate   = $reader["LogDate"]
                        Direction = $dir
                        CardNo    = [string]$reader["CardNo"]
                        BadgeNo   = [string]$reader["Badgenumber"]
                        Name      = [string]$reader["EmployeeName"]
                        Dept      = "" 
                        DeviceId  = [string]$reader["DeviceId"]
                    }
                }
                $reader.Close()
            }
            $currentDate = $currentDate.AddMonths(1)
            $currentDate = New-Object DateTime($currentDate.Year, $currentDate.Month, 1)
        }
        $conn.Close()
    }
    catch { Write-Log "  ERROR querying ${DB_ETIME}: $_" }
    
    return $allLogs
}

# --- HikCentral Logic ---
function Get-HikLogs {
    param([DateTime]$StartDate, [DateTime]$EndDate)
    
    $connStr = "Server=$MSSQL_SERVER;Database=$DB_HIK;User Id=$MSSQL_USERNAME;Password=$MSSQL_PASSWORD;TrustServerCertificate=True;"
    $allLogs = @()

    try {
        $conn = New-Object System.Data.SqlClient.SqlConnection($connStr)
        $conn.Open()
        Write-Log "Connected to $DB_HIK"
        
        $tableName = "HikvisionLogs"
        $checkCmd = New-Object System.Data.SqlClient.SqlCommand("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$tableName'", $conn)
        
        if ($checkCmd.ExecuteScalar() -gt 0) {
            Write-Log "  Querying $tableName..."
            $query = @"
SELECT person_id, access_datetime, direction, card_no, device_name, person_name, emp_dept
FROM $tableName
WHERE access_datetime >= @Start AND access_datetime <= @End
ORDER BY access_datetime ASC
"@
            $cmd = New-Object System.Data.SqlClient.SqlCommand($query, $conn)
            $cmd.Parameters.AddWithValue("@Start", $StartDate) | Out-Null
            $cmd.Parameters.AddWithValue("@End", $EndDate) | Out-Null
            $reader = $cmd.ExecuteReader()
            
            while ($reader.Read()) {
                # Normalize Direction
                $dir = "in"
                if ($reader["direction"] -match "Exit" -or $reader["direction"] -match "Out") { $dir = "out" }

                $allLogs += [PSCustomObject]@{
                    Source    = "Hik"
                    UserId    = [string]$reader["person_id"]
                    LogDate   = $reader["access_datetime"]
                    Direction = $dir
                    CardNo    = [string]$reader["card_no"]
                    BadgeNo   = [string]$reader["person_id"]
                    Name      = [string]$reader["person_name"]
                    Dept      = [string]$reader["emp_dept"]
                    DeviceId  = [string]$reader["device_name"]
                }
            }
            $reader.Close()
        }
        else { Write-Log "  Table $tableName NOT FOUND" }
        $conn.Close()
    }
    catch { Write-Log "  ERROR querying ${DB_HIK}: $_" }
    
    return $allLogs
}

function Send-ToApexV5 {
    param($Logs)
    
    if ($Logs.Count -eq 0) { Write-Log "No punches to send"; return }
    
    # Convert to API Payload
    $punches = @()
    foreach ($log in $Logs) {
        $punches += @{
            device_emp_code = $log.UserId
            punch_time      = $log.LogDate.ToString("yyyy-MM-dd HH:mm:ss")
            type            = $log.Direction
            device_id       = $log.DeviceId
            card_no         = $log.CardNo
            emp_code        = $log.BadgeNo
            name            = $log.Name
            department      = $log.Dept
        }
    }

    Write-Log "Sending $($punches.Count) punches..."
    
    # Batch Send
    for ($i = 0; $i -lt $punches.Count; $i += $BATCH_SIZE) {
        $batch = $punches[$i..[Math]::Min($i + $BATCH_SIZE - 1, $punches.Count - 1)]
        $batchNum = ($i / $BATCH_SIZE) + 1
        
        try {
            $body = @{ punches = $batch; token = $APEXV5_API_TOKEN } | ConvertTo-Json -Depth 3
            
            # Using -DisableKeepAlive to prevent connection drops on long runs
            $resp = Invoke-RestMethod -Uri $APEXV5_API_URL -Method POST -Headers @{ "Authorization" = "Bearer $APEXV5_API_TOKEN" } -Body $body -ContentType "application/json" -TimeoutSec 60 -DisableKeepAlive
            
            Write-Log "  Batch ${batchNum}: SUCCESS - $($resp.imported) / $($resp.failed)"
        }
        catch {
            Write-Log "  Batch ${batchNum}: ERROR - $_"
        }
        Start-Sleep -Milliseconds 1000 # Increased wait to avoid Rate Limiting (429)
    }
}
#endregion

#region Main
Write-Log "=== ApexV5 Dual Sync Agent Started ==="
$end = Get-Date
$start = $end.AddDays(-$DAYS_TO_SYNC)

# 1. Get Etime Logs
$etime = Get-EtimeLogs -StartDate $start -EndDate $end
Write-Log "Fetched $($etime.Count) from Etime"

# 2. Get Hik Logs
$hik = Get-HikLogs -StartDate $start -EndDate $end
Write-Log "Fetched $($hik.Count) from HikCentral"

# 3. Merge
$all = $etime + $hik
Write-Log "Total Combined: $($all.Count)"

# 4. Send
if ($all.Count -gt 0) {
    # Sort by Date
    $all = $all | Sort-Object LogDate
    Send-ToApexV5 -Logs $all
}

Write-Log "=== Finished ==="
#endregion
