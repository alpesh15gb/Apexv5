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
$BATCH_SIZE = 100

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
                        Dept      = "" # Etime dept join is complex, skipping for now
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
    catch { Write-Log "  ERROR querying $DB_ETIME: $_" }
    
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
    catch { Write-Log "  ERROR querying $DB_HIK: $_" }
    
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
            $headers = @{ "Content-Type" = "application/json" }
            if ($APEXV5_API_TOKEN) { $headers["Authorization"] = "Bearer $APEXV5_API_TOKEN" }
            
            $resp = Invoke-RestMethod -Uri $APEXV5_API_URL -Method POST -Headers $headers -Body $body -TimeoutSec 30
            Write-Log "  Batch $batchNum: SUCCESS - $($resp.imported) / $($resp.failed)"
        }
        catch {
            Write-Log "  Batch $batchNum: ERROR - $_"
        }
        Start-Sleep -Milliseconds 500
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
# Syncs from BOTH Etimetracklite1 AND HikCentral to ApexV5 Server

#region Configuration
$MSSQL_SERVER = "localhost"
$MSSQL_USERNAME = "essl"
$MSSQL_PASSWORD = "Keystone@456"

# Database Names
$DB_ETIME = "Etimetracklite1"
$DB_HIK = "hikcentral"

$APEXV5_API_URL = "https://ho.apextime.in/api/punches/import"
$APEXV5_API_TOKEN = "secret-token"  # UPDATED: Use value from .env SYNC_API_TOKEN

# Sync settings
$DAYS_TO_SYNC = 12 
$BATCH_SIZE = 100

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
       u.EmployeeRFIDNumber as CardNo, u.EmployeeCode as Badgenumber
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
    catch { Write-Log "  ERROR querying $DB_ETIME: $_" }
    
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
SELECT person_id, access_datetime, direction, card_no, device_name
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
                    BadgeNo   = [string]$reader["person_id"] # Use PersonID as Badge
                    DeviceId  = [string]$reader["device_name"]
                }
            }
            $reader.Close()
        }
        else { Write-Log "  Table $tableName NOT FOUND" }
        $conn.Close()
    }
    catch { Write-Log "  ERROR querying $DB_HIK: $_" }
    
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
        }
    }

    Write-Log "Sending $($punches.Count) punches..."
    
    # Batch Send
    for ($i = 0; $i -lt $punches.Count; $i += $BATCH_SIZE) {
        $batch = $punches[$i..[Math]::Min($i + $BATCH_SIZE - 1, $punches.Count - 1)]
        $batchNum = ($i / $BATCH_SIZE) + 1
        
        try {
            $body = @{ punches = $batch; token = $APEXV5_API_TOKEN } | ConvertTo-Json -Depth 3
            $headers = @{ "Content-Type" = "application/json" }
            if ($APEXV5_API_TOKEN) { $headers["Authorization"] = "Bearer $APEXV5_API_TOKEN" }
            
            $resp = Invoke-RestMethod -Uri $APEXV5_API_URL -Method POST -Headers $headers -Body $body -TimeoutSec 30
            Write-Log "  Batch $batchNum: SUCCESS - $($resp.imported) / $($resp.failed)"
        }
        catch {
            Write-Log "  Batch $batchNum: ERROR - $_"
        }
        Start-Sleep -Milliseconds 500
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
# Syncs HikvisionLogs from on-premise MSSQL to ApexV5 Server
# Run this script on your on-premise Windows machine with SQL Server access

#region Configuration
$MSSQL_SERVER = "localhost"  # Change to your SQL Server address
$MSSQL_DATABASE = "hikcentral" # UPDATED: Database Name
$MSSQL_USERNAME = "essl"
$MSSQL_PASSWORD = "Keystone@456"

$APEXV5_API_URL = "https://ho.apextime.in/api/punches/import"  # Your ApexV5 server URL
$APEXV5_API_TOKEN = "secret-token"  # Use value from .env SYNC_API_TOKEN

# Sync settings
$DAYS_TO_SYNC = 90  # Sync last 90 days of data
$BATCH_SIZE = 100  # Batch size

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

function Get-HikLogs {
    param(
        [DateTime]$StartDate,
        [DateTime]$EndDate
    )
    
    $connectionString = "Server=$MSSQL_SERVER;Database=$MSSQL_DATABASE;User Id=$MSSQL_USERNAME;Password=$MSSQL_PASSWORD;TrustServerCertificate=True;"
    
    try {
        $connection = New-Object System.Data.SqlClient.SqlConnection($connectionString)
        $connection.Open()
        
        Write-Log "Connected to MSSQL: $MSSQL_SERVER ($MSSQL_DATABASE)"
        
        $allLogs = @()
        
        # Check if table exists
        $tableName = "HikvisionLogs"
        $checkQuery = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '$tableName'"
        $checkCmd = New-Object System.Data.SqlClient.SqlCommand($checkQuery, $connection)
        $tableExists = $checkCmd.ExecuteScalar()
        
        if ($tableExists -gt 0) {
            Write-Log "Querying table: $tableName"
            
            $query = @"
SELECT 
    LogId,
    person_id,
    access_datetime,
    direction,
    card_no,
    device_name
FROM $tableName
WHERE access_datetime >= @StartDate 
  AND access_datetime <= @EndDate
ORDER BY access_datetime ASC
"@
            
            $cmd = New-Object System.Data.SqlClient.SqlCommand($query, $connection)
            $cmd.Parameters.AddWithValue("@StartDate", $StartDate) | Out-Null
            $cmd.Parameters.AddWithValue("@EndDate", $EndDate) | Out-Null
            
            $reader = $cmd.ExecuteReader()
            
            while ($reader.Read()) {
                $allLogs += [PSCustomObject]@{
                    LogId     = $reader["LogId"]
                    UserId    = $reader["person_id"]
                    LogDate   = $reader["access_datetime"]
                    Direction = $reader["direction"]
                    CardNo    = $reader["card_no"]
                    DeviceId  = $reader["device_name"]
                }
            }
            
            $reader.Close()
            Write-Log "  Found $($allLogs.Count) records in $tableName"
            
            $uniqueUserIds = $allLogs | Select-Object -ExpandProperty UserId -Unique
            Write-Log "  Unique User IDs found: $($uniqueUserIds -join ', ')"
        }
        else {
            Write-Log "  Table $tableName NOT FOUND in $MSSQL_DATABASE"
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
        # Check explicit direction from HikCentral (Enter/Exit)
        if ($log.Direction -match "Exit" -or $log.Direction -match "Out") {
            $direction = "out"
        }
        
        # Handle cases where null or empty
        $cardNo = if ($log.CardNo) { $log.CardNo.ToString() } else { "" }
        $empCode = if ($log.UserId) { $log.UserId.ToString() } else { "" }
        $logDate = if ($log.LogDate) { $log.LogDate.ToString("yyyy-MM-dd HH:mm:ss") } else { (Get-Date).ToString("yyyy-MM-dd HH:mm:ss") }

        $punches += @{
            device_emp_code = $empCode
            punch_time      = $logDate
            type            = $direction
            device_id       = [string]$log.DeviceId
            card_no         = $cardNo
            emp_code        = $empCode # Map Person ID to Employee Code
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
                token   = $APEXV5_API_TOKEN
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
Write-Log "=== ApexV5 Local Sync Agent (HikCentral Mode) Started ==="
Write-Log "Server: $APEXV5_API_URL"
Write-Log "Days to sync: $DAYS_TO_SYNC"

$endDate = Get-Date
$startDate = $endDate.AddDays(-$DAYS_TO_SYNC)

Write-Log "Date range: $($startDate.ToString('yyyy-MM-dd')) to $($endDate.ToString('yyyy-MM-dd'))"

# Step 1: Get data from MSSQL (HikCentral)
$deviceLogs = Get-HikLogs -StartDate $startDate -EndDate $endDate

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
    u.EmployeeRFIDNumber as CardNo,
    u.EmployeeCode as Badgenumber
FROM $tableName d
LEFT JOIN Employees u ON CAST(d.UserId AS NVARCHAR(50)) = CAST(u.EmployeeId AS NVARCHAR(50))
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
                token   = $APEXV5_API_TOKEN
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
