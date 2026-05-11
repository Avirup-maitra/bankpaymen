# Export System - Implementation Checklist

## Pre-Implementation
- [ ] Review EXPORT_DOCUMENTATION.md
- [ ] Review EXPORT_QUICK_REFERENCE.md
- [ ] Backup current database
- [ ] Backup current code

## Step 1: Database Migration
- [ ] Run migration: `php artisan migrate`
  ```bash
  php artisan migrate
  ```
- [ ] Verify tables:
  ```bash
  php artisan tinker
  >>> DB::table('exports_log')->get()
  ```
- [ ] Check columns added:
  ```bash
  php artisan tinker
  >>> DB::getSchemaBuilder()->getColumnListing('exports_log')
  ```

## Step 2: Configuration
- [ ] Verify `config/bankfiles.php` exists
  ```php
  config('bankfiles.export_outbox')
  ```
- [ ] Check `.env` file has export paths:
  ```env
  EXPORT_OUTBOX_PATH=storage/app/exports/outbox
  ```
- [ ] Test config:
  ```bash
  php artisan tinker
  >>> config('bankfiles.export_outbox')
  ```

## Step 3: Directory Setup
- [ ] Create export directory:
  ```bash
  mkdir -p storage/app/exports/outbox
  ```
- [ ] Set permissions:
  ```bash
  chmod -R 775 storage/
  chown -R www-data:www-data storage/ # if Apache/Nginx user
  ```
- [ ] Verify directory:
  ```bash
  ls -la storage/app/exports/outbox/
  ```

## Step 4: Service Layer
- [ ] Verify `app/Services/ExportService.php` exists
- [ ] Test service:
  ```bash
  php artisan tinker
  >>> $service = new App\Services\ExportService()
  >>> $service->exportAll()
  ```

## Step 5: Export Classes
- [ ] Verify `app/Exports/BankTransactionsExport.php` exists
- [ ] Verify `app/Exports/TodayTransactionsExport.php` exists

## Step 6: Controller
- [ ] Verify `app/Http/Controllers/ExportController.php` exists
- [ ] Check controller has all methods:
  - [ ] exportAll()
  - [ ] exportToday()
  - [ ] history()
  - [ ] statusToday()
  - [ ] exportTransaction()

## Step 7: Console Command
- [ ] Verify `app/Console/Commands/ExportTransactionsCommand.php` exists
- [ ] Test command:
  ```bash
  php artisan export:transactions --type=all
  php artisan export:transactions --type=today
  ```

## Step 8: Routes
- [ ] Verify `routes/web.php` has export routes:
  ```php
  Route::prefix('/export')->name('export.')->group(function () {
      Route::get('/all', ...);
      Route::get('/today', ...);
      Route::get('/history', ...);
      Route::get('/status/today', ...);
      Route::get('/transaction/{id}', ...);
  });
  ```
- [ ] Test routes:
  ```bash
  php artisan route:list | grep export
  ```

## Step 9: Scheduler
- [ ] Verify `app/Console/Kernel.php` has export schedules:
  - [ ] TODAY export at 06:00 AM
  - [ ] ALL export at 23:59 PM
- [ ] View schedules:
  ```bash
  php artisan schedule:list
  ```
- [ ] Test scheduler:
  ```bash
  php artisan schedule:run
  ```

## Step 10: Logging
- [ ] Verify log channel exists in `config/logging.php` (bank_processing)
- [ ] Check log file:
  ```bash
  tail -f storage/logs/bank_processing.log
  ```

## Step 11: API Testing

### Test ALL Export
```bash
curl -X GET "http://localhost/export/all" \
  -H "Accept: application/json"
```
Expected response:
```json
{
  "success": true,
  "type": "ALL",
  "filename": "transactions_all_*.xlsx",
  "total_rows": 123,
  "paid_rows": 100,
  "rejected_rows": 23
}
```

### Test TODAY Export
```bash
curl -X GET "http://localhost/export/today" \
  -H "Accept: application/json"
```

### Test Export History
```bash
curl -X GET "http://localhost/export/history?type=ALL&limit=10" \
  -H "Accept: application/json"
```

### Test Export Status
```bash
curl -X GET "http://localhost/export/status/today" \
  -H "Accept: application/json"
```

## Step 12: File Generation Verification
- [ ] Check if files are created:
  ```bash
  ls -la storage/app/exports/outbox/
  ```
- [ ] Verify file names match pattern:
  - [ ] ALL: `transactions_all_*.xlsx`
  - [ ] TODAY: `transactions_today_*.xlsx`
- [ ] Check file sizes (should be > 0):
  ```bash
  du -h storage/app/exports/outbox/*
  ```

## Step 13: Database Logging Verification
- [ ] Check if export is logged:
  ```bash
  php artisan tinker
  >>> App\Models\ExportsLog::latest()->first()
  ```
- [ ] Verify columns populated:
  ```bash
  >>> $log = App\Models\ExportsLog::latest()->first()
  >>> $log->toArray()
  ```

## Step 14: Scheduler Setup (Production)

### Option A: Using cron job
```bash
# Add to crontab
* * * * * cd /var/www/bankpayment && php artisan schedule:run >> /dev/null 2>&1
```

### Option B: Using supervisor
```bash
# Create /etc/supervisor/conf.d/laravel-scheduler.conf
[program:laravel-scheduler]
process_name=%(program_name)s
command=php /var/www/bankpayment/artisan schedule:work
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/bankpayment/storage/logs/scheduler.log
user=www-data
```

- [ ] Verify cron/supervisor running:
  ```bash
  ps aux | grep "schedule:work"
  ```

## Step 15: Manual Testing

### Test ALL Export via CLI
```bash
php artisan export:transactions --type=all
```
Expected output:
```
✓ Export completed successfully
Type: ALL
Filename: transactions_all_2026-03-30_14-30-45.xlsx
Total rows: 250
Paid rows: 200
Rejected rows: 50
```

### Test TODAY Export via CLI
```bash
php artisan export:transactions --type=today
```
Expected output:
```
✓ Export completed successfully
Type: TODAY
Filename: transactions_today_2026-03-30_06-00-12.xlsx
Total rows: 45
Paid rows: 40
Rejected rows: 5
```

## Step 16: Monitoring Setup

### Add to dashboard (optional)
- [ ] Create export dashboard view
- [ ] Show last export status
- [ ] Show export history table
- [ ] Add manual export buttons

### Set up alerts (optional)
- [ ] Alert on failed exports
- [ ] Alert on missing daily exports
- [ ] Alert on unusual row counts

## Step 17: Performance Optimization (Optional)

- [ ] For large datasets, consider:
  - [ ] Pagination in history
  - [ ] Async export (queue)
  - [ ] Compression of old exports
  - [ ] Archive old exports

## Step 18: Documentation
- [ ] Share EXPORT_DOCUMENTATION.md with team
- [ ] Share EXPORT_QUICK_REFERENCE.md with team
- [ ] Create team guide/wiki entry
- [ ] Document any custom modifications

## Step 19: Backup and Retention
- [ ] Set up automated backup of export files
- [ ] Define retention policy (e.g., keep 90 days)
- [ ] Create cleanup script (optional):
  ```bash
  # Remove exports older than 90 days
  find storage/app/exports/outbox -type f -mtime +90 -delete
  ```

## Step 20: Final Verification
- [ ] [ ] Verify all files, directories, and migrations in place
- [ ] [ ] Test all API endpoints
- [ ] [ ] Test all console commands
- [ ] [ ] Verify scheduler listing
- [ ] [ ] Review export logs
- [ ] [ ] Check exported file contents
- [ ] [ ] Verify row counts match database
- [ ] [ ] Document any deviations

## Troubleshooting Guide

If something doesn't work:

1. Check logs first:
   ```bash
   tail -f storage/logs/bank_processing.log
   tail -f storage/logs/laravel.log
   ```

2. Verify database:
   ```bash
   php artisan tinker
   >>> DB::table('exports_log')->get()
   >>> DB::table('bank_transactions')->count()
   ```

3. Test service directly:
   ```bash
   php artisan tinker
   >>> $service = new App\Services\ExportService()
   >>> $result = $service->exportAll()
   >>> dd($result)
   ```

4. Check file permissions:
   ```bash
   ls -la storage/app/exports/outbox/
   stat storage/app/exports/outbox/
   ```

5. Verify routes:
   ```bash
   php artisan route:list | grep export
   ```

## Success Indicators

✅ All checklist items completed
✅ Export files being created in `storage/app/exports/outbox/`
✅ Exports are logged in `exports_log` table with SUCCESS status
✅ Row counts accurate (paid_rows + rejected_rows = exported_rows)
✅ File names follow naming convention
✅ API endpoints return valid JSON responses
✅ Scheduler running (check `php artisan schedule:list`)
✅ Logs showing successful exports in `storage/logs/bank_processing.log`

---

**Once all items are checked, the export system is ready for production use!**
