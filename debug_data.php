<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$record = App\Models\DailyAttendance::where('date', '2026-01-11')->first();
if ($record) {
    echo "Stored In Time: " . $record->in_time . "\n";
    echo "Serialized: " . json_encode($record) . "\n";
} else {
    echo "No record found for 2026-01-11\n";
}
