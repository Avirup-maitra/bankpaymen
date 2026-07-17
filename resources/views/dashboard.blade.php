<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Management Dashboard
            </h2>
            <form method="GET" action="{{ route('dashboard') }}" class="flex gap-2">
                <input type="date" name="date" value="{{ $viewDate->format('Y-m-d') }}" class="border rounded px-2.5 py-1.5 text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1.5 rounded text-sm font-medium transition">Go</button>
            </form>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
            Report date: <span class="font-bold">{{ $viewDate->format('d M Y') }}</span>
            @if($viewDate->isYesterday()) <span class="text-blue-600 dark:text-blue-400 font-semibold">(Default: sysdate - 1)</span> @elseif($viewDate->isToday()) <span class="text-green-600 dark:text-green-400 font-semibold">(Today)</span> @endif
        </p>
    </x-slot>

    <div class="py-2.5 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Executive KPIs -->
            <div class="mb-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <!-- Success Rate -->
                <div class="bg-gradient-to-br from-green-500 to-green-600 dark:from-green-700 dark:to-green-900 shadow-lg rounded-lg p-3 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-green-100 text-xs font-medium">Success Rate</div>
                            <div class="text-lg font-semibold mt-1">{{ $successRate }}%</div>
                            <div class="text-xs text-green-200 mt-1">{{ $okCount }}/{{ $totalTransactionsViewDate }}</div>
                        </div>
                        <div class="p-1.5 bg-green-400 bg-opacity-30 rounded text-lg">✓</div>
                    </div>
                </div>

                <!-- Total Transactions -->
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-700 dark:to-blue-900 shadow-lg rounded-lg p-3 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-blue-100 text-xs font-medium">Total Transactions</div>
                            <div class="text-lg font-semibold mt-1">{{ $totalTransactionsViewDate }}</div>
                            <div class="text-xs text-blue-200 mt-1">{{ $okCount }} ✓ / {{ $rejectedCount }} ✗</div>
                        </div>
                        <div class="p-1.5 bg-blue-400 bg-opacity-30 rounded text-xs font-bold">TXN</div>
                    </div>
                </div>

                <!-- Total Amount -->
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-700 dark:to-purple-900 shadow-lg rounded-lg p-3 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-purple-100 text-xs font-medium">Total Amount</div>
                            <div class="text-lg font-semibold mt-1">₹{{ number_format($totalAmountToday, 0) }}</div>
                            <div class="text-xs text-purple-200 mt-1">Avg: ₹{{ number_format($averageAmountToday, 0) }}</div>
                        </div>
                        <div class="p-1.5 bg-purple-400 bg-opacity-30 rounded text-xs font-bold">₹</div>
                    </div>
                </div>

                <!-- Files Received -->
                <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 dark:from-indigo-700 dark:to-indigo-900 shadow-lg rounded-lg p-3 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-indigo-100 text-xs font-medium">Files Received</div>
                            <div class="text-lg font-semibold mt-1">{{ $filesReceivedToday }}</div>
                            <div class="text-xs text-indigo-200 mt-1">{{ $filesProcessedToday }} Processed</div>
                        </div>
                        <div class="p-1.5 bg-indigo-400 bg-opacity-30 rounded text-xs font-bold">FILE</div>
                    </div>
                </div>

                <!-- Bank Types -->
                <div class="bg-gradient-to-br from-orange-500 to-orange-600 dark:from-orange-700 dark:to-orange-900 shadow-lg rounded-lg p-3 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-orange-100 text-xs font-medium">Bank Types</div>
                            <div class="text-base font-semibold mt-1">ICICI: {{ $iciciBankFiles }}</div>
                            <div class="text-sm text-orange-200">SBI: {{ $sbiBankFiles }}</div>
                        </div>
                        <div class="p-1.5 bg-orange-400 bg-opacity-30 rounded text-xs font-bold">BANK</div>
                    </div>
                </div>

                <!-- Today Exports -->
                <div class="bg-gradient-to-br from-cyan-500 to-cyan-600 dark:from-cyan-700 dark:to-cyan-900 shadow-lg rounded-lg p-3 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-cyan-100 text-xs font-medium">Today Exports</div>
                            <div class="text-lg font-semibold mt-1">{{ $todayExportsPaid }}</div>
                            @if($todayExportsData)
                                <div class="text-xs text-cyan-200 mt-1">
                                    ✓ {{ $todayExportsData->status }}
                                </div>
                            @else
                                <div class="text-xs text-cyan-300 mt-1">No exports yet</div>
                            @endif
                        </div>
                        <div class="p-1.5 bg-cyan-400 bg-opacity-30 rounded text-xs font-bold">EXP</div>
                    </div>
                </div>
            </div>

            <!-- Balanced Management Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
                <!-- Status Breakdown (Pie) -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Status Distribution</h3>
                    <div class="relative h-52">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="mt-4 flex justify-around text-sm">
                        <div class="text-center">
                            <div class="text-green-600 dark:text-green-400 font-bold text-lg">{{ $okCount }}</div>
                            <div class="text-gray-600 dark:text-gray-400 text-xs">Successful</div>
                        </div>
                        <div class="text-center">
                            <div class="text-red-600 dark:text-red-400 font-bold text-lg">{{ $rejectedCount }}</div>
                            <div class="text-gray-600 dark:text-gray-400 text-xs">Rejected</div>
                        </div>
                    </div>
                </div>

                <!-- 7 Day Volume Trend -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3 lg:col-span-2">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Last 7 Days Volume</h3>
                    <div class="relative h-52">
                        <canvas id="volumeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Operations Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
                <!-- Processing Statistics -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">File Processing (7 Days)</h3>
                    <div class="relative h-52">
                        <canvas id="processingChart"></canvas>
                    </div>
                </div>

                <!-- Bank Status Distribution -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Transaction Status</h3>
                    <div class="relative h-52">
                        <canvas id="bankStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Information Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
                <!-- Top Debit Accounts -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Top Debit Accounts</h3>
                    @if($topDebitAccounts->count() > 0)
                        <div class="space-y-2 max-h-52 overflow-y-auto">
                            @foreach($topDebitAccounts as $account)
                                <div class="flex justify-between items-center pb-2 border-b dark:border-gray-700 last:border-b-0">
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $account->debit_account_no }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $account->transaction_count }} transaction(s)</div>
                                    </div>
                                    <div class="text-right text-sm font-bold text-green-600 dark:text-green-400 shrink-0">₹{{ number_format($account->total_amount, 2) }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5 text-gray-500">No debit account data available</div>
                    @endif
                </div>

                <!-- Last 30 Days Payment Trend -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Last 30 Days Payment Trend</h3>
                    <div class="relative h-52">
                        <canvas id="last30PaymentTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Management View -->
            <div class="mb-4">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Management View</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Bank contribution, vendor concentration, payment bands, and exception signals.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3 lg:col-span-2">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Bank-wise Payment Contribution</h4>
                        <div class="relative h-56">
                            <canvas id="managementBankChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Payment Size Mix</h4>
                        <div class="relative h-56">
                            <canvas id="paymentBandsChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-3 mb-4">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Bank Performance</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-2.5 py-1.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Bank</th>
                                        <th class="px-2.5 py-1.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                                        <th class="px-2.5 py-1.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Share</th>
                                        <th class="px-2.5 py-1.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Txns</th>
                                        <th class="px-2.5 py-1.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Success</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @forelse($managementBankPerformance as $bank)
                                        <tr>
                                            <td class="px-2.5 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $bank['bank_type'] }}</td>
                                            <td class="px-2.5 py-2 text-sm text-right text-gray-800 dark:text-gray-200">₹{{ number_format($bank['total_amount'], 2) }}</td>
                                            <td class="px-2.5 py-2 text-sm text-right text-gray-800 dark:text-gray-200">{{ $managementTotalBankAmount > 0 ? number_format(($bank['total_amount'] / $managementTotalBankAmount) * 100, 1) : 0 }}%</td>
                                            <td class="px-2.5 py-2 text-sm text-right text-gray-800 dark:text-gray-200">{{ $bank['total_transactions'] }}</td>
                                            <td class="px-2.5 py-2 text-sm text-right">
                                                <span class="px-2 py-1 rounded bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200">{{ $bank['success_rate'] }}%</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-2.5 py-2 text-center text-sm text-gray-500">No bank data for selected date.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Top Vendors by Paid Amount</h4>
                        <div class="space-y-3 max-h-72 overflow-y-auto">
                            @forelse($topManagementVendors as $vendor)
                                <div class="flex items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-700 pb-3 last:border-b-0">
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $vendor->beneficiary_name ?: 'Unknown' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $vendor->transaction_count }} transaction(s), largest ₹{{ number_format($vendor->largest_amount, 2) }}</div>
                                    </div>
                                    <div class="text-right text-sm font-bold text-blue-600 dark:text-blue-400 shrink-0">₹{{ number_format($vendor->total_amount, 2) }}</div>
                                </div>
                            @empty
                                <div class="text-center py-5 text-gray-500">No vendor payments for selected date.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-3 mb-4">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">High-value Payments</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-2.5 py-1.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Vendor</th>
                                        <th class="px-2.5 py-1.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Bank</th>
                                        <th class="px-2.5 py-1.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Reference</th>
                                        <th class="px-2.5 py-1.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @forelse($highValueTransactions as $txn)
                                        <tr>
                                            <td class="px-2.5 py-2 text-sm text-gray-900 dark:text-gray-100 max-w-56 truncate">{{ $txn->beneficiary_name ?: 'Unknown' }}</td>
                                            <td class="px-2.5 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $txn->file->bank_type ?? '-' }}</td>
                                            <td class="px-2.5 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $txn->payment_ref_no ?: $txn->transaction_id ?: '-' }}</td>
                                            <td class="px-2.5 py-2 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">₹{{ number_format($txn->amount, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-2.5 py-2 text-center text-sm text-gray-500">No high-value payments for selected date.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Top Rejection Reasons</h4>
                        <div class="space-y-3 max-h-72 overflow-y-auto">
                            @forelse($topRejectReasons as $reason)
                                <div>
                                    <div class="flex justify-between gap-3 text-sm mb-1">
                                        <span class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $reason->reason }}</span>
                                        <span class="font-bold text-red-600 dark:text-red-400">{{ $reason->count }}</span>
                                    </div>
                                    <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded">
                                        <div class="h-2 bg-red-500 rounded" style="width: {{ $rejectedCount > 0 ? min(100, round(($reason->count / $rejectedCount) * 100)) : 0 }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-5 text-gray-500">No rejections for selected date.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Trend Chart -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-3 mb-4">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">12-Month Payment Trend</h3>
                <div class="relative h-60">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const percentageLabelPlugin = {
                id: 'percentageLabelPlugin',
                afterDatasetsDraw(chart) {
                    if (!['doughnut', 'pie'].includes(chart.config.type)) {
                        return;
                    }

                    const dataset = chart.data.datasets[0];
                    const total = dataset.data.reduce((sum, value) => sum + Number(value || 0), 0);
                    if (total <= 0) {
                        return;
                    }

                    const { ctx } = chart;
                    ctx.save();
                    ctx.font = '600 12px sans-serif';
                    ctx.fillStyle = '#fff';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';

                    chart.getDatasetMeta(0).data.forEach((arc, index) => {
                        const value = Number(dataset.data[index] || 0);
                        const percentage = (value / total) * 100;
                        if (percentage < 4) {
                            return;
                        }

                        const position = arc.tooltipPosition();
                        ctx.fillText(`${percentage.toFixed(1)}%`, position.x, position.y);
                    });

                    ctx.restore();
                }
            };

            Chart.register(percentageLabelPlugin);
            // Status Chart (Pie)
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusData = @json($statusBreakdown);
            
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['✓ Successful', '✗ Rejected'],
                    datasets: [{
                        data: [statusData['OK'], statusData['REJECTED']],
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.7)',   // Green
                            'rgba(239, 68, 68, 0.7)'    // Red
                        ],
                        borderColor: ['rgb(34, 197, 94)', 'rgb(239, 68, 68)'],
                        borderWidth: 2,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 15, font: { size: 12, weight: 'bold' } }
                        }
                    }
                }
            });

            // Volume Chart (Bar)
            const volumeCtx = document.getElementById('volumeChart').getContext('2d');
            const volumeData = @json($last7Days);
            
            new Chart(volumeCtx, {
                type: 'bar',
                data: {
                    labels: volumeData.map(d => d.date),
                    datasets: [{
                        label: 'Transaction Amount (₹)',
                        data: volumeData.map(d => d.amount),
                        backgroundColor: 'rgba(59, 130, 246, 0.6)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 2,
                        borderRadius: 5,
                        hoverBackgroundColor: 'rgba(37, 99, 235, 0.8)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString('en-IN');
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: { display: true, labels: { font: { size: 11 } } }
                    }
                }
            });

            // Processing Statistics (Line)
            const processingCtx = document.getElementById('processingChart').getContext('2d');
            const processingData = @json($processingStats);
            
            new Chart(processingCtx, {
                type: 'line',
                data: {
                    labels: processingData.map(d => d.date),
                    datasets: [
                        {
                            label: 'Received',
                            data: processingData.map(d => d.received),
                            borderColor: 'rgb(99, 102, 241)',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            borderWidth: 2,
                            pointRadius: 5,
                            pointBackgroundColor: 'rgb(99, 102, 241)',
                            tension: 0.3
                        },
                        {
                            label: 'Processed',
                            data: processingData.map(d => d.processed),
                            borderColor: 'rgb(34, 197, 94)',
                            backgroundColor: 'rgba(34, 197, 94, 0.1)',
                            borderWidth: 2,
                            pointRadius: 5,
                            pointBackgroundColor: 'rgb(34, 197, 94)',
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } },
                    plugins: { legend: { display: true, labels: { font: { size: 11 } } } }
                }
            });

            // Bank Type Distribution (Bar)
            const bankStatusCtx = document.getElementById('bankStatusChart').getContext('2d');
            const bankStatusData = @json($bankTypeDistribution);
            
            new Chart(bankStatusCtx, {
                type: 'bar',
                data: {
                    labels: bankStatusData.map(d => d.bank_type),
                    datasets: [
                        {
                            label: 'Count',
                            data: bankStatusData.map(d => d.count),
                            backgroundColor: 'rgba(168, 85, 247, 0.6)',
                            borderColor: 'rgb(168, 85, 247)',
                            borderWidth: 2,
                            borderRadius: 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: { x: { beginAtZero: true } },
                    plugins: { legend: { display: false } }
                }
            });

            // Last 30 Days Payment Trend
            const last30PaymentCtx = document.getElementById('last30PaymentTrendChart').getContext('2d');
            const last30PaymentData = @json($last30PaymentTrend);

            new Chart(last30PaymentCtx, {
                type: 'line',
                data: {
                    labels: last30PaymentData.map(d => d.date),
                    datasets: [{
                        label: 'Paid Amount',
                        data: last30PaymentData.map(d => d.amount),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.12)',
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.28
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    if (value >= 10000000) {
                                        return '₹' + (value / 10000000).toFixed(1) + 'Cr';
                                    }
                                    if (value >= 100000) {
                                        return '₹' + (value / 100000).toFixed(1) + 'L';
                                    }
                                    return '₹' + value.toLocaleString('en-IN');
                                }
                            }
                        },
                        x: {
                            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const item = last30PaymentData[context.dataIndex];
                                    return '₹' + context.parsed.y.toLocaleString('en-IN') + ' (' + item.count + ' txn)';
                                }
                            }
                        }
                    }
                }
            });

            // Management Bank Contribution Chart
            const managementBankCtx = document.getElementById('managementBankChart').getContext('2d');
            const managementBankData = @json($managementBankPerformance);

            new Chart(managementBankCtx, {
                type: 'bar',
                data: {
                    labels: managementBankData.map(d => d.bank_type),
                    datasets: [
                        {
                            label: 'Paid Amount',
                            data: managementBankData.map(d => d.total_amount),
                            backgroundColor: 'rgba(14, 165, 233, 0.65)',
                            borderColor: 'rgb(14, 165, 233)',
                            borderWidth: 2,
                            borderRadius: 5,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Success %',
                            data: managementBankData.map(d => d.success_rate),
                            type: 'line',
                            borderColor: 'rgb(34, 197, 94)',
                            backgroundColor: 'rgba(34, 197, 94, 0.15)',
                            pointBackgroundColor: 'rgb(34, 197, 94)',
                            tension: 0.35,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString('en-IN');
                                }
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            max: 100,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: { callback: value => value + '%' }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.yAxisID === 'y1') {
                                        return context.dataset.label + ': ' + context.parsed.y + '%';
                                    }
                                    return context.dataset.label + ': ₹' + context.parsed.y.toLocaleString('en-IN');
                                }
                            }
                        }
                    }
                }
            });

            // Payment Bands Chart
            const paymentBandsCtx = document.getElementById('paymentBandsChart').getContext('2d');
            const paymentBandsData = @json($paymentBands);

            new Chart(paymentBandsCtx, {
                type: 'doughnut',
                data: {
                    labels: paymentBandsData.map(d => d.band),
                    datasets: [{
                        data: paymentBandsData.map(d => d.total),
                        backgroundColor: [
                            'rgba(99, 102, 241, 0.7)',
                            'rgba(14, 165, 233, 0.7)',
                            'rgba(34, 197, 94, 0.7)',
                            'rgba(245, 158, 11, 0.7)',
                            'rgba(239, 68, 68, 0.7)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ₹' + context.parsed.toLocaleString('en-IN');
                                }
                            }
                        }
                    }
                }
            });

            // Monthly Trend Chart (Line)
            const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
            const monthlyData = @json($monthlyTrend);

            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyData.map(d => d.month),
                    datasets: [{
                        label: 'Total Payment Amount (₹)',
                        data: monthlyData.map(d => d.total),
                        backgroundColor: 'rgba(245, 158, 11, 0.2)',
                        borderColor: 'rgb(245, 158, 11)',
                        borderWidth: 3,
                        pointBackgroundColor: 'rgb(245, 158, 11)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgb(245, 158, 11)',
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    if (value >= 1000000) {
                                        return '₹' + (value / 1000000).toFixed(1) + 'M';
                                    } else if (value >= 1000) {
                                        return '₹' + (value / 1000).toFixed(1) + 'K';
                                    }
                                    return '₹' + value;
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: { display: true, labels: { font: { size: 12, weight: 'bold' } } },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '₹' + context.parsed.y.toLocaleString('en-IN');
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</x-app-layout>
