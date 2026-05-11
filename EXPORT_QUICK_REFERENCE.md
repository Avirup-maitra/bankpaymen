# Export System - Quick Reference

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    EXPORT SYSTEM                            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  API Endpoint       Console Command     Scheduler          │
│  ────────────       ────────────────    ─────────────      │
│  GET /export/all    php artisan         06:00 AM (TODAY)   │
│  GET /export/today  export:transactions 23:59 PM (ALL)     │
│  GET /export/status --type=all          └─────────────────┘
│  GET /export/history --type=today
│                                                              │
│                            ↓                                │
│                                                              │
│              ExportService (Business Logic)                │
│              ──────────────────────────────                │
│              • exportAll()                                 │
│              • exportToday()                               │
│              • getExportHistory()                          │
│              • getTodayExportStatus()                      │
│                                                              │
│                            ↓                                │
│                                                              │
│      BankTransactionsExport    TodayTransactionsExport    │
│      ────────────────────────  ──────────────────────────  │
│      (All transactions format)  (Today's format with extra │
│                                  reject reason & date)      │
│                                                              │
│                            ↓                                │
│                                                              │
│      storage/app/exports/outbox/                           │
│      └── transactions_all_*.xlsx                           │
│      └── transactions_today_*.xlsx                         │
│                                                              │
│                            ↓                                │
│                                                              │
│      exports_log (Database)                                │
│      ─────────────────────────                             │
│      • export_type (ALL|TODAY)                             │
│      • export_filename                                     │
│      • exported_rows, paid_rows, rejected_rows             │
│      • status (SUCCESS|FAILED)                             │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

## Quick Examples

### cURL Examples

#### Export All
```bash
curl -X GET "http://localhost/export/all" \
  -H "Accept: application/json"
```

#### Export Today
```bash
curl -X GET "http://localhost/export/today" \
  -H "Accept: application/json"
```

#### Check Status
```bash
curl -X GET "http://localhost/export/status/today" \
  -H "Accept: application/json"
```

#### View History
```bash
curl -X GET "http://localhost/export/history?type=ALL&limit=10" \
  -H "Accept: application/json"
```

### PHP Examples

```php
// Using Laravel's HTTP client
use Illuminate\Support\Facades\Http;

// Export all transactions
$response = Http::get('/export/all');
$data = $response->json();

// Export today's transactions
$response = Http::get('/export/today');
$data = $response->json();

// Check status
$response = Http::get('/export/status/today');
$status = $response->json();
```

### Artisan Examples

```bash
# Manual export all transactions
php artisan export:transactions --type=all

# Manual export today's transactions
php artisan export:transactions --type=today

# List all scheduled tasks
php artisan schedule:list

# Run scheduler once
php artisan schedule:run
```

## File Naming Convention

### ALL Export
```
transactions_all_2026-03-30_23-59-45.xlsx
             │                │
             └─ Export type   └─ Timestamp (Y-m-d_H-i-s)
```

### TODAY Export
```
transactions_today_2026-03-30_06-00-12.xlsx
             │                 │
             └─ Export type    └─ Date (Y-m-d) + Time (H-i-s)
```

## Export Data Structure

### ALL Export Includes:
- ✓ All transactions
- ✓ All statuses (PAID, PENDING, REJECTED, etc.)
- ✓ All dates

### TODAY Export Includes:
- ✓ Transactions from yesterday
- ✓ Status = PAID (successful payments)
- ✓ Status = REJECTED (failed payments - for bank return file)
- ✓ Reject reason and liquidation date

## Column Mapping

| Column | ALL Export | TODAY Export | Purpose |
|--------|-----------|--------------|---------|
| ID | ✓ | ✓ | Transaction ID |
| Amount | ✓ | ✓ | Transaction amount |
| Status | ✓ | ✓ | Payment status |
| Import Status | ✓ | ✓ | OK or REJECTED |
| Reject Reason | ✗ | ✓ | Why transaction was rejected |
| Liquidation Date | ✗ | ✓ | When payment cleared |
| Beneficiary | ✓ | ✓ | Payment recipient |
| Invoice ID | ✓ | ✓ | Reference |

## Scheduler Schedule

| Task | Frequency | Time | Purpose |
|------|-----------|------|---------|
| TODAY Export | Daily | 06:00 AM | Morning export of yesterday's data |
| ALL Export | Daily | 23:59 PM | End-of-day comprehensive export |

## Database Query Examples

### Check today's export status
```php
ExportsLog::where('export_date', now()->date())
    ->where('export_type', 'TODAY')
    ->latest()
    ->first();
```

### Get last 10 exports
```php
ExportsLog::latest()->limit(10)->get();
```

### Get all failed exports
```php
ExportsLog::where('status', 'FAILED')->get();
```

## Key Features

✓ **Automatic Scheduling** - Exports run automatically at set times
✓ **Manual Triggering** - Use API/CLI to export on-demand
✓ **Duplicate Prevention** - Log file prevents re-processing
✓ **Audit Trail** - Complete history in exports_log table
✓ **Error Handling** - Graceful failure with logging
✓ **Data Integrity** - Proper date filtering for TODAY export
✓ **Excel Formatting** - Professional headers and styling
✓ **Comprehensive Logging** - All operations tracked

## Important Notes

⚠️ **TODAY Export Only Gets Yesterday's Data**
- Uses `created_at` between yesterday 00:00 and today 00:00
- Includes only PAID status and REJECTED transactions
- Ensures no duplicate exports

⚠️ **Rejected Transactions Included**
- These appear in bank return files next day
- Critical for reconciliation
- Must be tracked in exports_log

⚠️ **File Storage**
- Files stored in `storage/app/exports/outbox/`
- Ensure proper permissions: `chmod -R 775 storage/`
- Implement backup strategy for exported files

## Next Steps

1. Run migration: `php artisan migrate`
2. Create export directories: `mkdir -p storage/app/exports/outbox`
3. Set proper permissions: `chmod -R 775 storage/`
4. Start scheduler: `php artisan schedule:work` (development)
5. Or setup cron: `* * * * * cd /path/to/app && php artisan schedule:run`

## Support

For issues:
1. Check export log: `storage/logs/bank_processing.log`
2. Verify database: `SELECT * FROM exports_log;`
3. Check file system: `ls -la storage/app/exports/outbox/`
4. Test API: `curl http://localhost/export/all`
