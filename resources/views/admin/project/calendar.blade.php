<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Project Calendar</h2>
        </div>
    </x-slot>

    @include('admin.project.partials._navbar')

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        @php
            $calendarProps = [
                'view' => $view,
                'day' => $day,
                'month' => $month,
                'year' => $year,
                'dayTasks' => $dayTasks->values()->toArray(),
                'weeks' => $weeks,
                'months' => $months,
                'periodStart' => $periodStart?->format('Y-m-d'),
                'periodEnd' => $periodEnd?->format('Y-m-d'),
                'calendarUrl' => route('admin.project.calendar'),
            ];
        @endphp
        <script type="application/json" id="project-calendar-props">@json($calendarProps)</script>
        <div id="project-calendar"></div>
    </div>
</x-app-layout>
