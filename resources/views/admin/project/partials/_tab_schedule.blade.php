<div class="bg-white border border-gray-200 rounded-lg">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Project Schedule</h3>
    </div>
    <div class="p-6">
        @include('admin.project.partials._gantt', ['effectiveDates' => $effectiveDates])
    </div>
</div>
