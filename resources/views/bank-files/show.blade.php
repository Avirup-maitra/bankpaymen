<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('File Details') }}: {{ $bankFile->original_filename }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h3 class="text-lg font-medium mb-2">Metadata</h3>
                        <p><span class="font-bold">ID:</span> {{ $bankFile->id }}</p>
                        <p><span class="font-bold">Source:</span> {{ $bankFile->source_type }}</p>
                        <p><span class="font-bold">Received:</span> {{ $bankFile->received_at }}</p>
                        <p><span class="font-bold">Processed:</span> {{ $bankFile->processed_at }}</p>
                        <p><span class="font-bold">Uploaded By:</span> {{ $bankFile->uploader->name ?? 'System' }}</p>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium mb-2">Statistics</h3>
                        <p><span class="font-bold">Status:</span> {{ $bankFile->status }}</p>
                        <p><span class="font-bold">Total Rows:</span> {{ $bankFile->total_rows }}</p>
                        <p><span class="font-bold">Success:</span> <span class="text-green-600">{{ $bankFile->success_rows }}</span></p>
                        <p><span class="font-bold">Rejected:</span> <span class="text-red-600">{{ $bankFile->rejected_rows }}</span></p>
                        <p><span class="font-bold">Total Amount:</span> {{ number_format($bankFile->total_amount, 2) }}</p>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200 dark:border-gray-700 flex gap-4">
                    @if($bankFile->rejected_rows > 0)
                        <a href="{{ route('bank-files.download-rejects', $bankFile) }}" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Download Rejects CSV
                        </a>
                    @endif
                    
                    @if($bankFile->error_summary)
                         <a href="{{ route('bank-files.download-error-summary', $bankFile) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:bg-gray-700 transition ease-in-out duration-150">
                            Download Error Log
                        </a>
                    @endif
                    
                     <a href="{{ route('transactions.index', ['file_id' => $bankFile->id]) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:bg-indigo-700 transition ease-in-out duration-150">
                        View Transactions
                    </a>
                </div>
            </div>

            <!-- Error Summary Text Display -->
            @if($bankFile->error_summary)
            <div class="bg-red-50 dark:bg-red-900 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-red-800 dark:text-red-200 mb-2">Processing Error Summary</h3>
                <pre class="whitespace-pre-wrap text-sm text-red-700 dark:text-red-300">{{ $bankFile->error_summary }}</pre>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
