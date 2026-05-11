<?php

use App\Models\BankFile;
use App\Models\ProcessingError;

$lastFile = BankFile::latest()->with('user')->first();

if (!$lastFile) {
    echo "No files found.\n";
} else {
    echo "Last File ID: " . $lastFile->id . "\n";
    echo "Filename: " . $lastFile->original_filename . "\n";
    echo "Status: " . $lastFile->status . "\n";
    echo "Created At: " . $lastFile->created_at . "\n";
    echo "Summary: " . $lastFile->error_summary . "\n";
    
    echo "Recent Processing Errors:\n";
    $errors = ProcessingError::where('bank_file_id', $lastFile->id)->get();
    if ($errors->isEmpty()) {
        echo "No processing errors logged for this file.\n";
    } else {
        foreach ($errors as $error) {
            echo "- Row {$error->row_number}: [{$error->error_code}] {$error->error_message}\n";
        }
    }
}
