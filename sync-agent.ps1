# ApexV5 Local Sync Agent
# Syncs DeviceLogs from on-premise MSSQL to ApexV5 Server
# Run this script on your on-premise Windows machine with SQL Server access

#region Configuration
$MSSQL_SERVER = "localhost"  # Change to your SQL Server address
$MSSQL_DATABASE = "Etimetracklite1"
$MSSQL_USERNAME = "essl"
$MSSQL_PASSWORD = "Keystone@456"

$APEXV5_API_URL = "https://ho.apextime.in/api/punches/import"  # Your ApexV5 server URL
$APEXV5_API_TOKEN = "secret-token"  # Use value from .env SYNC_API_TOKEN

# Sync settings
$DAYS_TO_SYNC = 90  # Sync last 90 days of data
$BATCH_SIZE = 100  # Reduced batch size to prevent timeouts

# Force TLS 1.2 (Required for modern servers)
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
# Disable Expect: 100-continue (Fixes "Connection closed" errors)
[System.Net.ServicePointManager]::Expect100Continue = $false
#endregion

#region Functions
function Write-Log {
    param($Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$timestamp] $Message"
}

function Get-MonthlyTableName {
    param([DateTime]$Date)
    $month = $Date.Month
    $year = $Date.Year
    return "DeviceLogs_${month}_${year}"
}

function Get-DeviceLogs {
    param(
        [DateTime]$StartDate,
        [DateTime]$EndDate
    )
    
    $connectionString = "Server=$MSSQL_SERVER;Database=$MSSQL_DATABASE;User Id=$MSSQL_USERNAME;Password=$MSSQL_PASSWORD;TrustServerCertificate=True;"
    
    try {
        $connection = New-Object System.Data.SqlClient.SqlConnection($connectionString)
        $connection.Open()
        
        Write-Log "Connected to MSSQL: $MSSQL_SERVER"
        
        $allLogs = @()
        
        # Query each monthly table in the date range
        $currentDate = $StartDate
        while ($currentDate -le $EndDate) {
            $tableName = Get-MonthlyTableName -Date $currentDate
            
            # Check if table exists
            $checkQuery = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$tableName'"
            $checkCmd = New-Object System.Data.SqlClient.SqlCommand($checkQuery, $connection)
            $tableExists = $checkCmd.ExecuteScalar()
            
            if ($tableExists -gt 0) {
                Write-Log "Querying table: $tableName"
                
                $query = @"
SELECT 
    d.DeviceLogId,
    d.UserId,
    d.DeviceId,
    d.LogDate,
    d.Direction,
    d.AttDirection,
    d.C1,
    d.WorkCode,
    u.CardNo,
    u.Badgenumber
FROM $tableName d
LEFT JOIN UserInfo u ON d.UserId = u.UserId
WHERE d.LogDate >= @StartDate 
  AND d.LogDate <= @EndDate
ORDER BY d.LogDate ASC
"@
                
                $cmd = New-Object System.Data.SqlClient.SqlCommand($query, $connection)
                $cmd.Parameters.AddWithValue("@StartDate", $StartDate) | Out-Null
                $cmd.Parameters.AddWithValue("@EndDate", $EndDate) | Out-Null
                
                $reader = $cmd.ExecuteReader()
                
                while ($reader.Read()) {
                    $allLogs += [PSCustomObject]@{
                        DeviceLogId  = $reader["DeviceLogId"]
                        UserId       = $reader["UserId"]
                        DeviceId     = $reader["DeviceId"]
                        LogDate      = $reader["LogDate"]
                        Direction    = $reader["Direction"]
                        AttDirection = $reader["AttDirection"]
                        C1           = $reader["C1"]
                        WorkCode     = $reader["WorkCode"]
                        CardNo       = $reader["CardNo"]
                        Badgenumber  = $reader["Badgenumber"]
                    }
                }
                
                $uniqueUserIds = $allLogs | Select-Object -ExpandProperty UserId -Unique
                Write-Log "  Unique User IDs found: $($uniqueUserIds -join ', ')"
                
                $reader.Close()
                Write-Log "  Found $($allLogs.Count) records in $tableName"
            }
            
            # Move to next month
            $currentDate = $currentDate.AddMonths(1)
            $currentDate = New-Object DateTime($currentDate.Year, $currentDate.Month, 1)
        }
        
        $connection.Close()
        Write-Log "Total records retrieved: $($allLogs.Count)"
        
        return $allLogs
    }
    catch {
        Write-Log "ERROR querying MSSQL: $_"
        return @()
    }
}

function Convert-ToPunchData {
    param($DeviceLogs)
    
    $punches = @()
    
    foreach ($log in $DeviceLogs) {
        # Determine punch direction (in/out)
        $direction = "in"
        if ($log.C1 -match "out" -or $log.AttDirection -match "out" -or $log.Direction -match "out") {
            $direction = "out"
        }
        
        $punches += @{
            device_emp_code = [string]$log.UserId
            punch_time      = $log.LogDate.ToString("yyyy-MM-dd HH:mm:ss")
            type            = $direction
            device_id       = [string]$log.DeviceId
            card_no         = [string]$log.CardNo
            emp_code        = [string]$log.Badgenumber
        }
    }
    
    return $punches
}

function Send-ToApexV5 {
    param($Punches)
    
    if ($Punches.Count -eq 0) {
        Write-Log "No punches to send"
        return $true
    }
    
    Write-Log "Sending $($Punches.Count) punches to ApexV5..."
    
    # Split into batches
    $batches = @()
    for ($i = 0; $i -lt $Punches.Count; $i += $BATCH_SIZE) {
        $end = [Math]::Min($i + $BATCH_SIZE - 1, $Punches.Count - 1)
        $batches += , @($Punches[$i..$end])
    }
    
    Write-Log "Split into $($batches.Count) batches of max $BATCH_SIZE records"
    
    $successCount = 0
    $failCount = 0
    
    for ($b = 0; $b -lt $batches.Count; $b++) {
        $batch = $batches[$b]
        $batchNum = $b + 1
        
        Write-Log "Sending batch $batchNum/$($batches.Count) ($($batch.Count) records)..."
        
        try {
            $body = @{
                punches = $batch
            } | ConvertTo-Json -Depth 3
            
            $headers = @{
                "Content-Type" = "application/json"
            }
            
            if ($APEXV5_API_TOKEN) {
                $headers["Authorization"] = "Bearer $APEXV5_API_TOKEN"
            }
            
            $response = Invoke-RestMethod -Uri $APEXV5_API_URL -Method POST -Headers $headers -Body $body -TimeoutSec 30
            
            Write-Log "  Batch ${batchNum}: SUCCESS - $($response.imported) imported, $($response.failed) failed"
            $successCount += $response.imported
            $failCount += $response.failed
        }
        catch {
            Write-Log "  Batch ${batchNum}: ERROR - $_"
            $failCount += $batch.Count
        }
        
        # Small delay between batches
        Start-Sleep -Milliseconds 500
    }
    
    Write-Log "SYNC COMPLETE: $successCount imported, $failCount failed"
    return $true
}
#endregion

#region Main Execution
Write-Log "=== ApexV5 Local Sync Agent Started ==="
Write-Log "Server: $APEXV5_API_URL"
Write-Log "Days to sync: $DAYS_TO_SYNC"

$endDate = Get-Date
$startDate = $endDate.AddDays(-$DAYS_TO_SYNC)

Write-Log "Date range: $($startDate.ToString('yyyy-MM-dd')) to $($endDate.ToString('yyyy-MM-dd'))"

# Step 1: Get data from MSSQL
$deviceLogs = Get-DeviceLogs -StartDate $startDate -EndDate $endDate

if ($deviceLogs.Count -eq 0) {
    Write-Log "No data to sync. Exiting."
    exit 0
}

# Step 2: Convert to ApexV5 format
$punches = Convert-ToPunchData -DeviceLogs $deviceLogs

# Step 3: Send to API
$result = Send-ToApexV5 -Punches $punches

Write-Log "=== Sync Agent Finished ==="
#endregion
