<div class="bg-white shadow-sm sm:rounded-lg mb-4 p-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>
            <span class="text-gray-500">Staff Name:</span>
            <span class="font-medium">{{ $timesheet->user->name }}</span>
        </div>
        <div>
            <span class="text-gray-500">Employee No:</span>
            <span class="font-medium">{{ $timesheet->user->staff_no ?? '-' }}</span>
        </div>
        <div>
            <span class="text-gray-500">Department:</span>
            <span class="font-medium">{{ $timesheet->user->department?->name ?? '-' }}</span>
        </div>
        <div>
            <span class="text-gray-500">Period:</span>
            <span class="font-medium">{{ DateTime::createFromFormat('!m', $timesheet->month)->format('F') }} {{ $timesheet->year }}</span>
        </div>
    </div>
</div>
