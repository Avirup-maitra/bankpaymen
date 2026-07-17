<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBankFile;
use App\Models\BankFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessBankFiles extends Command
{
    protected $signature = 'bank:process-files';

    protected $description = 'Scan configured ICICI/SBI inbox directories for new bank files and queue them';

    public function handle(): int
    {
        $sources = [
            'ICICI' => config('bankfiles.auto_import.icici_inbox'),
            'SBI' => config('bankfiles.auto_import.sbi_inbox'),
        ];

        $processedPath = $this->path(config('bankfiles.auto_import.processed'));
        $rejectedPath = $this->path(config('bankfiles.auto_import.rejected'));

        File::ensureDirectoryExists($processedPath, 0755, true);
        File::ensureDirectoryExists($rejectedPath, 0755, true);

        $queuedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($sources as $bankType => $configuredPath) {
            $inboxPath = $this->path($configuredPath);
            File::ensureDirectoryExists($inboxPath, 0755, true);

            $files = File::files($inboxPath);

            if (empty($files)) {
                $this->line("No {$bankType} files found in {$inboxPath}");
                continue;
            }

            foreach ($files as $file) {
                $extension = strtolower($file->getExtension());
                if (! in_array($extension, $this->allowedExtensions($bankType), true)) {
                    $skippedCount++;
                    $this->moveSourceFile($file->getPathname(), $rejectedPath, $bankType, 'unsupported');
                    $this->warn("Skipped unsupported {$bankType} file: {$file->getFilename()}");
                    continue;
                }

                try {
                    if (! is_readable($file->getPathname())) {
                        throw new \RuntimeException('File is not readable by the scheduler user.');
                    }

                    $hash = hash_file('sha256', $file->getPathname());
                    $existingFile = BankFile::where('file_hash', $hash)->first();

                    if ($existingFile) {
                        $skippedCount++;
                        $this->moveSourceFile($file->getPathname(), $rejectedPath, $bankType, 'duplicate');
                        $this->warn("Skipped duplicate {$bankType} file {$file->getFilename()} (existing ID: {$existingFile->id})");
                        continue;
                    }

                    $storedPath = $this->storePrivateCopy($file->getPathname(), $file->getFilename());

                    $bankFile = BankFile::create([
                        'original_filename' => $file->getFilename(),
                        'stored_path' => $storedPath,
                        'source_type' => 'AUTO',
                        'bank_type' => $bankType,
                        'received_at' => now(),
                        'status' => 'RECEIVED',
                        'file_hash' => $hash,
                    ]);

                    ProcessBankFile::dispatch($bankFile);
                    $this->moveSourceFile($file->getPathname(), $processedPath, $bankType, 'queued');

                    $queuedCount++;
                    $this->info("Queued {$bankType} file: {$file->getFilename()} (ID: {$bankFile->id})");

                    Log::channel('bank_processing')->info('Auto bank file queued', [
                        'file_id' => $bankFile->id,
                        'file_name' => $file->getFilename(),
                        'bank_type' => $bankType,
                        'stored_path' => $storedPath,
                    ]);
                } catch (\Throwable $e) {
                    $failedCount++;
                    $this->moveSourceFile($file->getPathname(), $rejectedPath, $bankType, 'error');
                    $this->error("Failed {$bankType} file {$file->getFilename()}: {$e->getMessage()}");

                    Log::channel('bank_processing')->error('Auto bank file import failed', [
                        'file_name' => $file->getFilename(),
                        'bank_type' => $bankType,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Auto import summary: queued={$queuedCount}, skipped={$skippedCount}, failed={$failedCount}");

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function allowedExtensions(string $bankType): array
    {
        return $bankType === 'SBI' ? ['txt'] : ['xlsx', 'xls'];
    }

    private function storePrivateCopy(string $sourcePath, string $originalFilename): string
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
        $storedName = Str::slug($baseName) . '_' . now()->format('YmdHis') . '_' . Str::random(8) . '.' . $extension;
        $storedPath = 'bank_uploads/' . now()->format('Y/m') . '/' . $storedName;

        Storage::put($storedPath, File::get($sourcePath));

        return $storedPath;
    }

    private function moveSourceFile(string $sourcePath, string $archiveRoot, string $bankType, string $prefix): void
    {
        if (! File::exists($sourcePath)) {
            return;
        }

        $targetDir = rtrim($archiveRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . strtolower($bankType) . DIRECTORY_SEPARATOR . now()->format('Y-m-d');
        File::ensureDirectoryExists($targetDir, 0755, true);

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $prefix . '_' . now()->format('His') . '_' . basename($sourcePath);
        if (File::exists($targetPath)) {
            $targetPath .= '_' . Str::random(6);
        }

        File::move($sourcePath, $targetPath);
    }

    private function path(?string $path): string
    {
        $path = $path ?: '';

        if (Str::startsWith($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return storage_path($path);
    }
}
