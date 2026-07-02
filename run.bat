@echo off
title Namma AutoParts Local Server Launcher
echo =========================================================
echo  Starting Namma AutoParts Local PHP & MariaDB Servers
echo =========================================================
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0bin\run-servers.ps1"
pause
