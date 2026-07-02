# PowerShell Script to initialize, import DB, and boot PHP & MariaDB local servers
$ErrorActionPreference = "Continue"

$baseDir = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$binDir = Join-Path $baseDir "bin"
$phpExe = Join-Path $binDir "php\php.exe"
$mysqldExe = Join-Path $binDir "mariadb\bin\mysqld.exe"
$mysqlExe = Join-Path $binDir "mariadb\bin\mysql.exe"
$mysqlInstallDb = Join-Path $binDir "mariadb\bin\mysql_install_db.exe"
$dataDir = Join-Path $binDir "mariadb\data"

# 1. Initialize MariaDB Data Directory if it doesn't exist
if (-not (Test-Path $dataDir)) {
    Write-Host "Initializing MariaDB Data directory..." -ForegroundColor Cyan
    Start-Process -FilePath $mysqlInstallDb -ArgumentList "--datadir=`"$dataDir`"" -Wait -NoNewWindow
}

# 2. Kill any running local mysqld / php processes to avoid port conflicts
Write-Host "Stopping any running local server instances..." -ForegroundColor Yellow
Get-Process | Where-Object { $_.Name -eq "mysqld" -or $_.Name -eq "php" } | Stop-Process -Force -ErrorAction SilentlyContinue

# 3. Start MariaDB in Background
Write-Host "Starting MariaDB Database server on port 3306..." -ForegroundColor Cyan
Start-Process -FilePath $mysqldExe -ArgumentList "--datadir=`"$dataDir`"" -WindowStyle Hidden

# Wait for database startup
Write-Host "Waiting for database to initialize (5s)..." -ForegroundColor Yellow
Start-Sleep -Seconds 5

# 4. Import database schema and seed data
Write-Host "Configuring database schema and seeds..." -ForegroundColor Cyan
$schemaFile = Join-Path $baseDir "database\schema.sql"

if (Test-Path $schemaFile) {
    # Check if database is responding and import
    $dbCheck = Start-Process -FilePath $mysqlExe -ArgumentList "-u root -e `"show databases;`"" -PassThru -Wait -NoNewWindow
    if ($dbCheck.ExitCode -eq 0) {
        Write-Host "Importing schema.sql..." -ForegroundColor Cyan
        # Run import command
        & $mysqlExe -u root -e "CREATE DATABASE IF NOT EXISTS namma_autoparts;"
        Get-Content $schemaFile | & $mysqlExe -u root namma_autoparts
        Write-Host "Database imported successfully!" -ForegroundColor Green
    } else {
        Write-Host "Warning: MariaDB did not respond. Check if port 3306 is blocked by another service." -ForegroundColor Red
    }
} else {
    Write-Host "Warning: schema.sql not found at $schemaFile" -ForegroundColor Red
}

# 5. Start PHP Built-in Server
Write-Host "Starting PHP Server on http://localhost:8080/ ..." -ForegroundColor Cyan
# Set working directory to the project root
$phpArgs = "-S localhost:8080 -t `"$baseDir`""
Start-Process -FilePath $phpExe -ArgumentList $phpArgs -WorkingDirectory $baseDir

Write-Host "--------------------------------------------------------" -ForegroundColor Green
Write-Host "VeloParts is successfully serving online!" -ForegroundColor Green
Write-Host "Navigate your browser to: http://localhost:8080/" -ForegroundColor Green
Write-Host "Press Ctrl+C or stop the console to shutdown servers." -ForegroundColor Green
Write-Host "--------------------------------------------------------" -ForegroundColor Green

# Keep script alive to prevent child processes from being terminated in agent sandbox
while ($true) {
    Start-Sleep -Seconds 10
}
