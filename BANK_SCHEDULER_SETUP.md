# Bank File Scheduler Setup Guide

## Overview
The bank file scheduler automatically scans the inbox directory every 15 minutes for new files and processes them without manual intervention.

---

## ✅ What's Been Configured

### 1. **Console Command** (`ProcessBankFiles`)
- **Location**: `app/Console/Commands/ProcessBankFiles.php`
- **Command**: `php artisan bank:process-files`
- **Features**:
  - Scans inbox directory at `BANK_INBOX_PATH`
  - Generates SHA256 hash for each file
  - Checks if file was already processed (prevents duplicates)
  - Logs file ID and name for validation
  - Only processes new/unprocessed files

### 2. **Task Scheduler** (`Kernel`)
- **Location**: `app/Console/Kernel.php`
- **Schedule**: Every 15 minutes automatically
- **Features**:
  - Prevents overlapping executions (timeout: 5 min)
  - Logs success/failure
  - Named for easy identification

### 3. **Logging Channel** (`bank_processing`)
- **Location**: `config/logging.php`
- **Log File**: `storage/logs/bank_processing.log`
- **Retention**: 30 days
- **Tracks**:
  - File ID and name
  - File hash (SHA256)
  - Duplicate detections
  - Processing status

### 4. **File Paths Configuration**
- **Location**: `config/bankfiles.php`
- **Environment Variables**: `.env`
- **Paths**:
  - `BANK_INBOX_PATH`: Where new files arrive
  - `BANK_PROCESSED_PATH`: Where processed files go
  - `BANK_REJECTED_PATH`: Where rejected files go
  - `EXPORT_OUTBOX_PATH`: Where exports are sent

---

## 🚀 How to Activate the Scheduler

### Option 1: Using Cron (Production - Recommended)

Add this line to your crontab (`crontab -e`):

```bash
* * * * * cd /var/www/bankpayment && php artisan schedule:run >> /dev/null 2>&1
```

This single cron job runs Laravel's scheduler every minute, which then triggers your 15-minute task.

**Verification**:
```bash
which crontab
crontab -l  # View existing cron jobs
```

### Option 2: Using Supervisor (For Daemon)

Create `/etc/supervisor/conf.d/bankpay-scheduler.conf`:

```ini
[program:bankpay-scheduler]
process_name=%(program_name)s
command=php /var/www/bankpayment/artisan schedule:work
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/bankpayment/storage/logs/scheduler.log
stopwaitsecs=600
```

Then restart supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bankpay-scheduler
```

### Option 3: Manual Testing (Development)

Test the command once:
```bash
php artisan bank:process-files
```

Watch the scheduler every minute (for testing):
```bash
php artisan schedule:work
```

---

## 📊 How Duplicate Prevention Works

### File Hash Validation
```
New File
   ↓
Generate SHA256 Hash
   ↓
Check in Database
   ├─→ Hash EXISTS? → Skip (Duplicate)
   └─→ Hash NOT found? → Create BankFile Record → Dispatch Job
```

### Database Columns Used for Validation
| Column | Purpose |
|--------|---------|
| `file_hash` | Unique SHA256 of file contents |
| `original_filename` | Name of the file |
| `id` | Bank file ID (logged) |
| `status` | Processing status |

### Log Example
```
[2026-03-30 12:15:00] bank_processing.INFO: File processing initiated 
{
  "file_id": 42,
  "file_name": "statement_2026_03_30.txt",
  "file_hash": "a1b2c3d4e5...",
  "received_at": "2026-03-30T12:15:00"
}

[2026-03-30 12:30:00] bank_processing.WARNING: Duplicate file skipped 
{
  "file_name": "statement_2026_03_30.txt",
  "file_hash": "a1b2c3d4e5...",
  "previous_id": 42,
  "previous_status": "PROCESSED"
}
```

---

## 📁 Directory Structure

Your configured directories will be:

```
storage/app/
├── bank_files/
│   ├── inbox/          ← New files arrive here
│   ├── processed/      ← After successful processing
│   └── rejected/       ← Failed files go here
└── exports/
    └── outbox/         ← Export files ready to send
```

**Create them with:**
```bash
mkdir -p storage/app/bank_files/{inbox,processed,rejected}
mkdir -p storage/app/exports/outbox
chmod -R 775 storage/
```

---

## 🔧 Configuration in Code

### Access Paths in Your Application
```php
// In any controller, service, or job:
$inboxPath = config('bankfiles.inbox');
$processedPath = config('bankfiles.processed');
$rejectedPath = config('bankfiles.rejected');
$outboxPath = config('bankfiles.export_outbox');

// Full path example:
$file = storage_path(config('bankfiles.inbox')) . '/file.txt';
```

### Modify Paths in `.env`
```env
# Short paths (relative to storage/)
BANK_INBOX_PATH=storage/app/bank_files/inbox
BANK_PROCESSED_PATH=storage/app/bank_files/processed
BANK_REJECTED_PATH=storage/app/bank_files/rejected
EXPORT_OUTBOX_PATH=storage/app/exports/outbox

# OR absolute paths
BANK_INBOX_PATH=/var/www/bankpayment/storage/app/bank_files/inbox
BANK_PROCESSED_PATH=/var/www/bankpayment/storage/app/bank_files/processed
BANK_REJECTED_PATH=/var/www/bankpayment/storage/app/bank_files/rejected
EXPORT_OUTBOX_PATH=/var/www/bankpayment/storage/app/exports/outbox
```

---

## 📋 How the Processing Flow Works

```
scheduler runs every 15 min
        ↓
bank:process-files command
        ↓
Scan storage/app/bank_files/inbox
        ↓
File Found?
├─ YES:
│  ├─ Calculate SHA256 hash
│  ├─ Check if hash exists in DB
│  ├─ New file? → Create BankFile record + Log
│  │             Dispatch ProcessBankFile job
│  │             Move to PROCESSING status
│  └─ Duplicate? → Log warning, Skip
└─ NO: Log info "No files found", Exit
        ↓
Job processes file async
├─ Success? → Move to processed/, status=PROCESSED
└─ Failed? → Move to rejected/, status=REJECTED, Log error
```

---

## 📝 Monitoring & Checking Logs

### View Bank Processing Logs
```bash
# Latest logs
tail -f storage/logs/bank_processing.log

# Today's logs
tail -f storage/logs/bank_processing.log

# Search for errors
grep ERROR storage/logs/bank_processing.log

# Search for duplicates
grep "Duplicate file skipped" storage/logs/bank_processing.log
```

### Check Database for Processed Files
```sql
-- Find all processed files
SELECT id, original_filename, file_hash, status, processed_at 
FROM bank_files 
ORDER BY received_at DESC;

-- Find specific file by hash
SELECT * FROM bank_files WHERE file_hash = 'abc123...';

-- Check duplicate attempts
SELECT file_hash, COUNT(*) as attempts 
FROM bank_files 
GROUP BY file_hash 
HAVING COUNT(*) > 1;
```

---

## 🐛 Troubleshooting

### Scheduler Not Running?
```bash
# Check if cron is running
ps aux | grep cron

# Check Laravel logs for errors
tail -f storage/logs/laravel.log

# Test manually
php artisan bank:process-files
php artisan schedule:list
```

### Files Not Processing?
```bash
# Check directory permissions
ls -la storage/app/bank_files/

# Verify paths in .env
cat .env | grep BANK_

# Check for duplicate files
SELECT COUNT(*) FROM bank_files WHERE file_hash = 'xxxxx';

# Check job queue status (if using database queue)
php artisan queue:failed
```

### Missing Directories?
```bash
# Create all directories
mkdir -p storage/app/bank_files/{inbox,processed,rejected}
mkdir -p storage/app/exports/outbox

# Fix permissions
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

---

## 📌 Summary: Key Points

✅ **Runs Automatically**: Every 15 minutes via scheduler  
✅ **No Duplicates**: Files hashed with SHA256, checked before processing  
✅ **Logged**: File ID, name, hash all tracked in `bank_processing.log`  
✅ **Efficient**: Only processes new files, skips already-processed  
✅ **Async**: Uses jobs queue for actual processing  
✅ **Configurable**: All paths in `.env` and `config/bankfiles.php`  

---

## 🎯 Next Steps

1. **Set up cron** for production scheduling
2. **Test the command**: `php artisan bank:process-files`
3. **Monitor logs**: `tail -f storage/logs/bank_processing.log`
4. **Place test files** in inbox directory
5. **Verify processing** in database

**That's it! Your bank file scheduler is ready to go! 🚀**
