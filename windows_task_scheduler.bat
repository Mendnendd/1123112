@echo off
REM Binance AI Trader - Windows Task Scheduler Setup
REM This creates a scheduled task to run the trading bot every 5 minutes

REM Set the path to your PHP executable (adjust as needed)
SET PHP_PATH=C:\xampp\php\php.exe

REM Set the path to your project directory (adjust as needed)
SET PROJECT_PATH=%~dp0

REM Create the scheduled task
echo Creating Windows scheduled task for Binance AI Trader...

schtasks /create /tn "BinanceAITrader" /tr "\"%PHP_PATH%\" \"%PROJECT_PATH%cron\trading_bot.php\"" /sc minute /mo 5 /ru "SYSTEM" /f

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Scheduled task "BinanceAITrader" created successfully!
    echo The trading bot will now run every 5 minutes automatically.
    echo.
    echo To manage the task:
    echo - View: schtasks /query /tn "BinanceAITrader"
    echo - Delete: schtasks /delete /tn "BinanceAITrader" /f
    echo - Run now: schtasks /run /tn "BinanceAITrader"
    echo.
) else (
    echo.
    echo ERROR: Failed to create scheduled task.
    echo Please run this script as Administrator.
    echo.
)

pause