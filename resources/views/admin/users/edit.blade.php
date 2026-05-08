<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit User: {{ $user->name }}</h2>
    </x-slot>

    <div class="max-w-2xl mx-auto">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    {{-- Read-only info from Desknet --}}
                    <div class="mb-6 bg-gray-50 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-500 mb-3">Synced from Desknet (read-only)</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div><span class="text-gray-500">Staff No:</span> <span class="font-medium">{{ $user->staff_no ?? '-' }}</span></div>
                            <div><span class="text-gray-500">Email:</span> <span class="font-medium">{{ $user->email }}</span></div>
                            <div><span class="text-gray-500">Department:</span> <span class="font-medium">{{ $user->department?->name ?? '-' }}</span></div>
                            <div><span class="text-gray-500">Designation:</span> <span class="font-medium">{{ $user->designation ?? '-' }}</span></div>
                            <div><span class="text-gray-500">Desknet ID:</span> <span class="font-medium">{{ $user->desknet_id ?? '-' }}</span></div>
                            <div><span class="text-gray-500">Last Synced:</span> <span class="font-medium">{{ $user->last_synced_at?->format('d M Y H:i') ?? 'Never' }}</span></div>
                        </div>
                    </div>

                    {{-- Editable fields (local only) --}}
                    <form method="POST" action="{{ route('admin.users.update', $user) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                            <select name="role" id="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="staff" {{ $user->role === 'staff' ? 'selected' : '' }}>Staff</option>
                                <option value="assistant_manager" {{ $user->role === 'assistant_manager' ? 'selected' : '' }}>Assistant Manager</option>
                                <option value="manager_hod" {{ $user->role === 'manager_hod' ? 'selected' : '' }}>Manager/HOD</option>
                                <option value="ceo" {{ $user->role === 'ceo' ? 'selected' : '' }}>CEO</option>
                                <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin</option>
                            </select>
                            @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="reports_to" class="block text-sm font-medium text-gray-700">Reports To</label>
                            <select name="reports_to" id="reports_to" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- None --</option>
                                @foreach($supervisors as $sup)
                                    <option value="{{ $sup->id }}" {{ $user->reports_to == $sup->id ? 'selected' : '' }}>
                                        {{ $sup->name }} ({{ $sup->designation ?? '-' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('reports_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Document Type Approvers --}}
                        <div class="mb-6 bg-blue-50 rounded-lg p-4">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Document Type Approvers</h3>

                            <div class="mb-4">
                                <label for="timesheet_approver_id" class="block text-sm font-medium text-gray-700">Timesheet Approver (Asst Mgr/Mngr)</label>
                                <select name="timesheet_approver_id" id="timesheet_approver_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- None --</option>
                                    @foreach($approvers as $approver)
                                        <option value="{{ $approver->id }}" {{ $user->timesheet_approver_id == $approver->id ? 'selected' : '' }}>
                                            {{ $approver->name }} ({{ $approver->designation ?? '-' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('timesheet_approver_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="ot_exec_approver_id" class="block text-sm font-medium text-gray-700">OT Form (Exec) Approver (HOD)</label>
                                <select name="ot_exec_approver_id" id="ot_exec_approver_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- None --</option>
                                    @foreach($approvers as $approver)
                                        <option value="{{ $approver->id }}" {{ $user->ot_exec_approver_id == $approver->id ? 'selected' : '' }}>
                                            {{ $approver->name }} ({{ $approver->designation ?? '-' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('ot_exec_approver_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="ot_exec_final_approver_id" class="block text-sm font-medium text-gray-700">OT Form (Exec) Final Approver (DGM/CEO)</label>
                                <select name="ot_exec_final_approver_id" id="ot_exec_final_approver_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- None --</option>
                                    @foreach($approvers as $approver)
                                        <option value="{{ $approver->id }}" {{ $user->ot_exec_final_approver_id == $approver->id ? 'selected' : '' }}>
                                            {{ $approver->name }} ({{ $approver->designation ?? '-' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('ot_exec_final_approver_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="ot_non_exec_approver_id" class="block text-sm font-medium text-gray-700">OT Form (Non-Exec) Approver (Mgr/HOD)</label>
                                <select name="ot_non_exec_approver_id" id="ot_non_exec_approver_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- None --</option>
                                    @foreach($approvers as $approver)
                                        <option value="{{ $approver->id }}" {{ $user->ot_non_exec_approver_id == $approver->id ? 'selected' : '' }}>
                                            {{ $approver->name }} ({{ $approver->designation ?? '-' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('ot_non_exec_approver_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="ot_non_exec_final_approver_id" class="block text-sm font-medium text-gray-700">OT Form (Non-Exec) Final Approver (DGM/CEO)</label>
                                <select name="ot_non_exec_final_approver_id" id="ot_non_exec_final_approver_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- None --</option>
                                    @foreach($approvers as $approver)
                                        <option value="{{ $approver->id }}" {{ $user->ot_non_exec_final_approver_id == $approver->id ? 'selected' : '' }}>
                                            {{ $approver->name }} ({{ $approver->designation ?? '-' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('ot_non_exec_final_approver_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="is_active" class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="is_active" id="is_active" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="1" {{ $user->is_active ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ !$user->is_active ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('is_active') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center gap-4">
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">
                                Save Changes
                            </button>
                            <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
    </div>
</x-app-layout>
