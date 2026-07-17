<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Bulk Upload Summary') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-6">
                        <div>
                            <h1 class="text-2xl font-bold">Bulk Upload Processing</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Session: <span class="font-mono break-all">{{ $sessionId }}</span></p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Bank Type: <span class="font-semibold">{{ $uploadData['bank_type'] ?? 'Unknown' }}</span></p>
                        </div>
                        <div class="flex gap-3">
                            <a href="{{ route('bulk-upload.index', ['session_id' => $sessionId]) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-indigo-700">Monitor</a>
                            <a href="{{ route('bulk-upload.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-blue-700">Upload More</a>
                        </div>
                    </div>

                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <span id="overallStatus" class="text-sm font-semibold text-gray-700 dark:text-gray-300">Loading...</span>
                            <span id="overallPercentage" class="text-sm font-bold text-blue-600">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                            <div id="overallProgressBar" class="bg-blue-600 h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                        <div class="p-4 bg-blue-50 dark:bg-gray-700 rounded-lg border-l-4 border-blue-500">
                            <div class="text-sm font-semibold text-blue-700 dark:text-blue-300">Files Received</div>
                            <div id="totalFiles" class="text-2xl font-bold">0</div>
                        </div>
                        <div class="p-4 bg-indigo-50 dark:bg-gray-700 rounded-lg border-l-4 border-indigo-500">
                            <div class="text-sm font-semibold text-indigo-700 dark:text-indigo-300">Completed Files</div>
                            <div id="filesComplete" class="text-2xl font-bold">0</div>
                        </div>
                        <div class="p-4 bg-amber-50 dark:bg-gray-700 rounded-lg border-l-4 border-amber-500">
                            <div class="text-sm font-semibold text-amber-700 dark:text-amber-300">Processing</div>
                            <div id="filesProcessing" class="text-2xl font-bold">0</div>
                        </div>
                        <div class="p-4 bg-emerald-50 dark:bg-gray-700 rounded-lg border-l-4 border-emerald-500">
                            <div class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Rows OK</div>
                            <div id="successRows" class="text-2xl font-bold">0</div>
                        </div>
                        <div class="p-4 bg-red-50 dark:bg-gray-700 rounded-lg border-l-4 border-red-500">
                            <div class="text-sm font-semibold text-red-700 dark:text-red-300">Rows Rejected</div>
                            <div id="rejectedRows" class="text-2xl font-bold">0</div>
                        </div>
                        <div class="p-4 bg-slate-50 dark:bg-gray-700 rounded-lg border-l-4 border-slate-500">
                            <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">Total Rows</div>
                            <div id="totalRows" class="text-2xl font-bold">0</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg lg:col-span-2">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold">File Stage Progress</h3>
                            <span id="lastUpdated" class="text-xs text-gray-500"></span>
                        </div>
                        <div id="filesContainer" class="space-y-3 max-h-[36rem] overflow-y-auto">
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">Waiting for processing data...</div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <h3 class="text-lg font-semibold mb-4">Reject Reasons</h3>
                            <div id="rejectReasons" class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                No rejects found.
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <h3 class="text-lg font-semibold mb-4">Rejected Files</h3>
                            <div id="failedFiles" class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                No rejected files found.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const sessionId = @json($sessionId);
        const bulkStatsUrl = @json(route('api.bulk-upload-stats'));
        let pollInterval = null;

        function numberFormat(value, decimals = 0) {
            return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        }

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value || '';
            return div.innerHTML;
        }

        function stageLabel(stage) {
            const labels = {
                queued: 'Queued',
                opening: 'Opening file',
                detecting_reader: 'Detecting reader',
                reading: 'Fast read',
                parsing_html: 'Parsing HTML Excel',
                parsing_text: 'Parsing text',
                validating: 'Validating rows',
                inserting: 'Inserting rows',
                processing_rows: 'Processing rows',
                excel_fallback: 'Excel fallback',
                reading_sbi_txt: 'Reading SBI TXT',
                finalizing: 'Finalizing',
                completed: 'Completed',
                failed: 'Failed',
                failed_or_rejected: 'Rejected'
            };

            return labels[stage] || stage || 'Waiting';
        }

        function statusClass(status) {
            if (status === 'PROCESSED') return 'bg-green-100 text-green-800';
            if (status === 'PARTIAL') return 'bg-yellow-100 text-yellow-800';
            if (status === 'REJECTED') return 'bg-red-100 text-red-800';
            if (status === 'PROCESSING') return 'bg-blue-100 text-blue-800';
            return 'bg-gray-100 text-gray-800';
        }

        async function fetchStats() {
            try {
                const response = await fetch(`${bulkStatsUrl}?session_id=${encodeURIComponent(sessionId)}`);
                const data = await response.json();

                if (!data.summary || !data.file_progresses) return;

                const processingCount = data.file_progresses.filter((file) => file.status === 'PROCESSING').length;
                const completed = data.summary.files_completed || 0;
                const total = data.summary.total_files || 0;

                document.getElementById('overallPercentage').textContent = `${data.summary.overall_percentage}%`;
                document.getElementById('overallProgressBar').style.width = `${data.summary.overall_percentage}%`;
                document.getElementById('overallStatus').textContent = `${completed} of ${total} files completed`;
                document.getElementById('totalFiles').textContent = numberFormat(total);
                document.getElementById('filesComplete').textContent = numberFormat(completed);
                document.getElementById('filesProcessing').textContent = numberFormat(processingCount);
                document.getElementById('successRows').textContent = numberFormat(data.summary.total_success);
                document.getElementById('rejectedRows').textContent = numberFormat(data.summary.total_rejected);
                document.getElementById('totalRows').textContent = numberFormat(data.summary.total_rows_processed);
                document.getElementById('lastUpdated').textContent = `Updated ${new Date().toLocaleTimeString()}`;

                renderFiles(data.file_progresses);
                renderRejectReasons(data.reject_reasons || []);
                renderFailedFiles(data.failed_files || []);

                if (data.ready && pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
            } catch (error) {
                console.error('Error fetching bulk stats:', error);
            }
        }

        function renderFiles(files) {
            const container = document.getElementById('filesContainer');
            if (!files.length) {
                container.innerHTML = '<div class="text-center py-8 text-gray-500 dark:text-gray-400">No file data found.</div>';
                return;
            }

            container.innerHTML = files.map((file, index) => {
                const percentage = Number(file.percentage || 0);
                const message = file.error_summary || file.stage_message || '';
                return `
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-700">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500">${index + 1}</span>
                                    <p class="font-semibold truncate">${escapeHtml(file.filename)}</p>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(stageLabel(file.stage))}: ${escapeHtml(message)}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Rows ${numberFormat(file.total_rows)} | OK ${numberFormat(file.success_rows)} | Rejected ${numberFormat(file.rejected_rows)} | Amount ${numberFormat(file.total_amount, 2)}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold ${statusClass(file.status)}">${escapeHtml(file.status)}</span>
                                <span class="text-sm font-semibold text-blue-600">${percentage}%</span>
                            </div>
                        </div>
                        <div class="mt-3 w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 overflow-hidden">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width:${percentage}%"></div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderRejectReasons(reasons) {
            const box = document.getElementById('rejectReasons');
            if (!reasons.length) {
                box.innerHTML = 'No rejects found.';
                return;
            }

            box.innerHTML = reasons.map((row) => `
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 dark:border-gray-700 pb-2">
                    <span>${escapeHtml(row.reason || 'Processing error')}</span>
                    <span class="font-semibold text-red-600">${numberFormat(row.count)}</span>
                </div>
            `).join('');
        }

        function renderFailedFiles(files) {
            const box = document.getElementById('failedFiles');
            if (!files.length) {
                box.innerHTML = 'No rejected files found.';
                return;
            }

            box.innerHTML = files.map((file) => `
                <div class="border-b border-gray-100 dark:border-gray-700 pb-2">
                    <div class="font-medium text-gray-900 dark:text-gray-100 truncate">${escapeHtml(file.original_filename)}</div>
                    <div class="text-xs">Rows ${numberFormat(file.total_rows)} | Rejected ${numberFormat(file.rejected_rows)}</div>
                    ${file.error_summary ? `<div class="text-xs text-red-600 mt-1">${escapeHtml(file.error_summary)}</div>` : ''}
                </div>
            `).join('');
        }

        fetchStats();
        pollInterval = setInterval(fetchStats, 5000);
    </script>
</x-app-layout>
