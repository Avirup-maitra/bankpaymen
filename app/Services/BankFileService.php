<?php

namespace App\Services;

use App\Models\BankFile;
use App\Models\User;
use App\Imports\BankFileImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BankFileService
{
    public function handleUpload(UploadedFile $file, ?User $user, string $sourceType = 'MANUAL', string $bankType = 'ICICI'): BankFile
    {
        $hash = hash_file('sha256', $file->getRealPath());

        // Check for duplicate
        $existing = BankFile::where('file_hash', $hash)->first();
        if ($existing) {
            // Allow re-upload if previously rejected
            if ($existing->status === 'REJECTED') {
                $existing->delete();
            } else {
                throw new \Exception("A file with the exact same contents has already been uploaded previously (Original File: {$existing->original_filename}). Duplicate files are not allowed.");
            }
        }

        $filename = $file->getClientOriginalName();
        $path = $file->storeAs(
            'bank_uploads/' . date('Y/m'),
            $filename . '_' . time() . '.' . $file->getClientOriginalExtension()
        );

        $bankFile = BankFile::create([
            'original_filename' => $filename,
            'stored_path' => $path,
            'source_type' => $sourceType,
            'bank_type' => $bankType,
            'received_at' => now(),
            'status' => 'RECEIVED', // RECEIVED
            'file_hash' => $hash,
            'created_by' => $user ? $user->id : null,
        ]);

        return $bankFile;
    }

    public function processFile(BankFile $bankFile)
    {
        try {
            $bankFile->update(['status' => 'PROCESSING', 'processed_at' => now()]);

            $path = Storage::path($bankFile->stored_path); // Use absolute path for import
            
            // Check if file exists
            if (!file_exists($path)) {
                 $path = Storage::path('app/'.$bankFile->stored_path); // fix for some configs
                 if (!file_exists($path)) {
                     $path = Storage::path('app/private/'.$bankFile->stored_path); // Laravel 11
                 }
            }

            if ($bankFile->bank_type === 'SBI') {
                $importer = new \App\Imports\SBIBankFileImport($bankFile);
                $importer->import($path);
            } else {
                try {
                    Excel::import(new BankFileImport($bankFile), $bankFile->stored_path);
            } catch (\Exception $e) {
                // If soft failure (like OLE error for HTML/CSV saved as XLS), try fallbacks
                $msg = $e->getMessage();
                if (Str::contains($msg, ['not recognised as an OLE file', 'Corrupt'])) {
                     try {
                         // Try as HTML (common for generated reports) - Use Simple import (no chunking)
                         Excel::import(new \App\Imports\SimpleBankFileImport($bankFile), $bankFile->stored_path, null, \Maatwebsite\Excel\Excel::HTML);
                     } catch (\Exception $e2) {
                        try {
                             // Detect delimiter (CSV or TSV)
                             $path = Storage::path($bankFile->stored_path);
                             if (!file_exists($path)) {
                                 $path = Storage::path('app/'.$bankFile->stored_path);
                                 if (!file_exists($path)) {
                                    $path = Storage::path('app/private/'.$bankFile->stored_path); // Laravel 11
                                 }
                             }
                             
                             $delimiter = ',';
                             if (file_exists($path)) {
                                 // Read first 1000 chars to detect delimiter
                                 $content = file_get_contents($path, false, null, 0, 1000);
                                 if ($content && substr_count($content, "\t") > substr_count($content, ",")) {
                                     $delimiter = "\t";
                                 }
                             }

                             // Try as CSV/TSV - Use Simple import with detected delimiter
                             Excel::import(new \App\Imports\SimpleBankFileImport($bankFile, $delimiter), $bankFile->stored_path, null, \Maatwebsite\Excel\Excel::CSV);
                        } catch (\Exception $e3) {
                            throw $e; // Throw original error if all fail
                        }
                     }
                } else {
                    throw $e;
                }
            }
        }

            // Post-processing status update
            $bankFile->refresh();
            
            // If all rows OK -> PROCESSED
            // If mix of OK + rejected -> PARTIAL
            // If all rows rejected or file parsing fails -> REJECTED
            
            if ($bankFile->rejected_rows > 0) {
                 if ($bankFile->success_rows > 0) {
                     $bankFile->update(['status' => 'PARTIAL']);
                 } else {
                     $bankFile->update(['status' => 'REJECTED']);
                 }
            } else {
                 $bankFile->update(['status' => 'PROCESSED']);
            }

        } catch (\Exception $e) {
            $bankFile->update([
                'status' => 'REJECTED',
                'error_summary' => $e->getMessage()
            ]);
        }
    }
}
