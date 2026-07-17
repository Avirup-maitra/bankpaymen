<x-app-layout>
    @php($maxFileUploads = (int) ini_get('max_file_uploads'))
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Upload Files') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                @if ($errors->any())
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('bulk-upload.store') }}" method="POST" enctype="multipart/form-data" id="bulkUploadForm">
                    @csrf
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="bank_type">
                            Select Bank First
                        </label>
                        <select name="bank_type" id="bank_type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:shadow-outline dark:bg-gray-700 dark:border-gray-600" required>
                            <option value="ICICI">ICICI Bank - HTML type Excel / XLS / XLSX / CSV</option>
                            <option value="SBI">SBI Bank - TXT files</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2" for="files">
                            Select Bulk Files
                        </label>
                        <input type="file" name="files[]" id="files" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 leading-tight focus:outline-none focus:shadow-outline dark:bg-gray-700 dark:border-gray-600" required accept=".xlsx,.xls,.csv,.html,.htm" multiple>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">ICICI accepts HTML type Excel, XLS, XLSX, CSV. SBI accepts TXT. You can select many files together.</p>
                        <p id="fileCountMessage" class="text-sm mt-2 text-gray-600 dark:text-gray-300"></p>
                        @if($maxFileUploads > 0)
                            <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">This page automatically uploads large selections in safe chunks of up to {{ min($maxFileUploads ?: 20, 20) }} files per request while keeping one bulk session.</p>
                        @endif
                    </div>

                    <div id="uploadProgressPanel" class="hidden mb-4 p-4 bg-blue-50 dark:bg-gray-700 rounded-lg border border-blue-200 dark:border-gray-600">
                        <div class="flex items-center justify-between mb-2">
                            <span id="uploadProgressText" class="text-sm font-semibold text-blue-800 dark:text-blue-200">Preparing upload...</span>
                            <span id="uploadProgressPercent" class="text-sm font-bold text-blue-700 dark:text-blue-300">0%</span>
                        </div>
                        <div class="w-full bg-blue-200 dark:bg-gray-600 rounded-full h-3 overflow-hidden">
                            <div id="uploadProgressBar" class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <button id="uploadButton" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline disabled:opacity-60" type="submit">
                            Upload Bulk Files
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
    <script>
        const form = document.getElementById('bulkUploadForm');
        const bankType = document.getElementById('bank_type');
        const files = document.getElementById('files');
        const fileCountMessage = document.getElementById('fileCountMessage');
        const uploadButton = document.getElementById('uploadButton');
        const uploadProgressPanel = document.getElementById('uploadProgressPanel');
        const uploadProgressText = document.getElementById('uploadProgressText');
        const uploadProgressPercent = document.getElementById('uploadProgressPercent');
        const uploadProgressBar = document.getElementById('uploadProgressBar');
        const maxFileUploads = @json($maxFileUploads);
        const chunkSize = Math.max(1, Math.min(maxFileUploads || 20, 20));
        let isUploading = false;

        function setFileAccept() {
            files.value = '';
            files.accept = bankType.value === 'SBI' ? '.txt' : '.xlsx,.xls,.csv,.html,.htm';
        }

        function updateFileCount() {
            const count = files.files.length;
            if (!count) {
                fileCountMessage.textContent = '';
                fileCountMessage.className = 'text-sm mt-2 text-gray-600 dark:text-gray-300';
                return;
            }

            const chunkCount = Math.ceil(count / chunkSize);
            fileCountMessage.textContent = `${count} file(s) selected. They will upload automatically in ${chunkCount} safe request(s), ${chunkSize} file(s) at a time.`;
            fileCountMessage.className = 'text-sm mt-2 text-green-700 dark:text-green-300';
        }

        function setUploadProgress(done, total, label) {
            const percentage = total > 0 ? Math.round((done / total) * 100) : 0;
            uploadProgressPanel.classList.remove('hidden');
            uploadProgressText.textContent = label || `${done} of ${total} files uploaded`;
            uploadProgressPercent.textContent = `${percentage}%`;
            uploadProgressBar.style.width = `${percentage}%`;
        }

        async function uploadChunk(chunk, sessionId, chunkIndex, totalChunks, totalFiles) {
            const formData = new FormData();
            formData.append('_token', form.querySelector('input[name="_token"]').value);
            formData.append('bank_type', bankType.value);
            formData.append('total_expected_files', totalFiles);
            formData.append('chunk_index', chunkIndex + 1);
            formData.append('total_chunks', totalChunks);

            if (sessionId) {
                formData.append('session_id', sessionId);
            }

            chunk.forEach((file) => formData.append('files[]', file));

            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
                const message = data.message || Object.values(data.errors || {}).flat().join(' ') || 'Upload failed.';
                throw new Error(message);
            }

            return data;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const selectedFiles = Array.from(files.files);
            if (!selectedFiles.length) {
                fileCountMessage.textContent = 'Please select files first.';
                fileCountMessage.className = 'text-sm mt-2 text-red-700 dark:text-red-300 font-semibold';
                return;
            }

            uploadButton.disabled = true;
            bankType.disabled = true;
            files.disabled = true;
            isUploading = true;

            let sessionId = null;
            let summaryUrl = null;
            let uploaded = 0;
            const chunks = [];

            for (let i = 0; i < selectedFiles.length; i += chunkSize) {
                chunks.push(selectedFiles.slice(i, i + chunkSize));
            }

            try {
                setUploadProgress(0, selectedFiles.length, `Starting upload of ${selectedFiles.length} files...`);

                for (let i = 0; i < chunks.length; i++) {
                    setUploadProgress(uploaded, selectedFiles.length, `Uploading chunk ${i + 1} of ${chunks.length}...`);
                    const data = await uploadChunk(chunks[i], sessionId, i, chunks.length, selectedFiles.length);
                    sessionId = data.session_id;
                    summaryUrl = data.summary_url;
                    uploaded += chunks[i].length;
                    setUploadProgress(uploaded, selectedFiles.length, `${uploaded} of ${selectedFiles.length} files uploaded. Processing has started.`);
                }

                setUploadProgress(selectedFiles.length, selectedFiles.length, 'Upload complete. Opening processing summary...');
                isUploading = false;
                window.location.href = summaryUrl;
            } catch (error) {
                isUploading = false;
                uploadButton.disabled = false;
                bankType.disabled = false;
                files.disabled = false;
                fileCountMessage.textContent = error.message;
                fileCountMessage.className = 'text-sm mt-2 text-red-700 dark:text-red-300 font-semibold';
                setUploadProgress(uploaded, selectedFiles.length, `Upload stopped after ${uploaded} file(s).`);
            }
        });

        bankType.addEventListener('change', () => {
            setFileAccept();
            updateFileCount();
        });
        files.addEventListener('change', updateFileCount);
        window.addEventListener('beforeunload', (event) => {
            if (!isUploading) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });
        setFileAccept();
    </script>
</x-app-layout>
