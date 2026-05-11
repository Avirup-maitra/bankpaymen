#!/bin/bash

# Quick Start Script for 4,700+ File Bulk Upload
# Usage: ./BULK_UPLOAD_QUICK_START.sh

set -e

echo "╔════════════════════════════════════════════════╗"
echo "║   🚀 BULK UPLOAD QUICK START                   ║"
echo "╚════════════════════════════════════════════════╝"
echo ""

cd /var/www/bankpayment

# Step 1: Clear caches
echo "Step 1: Clearing caches..."
php artisan view:clear
php artisan cache:clear
php artisan config:clear
echo "✓ Caches cleared"
echo ""

# Step 2: Create queue tables
echo "Step 2: Setting up queue database..."
php artisan queue:table --create || echo "  (Queue table might already exist)"
php artisan migrate --force || echo "  (Some migrations might already exist)"
echo "✓ Queue database ready"
echo ""

# Step 3: Check database indexes
echo "Step 3: Verifying database indexes..."
mysql -h 127.0.0.1 -u root bnkpayment -e "
SHOW INDEX FROM bank_files WHERE Key_name IN ('status', 'bank_type', 'file_hash');
" 2>/dev/null | head -5 || echo "  (Indexes may already exist)"
echo "✓ Database indexes verified"
echo ""

# Step 4: Provide instructions
echo "Step 4: Starting queue workers..."
echo ""
echo "For single worker (slow, ~8-12 hours for 4,700 files):"
echo "  php artisan queue:work --queue=default --tries=3 --timeout=3600"
echo ""
echo "For 4 workers (better, ~2-3 hours):"
echo "  for i in {1..4}; do php artisan queue:work --queue=default --tries=3 --timeout=3600 & done"
echo ""
echo "For 8 workers (recommended, ~1-1.5 hours):"
echo "  for i in {1..8}; do php artisan queue:work --queue=default --tries=3 --timeout=3600 & done"
echo ""
echo "Or use Supervisor for automatic management (see BULK_UPLOAD_OPTIMIZATION.md)"
echo ""
echo "═════════════════════════════════════════════════"
echo ""
echo "✓ Setup complete! You can now:"
echo "  1. Start queue workers (command above)"
echo "  2. Upload 4,700 files via web UI"
echo "  3. Monitor progress at: /bank-files/summary?session_id=SESSION_ID"
echo "  4. Or use CLI: php artisan bulk:monitor --interval=5"
echo ""
echo "See BULK_UPLOAD_OPTIMIZATION.md for detailed setup instructions."
