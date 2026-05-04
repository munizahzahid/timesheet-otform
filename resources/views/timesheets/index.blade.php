<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My Timesheets') }}</h2>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto">

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            {{-- Create New Timesheet --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Create New Timesheet</h3>
                    <form method="POST" action="{{ route('timesheets.store') }}" class="flex items-end gap-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Month</label>
                            <select name="month" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" {{ $m == now()->month ? 'selected' : '' }}>
                                        {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Year</label>
                            <select name="year" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                @for($y = now()->year - 1; $y <= now()->year + 1; $y++)
                                    <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <button type="submit" class="px-4 py-2 rounded-md text-sm hover:shadow-md transition-all" style="background-color: #4f46e5 !important; color: white !important;">
                            + New Timesheet
                        </button>
                    </form>
                </div>
            </div>

            {{-- Timesheet List --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month / Year</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($timesheets as $ts)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                            {{ DateTime::createFromFormat('!m', $ts->month)->format('F') }} {{ $ts->year }}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            @php
                                                $badgeClass = match($ts->status) {
                                                    'draft' => 'bg-gray-100 text-gray-800',
                                                    'pending_hod' => 'bg-yellow-100 text-yellow-800',
                                                    'pending_l1' => 'bg-blue-100 text-blue-800',
                                                    'approved' => 'bg-green-100 text-green-800',
                                                    'rejected_hod', 'rejected_l1' => 'bg-red-100 text-red-800',
                                                    default => 'bg-gray-100 text-gray-800',
                                                };
                                                $statusLabel = match($ts->status) {
                                                    'pending_hod' => 'Pending HOD Approval',
                                                    'pending_l1' => 'Pending Asst Mgr Approval',
                                                    'rejected_hod' => 'Rejected by HOD',
                                                    'rejected_l1' => 'Rejected by Asst Mgr',
                                                    default => str_replace('_', ' ', ucfirst($ts->status)),
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $ts->created_at->format('d M Y') }}</td>
                                        <td class="px-4 py-3 text-sm flex gap-3">
                                            @if(in_array($ts->status, ['draft', 'rejected_hod', 'rejected_l1']))
                                                <a href="{{ route('timesheets.edit', $ts) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                            @else
                                                <a href="{{ route('timesheets.edit', $ts) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                                            @endif
                                            @if($ts->status === 'draft')
                                                <form method="POST" action="{{ route('timesheets.destroy', $ts) }}"
                                                      onsubmit="return confirm('Delete this draft timesheet?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                            @elseif(auth()->user()->isAdmin())
                                                <button onclick="showDeleteModal({{ $ts->id }}, 'timesheet', '{{ $ts->status }}')"
                                                        class="text-red-600 hover:text-red-900 font-medium">Delete (Admin)</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">No timesheets yet. Create one above.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $timesheets->links() }}</div>
                </div>
            </div>
    </div>

    {{-- Delete Reason Modal --}}
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirm Deletion</h3>
                <p class="text-sm text-gray-600 mb-4">
                    You are about to delete a <span id="deleteType" class="font-medium"></span> with status:
                    <span id="deleteStatus" class="font-medium text-red-600"></span>
                </p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason for deletion (required):</label>
                    <textarea id="deleteReason" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Please provide a reason..."></textarea>
                    <p id="deleteReasonError" class="text-red-600 text-xs mt-1 hidden">Reason is required</p>
                </div>
                <div class="flex justify-end gap-3">
                    <button onclick="closeDeleteModal()" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-md">Cancel</button>
                    <button onclick="confirmDelete()" class="px-4 py-2 text-sm text-white bg-red-600 hover:bg-red-700 rounded-md">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let deleteId = null;
        let deleteType = null;

        function showDeleteModal(id, type, status) {
            deleteId = id;
            deleteType = type;
            document.getElementById('deleteType').textContent = type === 'timesheet' ? 'timesheet' : 'OT form';
            document.getElementById('deleteStatus').textContent = status;
            document.getElementById('deleteReason').value = '';
            document.getElementById('deleteReasonError').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
            deleteId = null;
            deleteType = null;
        }

        function confirmDelete() {
            const reason = document.getElementById('deleteReason').value.trim();
            if (!reason) {
                document.getElementById('deleteReasonError').classList.remove('hidden');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('timesheets.destroy', ['timesheet' => 0]) }}'.replace('/0', '/' + deleteId);

            if (deleteType === 'ot-form') {
                form.action = '{{ route('ot-forms.destroy', ['otForm' => 0]) }}'.replace('/0', '/' + deleteId);
            }

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;

            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';

            const reasonInput = document.createElement('input');
            reasonInput.type = 'hidden';
            reasonInput.name = 'delete_reason';
            reasonInput.value = reason;

            form.appendChild(csrfInput);
            form.appendChild(methodInput);
            form.appendChild(reasonInput);
            document.body.appendChild(form);
            form.submit();
        }
    </script>

    <x-help-button title="Bantuan Timesheet">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Timesheet Saya</h3>
            <p class="mb-3">Halaman ini menunjukkan semua timesheet anda. Anda boleh membuat yang baru dan mengurus yang sedia ada.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Cara guna</h4>
            <ul class="list-disc pl-5 space-y-1 mb-3">
                <li><strong>Buat Baru</strong> — Pilih bulan dan tahun, kemudian klik "+ Timesheet Baru"</li>
                <li><strong>Edit</strong> — Klik "Edit" untuk mengisi jam harian anda dan muat naik PDF kehadiran</li>
                <li><strong>Hantar</strong> — Setelah selesai, hantar untuk kelulusan dari halaman edit</li>
                <li><strong>Padam</strong> — Hanya timesheet draf boleh dipadamkan</li>
            </ul>
            <h4 class="font-semibold text-gray-900 mb-1">Status</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Draf</strong> — Belum dihantar, masih boleh diedit</li>
                <li><strong>Menantu</strong> — Menunggu kelulusan</li>
                <li><strong>Diluluskan</strong> — Diluluskan oleh penyelia</li>
                <li><strong>Ditolak</strong> — Dikembalikan untuk pembetulan</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
