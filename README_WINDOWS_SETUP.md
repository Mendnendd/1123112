# Windows Cron Setup for Binance AI Trader

Since Windows doesn't have a built-in cron system like Linux, we provide several alternatives to run the trading bot automatically.

## Option 1: Windows Task Scheduler (Recommended)

### Automatic Setup
1. **Run as Administrator**: Right-click `windows_task_scheduler.bat` and select "Run as administrator"
2. The script will automatically create a scheduled task that runs every 5 minutes
3. Verify the task was created by opening Task Scheduler (taskschd.msc)

### Manual Setup
1. Open Task Scheduler (Windows + R, type `taskschd.msc`)
2. Click "Create Basic Task"
3. Name: `Binance AI Trader`
4. Trigger: Daily
5. Start time: Now
6. Action: Start a program
7. Program: `C:\xampp\php\php.exe` (adjust path as needed)
8. Arguments: `C:\path\to\your\project\cron\trading_bot.php`
9. In "Triggers" tab, edit the trigger:
   - Change to "Repeat task every: 5 minutes"
   - Duration: Indefinitely

## Option 2: Continuous Batch Script

### Setup
1. Edit `windows_cron.bat` and update the paths:
   - `PHP_PATH`: Path to your PHP executable
   - `PROJECT_PATH`: Path to your project directory
2. Double-click `windows_cron.bat` to start
3. The script will run continuously, executing the bot every 5 minutes

### Running as Windows Service (Advanced)
To run the batch script as a Windows service, you can use tools like:
- NSSM (Non-Sucking Service Manager)
- Windows Service Wrapper (WinSW)

## Option 3: Single Run (Testing)

Use `windows_cron_single.bat` to run the trading bot once for testing purposes.

## Configuration

### Update PHP Path
Edit the batch files and change this line to match your PHP installation:
```batch
SET PHP_PATH=C:\xampp\php\php.exe
```

Common PHP paths:
- XAMPP: `C:\xampp\php\php.exe`
- WAMP: `C:\wamp64\bin\php\php8.x.x\php.exe`
- Standalone: `C:\php\php.exe`

### Update Project Path
The `%~dp0` variable automatically uses the directory where the batch file is located. If you move the batch files, update the PROJECT_PATH variable.

## Troubleshooting

### Permission Issues
- Run batch files as Administrator
- Ensure PHP has permission to write to the logs directory
- Check that the database is accessible

### Path Issues
- Use full absolute paths in batch files
- Verify PHP executable path is correct
- Ensure project path is correct

### API Issues
- Configure Binance API credentials in the admin panel
- Enable testnet mode for testing
- Check API key permissions

## Monitoring

### View Logs
- Check `logs/error.log` for PHP errors
- System logs are stored in the database (admin panel > System Logs)
- Trading bot logs show in the system logs with [TRADING_BOT] prefix

### Task Scheduler Logs
- Open Task Scheduler
- Find "BinanceAITrader" task
- Check "History" tab for execution logs

### Manual Testing
Run the single execution script to test:
```cmd
windows_cron_single.bat
```

## Security Notes

- Keep your API keys secure
- Use testnet for development
- Monitor system logs regularly
- Set appropriate stop-loss and position limits
- Never risk more than you can afford to lose

## Stopping the Bot

### Task Scheduler
```cmd
schtasks /delete /tn "BinanceAITrader" /f
```

### Batch Script
- Close the command prompt window
- Or press Ctrl+C to stop

## Support

If you encounter issues:
1. Check the error logs
2. Verify API credentials
3. Test with a single run first
4. Ensure all paths are correct
5. Run as Administrator if needed