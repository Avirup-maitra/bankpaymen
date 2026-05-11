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
        // Column already exists in create_bank_files_table
        // No action needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do nothing to avoid accidental drop
    }
};