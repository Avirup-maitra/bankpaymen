# Export System Documentation

## Overview
The export system provides two export types for bank transactions:
1. **ALL** - Complete export of all transactions
2. **TODAY** - Export of today's transactions (yesterday's data with PAID and REJECTED status)

## Configuration

### Environment Variables (.env)
```env
# Export path is defined in bankfiles.php config
EXPORT_OUTBOX_PATH=storage/app/exports/outbox
```

### Configuration File (config/bankfiles.php)
```php
'export_outbox' => env('EXPORT_OUTBOX_PATH', 'storage/app/exports/outbox'),
```

## Usage

### 1. API Endpoints

#### Export All Transactions
```bash
GET /export/all
```
**Response:**
```json
{
  "success": true,
  "type": "ALL",
  "filename": "transactions_all_2026-03-30_14-30-45.xlsx",
  "total_rows": 1250,
  "paid_rows": 1100,
  "rejected_rows": 150,
  "message": "Export completed successfully",
  "log_id": 5
}
```

#### Export Today's Transactions
```bash
GET /export/today
```
**Response:**
```json
{
  "success": true,
  "type": "TODAY",
  "filename": "transactions_today_2026-03-30_06-00-12.xlsx",
  "total_rows": 45,
  "paid_rows": 40,
  "rejected_rows": 5,
  "exported_rows": 45,
  "message": "Export completed successfully",
  "log_id": 6
}
```

#### Get Export History
```bash
GET /export/history?type=ALL&limit=50
```

#### Get Today's Export Status
```bash
GET /export/status/today
```

#### Export Single Transaction
```bash
GET /export/transaction/{id}
```

### 2. Console Commands

#### Export All Transactions
```bash
php artisan export:transactions --type=all
```

#### Export Today's Transactions
```bash
php artisan export:transactions --type=today
```

### 3. Scheduler (Automatic)

The scheduler automatically runs exports at:
- **06:00 AM** - TODAY export (yesterday's PAID + REJECTED transactions)
- **23:59 PM** - ALL export (complete transaction export)

To monitor scheduler:
```bash
# See all scheduled tasks
php artisan schedule:list

# Run the scheduler manually
php artisan schedule:run
```

## Export Types

### ALL Export
- **Frequency**: Daily at 11:59 PM
- **Includes**: All transactions from database
- **Columns**: ID, Transaction Type, Amount, Debit Account, IFSC, Beneficiary Account, Beneficiary Name, Transaction ID, Transaction Date, Invoice ID, Status, Import Status, Payment Ref, Email, Phone, Remarks Client, Remarks Beneficiary, Created

### TODAY Export
- **Frequency**: Daily at 6:00 AM
- **Includes**: 
  - Transactions uploaded yesterday with PAID status
  - Rejected transactions from yesterday (for bank return files)
- **Columns**: All columns from ALL export + Reject Reason and Liquidation Date

## Database Tables

### exports_log
Tracks all export operations:
```
- id
- export_date (date)
- export_type (ALL|TODAY)
- export_filename
- exported_rows
- total_rows
- paid_rows
- rejected_rows
- status (SUCCESS|FAILED)
- message
- created_at
- updated_at
```

## Export Service Usage

```php
use App\Services\ExportService;

$exportService = new ExportService();

// Export all transactions
$result = $exportService->exportAll();

// Export today's transactions
$result = $exportService->exportToday();

// Get export history
$history = $exportService->getExportHistory('TODAY', 50);

// Get today's export status
$status = $exportService->getTodayExportStatus();
```

## File Structure

- **Export Directory**: `storage/app/exports/outbox/`
- **File Naming**: 
  - ALL: `transactions_all_Y-m-d_H-i-s.xlsx`
  - TODAY: `transactions_today_Y-m-d_H-i-s.xlsx`

## Logging

Export operations are logged in:
- **File**: `storage/logs/bank_processing.log`
- **Info Level**: Export success
- **Error Level**: Export failures

## Features

✅ Two export types (ALL and TODAY)
✅ Automatic duplicate prevention via logging
✅ Scheduled exports (6 AM and 11:59 PM)
✅ Manual export via API endpoints
✅ Export history tracking
✅ Success/failure logging
✅ Comprehensive audit trail
✅ Rejected transactions included in TODAY export
✅ Excel format with proper headers and styling

## Troubleshooting

### Exports not running automatically
1. Check scheduler is running: `php artisan schedule:work`
2. Verify cron job is set up: `* * * * * cd /var/www/bankpayment && php artisan schedule:run >> /dev/null 2>&1`

### Files not found
1. Check export path exists: `storage/app/exports/outbox/`
2. Verify permissions: `chmod -R 775 storage/`

### No data exported
1. Check if transactions exist with correct status (PAID for ALL, PAID for TODAY paid, REJECTED for TODAY rejected)
2. Check transaction dates for TODAY export (only yesterday's data)
3. Check permissions on storage directory

## Integration Example

```php
// In a controller
public function dashboard()
{
    $exportService = new ExportService();
    $status = $exportService->getTodayExportStatus();
    
    return view('dashboard', [
        'exportStatus' => $status,
    ]);
}
```

## Best Practices

1. **Regular Backups**: Backup exported files regularly
2. **Monitoring**: Set up alerts for failed exports
3. **Retention**: Implement a retention policy for old exports
4. **Validation**: Verify row counts match expected data
5. **Scheduling**: Adjust times based on your bank's requirements
