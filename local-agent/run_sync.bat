@echo off
echo Starting ApexV5 Sync...
php sync.php
if %errorlevel% neq 0 (
    echo Sync finished with errors.
    pause
) else (
    echo Sync finished successfully.
    timeout /t 5
)
