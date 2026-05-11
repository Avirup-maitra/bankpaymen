<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes for bulk upload performance
        Schema::table('bank_files', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_files', 'status')) {
                return; // Table structure check
            }
            
            // Check and add indexes only if they don't exist
            try {
                $table->index('status')->comment('Index for file status filtering');
                $table->index('bank_type')->comment('Index for bank type filtering');
                $table->index('created_by')->comment('Index for created_by filtering');
            } catch (\Exception $e) {
                // Indexes might already exist, that's okay
            }
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_transactions', 'bank_file_id')) {
                return;
            }
            
            try {
                $table->index('bank_file_id')->comment('Index for fast transaction lookup by file');
                $table->index('import_status')->comment('Index for import status filtering');
                $table->index('amount')->comment('Index for amount queries');
                $table->index(['bank_file_id', 'import_status'])->comment('Composite index');
            } catch (\Exception $e) {
                // Indexes might already exist, that's okay
            }
        });

        Schema::table('processing_errors', function (Blueprint $table) {
            if (!Schema::hasColumn('processing_errors', 'bank_file_id')) {
                return;
            }
            
            try {
                $table->index('bank_file_id')->comment('Index for error lookup by file');
            } catch (\Exception $e) {
                // Index might already exist, that's okay
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Indexes will be automatically dropped if table is dropped
        // No need to explicitly drop them
    }
};
