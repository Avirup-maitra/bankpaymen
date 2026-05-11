<?php

namespace App\Console\Commands;

use App\Models\BankFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorBulkUpload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bulk:monitor {--session-id= : Filter by session ID} {--interval=5 : Refresh interval in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor bulk upload processing progress in real-time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $interval = (int)$this->option('interval');
        $sessionId = $this->option('session-id');

        while (true) {
            system('clear');

            $this->info('╔════════════════════════════════════════════╗');
            $this->info('║   📊 BULK UPLOAD MONITOR                   ║');
            $this->info('╚════════════════════════════════════════════╝');
            $this->newLine();

            // Queue status
            $queueCount = DB::table('jobs')->count();
            $this->line("Queue Jobs: <fg=cyan>$queueCount</>");

            // Files status
            $received = BankFile::where('status', 'RECEIVED')->count();
            $processing = BankFile::where('status', 'PROCESSING')->count();
            $processed = BankFile::where('status', 'PROCESSED')->count();
            $rejected = BankFile::where('status', 'REJECTED')->count();

            $this->line("Received:   <fg=yellow>$received</> files");
            $this->line("Processing: <fg=blue>$processing</> files");
            $this->line("Processed:  <fg=green>$processed</> files");
            $this->line("Rejected:   <fg=red>$rejected</> files");

            $total = $received + $processing + $processed + $rejected;
            if ($total > 0) {
                $percentage = round(($processed / $total) * 100);
                $this->newLine();
                $this->line("Overall Progress: <fg=cyan>$percentage%</>");

                // Progress bar
                $barLength = 40;
                $filledLength = (int)($percentage * $barLength / 100);
                $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);
                $this->line("[$bar]");
            }

            $this->newLine();
            $this->line("Last updated: " . now()->format('Y-m-d H:i:s'));
            $this->line("Refreshing in {$interval}s... (Ctrl+C to exit)");

            sleep($interval);
        }
    }
}
