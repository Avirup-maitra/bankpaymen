<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BankTransaction;
use App\Models\BankFile;
use App\Models\ExportsLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $viewDate = Carbon::today();
        $user = auth()->user();
        $isAdmin = $user->isAdmin();
        $userId = $user->id;

        $bankFileQuery = function () use ($isAdmin, $userId) {
            $query = BankFile::query();

            if (! $isAdmin) {
                $query->where('created_by', $userId);
            }

            return $query;
        };

        $transactionQuery = function () use ($isAdmin, $userId) {
            $query = BankTransaction::query();

            if (! $isAdmin) {
                $query->whereHas('file', function ($fileQuery) use ($userId) {
                    $fileQuery->where('created_by', $userId);
                });
            }

            return $query;
        };

        // Allow date selection but default to TODAY
        if ($request->filled('date')) {
            $viewDate = Carbon::parse($request->date);
        } else {
            $viewDate = Carbon::today();
        }

        $filesReceivedToday = $bankFileQuery()
            ->whereDate('received_at', $viewDate)
            ->count();

        $filesProcessedToday = $bankFileQuery()
            ->whereDate('processed_at', $viewDate)
            ->whereIn('status', ['PROCESSED', 'PARTIAL'])
            ->count();

        $paidTransactionsForViewDate = $transactionQuery()
            ->where('import_status', 'OK')
            ->whereIn('bank_status', ['Paid', 'SUCCESS'])
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('liquidation_date', $viewDate)
                      ->orWhere(function ($subQuery) use ($viewDate) {
                          $subQuery->whereNull('liquidation_date')
                                   ->whereDate('transaction_date', $viewDate);
                      });
            });

        $totalAmountToday = (clone $paidTransactionsForViewDate)->sum('amount');
        $largestPaymentToday = (clone $paidTransactionsForViewDate)->max('amount');

        $topDebitAccount = $transactionQuery()
            ->where('import_status', 'OK')
            ->whereIn('bank_status', ['Paid', 'SUCCESS'])
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('liquidation_date', $viewDate)
                      ->orWhere(function ($subQuery) use ($viewDate) {
                          $subQuery->whereNull('liquidation_date')
                                   ->whereDate('transaction_date', $viewDate);
                      });
            })
            ->select('debit_account_no', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('debit_account_no')
            ->orderByDesc('total_amount')
            ->first();

        $last7Days = collect(range(0, 6))
            ->map(function ($i) use ($viewDate, $transactionQuery) {
                $date = $viewDate->copy()->subDays($i);

                $amount = $transactionQuery()
                    ->where('import_status', 'OK')
                    ->whereIn('bank_status', ['Paid', 'SUCCESS'])
                    ->where(function ($query) use ($date) {
                        $query->whereDate('liquidation_date', $date)
                              ->orWhere(function ($subQuery) use ($date) {
                                  $subQuery->whereNull('liquidation_date')
                                           ->whereDate('transaction_date', $date);
                              });
                    })
                    ->sum('amount');

                return [
                    'date' => $date->format('Y-m-d'),
                    'amount' => $amount,
                ];
            })
            ->reverse()
            ->values();

        $statusBreakdown = [
            'OK' => $transactionQuery()
                ->where('import_status', 'OK')
                ->where(function ($query) use ($viewDate) {
                    $query->whereDate('liquidation_date', $viewDate)
                          ->orWhere(function ($subQuery) use ($viewDate) {
                              $subQuery->whereNull('liquidation_date')
                                       ->whereDate('transaction_date', $viewDate);
                          });
                })
                ->count(),

            'REJECTED' => $transactionQuery()
                ->where('import_status', 'REJECTED')
                ->where(function ($query) use ($viewDate) {
                    $query->whereDate('liquidation_date', $viewDate)
                          ->orWhere(function ($subQuery) use ($viewDate) {
                              $subQuery->whereNull('liquidation_date')
                                       ->whereDate('transaction_date', $viewDate);
                          });
                })
                ->count(),
        ];

        $startDate = Carbon::today()->subMonths(11)->startOfMonth();
        $driver = DB::connection()->getDriverName();

        $monthExpression = match ($driver) {
            'mysql' => "DATE_FORMAT(COALESCE(liquidation_date, transaction_date), '%Y-%m')",
            'sqlite' => "strftime('%Y-%m', COALESCE(liquidation_date, transaction_date))",
            'pgsql' => "TO_CHAR(COALESCE(liquidation_date, transaction_date), 'YYYY-MM')",
            default => "DATE_FORMAT(COALESCE(liquidation_date, transaction_date), '%Y-%m')",
        };

        $monthlyTrendRaw = $transactionQuery()
            ->where('import_status', 'OK')
            ->where(function ($query) {
                $query->where('bank_status', 'LIKE', 'Paid%');
            })
            ->where(function ($query) use ($startDate) {
                $query->whereDate('liquidation_date', '>=', $startDate)
                      ->orWhere(function ($subQuery) use ($startDate) {
                          $subQuery->whereNull('liquidation_date')
                                   ->whereDate('transaction_date', '>=', $startDate);
                      });
            })
            ->selectRaw("$monthExpression as month, SUM(amount) as total")
            ->groupByRaw($monthExpression)
            ->orderBy('month', 'asc')
            ->get();

        $monthlyTrend = collect();
        $current = $startDate->copy();
        $end = Carbon::today()->endOfMonth();

        while ($current <= $end) {
            $monthStr = $current->format('Y-m');
            $data = $monthlyTrendRaw->firstWhere('month', $monthStr);

            $monthlyTrend->push([
                'month' => $current->format('M Y'),
                'total' => $data ? $data->total : 0,
            ]);

            $current->addMonth();
        }

        // Additional metrics for enhanced dashboard
        $totalTransactionsViewDate = $transactionQuery()
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('liquidation_date', $viewDate)
                      ->orWhere(function ($subQuery) use ($viewDate) {
                          $subQuery->whereNull('liquidation_date')
                                   ->whereDate('transaction_date', $viewDate);
                      });
            })
            ->count();

        // Success Metrics Calculation
        $successfulCount = $transactionQuery()
            ->whereIn('bank_status', ['Paid', 'SUCCESS'])
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('liquidation_date', $viewDate)
                      ->orWhere(function ($subQuery) use ($viewDate) {
                          $subQuery->whereNull('liquidation_date')
                                   ->whereDate('transaction_date', $viewDate);
                      });
            })
            ->count();

        $okCount = $statusBreakdown['OK'] ?? 0;
        $rejectedCount = $statusBreakdown['REJECTED'] ?? 0;
        $totalCount = $okCount + $rejectedCount;
        $successRate = $totalCount > 0 ? round(($successfulCount / $totalCount) * 100, 2) : 0;

        // Average Amount (use successful count for accuracy)
        $averageAmountToday = $successfulCount > 0 ? round($totalAmountToday / $successfulCount, 2) : 0;

        // Export Statistics (with error handling)
        try {
            $todayExportsPaid = ExportsLog::where('export_type', 'TODAY')
                ->where('export_date', $viewDate->toDateString())
                ->count();

            $todayExportsData = ExportsLog::where('export_type', 'TODAY')
                ->where('export_date', $viewDate->toDateString())
                ->latest()
                ->first();
        } catch (\Exception $e) {
            // Migration might not be run yet
            $todayExportsPaid = 0;
            $todayExportsData = null;
        }

        // Bank Type Distribution - Group transactions by bank type (not status)
        $bankTypeDistribution = $transactionQuery()
            ->join('bank_files', 'bank_transactions.bank_file_id', '=', 'bank_files.id')
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('bank_transactions.transaction_date', $viewDate)
                      ->orWhereDate('bank_transactions.liquidation_date', $viewDate);
            })
            ->select('bank_files.bank_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(bank_transactions.amount) as total'))
            ->groupBy('bank_files.bank_type')
            ->get()
            ->map(function ($item) {
                return [
                    'bank_type' => $item->bank_type,
                    'count' => $item->count,
                    'total' => $item->total
                ];
            });

        // Top Beneficiaries
        $topBeneficiaries = $transactionQuery()
            ->whereIn('bank_status', ['Paid', 'SUCCESS'])
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('liquidation_date', $viewDate)
                      ->orWhere(function ($subQuery) use ($viewDate) {
                          $subQuery->whereNull('liquidation_date')
                                   ->whereDate('transaction_date', $viewDate);
                      });
            })
            ->select('beneficiary_name', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('beneficiary_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Processing Statistics (last 7 days)
        $processingStats = collect(range(0, 6))
            ->map(function ($i) use ($viewDate, $bankFileQuery) {
                $date = $viewDate->copy()->subDays($i);
                return [
                    'date' => $date->format('Y-m-d'),
                    'received' => $bankFileQuery()->whereDate('received_at', $date)->count(),
                    'processed' => $bankFileQuery()
                        ->whereDate('processed_at', $date)
                        ->whereIn('status', ['PROCESSED', 'PARTIAL'])
                        ->count()
                ];
            })
            ->reverse()
            ->values();

        // Bank Type Count - Count transactions by bank type (using transaction date, only OK + REJECTED)
        $iciciBankFiles = $transactionQuery()
            ->whereHas('file', function ($q) {
                $q->where('bank_type', 'ICICI');
            })
            ->whereIn('import_status', ['OK', 'REJECTED'])
            ->where(function ($q) use ($viewDate) {
                $q->whereDate('transaction_date', $viewDate)
                    ->orWhereDate('liquidation_date', $viewDate);
            })
            ->count();

        $sbiBankFiles = $transactionQuery()
            ->whereHas('file', function ($q) {
                $q->where('bank_type', 'SBI');
            })
            ->whereIn('import_status', ['OK', 'REJECTED'])
            ->where(function ($q) use ($viewDate) {
                $q->whereDate('transaction_date', $viewDate)
                    ->orWhereDate('liquidation_date', $viewDate);
            })
            ->count();

        return view('dashboard', compact(
            'filesReceivedToday',
            'filesProcessedToday',
            'totalAmountToday',
            'largestPaymentToday',
            'topDebitAccount',
            'last7Days',
            'statusBreakdown',
            'viewDate',
            'monthlyTrend',
            'successRate',
            'totalTransactionsViewDate',
            'averageAmountToday',
            'todayExportsPaid',
            'todayExportsData',
            'bankTypeDistribution',
            'topBeneficiaries',
            'processingStats',
            'iciciBankFiles',
            'sbiBankFiles',
            'okCount',
            'rejectedCount'
        ));
    }
}