
$MSSQL_SERVER = "localhost"
$MSSQL_USERNAME = "essl"
$MSSQL_PASSWORD = "Keystone@456"
$DB_ETIME = "Etimetracklite1"
$DB_HIK = "hikcentral"

$connStr = "Server=$MSSQL_SERVER;Database=$DB_ETIME;User Id=$MSSQL_USERNAME;Password=$MSSQL_PASSWORD;TrustServerCertificate=True;"

try {
    # ---------------------------------------------------------
    # PART A: Etime
    $conn = New-Object System.Data.SqlClient.SqlConnection($connStr)
    $conn.Open()
    Write-Output "Connected to $DB_ETIME"

    # 1. Find Employee in Etimetracklite1
    $empName = "Sakendra"
    Write-Output "`n[Etimetracklite1] Searching for employee '$empName'..."
    $cmd = $conn.CreateCommand()
    $cmd.CommandText = "SELECT EmployeeId, EmployeeName, EmployeeCode, EmployeeRFIDNumber FROM Employees WHERE EmployeeName LIKE '%$empName%'"
    
    $empId = $null
    $reader = $cmd.ExecuteReader()
    if ($reader.HasRows) {
        while ($reader.Read()) {
            $empId = $reader["EmployeeId"]
            $name = $reader["EmployeeName"]
            $code = $reader["EmployeeCode"]
            $card = $reader["EmployeeRFIDNumber"]
            Write-Output "  FOUND: Name='$name', ID='$empId', Code='$code', Card='$card'"
        }
    }
    else {
        Write-Output "  Employee not found in Etimetracklite1."
    }
    $reader.Close()

    # 2. Dump 3 RANDOM employees from Etime to verify connection
    Write-Output "`n[Etimetracklite1] Dumping top 3 employees to verify data existence..."
    $cmd.CommandText = "SELECT TOP 3 EmployeeName, EmployeeCode FROM Employees"
    $reader = $cmd.ExecuteReader()
    while ($reader.Read()) {
        Write-Output "  - $($reader["EmployeeName"]) ($($reader["EmployeeCode"]))"
    }
    $reader.Close()
    $conn.Close()

    # ---------------------------------------------------------
    # PART B: HikCentral
    $connH = New-Object System.Data.SqlClient.SqlConnection("Server=$MSSQL_SERVER;Database=$DB_HIK;User Id=$MSSQL_USERNAME;Password=$MSSQL_PASSWORD;TrustServerCertificate=True;")
    try {
        $connH.Open()
        Write-Output "`nConnected to $DB_HIK"
        
        Write-Output "[HikCentral] Searching for '$empName' in HikvisionLogs (person_name)..."
        $cmdH = $connH.CreateCommand()
        $cmdH.CommandText = "SELECT TOP 10 person_id, person_name, access_datetime, direction FROM HikvisionLogs WHERE person_name LIKE '%$empName%' ORDER BY access_datetime DESC"
        
        $readerH = $cmdH.ExecuteReader()
        if ($readerH.HasRows) {
            while ($readerH.Read()) {
                Write-Output "  FOUND: Name='$($readerH["person_name"])', ID='$($readerH["person_id"])', Time='$($readerH["access_datetime"])', Dir='$($readerH["direction"])'"
            }
        }
        else {
            Write-Output "  No logs found for '$empName' in HikvisionLogs."
        }
        $readerH.Close()

        # 3b. Dump top 3 logs to verify table
        Write-Output "`n[HikCentral] Dumping top 3 logs..."
        $cmdH.CommandText = "SELECT TOP 3 person_name, access_datetime FROM HikvisionLogs ORDER BY access_datetime DESC"
        $readerH = $cmdH.ExecuteReader()
        while ($readerH.Read()) {
            Write-Output "  - $($readerH["person_name"]) @ $($readerH["access_datetime"])"
        }
        $readerH.Close()
        $connH.Close()

    }
    catch {
        Write-Output "  Could not connect to $($DB_HIK): $_"
    }

}
catch {
    Write-Output "Error: $_"
}
