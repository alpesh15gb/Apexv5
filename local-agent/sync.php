<?php
/**
 * Local Sync Agent for ApexV5
 * 
 * Usage: php local_sync_agent.php
 * Requirements: PHP 8.0+, php_sqlsrv, php_curl
 */

// --- CONFIGURATION ---
$mssqlConfig = [
    'server' => 'localhost\SQLExpress', // Update this
    'database' => 'Etimetracklite1',
    'username' => 'sa',
    'password' => 'password'
];

$cloudConfig = [
    'url' => 'https://your-hostinger-domain.com/api/punches/sync',
    'api_token' => 'YOUR_SECRET_API_TOKEN' // Secure this!
];

// --- LOGGING ---
$logFile = 'sync.log';

function logMessage($msg)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $formattedMsg = "[$timestamp] $msg" . PHP_EOL;
    file_put_contents($logFile, $formattedMsg, FILE_APPEND);
    echo $formattedMsg; // Also print to console
}

// --- STATE TRACKING ---
$stateFile = 'last_sync_state.json';
$lastSyncTime = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true)['last_log_date'] : date('Y-m-d H:i:s', strtotime('-1 month'));

logMessage("Starting Sync... Last Success: $lastSyncTime");

// --- CONNECT MSSQL ---
try {
    $conn = new PDO(
        "sqlsrv:Server={$mssqlConfig['server']};Database={$mssqlConfig['database']}",
        $mssqlConfig['username'],
        $mssqlConfig['password']
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Data connection failed: " . $e->getMessage() . "\n");
}

// --- FETCH DATA ---
// --- HELPER TO GET TABLE NAME ---
function getTableName($month, $year)
{
    return "DeviceLogs_{$month}_{$year}";
}

// --- SMART TABLE SELECTION ---
// Strategy: 
// 1. If we are in the first 5 days of the month, check the PREVIOUS month's table first.
// 2. If the previous month has data newer than our last sync, process that.
// 3. Otherwise, process the current month.

$currentMonth = (int) date('n');
$currentYear = (int) date('Y');
$dayOfMonth = (int) date('j');

// Determine candidate tables
$tablesToCheck = [];

// If early in the month, add previous month to check list
if ($dayOfMonth <= 5) {
    $prevMonth = $currentMonth - 1;
    $prevYear = $currentYear;
    if ($prevMonth == 0) {
        $prevMonth = 12;
        $prevYear--;
    }
    $tablesToCheck[] = getTableName($prevMonth, $prevYear);
}
// Always add current month
$tablesToCheck[] = getTableName($currentMonth, $currentYear);

$punches = [];
$activeTable = '';

// Check tables in order
foreach ($tablesToCheck as $tableName) {
    // Check if table exists (basic check via try-catch on query)
    try {
        $sql = "SELECT TOP 500 LogDate, DeviceId, UserId, DeviceLogId 
                FROM {$tableName} 
                WHERE LogDate > ? 
                ORDER BY LogDate ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$lastSyncTime]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $punches = $rows;
            $activeTable = $tableName;
            logMessage("Found " . count($punches) . " new punches in $activeTable");
            break; // Stop at the first table that gives us data (process oldest first)
        }
    } catch (PDOException $e) {
        // Table might not exist (e.g. if new month hasn't started logging yet), just continue
        // logMessage("Skipping $tableName (Table not found or empty)"); // Optional verbosity
    }
}

if (empty($punches)) {
    logMessage("No new punches found in checked tables.");
    exit;
}

// --- PUSH TO CLOUD ---
$ch = curl_init($cloudConfig['url']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'punches' => $punches,
    'token' => $cloudConfig['api_token']
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// --- UPDATE STATE ---
if ($httpCode === 200) {
    $lastRecord = end($punches);
    file_put_contents($stateFile, json_encode(['last_log_date' => $lastRecord['LogDate']]));
    logMessage("Sync Successful! Pushed " . count($punches) . " records. Updated state to: " . $lastRecord['LogDate']);
} else {
    logMessage("Sync Failed. HTTP $httpCode - Response: $response");
}
