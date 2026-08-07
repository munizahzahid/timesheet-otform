@php
    $tab = $tab ?? request('tab', 'dashboard');
    $tabs = [
        'dashboard' => ['label' => 'Dashboard', 'route' => route('admin.project.projects.show', $project) . '?tab=dashboard'],
        'details' => ['label' => 'Details', 'route' => route('admin.project.projects.show', $project) . '?tab=details'],
        'schedule' => ['label' => 'Gantt', 'route' => route('admin.project.projects.show', $project) . '?tab=schedule'],
        'tasks' => ['label' => 'Kanban', 'route' => route('admin.project.projects.show', $project) . '?tab=tasks'],
    ];
@endphp

<div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-1.5 mb-6">
    <nav class="flex gap-1" aria-label="Project tabs">
        @foreach($tabs as $key => $tabData)
            <a href="{{ $tabData['route'] }}"
               class="flex-1 text-center whitespace-nowrap py-2.5 px-2 rounded-xl text-sm font-medium transition
                      {{ $tab === $key ? 'bg-indigo-50 text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}">
                {{ $tabData['label'] }}
            </a>
        @endforeach
    </nav>
</div>
