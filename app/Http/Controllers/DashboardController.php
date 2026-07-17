<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BankTransaction;
use App\Models\BankFile;
use App\Models\ExportsLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $viewDate = Carbon::yesterday();
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

        // Allow date selection but default to yesterday because bank response files arrive on the next day.
        if ($request->filled('date')) {
            $viewDate = Carbon::parse($request->date);
        } else {
            $viewDate = Carbon::yesterday();
        }

        $dashboardCacheKey = 'dashboard:' . $userId . ':' . $viewDate->toDateString() . ':v6';

        if (Cache::has($dashboardCacheKey)) {
            return view('dashboard', Cache::get($dashboardCacheKey));
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

        $topDebitAccounts = $transactionQuery()
            ->where('import_status', 'OK')
            ->whereIn('bank_status', ['Paid', 'SUCCESS'])
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('liquidation_date', $viewDate)
                      ->orWhere(function ($subQuery) use ($viewDate) {
                          $subQuery->whereNull('liquidation_date')
                                   ->whereDate('transaction_date', $viewDate);
                      });
            })
            ->whereNotNull('debit_account_no')
            ->select('debit_account_no', DB::raw('COUNT(*) as transaction_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('debit_account_no')
            ->orderByDesc('total_amount')
            ->limit(8)
            ->get();

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

        $last30StartDate = $viewDate->copy()->subDays(29)->startOfDay();

        $last30PaymentRaw = $transactionQuery()
            ->where('import_status', 'OK')
            ->whereIn('bank_status', ['Paid', 'SUCCESS'])
            ->where(function ($query) use ($last30StartDate, $viewDate) {
                $query->whereBetween('liquidation_date', [$last30StartDate, $viewDate->copy()->endOfDay()])
                    ->orWhere(function ($subQuery) use ($last30StartDate, $viewDate) {
                        $subQuery->whereNull('liquidation_date')
                            ->whereBetween('transaction_date', [$last30StartDate, $viewDate->copy()->endOfDay()]);
                    });
            })
            ->selectRaw('DATE(COALESCE(liquidation_date, transaction_date)) as payment_date, SUM(amount) as total_amount, COUNT(*) as transaction_count')
            ->groupBy('payment_date')
            ->orderBy('payment_date')
            ->get()
            ->keyBy('payment_date');

        $last30PaymentTrend = collect(range(0, 29))
            ->map(function ($i) use ($last30StartDate, $last30PaymentRaw) {
                $date = $last30StartDate->copy()->addDays($i);
                $dateKey = $date->toDateString();
                $row = $last30PaymentRaw->get($dateKey);

                return [
                    'date' => $date->format('d M'),
                    'amount' => $row ? (float) $row->total_amount : 0,
                    'count' => $row ? (int) $row->transaction_count : 0,
                ];
            });

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


        $managementBankPerformance = $transactionQuery()
            ->join('bank_files', 'bank_transactions.bank_file_id', '=', 'bank_files.id')
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('bank_transactions.liquidation_date', $viewDate)
                    ->orWhere(function ($subQuery) use ($viewDate) {
                        $subQuery->whereNull('bank_transactions.liquidation_date')
                            ->whereDate('bank_transactions.transaction_date', $viewDate);
                    });
            })
            ->select(
                'bank_files.bank_type',
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(CASE WHEN bank_transactions.import_status = "OK" AND bank_transactions.bank_status IN ("Paid", "SUCCESS") THEN 1 ELSE 0 END) as successful_transactions'),
                DB::raw('SUM(CASE WHEN bank_transactions.import_status = "REJECTED" THEN 1 ELSE 0 END) as rejected_transactions'),
                DB::raw('SUM(CASE WHEN bank_transactions.import_status = "OK" AND bank_transactions.bank_status IN ("Paid", "SUCCESS") THEN bank_transactions.amount ELSE 0 END) as total_amount')
            )
            ->groupBy('bank_files.bank_type')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($bank) {
                $totalTransactions = (int) $bank->total_transactions;
                $successfulTransactions = (int) $bank->successful_transactions;

                return [
                    'bank_type' => $bank->bank_type ?: 'Unknown',
                    'total_transactions' => $totalTransactions,
                    'successful_transactions' => $successfulTransactions,
                    'rejected_transactions' => (int) $bank->rejected_transactions,
                    'total_amount' => (float) $bank->total_amount,
                    'success_rate' => $totalTransactions > 0 ? round(($successfulTransactions / $totalTransactions) * 100, 2) : 0,
                ];
            });

        $managementTotalBankAmount = $managementBankPerformance->sum('total_amount');

        $topManagementVendors = $transactionQuery()
            ->where('import_status', 'OK')
            ->whereIn('bank_status', ['Paid', 'SUCCESS'])
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('liquidation_date', $viewDate)
                    ->orWhere(function ($subQuery) use ($viewDate) {
                        $subQuery->whereNull('liquidation_date')
                            ->whereDate('transaction_date', $viewDate);
                    });
            })
            ->select('beneficiary_name', DB::raw('COUNT(*) as transaction_count'), DB::raw('SUM(amount) as total_amount'), DB::raw('MAX(amount) as largest_amount'))
            ->groupBy('beneficiary_name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        $highValueTransactions = $transactionQuery()
            ->with('file:id,bank_type,original_filename')
            ->where('import_status', 'OK')
            ->whereIn('bank_status', ['Paid', 'SUCCESS'])
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('liquidation_date', $viewDate)
                    ->orWhere(function ($subQuery) use ($viewDate) {
                        $subQuery->whereNull('liquidation_date')
                            ->whereDate('transaction_date', $viewDate);
                    });
            })
            ->orderByDesc('amount')
            ->limit(10)
            ->get(['id', 'bank_file_id', 'transaction_date', 'amount', 'beneficiary_name', 'payment_ref_no', 'transaction_id']);

        $topRejectReasons = $transactionQuery()
            ->where('import_status', 'REJECTED')
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('liquidation_date', $viewDate)
                    ->orWhere(function ($subQuery) use ($viewDate) {
                        $subQuery->whereNull('liquidation_date')
                            ->whereDate('transaction_date', $viewDate);
                    });
            })
            ->selectRaw("COALESCE(NULLIF(reject_reason, ''), 'Not specified') as reason, COUNT(*) as count")
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        $paymentBands = $transactionQuery()
            ->where('import_status', 'OK')
            ->whereIn('bank_status', ['Paid', 'SUCCESS'])
            ->where(function ($query) use ($viewDate) {
                $query->whereDate('liquidation_date', $viewDate)
                    ->orWhere(function ($subQuery) use ($viewDate) {
                        $subQuery->whereNull('liquidation_date')
                            ->whereDate('transaction_date', $viewDate);
                    });
            })
            ->selectRaw("CASE
                WHEN amount < 100000 THEN 'Below 1L'
                WHEN amount < 500000 THEN '1L - 5L'
                WHEN amount < 1000000 THEN '5L - 10L'
                WHEN amount < 5000000 THEN '10L - 50L'
                ELSE 'Above 50L'
            END as band, COUNT(*) as count, SUM(amount) as total")
            ->groupBy('band')
            ->get()
            ->sortBy(function ($row) {
                return array_search($row->band, ['Below 1L', '1L - 5L', '5L - 10L', '10L - 50L', 'Above 50L'], true);
            })
            ->values();

        $dashboardData = compact(
            'filesReceivedToday',
            'filesProcessedToday',
            'totalAmountToday',
            'largestPaymentToday',
            'topDebitAccounts',
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
            'rejectedCount',
            'managementBankPerformance',
            'managementTotalBankAmount',
            'topManagementVendors',
            'highValueTransactions',
            'topRejectReasons',
            'paymentBands',
            'last30PaymentTrend'
        );

        Cache::put($dashboardCacheKey, $dashboardData, now()->addSeconds(60));

        return view('dashboard', $dashboardData);
    }
}