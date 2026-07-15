<x-app-layout>
    <x-slot name="header">Training Attendance Report</x-slot>

    @push('sub-navbar')
        @include('layouts._hr-sub-navbar')
    @endpush

    @push('styles')
        <link href="https://fonts.googleapis.com/css2?family=Dancing+Script&display=swap" rel="stylesheet">
        <style>
            .signature-text { font-family: 'Dancing Script', cursive; font-size: 1.25rem; }
        </style>
    @endpush

    <div class="max-w-5xl mx-auto">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-sm">{{ session('error') }}</div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm rounded-lg mb-6">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-bold text-gray-900">{{ $session->name }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3 text-sm text-gray-700">
                    <div>
                        <span class="font-medium">Date:</span> {{ $session->training_date->format('d M Y') }}
                    </div>
                    <div>
                        <span class="font-medium">Time:</span> {{ $session->time_in->format('H:i') }} - {{ $session->time_out->format('H:i') }}
                    </div>
                    <div>
                        <span class="font-medium">Venue:</span> {{ $session->venue }}
                    </div>
                </div>
            </div>

            @if($isAdminOrHr && $availableUsers->isNotEmpty())
                <div class="p-6 border-b border-gray-200 bg-gray-50">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Add Attendee</h4>
                    <form action="{{ route('training-attendance.add-attendee', $session) }}" method="POST" class="flex flex-col sm:flex-row sm:items-end gap-3">
                        @csrf
                        <div class="flex-1">
                            <label for="user_id" class="block text-xs font-medium text-gray-700 mb-1">Select User</label>
                            <select name="user_id" id="user_id" required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 text-sm">
                                <option value="">Select a user</option>
                                @foreach($availableUsers as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->staff_no ?? 'No Staff No' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1">
                            <label for="signature" class="block text-xs font-medium text-gray-700 mb-1">Signature</label>
                            <input type="text" name="signature" id="signature" placeholder="Type signature" required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 text-sm">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">Add Attendee</button>
                    </form>
                </div>
            @endif

            <div class="p-6">
                @if($attendances->isEmpty())
                    <p class="text-sm text-gray-500">No attendees yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">No.</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Staff No</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Signature</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Time In</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Time Out</th>
                                    @if($isAdminOrHr)
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($attendances as $index => $attendance)
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-b">{{ $index + 1 }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-b">{{ $attendance->user->name }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-b">{{ $attendance->staff_no ?? '-' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-b signature-text">{{ $attendance->signature }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-b">{{ $session->time_in->format('H:i') }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-b">{{ $session->time_out->format('H:i') }}</td>
                                        @if($isAdminOrHr)
                                            <td class="px-4 py-2 text-sm text-gray-900 border-b">
                                                <form action="{{ route('training-attendance.attendance.destroy', $attendance) }}" method="POST" onsubmit="return confirm('Remove this attendee?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="px-2 py-1 bg-red-100 text-red-800 rounded hover:bg-red-200 text-xs font-medium">Delete</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="mb-6 flex items-center gap-3">
            <a href="{{ route('training-attendance.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm font-medium">Back to List</a>
            <a href="{{ route('training-attendance.export-pdf', $session) }}" target="_blank" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium">Export PDF</a>
        </div>
    </div>
</x-app-layout>
