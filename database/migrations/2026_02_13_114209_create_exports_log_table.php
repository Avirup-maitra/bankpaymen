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
        Schema::create('exports_log', function (Blueprint $table) {
            $table->id();
            $table->date('export_date');
            $table->string('export_filename');
            $table->integer('exported_rows')->default(0);
            $table->string('status'); // SUCCESS, FAILED
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports_log');
    }
};
