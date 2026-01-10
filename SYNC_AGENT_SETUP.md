# Local Sync Agent Setup Guide

This guide will help you set up the sync agent on your on-premise Windows machine to automatically push punch data from your MSSQL database to the ApexV5 server.

## Prerequisites

- Windows machine with PowerShell (comes with Windows)
- Network access to both:
  - SQL Server (Etimetracklite1 database)
  - ApexV5 server (ho.apextime.in)

## Installation Steps

### 1. Copy the Sync Script

Copy `sync-agent.ps1` to your on-premise Windows machine (e.g., `C:\Apexsync\sync-agent.ps1`).

### 2. Configure the Script

The script is already pre-configured with the default credentials for `essl` user. You only need to verify the following settings in `sync-agent.ps1` if your environment differs:

```powershell
$MSSQL_SERVER = "localhost"  # Your SQL Server address
$MSSQL_DATABASE = "Etimetracklite1"
$MSSQL_USERNAME = "essl"
$MSSQL_PASSWORD = "Keystone@456"

$APEXV5_API_URL = "https://ho.apextime.in/api/punches/import"
$APEXV5_API_TOKEN = "secret-token"
```

### 3. Test the Script

Open PowerShell as Administrator and run:

```powershell
cd C:\Apexsync
.\sync-agent.ps1
```

### 4. Schedule Automatic Sync

#### Option A: Using Windows Task Scheduler (Recommended)

1. Open **Task Scheduler** (search in Start menu)
2. Click **Create Basic Task**
3. Name: `ApexV5 Sync Agent`
4. Trigger: **Daily** at a specific time (e.g., now)
5. Action: **Start a Program**
   - Program: `powershell.exe`
   - Arguments: `-ExecutionPolicy Bypass -File "C:\Apexsync\sync-agent.ps1"`
   - Start in: `C:\Apexsync`
6. Click **Finish**

**To repeat every 15 minutes:**
1. Right-click the new task -> **Properties**
2. Go to **Triggers** tab -> **Edit**
3. Check **Repeat task every:** `15 minutes`
4. Set **for a duration of:** `Indefinitely`
5. Click **OK**

#### Option B: Run on Startup

1. Press `Win + R`, type `shell:startup`, press Enter
2. Create a shortcut to the script:
   - Right-click → New → Shortcut
   - Location: `powershell.exe -ExecutionPolicy Bypass -WindowStyle Hidden -File "C:\Apexsync\sync-agent.ps1"`
   - Name: `ApexV5 Sync`

### 5. Monitor Sync

The script outputs logs to the console. To save logs to a file, modify the Task Scheduler arguments:

```
-ExecutionPolicy Bypass -File "C:\Apexsync\sync-agent.ps1" >> "C:\Apexsync\sync.log" 2>&1
```

Then view logs:
```powershell
Get-Content C:\Apexsync\sync.log -Tail 50
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

### API Connection Error

- Verify ApexV5 server is accessible: `Test-NetConnection ho.apextime.in -Port 443`
- Check the API URL is correct (must be HTTPS)

## Support

For issues, check:
- `C:\Apexsync\sync.log`
- Network connectivity between machines
