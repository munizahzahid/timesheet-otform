<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My OT Forms') }}</h2>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto">

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            {{-- Create New OT Form --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Create New OT Form</h3>
                    <form method="POST" action="{{ route('ot-forms.store') }}" class="flex flex-wrap items-end gap-4">
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Form Type</label>
                            <div class="mt-1 flex items-center gap-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="form_type" value="executive" checked class="text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm">Executive</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="form_type" value="non_executive" class="text-indigo-600 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm">Non-Executive</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Company</label>
                            <select name="company_name" required class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="INGRESS CORPORATION">Ingress Corporation</option>
                                <option value="INGRESS ENGINEERING">Ingress Engineering</option>
                                <option value="INGRESS PRECISION">Ingress Precision</option>
                                <option value="TALENT SYNERGY" selected>Talent Synergy</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Section/Line</label>
                            <input type="text" name="section_line" placeholder="Optional"
                                   class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-32">
                        </div>
                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">
                            + New OT Form
                        </button>
                    </form>
                    @if($errors->any())
                        <div class="mt-2 text-red-600 text-sm">
                            @foreach($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- OT Form List --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month / Year</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($otForms as $ot)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                            {{ DateTime::createFromFormat('!m', $ot->month)->format('F') }} {{ $ot->year }}
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $ot->form_type === 'executive' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                                {{ $ot->form_type === 'executive' ? 'Executive' : 'Non-Executive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $ot->company_name }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @php
                                                $badgeClass = match($ot->status) {
                                                    'draft' => 'bg-gray-100 text-gray-800',
                                                    'pending_hod' => 'bg-yellow-100 text-yellow-800',
                                                    'approved', 'ceo_approved' => 'bg-green-100 text-green-800',
                                                    'pending_ceo' => 'bg-blue-100 text-blue-800',
                                                    'rejected' => 'bg-red-100 text-red-800',
                                                    default => 'bg-gray-100 text-gray-800',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                                {{ $ot->status_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $ot->created_at->format('d M Y') }}</td>
                                        <td class="px-4 py-3 text-sm flex gap-3">
                                            @if($ot->isEditable())
                                                <a href="{{ route('ot-forms.edit', $ot) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                            @else
                                                <a href="{{ route('ot-forms.edit', $ot) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                                            @endif
                                            @if($ot->status === 'draft')
                                                <form method="POST" action="{{ route('ot-forms.destroy', $ot) }}"
                                                      onsubmit="return confirm('Delete this draft OT form?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No OT forms yet. Create one above.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">{{ $otForms->links() }}</div>
                </div>
            </div>
    </div>

    <x-help-button title="OT Forms Help">
        <x-slot name="content">
            <h3 class="font-semibold text-gray-900 mb-2">My OT Forms</h3>
            <p class="mb-3">Manage your monthly overtime (OT) forms here.</p>
            <h4 class="font-semibold text-gray-900 mb-1">How to use</h4>
            <ul class="list-disc pl-5 space-y-1 mb-3">
                <li><strong>Create New</strong> — Select month, year, form type (Executive/Non-Executive), and company</li>
                <li><strong>Edit</strong> — Fill in planned and actual OT times for each day</li>
                <li><strong>Auto-Fill</strong> — Upload attendance PDF in your timesheet first, then use the Auto-Fill button in the OT form to populate actual times automatically</li>
                <li><strong>Submit</strong> — Submit the completed form for manager approval</li>
            </ul>
            <h4 class="font-semibold text-gray-900 mb-1">Form Types</h4>
            <ul class="list-disc pl-5 space-y-1">
                <li><strong>Executive</strong> — For executive-level staff</li>
                <li><strong>Non-Executive</strong> — For non-executive staff with additional fields</li>
            </ul>
        </x-slot>
    </x-help-button>
</x-app-layout>
