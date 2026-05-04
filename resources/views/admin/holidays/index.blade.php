<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Public Holidays') }}</h2>
            <button onclick="document.getElementById('addHolidayModal').classList.remove('hidden')"
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">
                + Add Company Holiday
            </button>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto">

            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{{ session('error') }}</div>
            @endif

            {{-- Year selector --}}
            <div class="mb-4 flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700">Year:</label>
                <select onchange="window.location.href='{{ route('admin.holidays.index') }}?year='+this.value"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
                <span class="text-sm text-gray-500 ml-2">({{ $holidays->count() }} holidays)</span>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Day</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recurring</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($holidays as $holiday)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $holiday->holiday_date->format('d M Y') }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $holiday->holiday_date->format('l') }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $holiday->name }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($holiday->source === 'gazetted')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Gazetted</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Company</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $holiday->is_recurring ? 'Yes' : 'No' }}</td>
                                        <td class="px-4 py-3 text-sm flex gap-2" x-data="{ editing: false }">
                                            <button @click="editing = !editing" class="text-indigo-600 hover:text-indigo-900 text-sm">Edit</button>
                                            <form method="POST" action="{{ route('admin.holidays.destroy', $holiday) }}"
                                                  onsubmit="return confirm('Delete this holiday?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                            </form>

                                            {{-- Inline edit form --}}
                                            <div x-show="editing" x-cloak class="fixed inset-0 bg-gray-500 bg-opacity-50 flex items-center justify-center z-50">
                                                <div class="bg-white rounded-lg shadow-xl p-6 w-96" @click.outside="editing = false">
                                                    <h3 class="text-lg font-medium mb-4">Edit Holiday</h3>
                                                    <form method="POST" action="{{ route('admin.holidays.update', $holiday) }}">
                                                        @csrf @method('PUT')
                                                        <div class="mb-4">
                                                            <label class="block text-sm font-medium text-gray-700">Date</label>
                                                            <input type="date" name="holiday_date" value="{{ $holiday->holiday_date->format('Y-m-d') }}"
                                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                        </div>
                                                        <div class="mb-4">
                                                            <label class="block text-sm font-medium text-gray-700">Name</label>
                                                            <input type="text" name="name" value="{{ $holiday->name }}"
                                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                                        </div>
                                                        <div class="flex justify-end gap-2">
                                                            <button type="button" @click="editing = false" class="px-4 py-2 text-sm text-gray-700 border rounded-md hover:bg-gray-50">Cancel</button>
                                                            <button type="submit" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Save</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">No holidays for {{ $year }}.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </div>

    {{-- Add Holiday Modal --}}
    <div id="addHolidayModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-96">
            <h3 class="text-lg font-medium mb-4">Add Company Holiday</h3>
            <form method="POST" action="{{ route('admin.holidays.store') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" name="holiday_date" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" required placeholder="e.g., Company Annual Dinner"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('addHolidayModal').classList.add('hidden')"
                            class="px-4 py-2 text-sm text-gray-700 border rounded-md hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Add Holiday</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
