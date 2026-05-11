<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run bank file processing every 15 minutes
        $schedule->command('bank:process-files')
            ->everyFifteenMinutes()
            ->name('bank-file-processor')
            ->description('Scan inbox and process new bank files')
            ->withoutOverlapping(5) // Prevent overlapping, timeout after 5 minutes
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('bank_processing')->error('bank:process-files command failed');
            })
            ->onSuccess(function () {
                // Optional: Log success for monitoring
                \Illuminate\Support\Facades\Log::channel('bank_processing')->debug('bank:process-files command executed successfully');
            });

        // Export TODAY's transactions (paid + rejected from yesterday)
        // Runs every 3 hours automatically
        $schedule->command('export:transactions --type=today')
            ->everyThreeHours()
            ->name('export-today-transactions')
            ->description('Export today\'s transactions (paid & rejected from yesterday)')
            ->withoutOverlapping(5)
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::channel('bank_processing')->error('export:transactions --type=today command failed');
            })
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::channel('bank_processing')->info('Today\'s transactions exported successfully');
            });

        // IMPORTANT: ALL export is NOT scheduled
        // It only runs on-demand via:
        // - API endpoint: GET /export/all
        // - CLI command: php artisan export:transactions --type=all
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
