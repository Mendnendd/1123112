@echo off
REM Binance AI Trader - Single Run Script
REM This runs the trading bot once (useful for testing)

REM Set the path to your PHP executable (adjust as needed)
SET PHP_PATH=C:\xampp\php\php.exe

REM Set the path to your project directory (adjust as needed)
SET PROJECT_PATH=%~dp0

REM Change to project directory
cd /d "%PROJECT_PATH%"

REM Run the trading bot
echo [%date% %time%] Running trading bot...
"%PHP_PATH%" cron\trading_bot.php

REM Check if successful
if %errorlevel% equ 0 (
    echo [%date% %time%] Trading bot completed successfully.
) else (
    echo [%date% %time%] Trading bot failed with error code: %errorlevel%
)

echo.
echo Press any key to exit...
pause > nul