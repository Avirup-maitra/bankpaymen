# 💻 BANK FILE SCHEDULER - DEVELOPER GUIDE

## Using File Paths in Your Code

### 1. Access Paths in Any Service/Controller

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class BankFileService
{
    /**
     * Get all configured paths
     */
    public function getPaths()
    {
        return [
            'inbox' => storage_path(config('bankfiles.inbox')),
            'processed' => storage_path(config('bankfiles.processed')),
            'rejected' => storage_path(config('bankfiles.rejected')),
            'outbox' => storage_path(config('bankfiles.export_outbox')),
        ];
    }

    /**
     * Example: Process file from inbox
     */
    public function processFile($filename)
    {
        $inboxPath = storage_path(config('bankfiles.inbox'));
        $filePath = $inboxPath . '/' . $filename;

        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filename}");
        }

        // Your processing logic here
        $content = file_get_contents($filePath);
        
        // ... process content ...

        // Move to processed directory
        $processedPath = storage_path(config('bankfiles.processed'));
        rename($filePath, $processedPath . '/' . $filename);

        return true;
    }

    /**
     * Example: Handle failed files
     */
    public function moveToRejected($filename, $reason)
    {
        $inboxPath = storage_path(config('bankfiles.inbox'));
        $rejectedPath = storage_path(config('bankfiles.rejected'));

        $source = $inboxPath . '/' . $filename;
        $destination = $rejectedPath . '/' . $filename;

        if (rename($source, $destination)) {
            Log::channel('bank_processing')->error('File moved to rejected', [
                'filename' => $filename,
                'reason' => $reason,
                'destination' => $rejectedPath,
            ]);
        }
    }
}
```

---

## 2. Using in BankFileService

```php
<?php

namespace App\Services;

use App\Models\BankFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class BankFileService
{
    /**
     * Process bank file with logging
     */
    public function processFile(BankFile $bankFile)
    {
        try {
            $filePath = storage_path($bankFile->stored_path);

            // Log processing started
            Log::channel('bank_processing')->info('Bank file processing started', [
                'file_id' => $bankFile->id,
                'file_name' => $bankFile->original_filename,
                'file_hash' => $bankFile->file_hash,
                'status' => $bankFile->status,
            ]);

            // ... your processing logic ...

            // Move to processed
            $processedPath = storage_path(config('bankfiles.processed'));
            $newPath = $processedPath . '/' . $bankFile->original_filename;
            rename($filePath, $newPath);

            // Update database
            $bankFile->update([
                'status' => 'PROCESSED',
                'processed_at' => now(),
                'stored_path' => config('bankfiles.processed') . '/' . $bankFile->original_filename,
            ]);

            Log::channel('bank_processing')->info('Bank file processing completed', [
                'file_id' => $bankFile->id,
                'file_name' => $bankFile->original_filename,
                'file_hash' => $bankFile->file_hash,
                'new_location' => $newPath,
                'status' => 'PROCESSED',
            ]);

        } catch (\Exception $e) {
            Log::channel('bank_processing')->error('Bank file processing failed', [
                'file_id' => $bankFile->id,
                'file_name' => $bankFile->original_filename,
                'file_hash' => $bankFile->file_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
```

---

## 3. Using in Controllers

```php
<?php

namespace App\Http\Controllers;

use App\Models\BankFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class BankFileController
{
    /**
     * Get file counts by status
     */
    public function getStatistics()
    {
        return [
            'inbox_files' => BankFile::where('status', 'RECEIVED')->count(),
            'processing_files' => BankFile::where('status', 'PROCESSING')->count(),
            'processed_files' => BankFile::where('status', 'PROCESSED')->count(),
            'rejected_files' => BankFile::where('status', 'REJECTED')->count(),
        ];
    }

    /**
     * Get recent files with hash validation
     */
    public function getRecentFiles()
    {
        return BankFile::latest()
            ->limit(20)
            ->select(['id', 'original_filename', 'file_hash', 'status', 'received_at'])
            ->get();
    }

    /**
     * Check if file was already processed (by hash)
     */
    public function checkDuplicate($fileHash)
    {
        $existing = BankFile::where('file_hash', $fileHash)
            ->select(['id', 'original_filename', 'status', 'processed_at'])
            ->first();

        if ($existing) {
            Log::channel('bank_processing')->info('Duplicate check - file found', [
                'search_hash' => $fileHash,
                'found_id' => $existing->id,
                'found_name' => $existing->original_filename,
                'found_status' => $existing->status,
            ]);

            return $existing;
        }

        return null;
    }

    /**
     * Check inbox directory and count files
     */
    public function checkInboxStatus()
    {
        $inboxPath = storage_path(config('bankfiles.inbox'));
        
        if (!is_dir($inboxPath)) {
            return ['status' => 'error', 'message' => 'Inbox directory not found'];
        }

        $files = scandir($inboxPath);
        $fileCount = count(array_diff($files, ['.', '..']));

        return [
            'inbox_path' => $inboxPath,
            'file_count' => $fileCount,
            'last_scan' => now(),
            'next_scan' => now()->addMinutes(15),
        ];
    }
}
```

---

## 4. Custom Queries for Monitoring

```php
<?php

// In routes/web.php or artisan tinker

// Get all files processed in last hour
$recentFiles = App\Models\BankFile::where('processed_at', '>=', now()->subHour())
    ->get(['id', 'original_filename', 'file_hash', 'status']);

// Find file by hash
$file = App\Models\BankFile::where('file_hash', 'abc123...')->first();

// Count by status
$counts = App\Models\BankFile::selectRaw('status, COUNT(*) as count')
    ->groupBy('status')
    ->get();

// Find duplicate attempts
$duplicates = App\Models\BankFile::select('file_hash')
    ->groupBy('file_hash')
    ->havingRaw('COUNT(*) > 1')
    ->get();

// Get failed files in last 24 hours
$recentRejected = App\Models\BankFile::where('status', 'REJECTED')
    ->where('created_at', '>=', now()->subDay())
    ->get();

// Search by filename
$file = App\Models\BankFile::where('original_filename', 'LIKE', '%statement%')
    ->get();
```

---

## 5. Logging Best Practices

```php
<?php

use Illuminate\Support\Facades\Log;

// ✅ GOOD - Structured logging with all validation data
Log::channel('bank_processing')->info('File processing initiated', [
    'file_id' => 504,                           // Database ID
    'file_name' => 'statement_2026_03.txt',     // Filename
    'file_hash' => 'abc123def456...',           // SHA256 hash
    'received_at' => now(),
    'status' => 'RECEIVED',
]);

// ✅ GOOD - Log duplicate with reference to original
Log::channel('bank_processing')->warning('Duplicate file detected', [
    'current_file' => 'statement.txt',
    'current_hash' => 'abc123...',
    'previous_id' => 502,                       // Reference to existing record
    'previous_status' => 'PROCESSED',
    'first_processed_at' => $previousFile->processed_at,
]);

// ✅ GOOD - Log errors with full context
Log::channel('bank_processing')->error('Processing failed', [
    'file_id' => 504,
    'file_name' => 'statement_2026_03.txt',
    'file_hash' => 'abc123...',
    'error' => $exception->getMessage(),
    'error_line' => $exception->getLine(),
    'error_file' => $exception->getFile(),
]);

// ✓ BAD - Vague logging without validation data
Log::warning('File problem');

// ✓ BAD - Mixing file operations without logging
rename($oldPath, $newPath);  // ← Should log this!
```

---

## 6. Example: Custom Command Using Scheduler

```php
<?php

namespace App\Console\Commands;

use App\Models\BankFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ReprocessRejectedFiles extends Command
{
    protected $signature = 'bank:reprocess-rejected {--limit=10}';
    protected $description = 'Reprocess rejected bank files';

    public function handle()
    {
        // Get rejected files
        $files = BankFile::where('status', 'REJECTED')
            ->limit($this->option('limit'))
            ->get();

        $this->info('Found ' . count($files) . ' rejected file(s)');

        foreach ($files as $file) {
            $rejectedPath = storage_path(config('bankfiles.rejected'));
            $inboxPath = storage_path(config('bankfiles.inbox'));
            
            $source = $rejectedPath . '/' . $file->original_filename;
            $destination = $inboxPath . '/' . $file->original_filename;

            if (file_exists($source)) {
                // Move back to inbox
                rename($source, $destination);
                
                // Reset status
                $file->update(['status' => 'RECEIVED']);

                Log::channel('bank_processing')->info('Rejected file reprocessed', [
                    'file_id' => $file->id,
                    'file_name' => $file->original_filename,
                    'file_hash' => $file->file_hash,
                    'moved_from' => 'rejected',
                    'moved_to' => 'inbox',
                ]);

                $this->line('✓ ' . $file->original_filename);
            }
        }

        $this->info('✅ Reprocessing complete');
    }
}
```

---

## 7. Example: Dashboard Widget Data

```php
<?php

namespace App\Http\Controllers;

use App\Models\BankFile;
use Illuminate\Support\Facades\Config;

class DashboardController
{
    public function getBankFileStats()
    {
        $stats = [
            'total_processed' => BankFile::where('status', 'PROCESSED')->count(),
            'total_rejected' => BankFile::where('status', 'REJECTED')->count(),
            'currently_processing' => BankFile::where('status', 'PROCESSING')->count(),
            'waiting_in_inbox' => BankFile::where('status', 'RECEIVED')->count(),
            
            'last_24_hours' => BankFile::where('created_at', '>=', now()->subDay())
                ->count(),
            
            'success_rate' => BankFile::selectRaw(
                'ROUND(SUM(CASE WHEN status = "PROCESSED" THEN 1 ELSE 0 END) * 100 / COUNT(*), 2) as rate'
            )->first()->rate,
            
            'last_processed' => BankFile::where('status', 'PROCESSED')
                ->latest('processed_at')
                ->first(['id', 'original_filename', 'processed_at']),
            
            'inbox_path' => storage_path(config('bankfiles.inbox')),
            'next_scan' => now()->addMinutes(15),
        ];

        return $stats;
    }
}
```

---

## 8. Testing the Scheduler

```php
<?php

namespace Tests\Feature;

use App\Models\BankFile;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BankFileSchedulerTest extends TestCase
{
    public function test_scheduler_detects_new_files()
    {
        // Create test file
        $testFile = storage_path('app/bank_files/inbox/test.txt');
        file_put_contents($testFile, 'TEST_CONTENT');

        // Run command
        Artisan::call('bank:process-files');

        // Verify database record
        $bankFile = BankFile::where('original_filename', 'test.txt')->first();
        $this->assertNotNull($bankFile);
        $this->assertEquals('RECEIVED', $bankFile->status);
    }

    public function test_scheduler_prevents_duplicate()
    {
        // Create file 1
        file_put_contents(
            storage_path('app/bank_files/inbox/file1.txt'),
            'SAME_CONTENT'
        );
        Artisan::call('bank:process-files');
        
        $file1 = BankFile::where('original_filename', 'file1.txt')->first();

        // Create file 2 with same content (same hash)
        file_put_contents(
            storage_path('app/bank_files/inbox/file2.txt'),
            'SAME_CONTENT'
        );
        Artisan::call('bank:process-files');

        // File 2 should not be created
        $file2 = BankFile::where('original_filename', 'file2.txt')->first();
        $this->assertNull($file2);

        // But file 1 should exist
        $this->assertNotNull($file1);
        $this->assertEquals($file1->file_hash, hash_file('sha256', storage_path('app/bank_files/inbox/file1.txt')));
    }
}
```

---

## Key Points for Developers

1. **Always use** `config('bankfiles.xxx')` for paths - never hardcode
2. **Always log** with file_id, file_name, and file_hash
3. **Check for duplicates** using file_hash before processing
4. **Use the logging channel** `'bank_processing'` for bank-related logs
5. **Update database status** as files move between directories
6. **Test thoroughly** with test files before production

---

For more information, see:
- `SCHEDULER_SUMMARY.md` - Overview
- `BANK_SCHEDULER_SETUP.md` - Detailed setup
- `SCHEDULER_CHECKLIST.md` - Implementation checklist
