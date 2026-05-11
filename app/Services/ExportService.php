<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\ExportsLog;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BankTransactionsExport;
use App\Exports\TodayTransactionsExport;

class ExportService
{
    const CHUNK_SIZE = 5000; // Process 5000 records at a time for memory efficiency

    /**
     * Export all transactions
     * Uses chunked queries to handle large datasets (2000+ files)
     * 
     * @return array
     */
    public function exportAll(): array
    {
        try {
            $filename = 'transactions_all_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
            $path = config('bankfiles.export_outbox') . '/' . $filename;

            // Count totals for logging
            $totalRows = BankTransaction::count();
            $paidRows = BankTransaction::where('bank_status', 'PAID')->count();
            $rejectedRows = BankTransaction::where('import_status', 'REJECTED')->count();

            if ($totalRows === 0) {
                ExportsLog::create([
                    'export_date' => now()->date(),
                    'export_type' => 'ALL',
                    'export_filename' => 'NO_DATA',
                    'exported_rows' => 0,
                    'total_rows' => 0,
                    'paid_rows' => 0,
                    'rejected_rows' => 0,
                    'status' => 'SUCCESS',
                    'message' => 'No transactions to export',
                ]);

                return [
                    'success' => true,
                    'type' => 'ALL',
                    'filename' => null,
                    'total_rows' => 0,
                    'paid_rows' => 0,
                    'rejected_rows' => 0,
                    'message' => 'No transactions available for export',
                ];
            }

            // Use chunked query to avoid memory issues
            $this->exportToFile($path, BankTransaction::query(), 'BankTransactionsExport');

            // Log the export
            $log = ExportsLog::create([
                'export_date' => now()->date(),
                'export_type' => 'ALL',
                'export_filename' => $filename,
                'exported_rows' => $totalRows,
                'total_rows' => $totalRows,
                'paid_rows' => $paidRows,
                'rejected_rows' => $rejectedRows,
                'status' => 'SUCCESS',
                'message' => 'All transactions exported successfully',
            ]);

            return [
                'success' => true,
                'type' => 'ALL',
                'filename' => $filename,
                'total_rows' => $totalRows,
                'paid_rows' => $paidRows,
                'rejected_rows' => $rejectedRows,
                'message' => 'Export completed successfully',
                'log_id' => $log->id,
            ];
        } catch (\Exception $e) {
            // Log the failed export
            ExportsLog::create([
                'export_date' => now()->date(),
                'export_type' => 'ALL',
                'export_filename' => 'ERROR',
                'exported_rows' => 0,
                'status' => 'FAILED',
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'type' => 'ALL',
                'message' => 'Export failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Export today's transactions (Yesterday's data)
     * - Transactions uploaded yesterday with PAID status
     * - Include REJECTED transactions (for bank return file)
     * Uses chunked queries for memory efficiency
     * 
     * @return array
     */
    public function exportToday(): array
    {
        try {
            $yesterday = Carbon::yesterday()->startOfDay();
            $today = Carbon::today()->startOfDay();

            // Get counts
            $paidCount = BankTransaction::whereBetween('created_at', [$yesterday, $today])
                ->where('bank_status', 'PAID')
                ->where('import_status', 'OK')
                ->count();

            $rejectedCount = BankTransaction::whereBetween('created_at', [$yesterday, $today])
                ->where('import_status', 'REJECTED')
                ->count();

            $totalCount = $paidCount + $rejectedCount;

            if ($totalCount === 0) {
                ExportsLog::create([
                    'export_date' => now()->date(),
                    'export_type' => 'TODAY',
                    'export_filename' => 'NO_DATA',
                    'exported_rows' => 0,
                    'total_rows' => 0,
                    'paid_rows' => 0,
                    'rejected_rows' => 0,
                    'status' => 'SUCCESS',
                    'message' => 'No transactions to export for today',
                ]);

                return [
                    'success' => true,
                    'type' => 'TODAY',
                    'filename' => null,
                    'total_rows' => 0,
                    'paid_rows' => 0,
                    'rejected_rows' => 0,
                    'message' => 'No transactions available for export',
                ];
            }

            $filename = 'transactions_today_' . now()->format('Y-m-d') . '_' . now()->format('H-i-s') . '.xlsx';
            $path = config('bankfiles.export_outbox') . '/' . $filename;

            // Combine two queries using union for chunked processing
            $query = BankTransaction::whereBetween('created_at', [$yesterday, $today])
                ->where('bank_status', 'PAID')
                ->where('import_status', 'OK')
                ->unionAll(
                    BankTransaction::whereBetween('created_at', [$yesterday, $today])
                        ->where('import_status', 'REJECTED')
                );

            $this->exportToFile($path, $query, 'TodayTransactionsExport');

            // Log the export
            $log = ExportsLog::create([
                'export_date' => now()->date(),
                'export_type' => 'TODAY',
                'export_filename' => $filename,
                'exported_rows' => $totalCount,
                'total_rows' => $totalCount,
                'paid_rows' => $paidCount,
                'rejected_rows' => $rejectedCount,
                'status' => 'SUCCESS',
                'message' => 'Today\'s transactions exported successfully',
            ]);

            return [
                'success' => true,
                'type' => 'TODAY',
                'filename' => $filename,
                'total_rows' => $totalCount,
                'paid_rows' => $paidCount,
                'rejected_rows' => $rejectedCount,
                'exported_rows' => $totalCount,
                'message' => 'Export completed successfully',
                'log_id' => $log->id,
            ];
        } catch (\Exception $e) {
            // Log the failed export
            ExportsLog::create([
                'export_date' => now()->date(),
                'export_type' => 'TODAY',
                'export_filename' => 'ERROR',
                'exported_rows' => 0,
                'status' => 'FAILED',
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'type' => 'TODAY',
                'message' => 'Export failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Export query results to file using chunking
     * Processes data in chunks to avoid memory exhaustion
     */
    protected function exportToFile($path, $query, $exportClass = 'BankTransactionsExport'): void
    {
        $chunkNumber = 0;
        $tempFiles = [];

        // Process query in chunks
        $query->chunk(self::CHUNK_SIZE, function ($chunk) use (&$tempFiles, &$chunkNumber, $exportClass) {
            $tempFile = storage_path('temp/export_chunk_' . uniqid() . '.xlsx');
            
            // Ensure temp directory exists
            @mkdir(dirname($tempFile), 0755, true);

            // Export chunk to temporary file
            $exportClass = 'App\\Exports\\' . $exportClass;
            Excel::store(new $exportClass(collect($chunk)), $tempFile, 'local');

            $tempFiles[] = $tempFile;
            $chunkNumber++;
        });

        // If only one file, use it directly
        if (count($tempFiles) === 1) {
            rename($tempFiles[0], storage_path($path));
            return;
        }

        // Merge multiple chunks if needed
        $this->mergeExcelFiles($tempFiles, storage_path($path));

        // Clean up temp files
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Merge multiple Excel files into one
     */
    protected function mergeExcelFiles(array $files, $outputPath): void
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $spreadsheet->removeSheetByIndex(0); // Remove default sheet

            $rowCounter = 1;
            $isFirstFile = true;

            foreach ($files as $file) {
                if (!file_exists($file)) {
                    continue;
                }

                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $tempSpreadsheet = $reader->load($file);
                $sourceSheet = $tempSpreadsheet->getActiveSheet();

                if ($isFirstFile) {
                    // Copy first sheet entirely with headers
                    $newSheet = $spreadsheet->addSheet(clone $sourceSheet);
                    $isFirstFile = false;
                    $rowCounter = $sourceSheet->getHighestRow() + 1;
                } else {
                    // For subsequent files, copy data rows only (skip header)
                    $newSheet = $spreadsheet->getSheetByIndex(0);
                    $highestRow = $sourceSheet->getHighestRow();
                    $highestColumn = $sourceSheet->getHighestColumn();

                    for ($row = 2; $row <= $highestRow; $row++) {
                        for ($col = 'A'; $col <= $highestColumn; $col++) {
                            $cell = $sourceSheet->getCell($col . $row);
                            $newSheet->setCellValue($col . $rowCounter, $cell->getValue());
                        }
                        $rowCounter++;
                    }
                }
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($outputPath);
        } catch (\Exception $e) {
            // Fallback: if merge fails, just use first file
            if (!empty($files) && file_exists($files[0])) {
                copy($files[0], $outputPath);
            }
        }
    }

    /**
     * Get export history
     */
    public function getExportHistory($type = null, $limit = 50)
    {
        $query = ExportsLog::orderBy('created_at', 'desc');
        
        if ($type) {
            $query->where('export_type', $type);
        }
        
        return $query->limit($limit)->get();
    }

    /**
     * Get today's export status
     */
    public function getTodayExportStatus()
    {
        $today = now()->date();
        
        $allExport = ExportsLog::where('export_date', $today)
            ->where('export_type', 'ALL')
            ->latest()
            ->first();

        $todayExport = ExportsLog::where('export_date', $today)
            ->where('export_type', 'TODAY')
            ->latest()
            ->first();

        return [
            'all_export' => $allExport ? [
                'id' => $allExport->id,
                'filename' => $allExport->export_filename,
                'exported_rows' => $allExport->exported_rows,
                'status' => $allExport->status,
                'exported_at' => $allExport->created_at,
            ] : null,
            'today_export' => $todayExport ? [
                'id' => $todayExport->id,
                'filename' => $todayExport->export_filename,
                'exported_rows' => $todayExport->exported_rows,
                'paid_rows' => $todayExport->paid_rows,
                'rejected_rows' => $todayExport->rejected_rows,
                'status' => $todayExport->status,
                'exported_at' => $todayExport->created_at,
            ] : null,
        ];
    }
}

