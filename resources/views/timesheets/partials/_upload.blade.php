{{-- Flash messages (shown FIRST so user always sees them) --}}
@if(session('upload_success'))
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded text-sm" id="uploadFlash">
        {{ session('upload_success') }}
    </div>
@endif

@if(session('upload_error'))
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-sm" id="uploadFlash">
        {{ session('upload_error') }}
    </div>
@endif

@if(session('upload_warnings'))
    <div class="mb-4 bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded text-sm" id="uploadFlash">
        <p class="font-medium mb-1">Warnings:</p>
        <ul class="list-disc list-inside text-xs">
            @foreach(session('upload_warnings') as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Attendance Upload Section (PDF primary, Excel fallback) --}}
@if(in_array($timesheet->status, ['draft', 'rejected_l1', 'rejected_l2']))
<div class="bg-white shadow-sm sm:rounded-lg mb-4 p-4"
     x-data="attendanceUpload()"
     x-init="scrollToFlash()">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700">Upload Attendance Report (Infotech)</h3>
        <span class="text-xs text-gray-400">Accepts .pdf, .xlsx, or .xls — max 5MB</span>
    </div>

    <form method="POST"
          action="{{ route('timesheets.upload-attendance', $timesheet) }}"
          enctype="multipart/form-data"
          x-ref="uploadForm">
        @csrf

        <div class="flex items-center gap-4">
            {{-- Drop zone / file picker --}}
            <label class="flex-1 flex items-center justify-center border-2 border-dashed rounded-lg px-4 py-3 cursor-pointer transition-colors"
                   :class="{
                       'border-indigo-500 bg-indigo-50': dragOver,
                       'border-gray-300 hover:border-gray-400': !dragOver && !uploading,
                       'border-emerald-400 bg-emerald-50 pointer-events-none': uploading
                   }"
                   @dragover.prevent="dragOver = true"
                   @dragleave.prevent="dragOver = false"
                   @drop.prevent="handleDrop($event)">
                <div class="text-center">
                    <template x-if="!uploading">
                        <div>
                            <svg class="mx-auto h-8 w-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                            <p class="mt-1 text-xs text-gray-500">
                                Drag & drop or <span class="text-indigo-600 font-medium">click to browse</span>
                            </p>
                        </div>
                    </template>
                    <template x-if="uploading">
                        <div>
                            <svg class="mx-auto h-8 w-8 text-emerald-600 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <p class="mt-1 text-xs text-emerald-700 font-medium" x-text="'Processing ' + fileName + '...'"></p>
                        </div>
                    </template>
                </div>
                <input type="file"
                       name="attendance_file"
                       accept=".pdf,.xlsx,.xls"
                       class="hidden"
                       x-ref="fileInput"
                       @change="handleFileSelect($event)">
            </label>
        </div>
    </form>

    @error('attendance_file')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<script>
function attendanceUpload() {
    return {
        dragOver: false,
        fileName: '',
        uploading: false,

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.fileName = file.name;
            this.submitForm();
        },

        handleDrop(event) {
            this.dragOver = false;
            const files = event.dataTransfer.files;
            if (!files.length) return;
            this.$refs.fileInput.files = files;
            this.fileName = files[0].name;
            this.submitForm();
        },

        submitForm() {
            if (this.uploading) return;
            this.$refs.uploadForm.submit();
            this.uploading = true;
        },

        scrollToFlash() {
            const flash = document.getElementById('uploadFlash');
            if (flash) {
                flash.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    };
}
</script>
@endif
