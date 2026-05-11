# Export System - Get Started (5-Minute Setup)

## What You Just Got

A complete **two-type export system** for your bank payment application:

1. **ALL Export** - Complete transaction backup (daily at 11:59 PM)
2. **TODAY Export** - Yesterday's paid + rejected transactions (daily at 6:00 AM)

## The 5-Minute Setup

### Step 1: Run Migration (1 min)
```bash
php artisan migrate
```
✅ This creates export tracking in your database

### Step 2: Create Directory (30 sec)
```bash
mkdir -p storage/app/exports/outbox
chmod -R 775 storage/
```
✅ This creates the folder where export files will be saved

### Step 3: Clear Cache (30 sec)
```bash
php artisan config:clear
php artisan config:cache
```
✅ This reloads your configuration

### Step 4: Test It Works (1 min)
```bash
php artisan export:transactions --type=all
```
You should see:
```
✓ Export completed successfully
Type: ALL
Filename: transactions_all_2026-03-30_14-30-45.xlsx
Total rows: 1250
Paid rows: 1100
Rejected rows: 150
```

### Step 5: Verify Files Created (1 min)
```bash
ls -la storage/app/exports/outbox/
```
You should see your `.xlsx` file

**Done! 🎉 Export system is ready!**

---

## How to Use It

### Option 1: Use the Scheduler (Automatic)
Changes happen automatically:
- **6:00 AM daily** → TODAY export (yesterday's paid + rejected)
- **11:59 PM daily** → ALL export (all transactions)

To verify scheduler is running:
```bash
php artisan schedule:list
```

### Option 2: API Endpoints (On-Demand)

Export everything:
```bash
curl http://localhost/export/all
```

Export today's data:
```bash
curl http://localhost/export/today
```

Check export status:
```bash
curl http://localhost/export/status/today
```

View export history:
```bash
curl http://localhost/export/history?limit=20
```

### Option 3: CLI Commands (Manual)

```bash
# Export all transactions right now
php artisan export:transactions --type=all

# Export today's transactions right now
php artisan export:transactions --type=today
```

---

## What Gets Exported

### ALL Export Contains
```
ALL Bank Transactions
├─ ID
├─ Amount
├─ Status (PAID, PENDING, FAILED, etc.)
├─ Beneficiary Name
├─ Invoice ID
├─ Transaction Date
├─ Payment Reference
├─ Email
├─ Phone
└─ ... more fields
```

### TODAY Export Contains
```
Yesterday's Transactions
├─ All fields from ALL export
├─ Reject Reason (why payment failed)
├─ Liquidation Date (when it cleared)
└─ Status: Only PAID or REJECTED
   (Perfect for bank reconciliation!)
```

---

## File Names

The system automatically creates files like:
```
transactions_all_2026-03-30_23-59-45.xlsx
transactions_today_2026-03-30_06-00-12.xlsx
```

Find them in:
```
storage/app/exports/outbox/
```

---

## Verify It's Working

### Check Database Logging
```bash
php artisan tinker
>>> App\Models\ExportsLog::latest()->first()->toArray()
```

Should show export entries with:
- ✅ export_type (ALL or TODAY)
- ✅ export_filename
- ✅ exported_rows (number of rows)
- ✅ paid_rows and rejected_rows
- ✅ status (SUCCESS)

### Check Files
```bash
ls -lh storage/app/exports/outbox/
```

Should show `.xlsx` files with good file sizes

---

## Production Setup

To run scheduler in production, set up cron:

```bash
# Add this line to your crontab
* * * * * cd /var/www/bankpayment && php artisan schedule:run >> /dev/null 2>&1
```

Or use supervisor for long-running daemon:

```bash
# Create /etc/supervisor/conf.d/laravel-scheduler.conf
[program:laravel-scheduler]
process_name=%(program_name)s
command=php /var/www/bankpayment/artisan schedule:work
autostart=true
autorestart=true
user=www-data
```

Then restart supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-scheduler
```

---

## Troubleshooting

### Scheduler not running?
```bash
# Check if it's listed
php artisan schedule:list

# Start it (development)
php artisan schedule:work

# Or check cron job
crontab -l | grep schedule
```

### No files created?
```bash
# Check directory permissions
ls -la storage/app/exports/outbox/

# Check if writable
touch storage/app/exports/outbox/test.txt && rm storage/app/exports/outbox/test.txt

# Check logs
tail storage/logs/bank_processing.log
```

### Check export logs in database
```bash
php artisan tinker
>>> DB::table('exports_log')->latest()->limit(10)->get()
```

---

## Next Steps

Once 5-minute setup is done, you can:

1. **Review Documentation**
   - `EXPORT_DOCUMENTATION.md` - Complete reference
   - `EXPORT_QUICK_REFERENCE.md` - Examples and curl commands
   - `EXPORT_FLOW_DIAGRAMS.md` - Visual diagrams
   - `EXPORT_SETUP_CHECKLIST.md` - Detailed setup guide

2. **Monitor Exports**
   - Check `storage/logs/bank_processing.log` for export logs
   - View `exports_log` database table
   - Monitor file creation in `storage/app/exports/outbox/`

3. **Integrate with Your Dashboard** (Optional)
   ```php
   // In a controller
   $exportService = new App\Services\ExportService();
   $status = $exportService->getTodayExportStatus();
   return view('dashboard', ['exports' => $status]);
   ```

4. **Set Up Alerts** (Optional)
   - Alert if daily export fails
   - Alert if row counts are unusual
   - Alert if no exports for 24+ hours

---

## Files Created

✅ Service Layer
- `app/Services/ExportService.php`

✅ Export Formats
- `app/Exports/BankTransactionsExport.php`
- `app/Exports/TodayTransactionsExport.php`

✅ API
- `app/Http/Controllers/ExportController.php`
- Routes added to `routes/web.php`

✅ Console
- `app/Console/Commands/ExportTransactionsCommand.php`
- Schedule added to `app/Console/Kernel.php`

✅ Database
- Migration: `database/migrations/*_exports_log_table.php`

✅ Configuration
- `config/bankfiles.php` (created earlier)

✅ Documentation
- `EXPORT_DOCUMENTATION.md`
- `EXPORT_QUICK_REFERENCE.md`
- `EXPORT_SETUP_CHECKLIST.md`
- `EXPORT_SYSTEM_SUMMARY.md`
- `EXPORT_FLOW_DIAGRAMS.md`
- `EXPORT_GET_STARTED.md` ← You are here

---

## Key Points to Remember

✅ **Two Export Types**
- ALL: Everything (daily 11:59 PM)
- TODAY: Yesterday only (daily 6:00 AM)

✅ **Three Ways to Trigger**
- Automatic scheduler (cron)
- API endpoints (/export/*)
- CLI commands (php artisan)

✅ **Duplicate Prevention**
- Each export logged in database
- Scheduler prevents overlapping
- Check log_id for verification

✅ **Excel Format**
- Professional formatting
- Color-coded headers
- All relevant columns

✅ **Complete Audit Trail**
- Every export tracked in database
- Success/failure logged
- Row counts recorded

---

## Questions?

Refer to the comprehensive documentation:
- Need examples? → `EXPORT_QUICK_REFERENCE.md`
- Need visual flow? → `EXPORT_FLOW_DIAGRAMS.md`
- Need step-by-step? → `EXPORT_SETUP_CHECKLIST.md`
- Need full documentation? → `EXPORT_DOCUMENTATION.md`

---

## Success Checklist

Once you've completed the 5-minute setup, verify:

- [ ] Migration runs without error
- [ ] Directory created and writable
- [ ] CLI command test succeeds
- [ ] Export files appear in `storage/app/exports/outbox/`
- [ ] Database entries in `exports_log` table
- [ ] API endpoint returns success JSON
- [ ] Files open in Excel without errors

**Once all checked, you're production-ready!** 🚀

---

**Setup Complete! Your export system is now active.**
