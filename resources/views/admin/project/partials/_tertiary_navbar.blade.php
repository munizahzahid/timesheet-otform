@php
    $tab = $tab ?? request('tab', 'dashboard');
    $tabs = [
        'dashboard' => ['label' => 'Dashboard', 'route' => route('admin.project.projects.show', $project) . '?tab=dashboard'],
        'details' => ['label' => 'Details', 'route' => route('admin.project.projects.show', $project) . '?tab=details'],
        'schedule' => ['label' => 'Gantt', 'route' => route('admin.project.projects.show', $project) . '?tab=schedule'],
        'tasks' => ['label' => 'Kanban', 'route' => route('admin.project.projects.show', $project) . '?tab=tasks'],
        'cards' => ['label' => 'Cards', 'route' => route('admin.project.projects.show', $project) . '?tab=cards'],
    ];
@endphp

<div class="border-b border-gray-200 mb-6">
    <nav class="-mb-px flex space-x-8" aria-label="Project tabs">
        @foreach($tabs as $key => $tabData)
            <a href="{{ $tabData['route'] }}"
               class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors
                      {{ $tab === $key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tabData['label'] }}
            </a>
        @endforeach
    </nav>
</div>
