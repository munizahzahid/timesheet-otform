import { useEffect, useMemo, useState } from 'react';
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
        weight: t.weight ?? 1,
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

        const phaseWeight = dated.reduce((sum, t) => sum + (t.weight ?? 1), 0);
        list.push({
            id: `phase-${p.id}`,
            name: p.name,
            start,
            end,
            progress: p.progress || 0,
            type: 'project',
            hideChildren: false,
            weight: phaseWeight,
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

const dateTimeOptions = { weekday: 'short', year: 'numeric', month: 'long', day: 'numeric' };

const localeDateStringCache = {};
function toLocaleDateStringFactory(locale) {
    return function (date, options) {
        const key = date.toString() + JSON.stringify(options);
        if (!localeDateStringCache[key]) {
            localeDateStringCache[key] = date.toLocaleDateString(locale, options);
        }
        return localeDateStringCache[key];
    };
}

const COL_NAME = 220;
const COL_WEIGHT = 60;
const COL_FROM = 120;
const COL_TO = 120;
const TOTAL_WIDTH = COL_NAME + COL_WEIGHT + COL_FROM + COL_TO + 3; // +3 for separators

function TaskListHeader({ headerHeight, fontFamily, fontSize }) {
    return (
        <div style={{ fontFamily, fontSize, borderBottom: '1px solid #e6e4e4', borderLeft: '1px solid #e6e4e4' }}>
            <div style={{ height: headerHeight - 2, display: 'flex', alignItems: 'center', background: '#f5f5f5' }}>
                <div style={{ minWidth: COL_NAME, maxWidth: COL_NAME, padding: '0 0.5rem', fontWeight: 500 }}>&nbsp;Name</div>
                <div style={{ width: 1, height: headerHeight * 0.5, background: '#c4c4c4', marginTop: headerHeight * 0.25, opacity: 0.5 }}></div>
                <div style={{ minWidth: COL_WEIGHT, maxWidth: COL_WEIGHT, padding: '0 0.5rem', fontWeight: 500, textAlign: 'center' }}>&nbsp;Weight</div>
                <div style={{ width: 1, height: headerHeight * 0.5, background: '#c4c4c4', marginTop: headerHeight * 0.25, opacity: 0.5 }}></div>
                <div style={{ minWidth: COL_FROM, maxWidth: COL_FROM, padding: '0 0.5rem', fontWeight: 500 }}>&nbsp;From</div>
                <div style={{ width: 1, height: headerHeight * 0.5, background: '#c4c4c4', marginTop: headerHeight * 0.25, opacity: 0.5 }}></div>
                <div style={{ minWidth: COL_TO, maxWidth: COL_TO, padding: '0 0.5rem', fontWeight: 500 }}>&nbsp;To</div>
            </div>
        </div>
    );
}

function TaskListTable({ rowHeight, fontFamily, fontSize, locale, tasks, selectedTaskId, setSelectedTask, onExpanderClick }) {
    const toLocaleDateString = useMemo(() => toLocaleDateStringFactory(locale), [locale]);
    return (
        <div style={{ fontFamily, fontSize, borderLeft: '1px solid #e6e4e4' }}>
            {tasks.map(t => {
                let expanderSymbol = '';
                if (t.hideChildren === false) expanderSymbol = '▼';
                else if (t.hideChildren === true) expanderSymbol = '▶';
                const isSelected = t.id === selectedTaskId;
                return (
                    <div key={t.id + 'row'} style={{ height: rowHeight, display: 'flex', alignItems: 'center', borderBottom: '1px solid #e6e4e4', background: isSelected ? '#fff8e1' : undefined }}
                         onClick={() => setSelectedTask(t.id === selectedTaskId ? '' : t.id)}>
                        <div style={{ minWidth: COL_NAME, maxWidth: COL_NAME, padding: '0 0.5rem', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }} title={t.name}>
                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}>
                                <span onClick={(e) => { e.stopPropagation(); onExpanderClick(t); }}
                                      style={{ cursor: 'pointer', fontSize: '0.6rem', minWidth: '1rem', display: 'inline-block', textAlign: 'center' }}>
                                    {expanderSymbol}
                                </span>
                                <span>{t.name}</span>
                            </span>
                        </div>
                        <div style={{ width: 1, height: rowHeight * 0.5, background: '#c4c4c4', opacity: 0.5 }}></div>
                        <div style={{ minWidth: COL_WEIGHT, maxWidth: COL_WEIGHT, padding: '0 0.5rem', textAlign: 'center', fontSize: '0.7rem', color: '#555' }}>
                            {t.weight !== undefined ? t.weight : ''}
                        </div>
                        <div style={{ width: 1, height: rowHeight * 0.5, background: '#c4c4c4', opacity: 0.5 }}></div>
                        <div style={{ minWidth: COL_FROM, maxWidth: COL_FROM, padding: '0 0.5rem', whiteSpace: 'nowrap', overflow: 'hidden', fontSize: '0.75rem' }}>
                            &nbsp;{toLocaleDateString(t.start, dateTimeOptions)}
                        </div>
                        <div style={{ width: 1, height: rowHeight * 0.5, background: '#c4c4c4', opacity: 0.5 }}></div>
                        <div style={{ minWidth: COL_TO, maxWidth: COL_TO, padding: '0 0.5rem', whiteSpace: 'nowrap', overflow: 'hidden', fontSize: '0.75rem' }}>
                            &nbsp;{toLocaleDateString(t.end, dateTimeOptions)}
                        </div>
                    </div>
                );
            })}
        </div>
    );
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

    // Enhance dependency arrows with visible arrowheads at the successor end
    useEffect(() => {
        let timeout;
        let observer;
        let styleId = 'gantt-arrow-style';

        const enhanceArrows = () => {
            const svg = document.querySelector('.gantt-wrapper svg');
            if (!svg) return;

            // Inject CSS for persistent arrow styling
            if (!document.getElementById(styleId)) {
                const style = document.createElement('style');
                style.id = styleId;
                style.textContent = `
                    .gantt-wrapper .arrow path {
                        stroke: #64748b !important;
                        stroke-width: 2.5 !important;
                    }
                    .gantt-wrapper .arrow polygon {
                        fill: #64748b !important;
                        stroke: #64748b !important;
                        stroke-width: 1 !important;
                    }
                `;
                document.head.appendChild(style);
            }

            // Make arrowhead polygons larger by scaling them
            svg.querySelectorAll('.arrow polygon').forEach((polygon) => {
                const points = polygon.getAttribute('points');
                if (!points) return;

                // Parse existing points (format: "x1,y1 x2,y2 x3,y3")
                const coords = points.trim().split(/\s+/).flatMap(p => p.split(',').map(Number));
                if (coords.length < 6) return;

                const cx = (coords[0] + coords[2] + coords[4]) / 3;
                const cy = (coords[1] + coords[3] + coords[5]) / 3;
                const scale = 1.8;

                const newPoints = coords.map((v, i) => {
                    const center = i % 2 === 0 ? cx : cy;
                    return center + (v - center) * scale;
                });

                polygon.setAttribute('points', [
                    `${newPoints[0]},${newPoints[1]}`,
                    `${newPoints[2]},${newPoints[3]}`,
                    `${newPoints[4]},${newPoints[5]}`,
                ].join(' '));
            });
        };

        const startObserving = () => {
            const wrapper = document.querySelector('.gantt-wrapper');
            if (!wrapper) return;
            observer = new MutationObserver(() => {
                enhanceArrows();
            });
            observer.observe(wrapper, { childList: true, subtree: true });
            enhanceArrows();
        };

        timeout = setTimeout(startObserving, 200);

        return () => {
            clearTimeout(timeout);
            if (observer) observer.disconnect();
            document.getElementById(styleId)?.remove();
        };
    }, [tasks, viewKey]);

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
                        listCellWidth={`${TOTAL_WIDTH}px`}
                        columnWidth={current.columnWidth}
                        locale="en-GB"
                        barFill={65}
                        TaskListHeader={TaskListHeader}
                        TaskListTable={TaskListTable}
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
