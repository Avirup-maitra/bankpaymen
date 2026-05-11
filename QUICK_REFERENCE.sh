#!/bin/bash

# Bank File Scheduler - Quick Start Commands
# Run these commands from /var/www/bankpayment

echo "=========================================="
echo "BANK FILE SCHEDULER - QUICK REFERENCE"
echo "=========================================="
echo ""

# 1. Test the scheduler manually (one-time run)
echo "1️⃣  TEST COMMAND (One-time execution):"
echo "   php artisan bank:process-files"
echo ""

# 2. Watch scheduler work in real-time (for development/testing)
echo "2️⃣  WATCH SCHEDULER (Development):"
echo "   php artisan schedule:work"
echo ""

# 3. View all scheduled tasks
echo "3️⃣  LIST ALL SCHEDULED TASKS:"
echo "   php artisan schedule:list"
echo ""

# 4. View real-time logs
echo "4️⃣  WATCH LOGS (Real-time):"
echo "   tail -f storage/logs/bank_processing.log"
echo ""

# 5. Search logs
echo "5️⃣  SEARCH LOGS:"
echo "   grep 'KEYWORD' storage/logs/bank_processing.log"
echo "   grep 'ERROR' storage/logs/bank_processing.log"
echo "   grep 'Duplicate' storage/logs/bank_processing.log"
echo ""

# 6. Check inbox directory
echo "6️⃣  CHECK INBOX FILES:"
echo "   ls -lah storage/app/bank_files/inbox/"
echo ""

# 7. Database checks
echo "7️⃣  DATABASE QUERIES:"
echo "   # View last processed files:"
echo "   php artisan tinker"
echo "   > App\Models\BankFile::latest()->limit(10)->get()"
echo ""
echo "   # Count total processed files:"
echo "   > App\Models\BankFile::count()"
echo ""
echo "   # Find by filename:"
echo "   > App\Models\BankFile::where('original_filename', 'file.txt')->first()"
echo ""

# 8. Configure cron for production
echo "8️⃣  SETUP CRON (Production):"
echo "   crontab -e"
echo "   # Add this line:"
echo "   * * * * * cd /var/www/bankpayment && php artisan schedule:run >> /dev/null 2>&1"
echo ""

# 9. Check if cron is running
echo "9️⃣  VERIFY CRON:"
echo "   ps aux | grep cron"
echo "   crontab -l"
echo ""

# 10. Clear cache if needed
echo "🔟 CLEAR CACHE (if config changed):"
echo "    php artisan config:cache"
echo "    php artisan cache:clear"
echo ""

echo "=========================================="
echo "For detailed guide, see: BANK_SCHEDULER_SETUP.md"
echo "=========================================="
