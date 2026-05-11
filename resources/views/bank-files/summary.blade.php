<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            📊 Bulk Upload Summary
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto px-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
                <!-- Header -->
                <div class="mb-8 pb-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Bulk Upload Progress</h1>
                            <p class="text-gray-600 dark:text-gray-400">
                                Bank Type: <span class="font-semibold text-blue-600">{{ $uploadData['bank_type'] ?? 'Unknown' }}</span>
                            </p>
                        </div>
                        <div class="text-6xl">📊</div>
                    </div>
                </div>

                <!-- Overall Progress -->
                <div class="mb-8 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-700 dark:to-gray-800 rounded-lg border border-blue-200 dark:border-gray-600">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Overall Progress</h3>
                        <span id="overallPercentage" class="text-3xl font-bold text-blue-600">0%</span>
                    </div>
                    <div class="w-full bg-gray-300 dark:bg-gray-600 rounded-full h-4 overflow-hidden">
                        <div id="overallProgressBar" class="bg-gradient-to-r from-blue-500 to-indigo-600 h-4 rounded-full transition-all duration-300 ease-out" style="width: 0%"></div>
                    </div>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                        <span id="filesComplete">0</span> of <span id="filesTotal">{{ $uploadData['files_uploaded'] ?? 0 }}</span> files processed
                    </p>
                </div>

                <!-- Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <!-- Files Uploaded -->
                    <div class="bg-blue-50 dark:bg-gray-700 p-4 rounded-lg border-l-4 border-blue-500">
                        <p class="text-blue-600 dark:text-blue-400 font-semibold text-sm">Files Uploaded</p>
                        <p class="text-3xl font-bold text-blue-600 dark:text-blue-300 mt-1">{{ $uploadData['files_uploaded'] ?? 0 }}</p>
                    </div>

                    <!-- Transactions Processed -->
                    <div class="bg-green-50 dark:bg-gray-700 p-4 rounded-lg border-l-4 border-green-500">
                        <p class="text-green-600 dark:text-green-400 font-semibold text-sm">Transactions Processed</p>
                        <p id="totalTransactions" class="text-3xl font-bold text-green-600 dark:text-green-300 mt-1">0</p>
                    </div>

                    <!-- Successful Transactions -->
                    <div class="bg-emerald-50 dark:bg-gray-700 p-4 rounded-lg border-l-4 border-emerald-500">
                        <p class="text-emerald-600 dark:text-emerald-400 font-semibold text-sm">Successful</p>
                        <p id="successTransactions" class="text-3xl font-bold text-emerald-600 dark:text-emerald-300 mt-1">0</p>
                    </div>

                    <!-- Rejected Transactions -->
                    <div class="bg-red-50 dark:bg-gray-700 p-4 rounded-lg border-l-4 border-red-500">
                        <p class="text-red-600 dark:text-red-400 font-semibold text-sm">Rejected</p>
                        <p id="rejectedTransactions" class="text-3xl font-bold text-red-600 dark:text-red-300 mt-1">0</p>
                    </div>
                </div>

                <!-- Individual File Progress -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">File Processing Details</h3>
                    <div id="filesContainer" class="space-y-3 max-h-96 overflow-y-auto">
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <p class="animate-pulse">⏳ Waiting for files to start processing...</p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <button id="refreshBtn" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                        🔄 Refresh
                    </button>
                    <a href="{{ route('bank-files.index') }}" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white font-semibold py-3 px-6 rounded-lg text-center transition duration-200">
                        📁 Back to Files
                    </a>
                </div>

                <!-- Processing Complete Message -->
                <div id="completeMessage" class="hidden mt-6 p-4 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
                    <p class="text-green-700 dark:text-green-200 text-center font-semibold">✅ Processing complete!</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const sessionId = @json($sessionId);
        const filesTotal = {{ $uploadData['files_uploaded'] ?? 0 }};
        let isProcessing = true;

        async function fetchStats() {
            try {
                const response = await fetch(`/api/bank-files/bulk-stats?session_id=${sessionId}`);
                const data = await response.json();

                if (!data.file_progresses) {
                    return;
                }

                // Update overall progress
                const filesComplete = data.file_progresses.length;
                document.getElementById('filesComplete').textContent = filesComplete;
                document.getElementById('filesTotal').textContent = filesTotal;
                document.getElementById('overallPercentage').textContent = data.summary.overall_percentage + '%';
                document.getElementById('overallProgressBar').style.width = data.summary.overall_percentage + '%';

                // Update summary statistics
                document.getElementById('totalTransactions').textContent = data.summary.total_rows_processed;
                document.getElementById('successTransactions').textContent = data.summary.total_success;
                document.getElementById('rejectedTransactions').textContent = data.summary.total_rejected;

                // Update individual file progress
                const container = document.getElementById('filesContainer');
                if (data.file_progresses.length > 0) {
                    let html = '';
                    data.file_progresses.forEach((file, index) => {
                        const percentage = file.percentage || 0;
                        html += `
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900 dark:text-white truncate">${index + 1}. ${file.filename}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Rows: ${file.total_rows} | Success: ${file.success_rows} | Rejected: ${file.rejected_rows}</p>
                                    </div>
                                    <span class="ml-4 font-bold text-sm text-blue-600 dark:text-blue-400">${percentage}%</span>
                                </div>
                                <div class="w-full bg-gray-300 dark:bg-gray-600 rounded-full h-2 overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full" style="width: ${percentage}%"></div>
                                </div>
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                }

                // Check if processing is complete
                if (data.ready) {
                    isProcessing = false;
                    document.getElementById('completeMessage').classList.remove('hidden');
                    clearInterval(pollInterval);
                }
            } catch (error) {
                console.error('Error fetching stats:', error);
            }
        }

        // Poll for updates
        let pollInterval = setInterval(fetchStats, 2000);

        // Manual refresh button
        document.getElementById('refreshBtn').addEventListener('click', fetchStats);

        // Initial fetch
        fetchStats();

        // Stop polling after 60 minutes
        setTimeout(() => clearInterval(pollInterval), 60 * 60 * 1000);
    </script>
</x-app-layout>
