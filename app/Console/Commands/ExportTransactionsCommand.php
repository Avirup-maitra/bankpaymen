<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ExportService;

class ExportTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:transactions {--type=today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export bank transactions (all or today)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type'); // 'all' or 'today'
        $exportService = new ExportService();

        if ($type === 'all') {
            $this->info('Starting ALL transactions export...');
            $result = $exportService->exportAll();
        } else {
            $this->info('Starting TODAY transactions export...');
            $result = $exportService->exportToday();
        }

        if ($result['success']) {
            $this->info('✓ Export completed successfully');
            $this->info('Type: ' . $result['type']);
            $this->info('Filename: ' . ($result['filename'] ?? 'N/A'));
            $this->info('Total rows: ' . $result['total_rows']);
            $this->info('Paid rows: ' . $result['paid_rows']);
            $this->info('Rejected rows: ' . $result['rejected_rows']);
            
            return Command::SUCCESS;
        } else {
            $this->error('✗ Export failed: ' . $result['message']);
            return Command::FAILURE;
        }
    }
}
