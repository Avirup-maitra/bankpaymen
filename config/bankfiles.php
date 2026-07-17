<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bank File Paths Configuration
    |--------------------------------------------------------------------------
    | Define all paths for bank file processing and storage
    |
    */

    'inbox' => env('BANK_INBOX_PATH', 'app/bank_files/inbox'),
    'processed' => env('BANK_PROCESSED_PATH', 'app/bank_files/processed'),
    'rejected' => env('BANK_REJECTED_PATH', 'app/bank_files/rejected'),
    'auto_import' => [
        'icici_inbox' => env('ICICI_AUTO_INBOX_PATH', 'app/bank_files/auto/icici/inbox'),
        'sbi_inbox' => env('SBI_AUTO_INBOX_PATH', 'app/bank_files/auto/sbi/inbox'),
        'processed' => env('BANK_AUTO_PROCESSED_PATH', 'app/bank_files/auto/processed'),
        'rejected' => env('BANK_AUTO_REJECTED_PATH', 'app/bank_files/auto/rejected'),
    ],
    'export_outbox' => env('EXPORT_OUTBOX_PATH', 'app/exports/outbox'),

    /*
    |--------------------------------------------------------------------------
    | Processing Configuration
    |--------------------------------------------------------------------------
    | Settings for bulk file processing and performance optimization
    |
    */

    'processing' => [
        // Batch size for inserting transactions (higher = faster but more memory)
        'batch_size' => env('BANK_FILE_BATCH_SIZE', 1000),

        // Number of files per queue job. Keep this small so multiple workers can process bulk uploads in parallel.
        'file_job_batch_size' => env('BANK_FILE_JOB_BATCH_SIZE', 100),
        
        // Chunk size for reading Excel files
        'chunk_size' => env('BANK_FILE_CHUNK_SIZE', 500),
        
        // Maximum execution time for processing (in seconds)
        // Should match PHP max_execution_time
        'max_execution_time' => env('BANK_FILE_MAX_TIME', 300),
        
        // Queue job timeout (in seconds)
        'job_timeout' => env('BANK_JOB_TIMEOUT', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    | Settings for bulk exports and memory optimization
    |
    */

    'export' => [
        // Number of records to process per chunk during export
        'chunk_size' => env('EXPORT_CHUNK_SIZE', 5000),
        
        // Maximum records per Excel sheet (0 = unlimited)
        'max_per_sheet' => env('EXPORT_MAX_PER_SHEET', 1000000),
        
        // Use streaming for exports (recommended for large files)
        'use_streaming' => env('EXPORT_USE_STREAMING', true),
    ],
];
