# Apex Human Capital - Attendance System

A full-featured Employee Attendance Tracking System built with Laravel 10, designed to sync data from an On-Premise MSSQL Server (Etimetracklite) to a Cloud Dashboard.

## Features
*   **Hybrid Sync**: Propels punch data from local intranet to cloud via a secure API.
*   **Attendance Engine**: Automatically calculates status (Present, Absent, Late) based on shifts.
*   **Dynamic Reporting**: Monthly Attendance Register and Daily Status reports.
*   **Docker Ready**: Full containerization for easy deployment.

## Architecture
1.  **Cloud App (Hostinger)**: Laravel Application running via Docker.
2.  **On-Prem Agent**: A lightweight PHP script (`local-agent/sync.php`) that pushes data from your biometric server.

---

## 🚀 Deployment Guide

### 1. Cloud Deployment (Hostinger VPS with Docker)

**Prerequisites:**
*   Docker & Docker Compose installed.
*   Git installed.

**Steps:**
1.  **Clone the Repository**:
    ```bash
    git clone https://github.com/alpesh15gb/Apexv5.git
    cd Apexv5
    ```

2.  **Configure Environment**:
    ```bash
    cp .env.example .env
    ```
    Update `.env` with your settings:
    ```ini
    APP_URL=https://your-domain.com
    DB_PASSWORD=your_secure_db_password
    SYNC_API_TOKEN=generate_a_strong_secret_token
    ```

3.  **Start Containers**:
    ```bash
    docker-compose up -d --build
    ```

4.  **Initialize App**:
    ```bash
    docker-compose exec app composer install
    docker-compose exec app php artisan migrate --seed
    docker-compose exec app php artisan key:generate
    ```

### 2. On-Premise Agent Setup (Windows Server)

**Prerequisites:**
*   PHP 8.0+ installed on Windows.
*   Drivers: `php_sqlsrv` and `php_curl` enabled in `php.ini`.

**Steps:**
1.  Copy the `local-agent` folder from this repo to your server (e.g., `C:\ApexAgent`).
2.  Edit `sync.php`:
    *   Set `$mssqlConfig` to your local SQL Express credentials.
    *   Set `$cloudConfig['url']` to `https://your-domain.com/api/punches/sync`.
    *   Set `$cloudConfig['api_token']` to match the `SYNC_API_TOKEN` in your Cloud `.env`.
3.  **Test Run**:
    Open PowerShell/CMD and run:
    ```cmd
    php sync.php
    ```
4.  **Schedule**:
    Use Windows Task Scheduler to run this script every 5 minutes.

---

## 🛠 Usage
*   **Dashboard**: Login to `https://your-domain.com/dashboard` to view stats.
*   **Reports**: Access "Monthly Register" to download attendance sheets.
*   **Logs**: Check `local-agent/sync.log` on your windows server to monitor data sync health.

## License
Proprietary software for Apex Human Capital.
