<x-app-layout>
    <x-slot name="header">Training Attendance</x-slot>

    @push('sub-navbar')
        @include('layouts._hr-sub-navbar')
    @endpush

    <div class="max-w-7xl mx-auto">
        @if($isAdminOrHr)
            <div class="mb-4">
                <a href="{{ route('training-attendance.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium">Create Training Session</a>
            </div>
        @endif

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-4 text-sm">{{ session('error') }}</div>
        @endif

        @if($sessions->isEmpty())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <p class="text-sm text-gray-500">No training sessions available.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($sessions as $session)
                    @php
                        $user = Auth::user();
                        $hasAttended = $session->attendedBy($user);
                    @endphp
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                        <div class="p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    @if($isAdminOrHr)
                                        <a href="{{ route('training-attendance.report', $session) }}" class="text-base font-semibold text-gray-900 hover:text-indigo-600">{{ $session->name }}</a>
                                    @else
                                        <h3 class="text-base font-semibold text-gray-900">{{ $session->name }}</h3>
                                    @endif
                                    @if($session->is_active)
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">Active</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-500">
                                    {{ $session->training_date->format('d M Y') }} &middot;
                                    {{ $session->time_in->format('H:i') }} - {{ $session->time_out->format('H:i') }} &middot;
                                    {{ $session->venue }}
                                </p>
                                @if($isAdminOrHr)
                                    <p class="text-xs text-gray-400 mt-1">Attendees: {{ $session->attendances_count }}</p>
                                @endif
                            </div>

                            <div class="flex flex-col gap-2">
                                @if($isAdminOrHr)
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if($session->is_active)
                                            <form action="{{ route('training-attendance.deactivate', $session) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 bg-yellow-100 text-yellow-800 rounded hover:bg-yellow-200 text-xs font-medium">Deactivate</button>
                                            </form>
                                        @else
                                            <form action="{{ route('training-attendance.activate', $session) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 bg-green-100 text-green-800 rounded hover:bg-green-200 text-xs font-medium">Activate</button>
                                            </form>
                                        @endif
                                        <a href="{{ route('training-attendance.edit', $session) }}" class="px-3 py-1.5 bg-gray-100 text-gray-800 rounded hover:bg-gray-200 text-xs font-medium">Edit</a>
                                        <form action="{{ route('training-attendance.destroy', $session) }}" method="POST" onsubmit="return confirm('Delete this training session?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-3 py-1.5 bg-red-100 text-red-800 rounded hover:bg-red-200 text-xs font-medium">Delete</button>
                                        </form>
                                    </div>
                                @endif

                                <div class="flex flex-wrap items-center gap-2">
                                    @if($isAdminOrHr)
                                        <a href="{{ route('training-attendance.export-pdf', $session) }}" target="_blank" class="px-3 py-1.5 bg-red-100 text-red-800 rounded hover:bg-red-200 text-xs font-medium">PDF</a>
                                    @endif

                                    @if($session->is_active && ! $hasAttended)
                                        <form action="{{ route('training-attendance.attend', $session) }}" method="POST" class="flex items-center gap-2">
                                            @csrf
                                            <input type="text" name="signature" placeholder="Type your signature" required
                                                   class="w-40 rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200">
                                            <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-xs font-medium">Mark Attendance</button>
                                        </form>
                                    @elseif($hasAttended)
                                        <span class="px-3 py-1.5 bg-green-100 text-green-800 rounded text-xs font-medium">Attended</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
