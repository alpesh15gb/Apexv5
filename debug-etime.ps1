
$MSSQL_SERVER = "localhost"
$MSSQL_USERNAME = "essl"
$MSSQL_PASSWORD = "Keystone@456"
$DB_NAME = "Etimetracklite1"

$connStr = "Server=$MSSQL_SERVER;Database=$DB_NAME;User Id=$MSSQL_USERNAME;Password=$MSSQL_PASSWORD;TrustServerCertificate=True;"

try {
    $conn = New-Object System.Data.SqlClient.SqlConnection($connStr)
    $conn.Open()
    Write-Output "Connected to $DB_NAME"

    # List DeviceLogs tables
    Write-Output "`n[1] Listing DeviceLogs tables..."
    $cmd = $conn.CreateCommand()
    $cmd.CommandText = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE 'DeviceLogs%'"
    $reader = $cmd.ExecuteReader()
    while ($reader.Read()) {
        Write-Output "  - $($reader["TABLE_NAME"])"
    }
    $reader.Close()

    $conn.Close()
}
catch {
    Write-Output "Error: $_"
}
