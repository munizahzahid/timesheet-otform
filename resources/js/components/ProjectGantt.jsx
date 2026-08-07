import { useEffect, useState } from 'react';
import { Gantt, ViewMode } from 'gantt-task-react';
import 'gantt-task-react/dist/index.css';

const viewOptions = [
    { key: 'day', label: 'Day', mode: ViewMode.Day, columnWidth: 60 },
    { key: 'week', label: 'Week', mode: ViewMode.Week, columnWidth: 150 },
    { key: 'month', label: 'Month', mode: ViewMode.Month, columnWidth: 200 },
    { key: 'year', label: 'Year', mode: ViewMode.Year, columnWidth: 350 },
];

function toDate(str) {
    if (!str) return null;
    const d = new Date(str + 'T00:00:00');
    return isNaN(d.getTime()) ? null : d;
}

function pickDates(t) {
    const planStart = toDate(t.start_date_plan);
    const planEnd = toDate(t.end_date_plan);
    if (planStart && planEnd) return [planStart, planEnd];

    const reviseStart = toDate(t.start_date_revise);
    const reviseEnd = toDate(t.end_date_revise);
    if (reviseStart && reviseEnd) return [reviseStart, reviseEnd];

    const actualStart = toDate(t.start_date_actual);
    const actualEnd = toDate(t.end_date_actual);
    if (actualStart && actualEnd) return [actualStart, actualEnd];

    return [null, null];
}

function formatDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function save(url, body) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
        },
        body: JSON.stringify(body),
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) alert(data.message || 'Failed to save change.');
        })
        .catch(() => alert('Failed to save change.'));
}

function taskToGantt(t, phaseId) {
    const [start, end] = pickDates(t);
    return {
        id: `task-${t.id}`,
        taskId: t.id,
        name: t.name,
        start,
        end,
        progress: t.progress || 0,
        type: 'task',
        project: phaseId ? `phase-${phaseId}` : undefined,
        dependencies: t.predecessor_task_id ? [`task-${t.predecessor_task_id}`] : [],
        updateUrl: t.updateUrl,
        editUrl: t.editUrl,
        styles: {
            backgroundColor: '#93c5fd',
            backgroundSelectedColor: '#60a5fa',
            progressColor: '#3b82f6',
            progressSelectedColor: '#2563eb',
        },
    };
}

function buildTasks(phases, standaloneTasks) {
    const list = [];

    phases.forEach(p => {
        const dated = p.tasks.filter(t => pickDates(t)[0] && pickDates(t)[1]);
        const starts = dated.map(t => pickDates(t)[0]);
        const ends = dated.map(t => pickDates(t)[1]);

        const phasePlanStart = toDate(p.start_date_plan);
        const phasePlanEnd = toDate(p.end_date_plan);
        if (phasePlanStart) starts.push(phasePlanStart);
        if (phasePlanEnd) ends.push(phasePlanEnd);

        if (starts.length === 0 || ends.length === 0) return;

        const start = new Date(Math.min(...starts.map(d => d.getTime())));
        const end = new Date(Math.max(...ends.map(d => d.getTime())));

        list.push({
            id: `phase-${p.id}`,
            name: p.name,
            start,
            end,
            progress: p.progress || 0,
            type: 'project',
            hideChildren: false,
            styles: {
                backgroundColor: '#6366f1',
                backgroundSelectedColor: '#4f46e5',
                progressColor: '#4338ca',
                progressSelectedColor: '#3730a3',
            },
        });

        dated.forEach(t => list.push(taskToGantt(t, p.id)));
    });

    standaloneTasks
        .filter(t => pickDates(t)[0] && pickDates(t)[1])
        .forEach(t => list.push(taskToGantt(t, null)));

    return list;
}

export default function ProjectGantt({ phases = [], standaloneTasks = [], urls = {} }) {
    const [tasks, setTasks] = useState(() => buildTasks(phases, standaloneTasks));
    const [viewKey, setViewKey] = useState('month');
    const [isFullscreen, setIsFullscreen] = useState(false);

    const current = viewOptions.find(v => v.key === viewKey);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') setIsFullscreen(false);
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    const handleDateChange = (task) => {
        setTasks(prev => prev.map(t => (t.id === task.id ? task : t)));
        if (task.type === 'task' && task.updateUrl) {
            save(task.updateUrl, {
                start_date_plan: formatDate(task.start),
                end_date_plan: formatDate(task.end),
            });
        }
    };

    const handleProgressChange = (task) => {
        setTasks(prev => prev.map(t => (t.id === task.id ? task : t)));
        if (task.type === 'task' && task.updateUrl) {
            save(task.updateUrl, { progress_actual: Math.round(task.progress) });
        }
    };

    const handleDoubleClick = (task) => {
        if (task.type === 'task' && task.editUrl) {
            window.location.href = task.editUrl;
        }
    };

    const handleExpanderClick = (task) => {
        setTasks(prev => prev.map(t => (t.id === task.id ? task : t)));
    };

    return (
        <div className={isFullscreen ? 'fixed inset-0 z-50 bg-white overflow-auto p-4' : 'relative'}>
            <div className="flex items-center justify-between mb-4 flex-wrap gap-3">
                <div className="flex items-center gap-2">
                    {urls.addPhase && (
                        <a href={urls.addPhase}
                            className="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                            Add Phase
                        </a>
                    )}
                    {urls.addTask && (
                        <a href={urls.addTask}
                            className="inline-flex items-center px-3 py-1.5 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                            Add Task
                        </a>
                    )}
                    {urls.exportExcel && (
                        <a href={urls.exportExcel}
                            className="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                            Export Excel
                        </a>
                    )}
                </div>

                <div className="flex items-center gap-2">
                    <div className="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                        {viewOptions.map(v => (
                            <button key={v.key} type="button" onClick={() => setViewKey(v.key)}
                                className={`px-3 py-1.5 text-xs font-medium transition ${viewKey === v.key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'}`}>
                                {v.label}
                            </button>
                        ))}
                    </div>
                    <button type="button" onClick={() => setIsFullscreen(f => !f)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                        {isFullscreen ? 'Exit' : 'Fullscreen'}
                    </button>
                </div>
            </div>

            {tasks.length > 0 ? (
                <div className="gantt-wrapper border border-gray-100 rounded-xl overflow-hidden">
                    <Gantt
                        tasks={tasks}
                        viewMode={current.mode}
                        onDateChange={handleDateChange}
                        onProgressChange={handleProgressChange}
                        onDoubleClick={handleDoubleClick}
                        onExpanderClick={handleExpanderClick}
                        listCellWidth="220px"
                        columnWidth={current.columnWidth}
                        locale="en-GB"
                        barFill={65}
                    />
                </div>
            ) : (
                <div className="text-center py-10 bg-gray-50 rounded-xl">
                    <p className="text-sm text-gray-400">No tasks with dates to display. Add plan dates to tasks to see them here.</p>
                </div>
            )}

            <p className="text-xs text-gray-400 mt-3">
                Drag bars to change plan dates. Drag the progress inside a bar to update actual progress. Double-click a task to edit. Arrows show dependencies (managed from the task edit page).
            </p>
        </div>
    );
}
