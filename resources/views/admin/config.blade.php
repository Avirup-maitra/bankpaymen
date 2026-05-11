<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('System Configuration') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
             @if(session('status'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form action="{{ route('admin.config.update') }}" method="POST">
                    @csrf
                    
                    <h3 class="font-bold text-lg mb-4 dark:text-gray-200">File Paths (Environment Variables)</h3>
                    
                    @foreach($config as $key => $value)
                    <div class="mb-4">
                        <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">{{ $key }}</label>
                        <input type="text" value="{{ $value }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:shadow-outline bg-gray-100 dark:bg-gray-700" readonly>
                        <p class="text-xs text-gray-500 mt-1">Update validation logic in .env or via deployment.</p>
                    </div>
                    @endforeach

                    <div class="mt-4 border-t pt-4 dark:border-gray-700">
                         <p class="text-sm text-yellow-600 dark:text-yellow-400 mb-2">Configuration is currently read-only in this demo. Update .env file to change paths.</p>
                         <button type="submit" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded" disabled>
                            Save Changes (Disabled)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
