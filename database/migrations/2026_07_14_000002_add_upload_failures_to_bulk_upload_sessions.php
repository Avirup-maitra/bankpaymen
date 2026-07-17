<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_upload_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('bulk_upload_sessions', 'upload_failed_count')) {
                $table->integer('upload_failed_count')->default(0)->after('files_failed');
            }

            if (! Schema::hasColumn('bulk_upload_sessions', 'upload_failed_files')) {
                $table->json('upload_failed_files')->nullable()->after('failed_file_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bulk_upload_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('bulk_upload_sessions', 'upload_failed_files')) {
                $table->dropColumn('upload_failed_files');
            }

            if (Schema::hasColumn('bulk_upload_sessions', 'upload_failed_count')) {
                $table->dropColumn('upload_failed_count');
            }
        });
    }
};
