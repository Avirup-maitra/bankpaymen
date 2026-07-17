<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Bulk Upload') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Bulk Upload Monitor</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Live processing status for uploaded SBI and ICICI file batches.</p>
                </div>
                <a href="{{ route('bulk-upload.create') }}" class="inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Upload Files
                </a>
            </div>

            @if($selectedSession)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-6">
                            <div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Selected Session</div>
                                <div class="font-semibold break-all">{{ $selectedSession->session_id }}</div>
                                <div class="text-sm mt-1">{{ $selectedSession->bank_type }} | {{ $selectedSession->created_at->format('Y-m-d H:i') }} | {{ $selectedSession->status }}</div>
                            </div>
                            <a href="{{ route('bank-files.summary', ['session_id' => $selectedSession->session_id]) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Open detailed progress</a>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                            <div class="p-4 bg-blue-50 dark:bg-gray-700 rounded-lg border-l-4 border-blue-500">
                                <div class="text-sm text-blue-700 dark:text-blue-300 font-semibold">Files Received</div>
                                <div id="totalFiles" class="text-2xl font-bold">{{ $selectedSession->total_files_uploaded }}</div>
                            </div>
                            <div class="p-4 bg-indigo-50 dark:bg-gray-700 rounded-lg border-l-4 border-indigo-500">
                                <div class="text-sm text-indigo-700 dark:text-indigo-300 font-semibold">Files Done</div>
                                <div id="filesDone" class="text-2xl font-bold">{{ $selectedSession->files_processed + $selectedSession->files_failed }}</div>
                            </div>
                            <div class="p-4 bg-emerald-50 dark:bg-gray-700 rounded-lg border-l-4 border-emerald-500">
                                <div class="text-sm text-emerald-700 dark:text-emerald-300 font-semibold">Rows OK</div>
                                <div id="rowsOk" class="text-2xl font-bold">{{ $selectedSession->total_rows_success }}</div>
                            </div>
                            <div class="p-4 bg-red-50 dark:bg-gray-700 rounded-lg border-l-4 border-red-500">
                                <div class="text-sm text-red-700 dark:text-red-300 font-semibold">Rows Rejected</div>
                                <div id="rowsRejected" class="text-2xl font-bold">{{ $selectedSession->total_rows_rejected }}</div>
                            </div>
                            <div class="p-4 bg-slate-50 dark:bg-gray-700 rounded-lg border-l-4 border-slate-500">
                                <div class="text-sm text-slate-700 dark:text-slate-300 font-semibold">Amount</div>
                                <div id="totalAmount" class="text-2xl font-bold">{{ number_format($selectedSession->total_amount_processed, 2) }}</div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Overall Progress</span>
                                <span id="overallPercentage" class="text-sm font-semibold text-blue-600">0%</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                <div id="overallProgressBar" class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-semibold mb-3">Reject Reasons</h4>
                                <div id="rejectReasons" class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Count</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @forelse($rejectReasons as $reason)
                                                <tr>
                                                    <td class="px-4 py-2 text-sm">{{ $reason->reason }}</td>
                                                    <td class="px-4 py-2 text-sm text-right">{{ $reason->count }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="2" class="px-4 py-4 text-sm text-center text-gray-500">No rejects found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold mb-3">Latest Rejected Rows</h4>
                                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg max-h-80">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">File</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Row</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @forelse($recentRejects as $reject)
                                                <tr>
                                                    <td class="px-4 py-2 text-sm max-w-48 truncate">{{ $reject->original_filename }}</td>
                                                    <td class="px-4 py-2 text-sm">{{ $reject->row_number }}</td>
                                                    <td class="px-4 py-2 text-sm">{{ $reject->reject_reason }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="3" class="px-4 py-4 text-sm text-center text-gray-500">No rejected rows found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h4 class="font-semibold mb-3">Recent File Progress</h4>
                            <div id="filesContainer" class="space-y-2 max-h-96 overflow-y-auto">
                                <div class="text-sm text-gray-500">Loading file progress...</div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 text-gray-700 dark:text-gray-300">
                    No bulk upload sessions found.
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h4 class="font-semibold mb-4">Bulk Upload Sessions</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bank</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Files</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rows OK/Rej</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($sessions as $session)
                                    <tr>
                                        <td class="px-4 py-2 text-sm">{{ $session->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="px-4 py-2 text-sm">{{ $session->bank_type }}</td>
                                        <td class="px-4 py-2 text-sm">{{ $session->status }}</td>
                                        <td class="px-4 py-2 text-sm">{{ $session->files_processed + $session->files_failed }}/{{ $session->total_files_uploaded }}</td>
                                        <td class="px-4 py-2 text-sm"><span class="text-green-600">{{ $session->total_rows_success }}</span> / <span class="text-red-600">{{ $session->total_rows_rejected }}</span></td>
                                        <td class="px-4 py-2 text-sm"><a class="text-indigo-600 dark:text-indigo-400 hover:underline" href="{{ route('bulk-upload.index', ['session_id' => $session->session_id]) }}">Monitor</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-4 text-sm text-center text-gray-500">No sessions found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $sessions->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($selectedSession)
        <script>
            const sessionId = @json($selectedSession->session_id);
            const bulkStatsUrl = @json(route('api.bulk-upload-stats'));

            function numberFormat(value, decimals = 0) {
                return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            }

            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = value || '';
                return div.innerHTML;
            }

            async function fetchBulkStats() {
                const response = await fetch(`${bulkStatsUrl}?session_id=${encodeURIComponent(sessionId)}`);
                if (!response.ok) return;

                const data = await response.json();
                document.getElementById('totalFiles').textContent = numberFormat(data.summary.total_files);
                document.getElementById('filesDone').textContent = numberFormat(data.summary.files_completed);
                document.getElementById('rowsOk').textContent = numberFormat(data.summary.total_success);
                document.getElementById('rowsRejected').textContent = numberFormat(data.summary.total_rejected);
                document.getElementById('overallPercentage').textContent = `${data.summary.overall_percentage}%`;
                document.getElementById('overallProgressBar').style.width = `${data.summary.overall_percentage}%`;

                const totalAmount = data.file_progresses.reduce((sum, file) => sum + Number(file.total_amount || 0), 0);
                document.getElementById('totalAmount').textContent = numberFormat(totalAmount, 2);

                const rejectBox = document.getElementById('rejectReasons');
                if (data.reject_reasons && data.reject_reasons.length) {
                    rejectBox.innerHTML = `<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"><thead class="bg-gray-50 dark:bg-gray-700"><tr><th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reason</th><th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Count</th></tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-700">${data.reject_reasons.map((row) => `<tr><td class="px-4 py-2 text-sm">${escapeHtml(row.reason || 'Processing error')}</td><td class="px-4 py-2 text-sm text-right">${numberFormat(row.count)}</td></tr>`).join('')}</tbody></table>`;
                }

                const container = document.getElementById('filesContainer');
                if (data.file_progresses && data.file_progresses.length) {
                    container.innerHTML = data.file_progresses.map((file) => {
                        const done = file.status === 'RECEIVED' ? 0 : 100;
                        return `<div class="p-3 border border-gray-200 dark:border-gray-700 rounded-lg"><div class="flex items-center justify-between gap-3"><div class="min-w-0"><div class="font-medium truncate">${escapeHtml(file.filename)}</div><div class="text-xs text-gray-500">${escapeHtml(file.status)} | Rows ${numberFormat(file.total_rows)} | OK ${numberFormat(file.success_rows)} | Rejected ${numberFormat(file.rejected_rows)}</div></div><div class="text-sm font-semibold text-blue-600">${done}%</div></div><div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2"><div class="bg-blue-600 h-2 rounded-full" style="width:${done}%"></div></div></div>`;
                    }).join('');
                }
            }

            fetchBulkStats();
            setInterval(fetchBulkStats, 3000);
        </script>
    @endif
</x-app-layout>
