# PowerShell Script to download and set up portable PHP 8.2 & MariaDB 10.11
$ErrorActionPreference = "Stop"

$baseDir = Join-Path $PSScriptRoot ".."
$binDir = Join-Path $baseDir "bin"

if (-not (Test-Path $binDir)) {
    New-Item -ItemType Directory -Path $binDir | Out-Null
}

$phpZipUrl = "https://windows.php.net/downloads/releases/archives/php-8.2.15-nts-Win32-vs16-x64.zip"
$mariadbZipUrl = "https://archive.mariadb.org/mariadb-10.11.2/winx64-packages/mariadb-10.11.2-winx64.zip"

$phpDest = Join-Path $binDir "php"
$mariadbDest = Join-Path $binDir "mariadb"

# 1. Download & Extract PHP
if (-not (Test-Path $phpDest)) {
    Write-Host "Downloading Portable PHP 8.2..." -ForegroundColor Cyan
    $phpZip = Join-Path $binDir "php.zip"
    Invoke-WebRequest -Uri $phpZipUrl -OutFile $phpZip
    
    Write-Host "Extracting PHP..." -ForegroundColor Cyan
    Expand-Archive -Path $phpZip -DestinationPath $phpDest -Force
    Remove-Item $phpZip -Force
} else {
    Write-Host "PHP already downloaded." -ForegroundColor Green
}

# 2. Download & Extract MariaDB
if (-not (Test-Path $mariadbDest)) {
    Write-Host "Downloading Portable MariaDB 10.11..." -ForegroundColor Cyan
    $mariadbZip = Join-Path $binDir "mariadb.zip"
    Invoke-WebRequest -Uri $mariadbZipUrl -OutFile $mariadbZip
    
    Write-Host "Extracting MariaDB..." -ForegroundColor Cyan
    $mariadbTemp = Join-Path $binDir "mariadb_temp"
    Expand-Archive -Path $mariadbZip -DestinationPath $mariadbTemp -Force
    
    $extractedFolder = Get-ChildItem $mariadbTemp -Directory | Select-Object -First 1
    Move-Item -Path $extractedFolder.FullName -Destination $mariadbDest
    
    Remove-Item $mariadbTemp -Recurse -Force
    Remove-Item $mariadbZip -Force
} else {
    Write-Host "MariaDB already downloaded." -ForegroundColor Green
}

# 3. Configure PHP php.ini
$phpIni = Join-Path $phpDest "php.ini"
if (-not (Test-Path $phpIni)) {
    Write-Host "Configuring php.ini..." -ForegroundColor Cyan
    Copy-Item (Join-Path $phpDest "php.ini-development") $phpIni -Force
    
    $content = Get-Content $phpIni
    $content = $content -replace ';extension_dir = "ext"', 'extension_dir = "ext"'
    $content = $content -replace ';extension=pdo_mysql', 'extension=pdo_mysql'
    $content = $content -replace ';extension=fileinfo', 'extension=fileinfo'
    $content = $content -replace ';extension=mbstring', 'extension=mbstring'
    $content = $content -replace ';extension=openssl', 'extension=openssl'
    $content = $content -replace ';extension=gd', 'extension=gd'
    # Increase upload sizes for used product images
    $content = $content -replace 'upload_max_filesize = 2M', 'upload_max_filesize = 10M'
    $content = $content -replace 'post_max_size = 8M', 'post_max_size = 20M'
    
    Set-Content $phpIni $content
    Write-Host "php.ini configured successfully." -ForegroundColor Green
}

Write-Host "Server environment downloaded and configured!" -ForegroundColor Green
