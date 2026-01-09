# Local Sync Agent Setup Guide

This guide will help you set up the sync agent on your on-premise Windows machine to automatically push punch data from your MSSQL database to the ApexV5 server.

## Prerequisites

- Windows machine with PowerShell (comes with Windows)
- Network access to both:
  - SQL Server (Etimetracklite1 database)
  - ApexV5 server (ho.apextime.in)

## Installation Steps

### 1. Copy the Sync Script

Copy `sync-agent.ps1` to your on-premise Windows machine (e.g., `C:\ApexSync\sync-agent.ps1`)

### 2. Configure the Script

Open `sync-agent.ps1` in Notepad and update these settings:

```powershell
$MSSQL_SERVER = "localhost"  # Your SQL Server address (localhost or IP)
$MSSQL_DATABASE = "Etimetracklite1"
$MSSQL_USERNAME = "sa"
$MSSQL_PASSWORD = "your_actual_password"  # ⚠️ UPDATE THIS!

$APEXV5_API_URL = "http://ho.apextime.in/api/punches/import"  # Your ApexV5 URL

$DAYS_TO_SYNC = 7  # How many days of data to sync each run
$BATCH_SIZE = 500  # Records per batch
```

### 3. Test the Script

Open PowerShell as Administrator and run:

```powershell
cd C:\ApexSync
.\sync-agent.ps1
```

You should see output like:
```
[2026-01-10 02:00:00] === ApexV5 Local Sync Agent Started ===
[2026-01-10 02:00:00] Connected to MSSQL: localhost
[2026-01-10 02:00:01] Querying table: DeviceLogs_1_2026
[2026-01-10 02:00:02] Total records retrieved: 1500
[2026-01-10 02:00:03] Sending batch 1/3 (500 records)...
[2026-01-10 02:00:04] Batch 1: SUCCESS - 500 imported, 0 failed
...
[2026-01-10 02:00:10] SYNC COMPLETE: 1500 imported, 0 failed
```

### 4. Schedule Automatic Sync

#### Option A: Using Windows Task Scheduler (Recommended)

1. Open **Task Scheduler** (search in Start menu)
2. Click **Create Basic Task**
3. Name: `ApexV5 Sync Agent`
4. Trigger: **Daily** at a specific time (e.g., every 6 hours)
   - Or choose **Recurring** → Every 30 minutes
5. Action: **Start a Program**
   - Program: `powershell.exe`
   - Arguments: `-ExecutionPolicy Bypass -File "C:\ApexSync\sync-agent.ps1"`
   - Start in: `C:\ApexSync`
6. Click **Finish**

#### Option B: Run on Startup

1. Press `Win + R`, type `shell:startup`, press Enter
2. Create a shortcut to the script:
   - Right-click → New → Shortcut
   - Location: `powershell.exe -ExecutionPolicy Bypass -WindowStyle Hidden -File "C:\ApexSync\sync-agent.ps1"`
   - Name: `ApexV5 Sync`

### 5. Monitor Sync

The script outputs logs to the console. To save logs to a file, modify the Task Scheduler arguments:

```
-ExecutionPolicy Bypass -File "C:\ApexSync\sync-agent.ps1" >> "C:\ApexSync\sync.log" 2>&1
```

Then view logs:
```powershell
Get-Content C:\ApexSync\sync.log -Tail 50
```

## Troubleshooting

### "Execution Policy" Error

If you see: `cannot be loaded because running scripts is disabled`

Run PowerShell as Administrator:
```powershell
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### SQL Connection Error

- Verify SQL Server is running
- Check firewall allows SQL port (1433)
- Verify credentials in script

### API Connection Error

- Verify ApexV5 server is accessible: `Test-NetConnection ho.apextime.in -Port 80`
- Check the API URL is correct

### No Data Syncing

- Check date range: Increase `$DAYS_TO_SYNC` if needed
- Verify DeviceLogs tables exist for the date range
- Check ApexV5 server logs

## Configuration Options

| Setting | Default | Description |
|---------|---------|-------------|
| `$DAYS_TO_SYNC` | 7 | Days of historical data to sync |
| `$BATCH_SIZE` | 500 | Records per API request |
| `$MSSQL_SERVER` | localhost | SQL Server address |
| `$APEXV5_API_URL` | http://ho.apextime.in/api/punches/import | ApexV5 API endpoint |

## How It Works

1. **Query MSSQL**: Script queries `DeviceLogs_M_YYYY` tables for the last N days
2. **Transform Data**: Maps MSSQL columns to ApexV5 format
3. **Batch Upload**: Sends data in batches to `/api/punches/import` endpoint
4. **Process**: ApexV5 imports punches and calculates daily attendance

## Support

For issues, check:
- Script output/logs
- ApexV5 server logs: `docker-compose logs app`
- Network connectivity between machines
