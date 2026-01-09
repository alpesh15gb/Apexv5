ApexV5 Local Sync Agent
=======================

REQUIREMENTS:
1. PHP for Windows (Version 8.0 or higher) - Download VS16 x64 Non Thread Safe.
2. Microsoft Drivers for PHP for SQL Server (php_sqlsrv).

INSTALLATION STEPS:
1. Install PHP:
   - Download zip from https://windows.php.net/download/
   - Extract to C:\php
   - Add C:\php to your System PATH environment variable.

2. Enable Extensions:
   - Edit C:\php\php.ini (rename php.ini-production if needed):
     - Uncomment: extension=curl
     - Uncomment: extension=mbstring
     - Uncomment: extension=openssl
   - Download SQLSRV drivers from Microsoft: https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server
   - Copy `php_sqlsrv_82_nts_x64.dll` (matching your PHP version) to `C:\php\ext`.
   - Add to php.ini: extension=php_sqlsrv_82_nts_x64.dll

3. Configuration:
   - Open `sync.php` in Notepad.
   - Update `$mssqlConfig` with your local database User/Pass.
   - Update `$cloudConfig` with your website URL and Token.

RUNNING:
- Manual: Double-click `run_sync.bat`
- Scheduled: Add `run_sync.bat` to Windows Task Scheduler to run every 5 minutes.
