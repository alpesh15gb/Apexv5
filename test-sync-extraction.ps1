
# Validates what Sync Agent extracts for a specific person
$MSSQL_SERVER = "localhost"
$MSSQL_USERNAME = "essl"
$MSSQL_PASSWORD = "Keystone@456"
# Database Names
$DB_ETIME = "Etimetracklite1"
$DB_HIK = "hikcentral"

# HikCentral Logic
function Get-HikLogs {
    param([DateTime]$StartDate, [DateTime]$EndDate)
    
    $connStr = "Server=$MSSQL_SERVER;Database=$DB_HIK;User Id=$MSSQL_USERNAME;Password=$MSSQL_PASSWORD;TrustServerCertificate=True;"
    $allLogs = @()

    try {
        $conn = New-Object System.Data.SqlClient.SqlConnection($connStr)
        $conn.Open()
        Write-Host "Connected to $DB_HIK"
        
        $tableName = "HikvisionLogs"
        # Search for Sakendra specifically for debug
        $query = @"
SELECT person_id, access_datetime, direction, card_no, device_name, person_name, emp_dept
FROM $tableName
WHERE access_datetime >= @Start AND access_datetime <= @End
AND person_name LIKE '%Sakendra%'
ORDER BY access_datetime ASC
"@
        $cmd = New-Object System.Data.SqlClient.SqlCommand($query, $conn)
        $cmd.Parameters.AddWithValue("@Start", $StartDate) | Out-Null
        $cmd.Parameters.AddWithValue("@End", $EndDate) | Out-Null
        $reader = $cmd.ExecuteReader()
        
        while ($reader.Read()) {
            # Normalize Direction logic from sync-agent
            $dir = "in"
            $rawDir = $reader["direction"]
            
            if ($rawDir -match "Exit" -or $rawDir -match "Out") { 
                $dir = "out" 
            }
            elseif ([string]::IsNullOrWhiteSpace($rawDir)) {
                # Infer from time if direction is missing
                if ($reader["access_datetime"].Hour -ge 14) { 
                    $dir = "out" 
                }
            }

            $allLogs += [PSCustomObject]@{
                Source    = "Hik"
                UserId    = [string]$reader["person_id"]
                LogDate   = $reader["access_datetime"]
                Direction = $dir
                RawDir    = $rawDir
                CardNo    = [string]$reader["card_no"]
                BadgeNo   = [string]$reader["person_id"]
                Name      = [string]$reader["person_name"]
            }
        }
        $reader.Close()
        $conn.Close()
    }
    catch { Write-Host "ERROR: $_" }
    
    return $allLogs
}

$end = Get-Date
$start = $end.AddDays(-5) # Last 5 days

Write-Host "Fetching logs for Sakendra from $start to $end..."
$logs = Get-HikLogs -StartDate $start -EndDate $end

Write-Host "Found $($logs.Count) logs."
foreach ($log in $logs) {
    Write-Host "Time: $($log.LogDate) | RawDir: '$($log.RawDir)' | CalcDir: $($log.Direction) | ID: $($log.UserId)"
}
