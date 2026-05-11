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
        Schema::create('bank_files', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('source_type'); // MANUAL, AUTO
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->string('status')->default('RECEIVED'); // RECEIVED, PROCESSING, PROCESSED, PARTIAL, REJECTED
            $table->integer('total_rows')->default(0);
            $table->integer('success_rows')->default(0);
            $table->integer('rejected_rows')->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('file_hash')->unique(); // SHA256
            $table->text('error_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_files');
    }
};
