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
        if (Schema::hasTable('exports_log')) {
            Schema::table('exports_log', function (Blueprint $table) {
                if (!Schema::hasColumn('exports_log', 'export_type')) {
                    $table->string('export_type')->default('ALL')->after('export_date'); // ALL or TODAY
                }
                if (!Schema::hasColumn('exports_log', 'total_rows')) {
                    $table->integer('total_rows')->default(0)->after('exported_rows');
                }
                if (!Schema::hasColumn('exports_log', 'paid_rows')) {
                    $table->integer('paid_rows')->default(0)->after('total_rows');
                }
                if (!Schema::hasColumn('exports_log', 'rejected_rows')) {
                    $table->integer('rejected_rows')->default(0)->after('paid_rows');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('exports_log')) {
            Schema::table('exports_log', function (Blueprint $table) {
                $table->dropColumnIfExists('export_type');
                $table->dropColumnIfExists('total_rows');
                $table->dropColumnIfExists('paid_rows');
                $table->dropColumnIfExists('rejected_rows');
            });
        }
    }
};
