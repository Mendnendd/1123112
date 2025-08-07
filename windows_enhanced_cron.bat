@echo off
REM Enhanced Binance AI Trader - Windows Cron Job Replacement
REM This batch file runs the enhanced trading bot every 5 minutes
REM Place this file in your project root directory

REM Set the path to your PHP executable (adjust as needed)
SET PHP_PATH=C:\xampp\php\php.exe

REM Set the path to your project directory (adjust as needed)
SET PROJECT_PATH=%~dp0

REM Change to project directory
cd /d "%PROJECT_PATH%"

REM Run the enhanced trading bot
echo [%date% %time%] Starting enhanced trading bot...
"%PHP_PATH%" cron\enhanced_trading_bot.php

REM Log completion
echo [%date% %time%] Enhanced trading bot completed.

REM Wait 5 minutes (300 seconds) before next run
timeout /t 300 /nobreak > nul

REM Loop back to run again
goto :loop

:loop
REM Run the enhanced trading bot
echo [%date% %time%] Starting enhanced trading bot...
"%PHP_PATH%" cron\enhanced_trading_bot.php

REM Log completion
echo [%date% %time%] Enhanced trading bot completed.

REM Wait 5 minutes (300 seconds) before next run
timeout /t 300 /nobreak > nul

REM Loop back
goto :loop