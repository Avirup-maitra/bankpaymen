<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
                📊 Dashboard
            </h2>
            <form method="GET" action="{{ route('dashboard') }}" class="flex gap-2">
                <input type="date" name="date" value="{{ $viewDate->format('Y-m-d') }}" class="border rounded px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-medium transition">📅 Go</button>
            </form>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
            Stats for: <span class="font-bold">{{ $viewDate->format('d M Y') }}</span>
            @if($viewDate->isToday()) <span class="text-green-600 dark:text-green-400 font-semibold">(Today)</span> @endif
        </p>
    </x-slot>

    <div class="py-12 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Performance KPIs -->
            <div class="mb-8 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <!-- Success Rate -->
                <div class="bg-gradient-to-br from-green-500 to-green-600 dark:from-green-700 dark:to-green-900 shadow-lg rounded-lg p-4 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-green-100 text-xs font-medium">Success Rate</div>
                            <div class="text-2xl font-bold mt-1">{{ $successRate }}%</div>
                            <div class="text-xs text-green-200 mt-1">{{ $okCount }}/{{ $totalTransactionsViewDate }}</div>
                        </div>
                        <div class="p-1.5 bg-green-400 bg-opacity-30 rounded text-lg">✓</div>
                    </div>
                </div>

                <!-- Total Transactions -->
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-700 dark:to-blue-900 shadow-lg rounded-lg p-4 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-blue-100 text-xs font-medium">Total Transactions</div>
                            <div class="text-2xl font-bold mt-1">{{ $totalTransactionsViewDate }}</div>
                            <div class="text-xs text-blue-200 mt-1">{{ $okCount }} ✓ / {{ $rejectedCount }} ✗</div>
                        </div>
                        <div class="p-1.5 bg-blue-400 bg-opacity-30 rounded text-lg">📊</div>
                    </div>
                </div>

                <!-- Total Amount -->
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-700 dark:to-purple-900 shadow-lg rounded-lg p-4 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-purple-100 text-xs font-medium">Total Amount</div>
                            <div class="text-2xl font-bold mt-1">₹{{ number_format($totalAmountToday, 0) }}</div>
                            <div class="text-xs text-purple-200 mt-1">Avg: ₹{{ number_format($averageAmountToday, 0) }}</div>
                        </div>
                        <div class="p-1.5 bg-purple-400 bg-opacity-30 rounded text-lg">💰</div>
                    </div>
                </div>

                <!-- Files Received -->
                <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 dark:from-indigo-700 dark:to-indigo-900 shadow-lg rounded-lg p-4 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-indigo-100 text-xs font-medium">Files Received</div>
                            <div class="text-2xl font-bold mt-1">{{ $filesReceivedToday }}</div>
                            <div class="text-xs text-indigo-200 mt-1">{{ $filesProcessedToday }} Processed</div>
                        </div>
                        <div class="p-1.5 bg-indigo-400 bg-opacity-30 rounded text-lg">📁</div>
                    </div>
                </div>

                <!-- Bank Types -->
                <div class="bg-gradient-to-br from-orange-500 to-orange-600 dark:from-orange-700 dark:to-orange-900 shadow-lg rounded-lg p-4 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-orange-100 text-xs font-medium">Bank Types</div>
                            <div class="text-lg font-bold mt-1">ICICI: {{ $iciciBankFiles }}</div>
                            <div class="text-sm text-orange-200">SBI: {{ $sbiBankFiles }}</div>
                        </div>
                        <div class="p-1.5 bg-orange-400 bg-opacity-30 rounded text-lg">🏦</div>
                    </div>
                </div>

                <!-- Today Exports -->
                <div class="bg-gradient-to-br from-cyan-500 to-cyan-600 dark:from-cyan-700 dark:to-cyan-900 shadow-lg rounded-lg p-4 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-cyan-100 text-xs font-medium">Today Exports</div>
                            <div class="text-2xl font-bold mt-1">{{ $todayExportsPaid }}</div>
                            @if($todayExportsData)
                                <div class="text-xs text-cyan-200 mt-1">
                                    ✓ {{ $todayExportsData->status }}
                                </div>
                            @else
                                <div class="text-xs text-cyan-300 mt-1">No exports yet</div>
                            @endif
                        </div>
                        <div class="p-1.5 bg-cyan-400 bg-opacity-30 rounded text-lg">📤</div>
                    </div>
                </div>
            </div>

            <!-- Primary Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Status Breakdown (Pie) -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg rounded-xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">📈 Status Distribution</h3>
                    <div class="relative h-64">
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
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg rounded-xl p-6 lg:col-span-2">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">💹 Last 7 Days Volume</h3>
                    <div class="relative h-64">
                        <canvas id="volumeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Secondary Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Processing Statistics -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg rounded-xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">🔄 File Processing (7 Days)</h3>
                    <div class="relative h-64">
                        <canvas id="processingChart"></canvas>
                    </div>
                </div>

                <!-- Bank Status Distribution -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg rounded-xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">🏦 Transaction Status</h3>
                    <div class="relative h-64">
                        <canvas id="bankStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Information Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Top Debit Accounts -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg rounded-xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">💳 Top Debit Account</h3>
                    @if($topDebitAccount)
                        <div class="space-y-2">
                            <div class="flex justify-between items-center pb-3 border-b dark:border-gray-700">
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Account Number</div>
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $topDebitAccount->debit_account_no }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Amount</div>
                                    <div class="font-bold text-green-600 dark:text-green-400">₹{{ number_format($topDebitAccount->total_amount, 2) }}</div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500">No data available</div>
                    @endif
                </div>

                <!-- Top Beneficiaries -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg rounded-xl p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">👥 Top Beneficiaries</h3>
                    @if($topBeneficiaries->count() > 0)
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            @foreach($topBeneficiaries as $beneficiary)
                                <div class="flex justify-between items-center pb-2 border-b dark:border-gray-700 last:border-b-0">
                                    <div class="truncate flex-1">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $beneficiary->beneficiary_name ?? 'Unknown' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $beneficiary->count }} transaction(s)</div>
                                    </div>
                                    <div class="text-right ml-2 flex-shrink-0">
                                        <div class="font-semibold text-blue-600 dark:text-blue-400">₹{{ number_format($beneficiary->total, 2) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500">No transactions</div>
                    @endif
                </div>
            </div>

            <!-- Monthly Trend Chart -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-lg rounded-xl p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">📊 12-Month Payment Trend</h3>
                <div class="relative h-80">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
