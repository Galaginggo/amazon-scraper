@echo off
REM Amazon Price Tracker - Windows Task Scheduler Setup
REM This script creates a scheduled task to run price updates automatically

echo ========================================
echo Amazon Price Tracker - Auto-Update Setup
echo ========================================
echo.

REM Get the current directory
set SCRIPT_DIR=%~dp0
set PHP_PATH=C:\xampp\php\php.exe
set AUTO_UPDATE_SCRIPT=%SCRIPT_DIR%auto_update.php

echo Script Directory: %SCRIPT_DIR%
echo PHP Path: %PHP_PATH%
echo Auto-Update Script: %AUTO_UPDATE_SCRIPT%
echo.

REM Check if PHP exists
if not exist "%PHP_PATH%" (
    echo ERROR: PHP not found at %PHP_PATH%
    echo Please update PHP_PATH in this script to match your XAMPP installation
    pause
    exit /b 1
)

echo Creating scheduled task...
echo.

REM Delete existing task if it exists
schtasks /Delete /TN "AmazonPriceTracker" /F >nul 2>&1

REM Create new task that runs every 5 minutes
schtasks /Create /TN "AmazonPriceTracker" /TR "\"%PHP_PATH%\" \"%AUTO_UPDATE_SCRIPT%\"" /SC MINUTE /MO 5 /F

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo SUCCESS! Scheduled task created.
    echo ========================================
    echo.
    echo The price tracker will now run automatically every 5 minutes.
    echo.
    echo To manage the task:
    echo - Open Task Scheduler (taskschd.msc)
    echo - Look for "AmazonPriceTracker" task
    echo.
    echo To change the interval:
    echo 1. Open index.php in your browser
    echo 2. Go to "Automatic Price Updates" section
    echo 3. Set your preferred interval
    echo 4. Click "Save Settings"
    echo.
    echo Note: The task runs every 5 minutes, but the script
    echo will only update if your configured interval has passed.
    echo.
) else (
    echo.
    echo ERROR: Failed to create scheduled task.
    echo Please run this script as Administrator.
    echo.
)

pause