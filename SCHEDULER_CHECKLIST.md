# ✅ BANK FILE SCHEDULER - IMPLEMENTATION CHECKLIST

## Phase 1: Configuration (✅ COMPLETED)

- [x] Environment variables created (.env)
  - [x] BANK_INBOX_PATH = app/bank_files/inbox
  - [x] BANK_PROCESSED_PATH = app/bank_files/processed
  - [x] BANK_REJECTED_PATH = app/bank_files/rejected
  - [x] EXPORT_OUTBOX_PATH = app/exports/outbox

- [x] Configuration file created (config/bankfiles.php)
  - [x] Reads from environment
  - [x] Provides fallback defaults
  - [x] Centralized access point

- [x] Logging configured (config/logging.php)
  - [x] Dedicated channel: 'bank_processing'
  - [x] Daily logs kept 30 days
  - [x] Location: storage/logs/bank_processing-YYY-MM-DD.log

- [x] Directories created
  - [x] storage/app/bank_files/inbox/
  - [x] storage/app/bank_files/processed/
  - [x] storage/app/bank_files/rejected/
  - [x] storage/app/exports/outbox/

## Phase 2: Application Code (✅ COMPLETED)

- [x] Console Command
  - [x] File: app/Console/Commands/ProcessBankFiles.php
  - [x] Command name: bank:process-files
  - [x] Scans inbox directory
  - [x] Generates SHA256 hash for each file
  - [x] Checks for duplicates in database
  - [x] Creates BankFile record with validation data
  - [x] Dispatches ProcessBankFile job
  - [x] Logs comprehensive details

- [x] Task Scheduler
  - [x] File: app/Console/Kernel.php
  - [x] Schedule: Every 15 minutes
  - [x] Overlapping protection: 5 minutes timeout
  - [x] Error/Success logging configured
  - [x] Named: 'bank-file-processor'

- [x] Duplicate Prevention
  - [x] Uses file_hash (SHA256) column
  - [x] Hash is unique constraint in database
  - [x] Validation logs file ID + name + hash
  - [x] Duplicate files logged with warning
  - [x] Previous file ID referenced in logs

## Phase 3: Testing (✅ COMPLETED)

- [x] Manual command test
  - [x] Ran: php artisan bank:process-files
  - [x] Status: ✅ Works without errors

- [x] File processing test
  - [x] Created test file in inbox
  - [x] Ran scheduler
  - [x] Status: ✅ File detected and logged (ID: 504)

- [x] Duplicate detection test
  - [x] Created duplicate file (same content/hash)
  - [x] Ran scheduler again
  - [x] Status: ✅ Both files skipped as duplicates

- [x] Logging verification
  - [x] Bank processing log created
  - [x] File IDs logged correctly
  - [x] Filenames logged correctly
  - [x] Hashes logged correctly
  - [x] Duplicate warnings present

## Phase 4: Production Activation (📋 TODO)

Choose ONE of the following:

### Option A: Cron Job
- [ ] Edit crontab: `crontab -e`
- [ ] Add line: `* * * * * cd /var/www/bankpayment && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Verify: `crontab -l`
- [ ] Command: `ps aux | grep cron` (check if running)

### Option B: Supervisor Daemon
- [ ] Create file: `/etc/supervisor/conf.d/bankpay-scheduler.conf`
- [ ] Copy supervisor config from BANK_SCHEDULER_SETUP.md
- [ ] Reload: `sudo supervisorctl reread`
- [ ] Update: `sudo supervisorctl update`
- [ ] Start: `sudo supervisorctl start bankpay-scheduler`
- [ ] Verify: `sudo supervisorctl status bankpay-scheduler`

## Phase 5: Monitoring (📋 TODO - Ongoing)

- [ ] Set up log monitoring
  - [ ] Watch logs: `tail -f storage/logs/bank_processing-*.log`
  - [ ] Search errors: `grep ERROR storage/logs/bank_processing-*.log`
  - [ ] Search duplicates: `grep "Duplicate file" storage/logs/bank_processing-*.log`

- [ ] Set up database monitoring
  - [ ] Track processed files: See database queries in BANK_SCHEDULER_SETUP.md
  - [ ] Monitor status values: RECEIVED, PROCESSING, PROCESSED, REJECTED

- [ ] Set up alert system (Optional)
  - [ ] Email on failures
  - [ ] Slack notifications
  - [ ] PagerDuty integration

## Files Created/Modified

### New Files Created:
1. ✅ app/Console/Commands/ProcessBankFiles.php
2. ✅ app/Console/Kernel.php
3. ✅ config/bankfiles.php
4. ✅ BANK_SCHEDULER_SETUP.md
5. ✅ SCHEDULER_SUMMARY.md
6. ✅ QUICK_REFERENCE.sh
7. ✅ SCHEDULER_CHECKLIST.md (this file)

### Files Modified:
1. ✅ .env (added bank paths)
2. ✅ config/logging.php (added bank_processing channel)

### Directories Created:
1. ✅ storage/app/bank_files/inbox/
2. ✅ storage/app/bank_files/processed/
3. ✅ storage/app/bank_files/rejected/
4. ✅ storage/app/exports/outbox/

---

## 🎯 How to Proceed

### Immediate Next Steps:
1. **Review the setup** - Read SCHEDULER_SUMMARY.md
2. **Choose activation method** - Cron or Supervisor
3. **Activate scheduler** - Follow Phase 4 above
4. **Test in production** - Put test files in inbox

### Verification Commands:
```bash
# 1. Scheduler list
php artisan schedule:list

# 2. Test command
php artisan bank:process-files

# 3. Check logs
tail -f storage/logs/bank_processing-*.log

# 4. Monitor database
php artisan tinker
> App\Models\BankFile::latest()->limit(5)->get(['id', 'original_filename', 'file_hash', 'status'])
```

---

## 📊 Scheduler Behavior Summary

**Trigger:** Every 15 minutes (automatic)

**When Files Found:**
- ✅ Calculate SHA256 hash
- ✅ Check if processed before
- ✅ If new: Create record, log details, dispatch job
- ✅ If duplicate: Skip, log warning

**What is Logged:**
- ✅ File ID (database primary key)
- ✅ Original Filename
- ✅ File Hash (SHA256) - for validation
- ✅ Processing Status
- ✅ Timestamp

**Where Logged:**
- ✅ Database: bank_files table
- ✅ File: storage/logs/bank_processing-YYYY-MM-DD.log
- ✅ Console: Real-time output when run manually

---

## 🔐 Security Features

- ✅ File hash uniqueness prevents duplicates
- ✅ File validation using SHA256
- ✅ Processing status prevents re-processing
- ✅ Audit trail in logs for compliance
- ✅ Error logging for troubleshooting

---

## 📈 Performance Notes

- **Scheduler interval:** 15 minutes (configurable)
- **Overlap prevention:** 5 minute timeout
- **Log retention:** 30 days (configurable)
- **Database overhead:** Minimal (one INSERT + hash check per file)

---

## 🆘 Support

For issues, check:
1. BANK_SCHEDULER_SETUP.md - Troubleshooting section
2. QUICK_REFERENCE.sh - Quick commands
3. storage/logs/bank_processing-*.log - Error details
4. Laravel logs - storage/logs/laravel.log

---

**Status:** ✅ Configuration & Testing COMPLETE  
**Ready for:** 🚀 Production Activation  
**Next Action:** Choose Cron or Supervisor and activate scheduler  

