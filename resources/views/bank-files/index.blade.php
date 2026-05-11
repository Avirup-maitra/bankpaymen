<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Bank Files') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">File Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Received At</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Rows (OK/Rej)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($files as $file)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $file->original_filename }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $file->received_at->format('Y-m-d H:i') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $file->source_type }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                @if($file->status === 'PROCESSED') bg-green-100 text-green-800 
                                                @elseif($file->status === 'REJECTED') bg-red-100 text-red-800 
                                                @elseif($file->status === 'PARTIAL') bg-yellow-100 text-yellow-800 
                                                @else bg-blue-100 text-blue-800 @endif">
                                                {{ $file->status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            {{ $file->total_rows }} 
                                            <span class="text-green-600">({{ $file->success_rows }})</span> / 
                                            <span class="text-red-600">({{ $file->rejected_rows }})</span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ number_format($file->total_amount, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('bank-files.show', $file) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-2">View</a>
                                            
                                            @if($file->rejected_rows > 0)
                                                <a href="{{ route('bank-files.download-rejects', $file) }}" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-600">Rejects CSV</a>
                                            @endif
                                            
                                            @if($file->error_summary)
                                                 <a href="{{ route('bank-files.download-error-summary', $file) }}" class="text-orange-600 hover:text-orange-900 ml-2">Error Log</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No files found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4">
                        {{ $files->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
