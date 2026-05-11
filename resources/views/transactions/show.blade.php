<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Transaction Details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                <div class="mb-6 flex justify-between">
                     <h3 class="text-lg font-bold">Transaction Information</h3>
                     <div>
                         <span class="px-2 py-1 rounded text-white {{ $bankTransaction->import_status === 'OK' ? 'bg-green-500' : 'bg-red-500' }}">
                             {{ $bankTransaction->import_status }}
                         </span>
                     </div>
                </div>

                @if($bankTransaction->reject_reason)
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    <strong>Reject Reason:</strong> {{ $bankTransaction->reject_reason }}
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Standard Fields -->
                    <div class="border p-4 rounded dark:border-gray-600">
                        <h4 class="font-bold mb-2 border-b pb-1 dark:border-gray-600">Core Data</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="font-semibold">Amount:</div>
                            <div>{{ number_format($bankTransaction->amount, 2) }}</div>
                            
                            <div class="font-semibold">Transaction Date:</div>
                            <div>{{ $bankTransaction->transaction_date ? $bankTransaction->transaction_date->format('Y-m-d') : '-' }}</div>
                            
                            <div class="font-semibold">Liquidation Date:</div>
                            <div>{{ $bankTransaction->liquidation_date ? $bankTransaction->liquidation_date->format('Y-m-d') : '-' }}</div>
                            
                            <div class="font-semibold">Payment Ref:</div>
                            <div>{{ $bankTransaction->payment_ref_no }}</div>
                            
                            <div class="font-semibold">Customer Ref:</div>
                            <div>{{ $bankTransaction->customer_ref_no }}</div>
                            
                            <div class="font-semibold">Bank Status:</div>
                            <div>{{ $bankTransaction->bank_status }}</div>
                        </div>
                    </div>

                    <div class="border p-4 rounded dark:border-gray-600">
                        <h4 class="font-bold mb-2 border-b pb-1 dark:border-gray-600">Beneficiary</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="font-semibold">Name:</div>
                            <div>{{ $bankTransaction->beneficiary_name }}</div>
                            
                            <div class="font-semibold">Account:</div>
                            <div>{{ $bankTransaction->beneficiary_account_no }}</div>
                            
                            <div class="font-semibold">IFSC:</div>
                            <div>{{ $bankTransaction->ifsc }}</div>
                            
                            <div class="font-semibold">Debit Account:</div>
                            <div>{{ $bankTransaction->debit_account_no }}</div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 border p-4 rounded dark:border-gray-600">
                    <h4 class="font-bold mb-2 border-b pb-1 dark:border-gray-600">Full Raw Data (JSON Payload)</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @if($bankTransaction->payload_json)
                                    @foreach($bankTransaction->payload_json as $key => $value)
                                    <tr>
                                        <td class="px-4 py-2 font-medium bg-gray-50 dark:bg-gray-900 w-1/3">{{ $key }}</td>
                                        <td class="px-4 py-2">{{ is_array($value) ? json_encode($value) : $value }}</td>
                                    </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="2" class="p-4">No JSON payload available.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-6">
                    <a href="{{ route('bank-files.show', $bankTransaction->bank_file_id) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">
                        &larr; Back to File
                    </a>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
