<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bulk_upload_sessions')) {
            Schema::create('bulk_upload_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_id')->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('bank_type');
                $table->integer('total_files_uploaded')->default(0);
                $table->integer('files_processed')->default(0);
                $table->integer('files_processing')->default(0);
                $table->integer('files_failed')->default(0);
                $table->bigInteger('total_rows_processed')->default(0);
                $table->bigInteger('total_rows_success')->default(0);
                $table->bigInteger('total_rows_rejected')->default(0);
                $table->decimal('total_amount_processed', 18, 2)->default(0);
                $table->json('failed_file_ids')->nullable();
                $table->json('file_ids')->nullable();
                $table->string('status')->default('QUEUED')->index();
                $table->timestamps();
            });
        }

        Schema::table('bank_files', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_files', 'bulk_upload_session_id')) {
                $table->foreignId('bulk_upload_session_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('bulk_upload_sessions')
                    ->nullOnDelete();
            }
        });

        try {
            Schema::table('bank_files', function (Blueprint $table) {
                $table->index(['bank_type', 'original_filename']);
                $table->index('bulk_upload_session_id');
            });
        } catch (Throwable $e) {
            // Restored databases may already have one of these indexes.
        }
    }

    public function down(): void
    {
        Schema::table('bank_files', function (Blueprint $table) {
            if (Schema::hasColumn('bank_files', 'bulk_upload_session_id')) {
                $table->dropConstrainedForeignId('bulk_upload_session_id');
            }
        });
    }
};
