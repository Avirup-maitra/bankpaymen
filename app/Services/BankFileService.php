<?php

namespace App\Services;

use App\Models\BankFile;
use App\Models\BulkUploadSession;
use App\Models\User;
use App\Imports\BankFileImport;
use App\Imports\FastIciciBankFileImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;

class BankFileService
{
    public function handleUpload(
        UploadedFile $file,
        ?User $user,
        string $sourceType = 'MANUAL',
        string $bankType = 'ICICI',
        ?BulkUploadSession $bulkUploadSession = null,
        bool $replaceExisting = false
    ): BankFile {
        $filename = $file->getClientOriginalName();
        $hash = hash_file('sha256', $file->getRealPath());

        $duplicateQuery = BankFile::where(function ($query) use ($bankType, $filename) {
            $query->where('bank_type', $bankType)
                ->where('original_filename', $filename);
        })->orWhere('file_hash', $hash);

        $duplicates = $duplicateQuery->get();

        if ($duplicates->isNotEmpty() && ! $replaceExisting) {
            $duplicate = $duplicates->first();
            throw new \Exception("A file named {$duplicate->original_filename} or with the same contents has already been uploaded previously (File ID: {$duplicate->id}). Duplicate files are not allowed.");
        }

        $path = $file->storeAs(
            'bank_uploads/' . date('Y/m'),
            pathinfo($filename, PATHINFO_FILENAME) . '_' . date('YmdHis') . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension()
        );

        return DB::transaction(function () use ($duplicates, $filename, $path, $sourceType, $bankType, $hash, $user, $bulkUploadSession) {
            foreach ($duplicates as $duplicate) {
                if ($duplicate->stored_path) {
                    Storage::delete($duplicate->stored_path);
                }

                $duplicate->delete();
            }

            return BankFile::create([
                'original_filename' => $filename,
                'stored_path' => $path,
                'source_type' => $sourceType,
                'bank_type' => $bankType,
                'received_at' => now(),
                'status' => 'RECEIVED',
                'file_hash' => $hash,
                'created_by' => $user ? $user->id : null,
                'bulk_upload_session_id' => $bulkUploadSession?->id,
            ]);
        });
    }

    public function processFile(BankFile $bankFile)
    {
        try {
            $this->updateProgressStage($bankFile, 'queued', 'File picked by queue worker', 5);
            $bankFile->update(['status' => 'PROCESSING', 'processed_at' => now()]);
            $this->updateProgressStage($bankFile, 'opening', 'Opening stored file', 10);

            $path = Storage::path($bankFile->stored_path);

            if (! is_file($path) || ! is_readable($path)) {
                throw new \Exception("File not readable at path: {$path}. Run the queue worker as the web-server user that owns uploaded files.");
            }

            if ($bankFile->bank_type === 'SBI') {
                $this->updateProgressStage($bankFile, 'reading_sbi_txt', 'Reading SBI TXT file', 20);
                $importer = new \App\Imports\SBIBankFileImport($bankFile);
                $importer->import($path);
            } else {
                $this->updateProgressStage($bankFile, 'detecting_reader', 'Detecting fastest ICICI reader', 15);
                $fastImporter = new FastIciciBankFileImport($bankFile);

                if (! $fastImporter->import($path)) {
                    $this->updateProgressStage($bankFile, 'excel_fallback', 'Using Excel package fallback reader', 20);
                    Excel::import(new BankFileImport($bankFile), $bankFile->stored_path);
                }
            }

            $this->updateProgressStage($bankFile, 'finalizing', 'Finalizing status and totals', 95);
            $bankFile->refresh();

            if ($bankFile->rejected_rows > 0) {
                $bankFile->update(['status' => $bankFile->success_rows > 0 ? 'PARTIAL' : 'REJECTED']);
            } else {
                $bankFile->update(['status' => 'PROCESSED']);
            }

            $bankFile->refresh();
            $this->updateProgressStage($bankFile, 'completed', 'File processing completed', 100);
        } catch (\Exception $e) {
            $bankFile->update([
                'status' => 'REJECTED',
                'error_summary' => $e->getMessage(),
            ]);
            $this->updateProgressStage($bankFile, 'failed', $e->getMessage(), 100);
        }
    }

    private function updateProgressStage(BankFile $bankFile, string $stage, string $message, int $percentage): void
    {
        Cache::put("bank_file_progress_{$bankFile->id}", [
            'bank_file_id' => $bankFile->id,
            'filename' => $bankFile->original_filename,
            'bank_type' => $bankFile->bank_type,
            'status' => $bankFile->status,
            'stage' => $stage,
            'stage_message' => $message,
            'total_rows' => $bankFile->total_rows,
            'success_rows' => $bankFile->success_rows,
            'rejected_rows' => $bankFile->rejected_rows,
            'total_amount' => $bankFile->total_amount,
            'percentage' => $percentage,
            'timestamp' => now(),
        ], now()->addHours(24));
    }
}
