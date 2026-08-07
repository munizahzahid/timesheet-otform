import React, { useState } from 'react';

const statusBar = {
    completed: { bg: 'bg-green-100', text: 'text-green-800', border: 'border-l-green-500', color: '#22c55e' },
    in_progress: { bg: 'bg-blue-100', text: 'text-blue-800', border: 'border-l-blue-500', color: '#3b82f6' },
    on_hold: { bg: 'bg-yellow-100', text: 'text-yellow-800', border: 'border-l-yellow-500', color: '#eab308' },
    not_started: { bg: 'bg-gray-100', text: 'text-gray-800', border: 'border-l-gray-400', color: '#9ca3af' },
    cancelled: { bg: 'bg-red-100', text: 'text-red-800', border: 'border-l-red-500', color: '#ef4444' },
};

const statusDot = {
    completed: 'bg-green-500',
    in_progress: 'bg-blue-500',
    on_hold: 'bg-yellow-500',
    not_started: 'bg-gray-400',
    cancelled: 'bg-red-500',
};

const statusLabel = (s) => {
    if (!s) return 'Not Set';
    return s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
};

const parseDate = (d) => new Date(d);

const fmt = (d, options) => {
    const date = parseDate(d);
    if (isNaN(date)) return d;
    return date.toLocaleDateString('en-GB', options);
};

const isSameDay = (a, b) => {
    const da = parseDate(a);
    const db = parseDate(b);
    return da.getFullYear() === db.getFullYear() && da.getMonth() === db.getMonth() && da.getDate() === db.getDate();
};

const isToday = (d) => isSameDay(d, new Date());

export default function ProjectCalendar({ view: initialView, day, month, year, dayTasks, weeks, months, periodStart, periodEnd, calendarUrl }) {
    const [view, setView] = useState(initialView);
    const [jump, setJump] = useState({ day, month, year });

    const buildUrl = (v, d, m, y) => `${calendarUrl}?view=${v}&day=${d}&month=${m}&year=${y}`;
    const navigate = (url) => { window.location.href = url; };

    const base = new Date(year, month - 1, day);

    const go = (offset, unit) => {
        const next = new Date(base);
        if (unit === 'day') next.setDate(base.getDate() + offset);
        if (unit === 'week') next.setDate(base.getDate() + offset * 7);
        if (unit === 'month') next.setMonth(base.getMonth() + offset);
        if (unit === 'year') next.setFullYear(base.getFullYear() + offset);
        navigate(buildUrl(view, next.getDate(), next.getMonth() + 1, next.getFullYear()));
    };

    const todayUrl = `${calendarUrl}?view=${view}`;
    const prevUrl = () => go(-1, view === 'week' ? 'week' : view);
    const nextUrl = () => go(1, view === 'week' ? 'week' : view);

    const currentLabel = () => {
        if (view === 'day') return fmt(base, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        if (view === 'week') return `${fmt(periodStart, { day: 'numeric', month: 'long', year: 'numeric' })} - ${fmt(periodEnd, { day: 'numeric', month: 'long', year: 'numeric' })}`;
        if (view === 'month') return new Date(year, month - 1, 1).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
        return year;
    };

    const handleViewChange = (v) => {
        setView(v);
        navigate(buildUrl(v, jump.day, jump.month, jump.year));
    };

    const handleJump = (e) => {
        e.preventDefault();
        navigate(buildUrl(view, jump.day, jump.month, jump.year));
    };

    const monthName = (m) => new Date(year, m - 1, 1).toLocaleDateString('en-GB', { month: 'long' });

    const DayView = () => (
        <div className="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
            <div className="mb-4">
                <h4 className="text-base font-semibold text-gray-800">{fmt(base, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</h4>
                <p className="text-sm text-gray-500">{dayTasks.length} task{dayTasks.length !== 1 ? 's' : ''}</p>
            </div>
            <div className="space-y-2">
                {dayTasks.length === 0 && <p className="text-sm text-gray-500">No tasks for this day.</p>}
                {dayTasks.map((task) => {
                    const s = statusBar[task.status] || statusBar.not_started;
                    return (
                        <div key={task.id} className={`flex items-center justify-between p-3 rounded-xl border shadow-sm hover:shadow-md transition ${s.bg} border-l-4 ${s.border} border-t-gray-200 border-r-gray-200 border-b-gray-200`}>
                            <div>
                                <a href={task.project ? `/admin/project/projects/${task.project.id}?tab=schedule` : '#'} className="text-sm font-medium text-indigo-700 hover:underline">
                                    {task.task_name}
                                </a>
                                <p className="text-xs text-gray-500">
                                    {task.project?.project_name} &bull; {fmt(task.start_date_plan, { day: 'numeric', month: 'short' })} - {fmt(task.end_date_plan, { day: 'numeric', month: 'short', year: 'numeric' })}
                                </p>
                            </div>
                            <span className="text-[11px] px-2.5 py-1 rounded-full bg-white border border-gray-200 text-gray-700 font-semibold shadow-sm">{statusLabel(task.status)}</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );

    const WeekMonthView = () => (
        <div className="border border-gray-100 rounded-2xl overflow-hidden bg-white shadow-sm">
            <div className="grid grid-cols-7 border-b border-gray-100 bg-slate-50/70">
                {['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'].map((d, i) => (
                    <div key={d} className={`text-center py-2 text-[11px] font-semibold text-gray-600 ${i < 6 ? 'border-r border-gray-100' : ''}`}>{d}</div>
                ))}
            </div>
            {weeks.map((week, weekIndex) => (
                <div key={weekIndex} className={`week-section ${weekIndex > 0 ? 'border-t border-gray-100' : ''}`} style={{ position: 'relative', minHeight: 120 }}>
                    <div className="absolute inset-0 grid grid-cols-7 pointer-events-none" style={{ zIndex: 0 }}>
                        {[0, 1, 2, 3, 4, 5, 6].map((col) => (
                            <div key={col} className={col < 6 ? 'border-r border-gray-100' : ''}></div>
                        ))}
                    </div>
                    <div className="grid grid-cols-7 relative" style={{ zIndex: 1 }}>
                        {week.days.map((day, i) => (
                            <div key={i} className="px-2 pt-2 pb-1">
                                <span className={`text-sm font-medium ${!day.in_month ? 'text-gray-300' : (isToday(day.date) ? 'inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-600 text-white text-xs' : 'text-gray-800')}`}>
                                    {parseDate(day.date).getDate()}
                                </span>
                            </div>
                        ))}
                    </div>
                    <div className="pb-2 relative" style={{ zIndex: 1 }}>
                        {week.max_level >= 0 ? (
                            Array.from({ length: week.max_level + 1 }).map((_, level) => (
                                <div key={level} className="grid grid-cols-7" style={{ minHeight: 28, marginBottom: 2 }}>
                                    {week.bars.filter((bar) => bar.level === level).map((bar) => {
                                        const s = statusBar[bar.task.status] || statusBar.not_started;
                                        const span = bar.end_col - bar.start_col + 1;
                                        return (
                                            <div key={`${bar.task.id}-${level}`} className="px-1" style={{ gridColumn: `${bar.start_col + 1} / span ${span}` }}>
                                                <a
                                                    href={bar.task.project ? `/admin/project/projects/${bar.task.project.id}?tab=schedule` : '#'}
                                                    className={`block h-full rounded-lg px-2 py-1 text-[11px] leading-tight truncate border-l-4 shadow-sm hover:shadow-md ${s.bg} ${s.text} ${s.border} hover:opacity-85 transition`}
                                                    title={`${bar.task.task_name} (${bar.task.project?.project_name})`}
                                                >
                                                    <span className="font-medium">{bar.task.task_name}</span>
                                                    <br />
                                                    <span className="opacity-75 text-[10px]">{fmt(bar.task.start_date_plan, { month: 'short', day: 'numeric' })} - {fmt(bar.task.end_date_plan, { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                                                </a>
                                            </div>
                                        );
                                    })}
                                </div>
                            ))
                        ) : (
                            <div style={{ minHeight: 60 }}></div>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );

    const YearView = () => (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {months.map((monthData) => (
                <div key={monthData.month} className="border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition">
                    <div className="bg-slate-50/70 px-3 py-2 text-sm font-semibold text-gray-800 border-b border-gray-100">{monthData.name}</div>
                    <div className="grid grid-cols-7 gap-px bg-gray-200">
                        {['S', 'M', 'T', 'W', 'T', 'F', 'S'].map((d, i) => (
                            <div key={i} className="bg-white text-center py-1 text-[10px] font-medium text-gray-500">{d}</div>
                        ))}
                        {monthData.weeks.flatMap((week) => week.days).map((day, i) => (
                            <div key={i} className={`bg-white min-h-[40px] p-1 hover:bg-slate-50/70 transition ${!day.in_month ? 'bg-gray-50 text-gray-300' : ''} ${isToday(day.date) ? 'ring-2 ring-inset ring-indigo-200' : ''}`}>
                                <div className={`text-[10px] font-medium ${day.in_month ? 'text-gray-700' : 'text-gray-300'}`}>{parseDate(day.date).getDate()}</div>
                                {day.tasks?.length > 0 && (
                                    <div className="mt-1 flex flex-wrap gap-0.5">
                                        {day.tasks.slice(0, 8).map((task, idx) => (
                                            <span
                                                key={idx}
                                                className={`w-1.5 h-1.5 rounded-full ${statusDot[task.status] || 'bg-gray-400'} cursor-help`}
                                                title={`${task.task_name} — ${task.project?.project_name || 'No project'}`}
                                            ></span>
                                        ))}
                                        {day.tasks.length > 8 && (
                                            <span
                                                className="text-[8px] text-gray-500 cursor-help"
                                                title={day.tasks.slice(8).map((t) => `${t.task_name} — ${t.project?.project_name || 'No project'}`).join('\n')}
                                            >
                                                +{day.tasks.length - 8}
                                            </span>
                                        )}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );

    const years = [];
    for (let y = new Date().getFullYear() + 2; y >= 2020; y--) years.push(y);

    return (
        <div className="space-y-6">
            <div className="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">
                    <div className="flex items-center gap-2">
                        <a href={todayUrl} className="inline-flex items-center px-3 py-1.5 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition shadow-sm hover:shadow-md">
                            Today
                        </a>
                        <button onClick={prevUrl} className="inline-flex items-center justify-center w-8 h-8 bg-white border border-gray-200 text-gray-500 hover:text-gray-800 hover:bg-gray-50 rounded-lg transition shadow-sm hover:shadow-md">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <h3 className="text-xl font-bold text-gray-800 text-center min-w-[180px]">{currentLabel()}</h3>
                        <button onClick={nextUrl} className="inline-flex items-center justify-center w-8 h-8 bg-white border border-gray-200 text-gray-500 hover:text-gray-800 hover:bg-gray-50 rounded-lg transition shadow-sm hover:shadow-md">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    <form onSubmit={handleJump} className="flex items-center gap-2">
                        <input type="hidden" name="day" value={jump.day} onChange={(e) => setJump({ ...jump, day: parseInt(e.target.value) })} />
                        <select
                            name="view"
                            value={view}
                            onChange={(e) => handleViewChange(e.target.value)}
                            className="border-gray-200 text-gray-700 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 bg-white shadow-sm hover:shadow-md transition"
                        >
                            {['day', 'week', 'month', 'year'].map((v) => (
                                <option key={v} value={v}>{v.charAt(0).toUpperCase() + v.slice(1)}</option>
                            ))}
                        </select>
                        {view !== 'year' && (
                            <select
                                name="month"
                                value={jump.month}
                                onChange={(e) => setJump({ ...jump, month: parseInt(e.target.value) })}
                                className="border-gray-200 text-gray-700 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 bg-white shadow-sm hover:shadow-md transition"
                            >
                                {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                                    <option key={m} value={m}>{monthName(m)}</option>
                                ))}
                            </select>
                        )}
                        <select
                            name="year"
                            value={jump.year}
                            onChange={(e) => setJump({ ...jump, year: parseInt(e.target.value) })}
                            className="border-gray-200 text-gray-700 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 bg-white shadow-sm hover:shadow-md transition"
                        >
                            {years.map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                        <button type="submit" className="inline-flex items-center text-gray-500 hover:text-gray-700 px-2" title="Apply">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        </button>
                    </form>
                </div>

                {view === 'day' && <DayView />}
                {(view === 'week' || view === 'month') && <WeekMonthView />}
                {view === 'year' && <YearView />}
            </div>

            <div className="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                <h3 className="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Task Status Legend</h3>
                <div className="flex flex-wrap gap-2">
                    {Object.entries(statusBar).map(([status, s]) => (
                        <div key={status} className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-l-4 bg-white shadow-sm">
                            <span className={`w-2.5 h-2.5 rounded-full ${s.border.replace('border-l-', 'bg-').replace('500', '500')}`} style={{ backgroundColor: s.color }}></span>
                            <span className="text-xs font-medium text-gray-700">{statusLabel(status)}</span>
                        </div>
                    ))}
                </div>
            </div>

            <div className="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-semibold text-gray-800">Milestones</h3>
                    <span className="text-[10px] px-2.5 py-1 bg-gray-100 text-gray-600 rounded-full font-semibold uppercase tracking-wider">Coming soon</span>
                </div>
                <p className="text-sm text-gray-500">Milestone tracking will be added here in a future update.</p>
            </div>
        </div>
    );
}
