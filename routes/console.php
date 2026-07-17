<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bank:process-files')
    ->everyFifteenMinutes()
    ->name('bank-file-processor')
    ->description('Scan ICICI/SBI watched folders and queue new bank files')
    ->withoutOverlapping(5)
    ->onFailure(function () {
        Log::channel('bank_processing')->error('bank:process-files command failed');
    })
    ->onSuccess(function () {
        Log::channel('bank_processing')->debug('bank:process-files command executed successfully');
    });

Schedule::command('export:transactions --type=today')
    ->everyThreeHours()
    ->name('export-today-transactions')
    ->description('Export today transactions')
    ->withoutOverlapping(5)
    ->onFailure(function () {
        Log::channel('bank_processing')->error('export:transactions --type=today command failed');
    })
    ->onSuccess(function () {
        Log::channel('bank_processing')->info('Today transactions exported successfully');
    });
