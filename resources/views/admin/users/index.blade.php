<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('User Management') }}</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto">

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    {{-- Filters --}}
                    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-6 flex flex-wrap gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Search</label>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Name, staff no, email..."
                                   class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Department</label>
                            <select name="department" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">All</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ request('department') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">All</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">Filter</button>
                            <a href="{{ route('admin.users.index') }}" class="ml-2 text-sm text-gray-600 hover:text-gray-900">Reset</a>
                        </div>
                    </form>

                    {{-- Table --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Staff No</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Designation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reports To</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">TS Approver</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">OT Exec</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">OT Non-Exec</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($users as $user)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $user->staff_no ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <div class="font-medium text-gray-900">{{ $user->name }}</div>
                                            <div class="text-gray-500 text-xs">{{ $user->email }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $user->department?->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $user->designation ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $user->supervisor?->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            @if($user->timesheet_approver_id)
                                                {{ $user->timesheetApprover?->name ?? '-' }}
                                            @else
                                                <span class="text-xs text-gray-400">Role-based</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            @if($user->ot_exec_approver_id)
                                                {{ $user->otExecApprover?->name ?? '-' }}
                                            @else
                                                <span class="text-xs text-gray-400">Role-based</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">
                                            @if($user->ot_non_exec_approver_id)
                                                {{ $user->otNonExecApprover?->name ?? '-' }}
                                            @else
                                                <span class="text-xs text-gray-400">Role-based</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($user->is_active)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                            <span class="text-gray-300 mx-1">|</span>
                                            <a href="{{ route('history.index', ['user_id' => $user->id]) }}" class="text-indigo-600 hover:text-indigo-900">History</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-4 py-8 text-center text-gray-500">No users found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
    </div>

    <x-help-button title="Bantuan Pengurusan Pengguna">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">Pengurusan Pengguna</h3>
            <p class="mb-3">Lihat dan urus semua pengguna sistem. Data pengguna disinkronkan dari Desknet.</p>
            <h4 class="font-semibold text-gray-900 mb-1">Ciri-ciri</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Cari/Tapis</strong> — Tapis pengguna mengikut nama, jabatan, atau status</li>
                <li><strong>Edit</strong> — Tukar penyelia pelaporan pengguna atau status aktif</li>
                <li><strong>Laporan Kepada</strong> — Menunjukkan penyelia yang ditugaskan kepada pengguna</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
