<?php

namespace App\Http\Controllers;

use App\Services\ExportService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    protected $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Export all transactions
     * GET /api/export/all
     */
    public function exportAll()
    {
        $result = $this->exportService->exportAll();
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Export today's transactions (yesterday's data with PAID and REJECTED status)
     * GET /api/export/today
     */
    public function exportToday()
    {
        $result = $this->exportService->exportToday();
        
        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Get export history
     * GET /api/export/history?type=ALL|TODAY&limit=50
     */
    public function history(Request $request)
    {
        $type = $request->query('type');
        $limit = $request->query('limit', 50);

        $history = $this->exportService->getExportHistory($type, $limit);

        return response()->json([
            'success' => true,
            'data' => $history,
            'count' => $history->count(),
        ]);
    }

    /**
     * Get today's export status
     * GET /api/export/status/today
     */
    public function statusToday()
    {
        $status = $this->exportService->getTodayExportStatus();

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Export a specific transaction by ID
     * GET /api/export/transaction/{id}
     */
    public function exportTransaction($id)
    {
        try {
            $transaction = \App\Models\BankTransaction::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $transaction,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }
    }
}
