import React, { useEffect, useMemo, useState } from 'react';

const statusColumns = {
    completed: 'Done',
    in_progress: 'Working on it',
    on_hold: 'Stuck',
    not_started: 'Not Started',
};

const statusOptions = {
    completed: 'Done',
    in_progress: 'Working on it',
    on_hold: 'Stuck',
    not_started: 'Not Started',
    cancelled: 'Cancelled',
};

const statusHeaderColors = {
    completed: 'bg-green-100',
    in_progress: 'bg-blue-100',
    on_hold: 'bg-red-100',
    not_started: 'bg-gray-100',
};

const statusBadgeColors = {
    completed: 'bg-green-500',
    in_progress: 'bg-blue-500',
    on_hold: 'bg-red-500',
    not_started: 'bg-gray-500',
    cancelled: 'bg-red-500',
};

export default function ProjectKanban({ tasks: initialTasks = [], csrfToken, addTaskUrl }) {
    const [tasks, setTasks] = useState(initialTasks);
    const [open, setOpen] = useState({ type: null, taskId: null });
    const [expanded, setExpanded] = useState({ comments: new Set(), attachments: new Set() });
    const [dragOverStatus, setDragOver] = useState(null);

    useEffect(() => {
        const close = () => setOpen({ type: null, taskId: null });
        document.addEventListener('click', close);
        return () => document.removeEventListener('click', close);
    }, []);

    const grouped = useMemo(() => {
        const map = {};
        Object.keys(statusColumns).forEach((key) => (map[key] = []));
        tasks.forEach((task) => {
            if (map[task.status] !== undefined) map[task.status].push(task);
        });
        Object.keys(map).forEach((key) => {
            map[key].sort((a, b) => (a.phase_id ?? Number.MAX_SAFE_INTEGER) - (b.phase_id ?? Number.MAX_SAFE_INTEGER));
        });
        return map;
    }, [tasks]);

    const updateTask = (taskId, updates) => {
        setTasks((prev) => prev.map((t) => (t.id === taskId ? { ...t, ...updates } : t)));
    };

    const handleInlineUpdate = async (taskId, url, payload, onSuccess) => {
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.success) {
                onSuccess(data.task);
                setOpen({ type: null, taskId: null });
            } else {
                alert(data.message || 'Update failed.');
            }
        } catch (e) {
            alert('Update failed. Please try again.');
        }
    };

    const toggle = (type, taskId, e) => {
        e.stopPropagation();
        if (open.type === type && open.taskId === taskId) {
            setOpen({ type: null, taskId: null });
        } else {
            setOpen({ type, taskId });
        }
    };

    const toggleSection = (section, taskId, e) => {
        e.stopPropagation();
        setExpanded((prev) => {
            const next = new Set(prev[section]);
            if (next.has(taskId)) next.delete(taskId);
            else next.add(taskId);
            return { ...prev, [section]: next };
        });
    };

    const renderColumn = (columnTasks) => {
        if (columnTasks.length === 0) {
            return <div className="text-center py-6"><p className="text-xs text-gray-400">No tasks</p></div>;
        }
        let lastPhaseId = null;
        return columnTasks.map((task) => {
            const currentPhaseId = task.phase_id ?? null;
            const showDivider = lastPhaseId !== currentPhaseId;
            lastPhaseId = currentPhaseId;
            return (
                <React.Fragment key={task.id}>
                    {showDivider && (
                        <div className="flex items-center gap-2 my-2">
                            <div className="flex-1 h-px bg-gradient-to-r from-transparent via-gray-300 to-transparent"></div>
                            <span className="text-[10px] uppercase tracking-wider text-gray-500 font-semibold bg-white px-2 rounded">{task.phase_name || 'No Phase'}</span>
                            <div className="flex-1 h-px bg-gradient-to-r from-transparent via-gray-300 to-transparent"></div>
                        </div>
                    )}
                    {renderTask(task)}
                </React.Fragment>
            );
        });
    };

    const renderTask = (task) => {
        const isMenuOpen = open.type === 'menu' && open.taskId === task.id;
        const isStatusOpen = open.type === 'status' && open.taskId === task.id;
        const isDateOpen = open.type === 'date' && open.taskId === task.id;
        const isWeightOpen = open.type === 'weight' && open.taskId === task.id;
        const showComments = expanded.comments.has(task.id);
        const showAttachments = expanded.attachments.has(task.id);

        return (
            <div
                className="bg-white rounded-xl p-4 border border-gray-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition duration-200 relative group cursor-grab active:cursor-grabbing"
                draggable
                onDragStart={(e) => {
                    e.dataTransfer.setData('text/plain', task.id);
                    e.dataTransfer.effectAllowed = 'move';
                }}
                onDragEnd={() => setDragOver(null)}
            >
                <div className="flex items-start justify-between mb-3 gap-2">
                    <a href={task.show_url} className="text-sm font-semibold text-gray-900 line-clamp-2 flex-1 hover:text-indigo-600">{task.task_name}</a>
                    <a href={task.edit_url} className="text-gray-400 hover:text-indigo-600 flex-shrink-0 p-1 rounded hover:bg-gray-100" title="Edit">
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </a>
                    <div className="relative flex-shrink-0">
                        <button onClick={(e) => toggle('menu', task.id, e)} type="button" className="text-gray-400 hover:text-gray-600 focus:outline-none p-1 rounded hover:bg-gray-100">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                            </svg>
                        </button>
                        {isMenuOpen && (
                            <div className="absolute right-0 mt-1 w-28 bg-white rounded-xl shadow-lg ring-1 ring-black/5 border border-gray-100 z-20 py-1" onClick={(e) => e.stopPropagation()}>
                                <a href={task.edit_url} className="block px-4 py-2 text-xs text-gray-700 hover:bg-gray-100">Edit</a>
                                <form action={task.delete_url} method="POST" onSubmit={(e) => { if (!window.confirm('Delete this task?')) e.preventDefault(); }}>
                                    <input type="hidden" name="_token" value={csrfToken} />
                                    <input type="hidden" name="_method" value="DELETE" />
                                    <button type="submit" className="w-full text-left px-4 py-2 text-xs text-red-600 hover:bg-gray-100">Delete</button>
                                </form>
                            </div>
                        )}
                    </div>
                </div>

                <div className="flex items-center gap-2 mb-3 flex-wrap">
                    <div className="relative flex-shrink-0">
                        <button onClick={(e) => toggle('status', task.id, e)} type="button" className={`inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium text-white ${statusBadgeColors[task.status] || 'bg-gray-500'} hover:opacity-80 cursor-pointer`}>
                            {statusOptions[task.status] || task.status}
                            <svg className="w-3 h-3 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        {isStatusOpen && (
                            <div className="absolute left-0 mt-1 w-36 bg-white rounded-xl shadow-lg ring-1 ring-black/5 border border-gray-100 z-30 py-1" onClick={(e) => e.stopPropagation()}>
                                {Object.entries(statusOptions).map(([value, label]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            handleInlineUpdate(task.id, task.update_url, { status: value }, (updated) => updateTask(task.id, { status: updated.status }));
                                        }}
                                        className="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-indigo-50 flex items-center gap-2"
                                    >
                                        <span className={`w-2 h-2 rounded-full ${statusBadgeColors[value]}`}></span>
                                        {label}
                                        {task.status === value && <svg className="w-3 h-3 ml-auto text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/></svg>}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="relative flex-shrink-0">
                        <button onClick={(e) => toggle('date', task.id, e)} type="button" className="inline-flex items-center gap-1 text-[11px] text-gray-500 bg-gray-100 px-2 py-1 rounded-full hover:bg-gray-200 cursor-pointer">
                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>{task.end_date_revise_formatted || task.end_date_plan_formatted || 'Set date'}</span>
                        </button>
                        {isDateOpen && (
                            <div className="absolute left-0 mt-1 bg-white rounded-xl shadow-lg ring-1 ring-black/5 border border-gray-100 z-30 p-2" onClick={(e) => e.stopPropagation()}>
                                <input
                                    type="date"
                                    className="text-xs border border-gray-300 rounded px-2 py-1 focus:border-indigo-500 focus:ring-indigo-500"
                                    defaultValue={task.end_date_revise || ''}
                                    onClick={(e) => e.stopPropagation()}
                                    onChange={(e) => {
                                        e.stopPropagation();
                                        const value = e.target.value;
                                        handleInlineUpdate(task.id, task.update_url, { end_date_revise: value || null }, (updated) => updateTask(task.id, { end_date_revise_formatted: updated.end_date_revise, end_date_revise: updated.end_date_revise_raw }));
                                    }}
                                />
                            </div>
                        )}
                    </div>

                    <div className="relative flex-shrink-0">
                        <button onClick={(e) => toggle('weight', task.id, e)} type="button" className="inline-flex items-center gap-0.5 text-[11px] text-gray-500 bg-gray-100 px-2 py-1 rounded-full hover:bg-gray-200 cursor-pointer">
                            <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 7v3m6-3v3M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                            </svg>
                            <span>{task.weight ?? 0}%</span>
                        </button>
                        {isWeightOpen && (
                            <div className="absolute left-0 mt-1 bg-white rounded-xl shadow-lg ring-1 ring-black/5 border border-gray-100 z-30 p-2" onClick={(e) => e.stopPropagation()}>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    className="w-16 text-xs border border-gray-300 rounded px-2 py-1 focus:border-indigo-500 focus:ring-indigo-500"
                                    defaultValue={task.weight ?? 0}
                                    onClick={(e) => e.stopPropagation()}
                                    onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); e.target.blur(); } }}
                                    onBlur={(e) => {
                                        const value = parseInt(e.target.value, 10);
                                        if (isNaN(value) || value < 0 || value > 100) {
                                            alert('Weight must be between 0 and 100.');
                                            return;
                                        }
                                        handleInlineUpdate(task.id, task.update_url, { weight: value }, (updated) => updateTask(task.id, { weight: updated.weight }));
                                    }}
                                />
                                <p className="text-[10px] text-gray-400 mt-1">Max 100% per phase</p>
                            </div>
                        )}
                    </div>
                </div>

                {task.remarks && <p className="text-xs text-gray-500 mb-3 line-clamp-2">{task.remarks}</p>}

                <div className="flex items-center gap-2 mb-3">
                    <div className="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div className="h-2 rounded-full bg-emerald-500" style={{ width: `${Math.min(task.progress_actual, 100)}%` }}></div>
                    </div>
                    <span className="text-xs text-gray-600">{task.progress_actual}%</span>
                </div>

                <div className="flex items-center justify-between pt-3 border-t border-gray-100">
                    {task.assigned_to_name ? (
                        <a href={task.assigned_to_url} className="flex items-center gap-1.5 text-xs text-gray-500 hover:text-indigo-600">
                            <div className="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-[11px] font-bold text-indigo-600 ring-2 ring-white shadow-sm">{task.assigned_to_name.charAt(0)}</div>
                            <span>{task.assigned_to_name}</span>
                        </a>
                    ) : (
                        <span className="flex items-center gap-1.5 text-xs text-gray-400">
                            <div className="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 ring-2 ring-white shadow-sm">
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <span>Unassigned</span>
                        </span>
                    )}
                    {task.plan_delay_days > 0 && <span className="text-red-600 font-semibold text-xs">{task.plan_delay_days}d delay</span>}
                    <div className="flex items-center gap-3">
                        <button onClick={(e) => toggleSection('comments', task.id, e)} className="flex items-center gap-1 text-xs text-gray-400 hover:text-indigo-600 focus:outline-none">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <span>{task.comments_count}</span>
                        </button>
                        <button onClick={(e) => toggleSection('attachments', task.id, e)} className="flex items-center gap-1 text-xs text-gray-400 hover:text-indigo-600 focus:outline-none">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                            <span>{task.attachments_count}</span>
                        </button>
                    </div>
                </div>

                {showComments && (
                    <div className="mt-3 space-y-2">
                        {task.comments.length > 0 && (
                            <div className="space-y-2 max-h-40 overflow-y-auto">
                                {task.comments.map((comment) => (
                                    <div key={comment.id} className="bg-gray-50 rounded p-2 text-xs">
                                        <div className="flex items-center justify-between mb-1">
                                            <span className="font-semibold text-gray-700">{comment.user_name || 'Unknown'}</span>
                                            <span className="text-gray-400">{comment.created_at}</span>
                                        </div>
                                        <p className="text-gray-600 whitespace-pre-wrap">{comment.comment}</p>
                                        {comment.is_owner && (
                                            <form action={comment.delete_url} method="POST" className="mt-1" onSubmit={(e) => { if (!window.confirm('Delete this comment?')) e.preventDefault(); }}>
                                                <input type="hidden" name="_token" value={csrfToken} />
                                                <input type="hidden" name="_method" value="DELETE" />
                                                <button type="submit" className="text-red-500 hover:text-red-700 text-[10px]">Delete</button>
                                            </form>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                        <form action={task.comment_store_url} method="POST" className="flex flex-col gap-1">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <input type="hidden" name="view" value="kanban" />
                            <textarea name="comment" rows="2" placeholder="Add a comment..." className="w-full rounded border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500 resize-none" required></textarea>
                            <button type="submit" className="self-end px-2 py-1 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700">Post</button>
                        </form>
                    </div>
                )}

                {showAttachments && (
                    <div className="mt-3 space-y-2">
                        {task.attachments.length > 0 && (
                            <div className="space-y-1 max-h-40 overflow-y-auto">
                                {task.attachments.map((attachment) => (
                                    <div key={attachment.id} className="bg-gray-50 rounded p-2 text-xs flex items-center justify-between gap-2">
                                        <a href={attachment.show_url} target="_blank" className="text-indigo-600 hover:text-indigo-800 truncate" title={attachment.file_name}>
                                            {attachment.file_name}
                                        </a>
                                        {attachment.is_owner && (
                                            <form action={attachment.delete_url} method="POST" onSubmit={(e) => { if (!window.confirm('Delete this file?')) e.preventDefault(); }}>
                                                <input type="hidden" name="_token" value={csrfToken} />
                                                <input type="hidden" name="_method" value="DELETE" />
                                                <button type="submit" className="text-red-500 hover:text-red-700 text-[10px] whitespace-nowrap">Delete</button>
                                            </form>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                        <form action={task.attachment_store_url} method="POST" encType="multipart/form-data" className="flex flex-col gap-1">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <input type="hidden" name="view" value="kanban" />
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input type="file" name="attachment" className="text-xs text-gray-600 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" required />
                            </label>
                            <button type="submit" className="self-end px-2 py-1 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700">Upload</button>
                        </form>
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="p-4">
            <div className="flex items-center justify-between mb-4">
                <div>
                    <h3 className="text-base font-semibold text-gray-800">Project Tasks</h3>
                    <p className="text-sm text-gray-500 mt-1">{tasks.length} task{tasks.length !== 1 ? 's' : ''}</p>
                </div>
                <a href={addTaskUrl} className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 hover:shadow-md transition shadow-sm">
                    Add Task
                </a>
            </div>

            {tasks.length === 0 ? (
                <div className="bg-white border border-gray-200 rounded-lg p-8 text-center">
                    <svg className="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p className="text-sm text-gray-500 mt-2">No tasks yet.</p>
                    <p className="text-xs text-gray-400 mt-1">Add a task to get started.</p>
                </div>
            ) : (
                <div className="flex gap-4 overflow-x-auto pb-2">
                    {Object.entries(statusColumns).map(([statusKey, statusLabel]) => (
                        <div
                            key={statusKey}
                            className={`bg-slate-50/70 rounded-2xl border border-gray-100 shadow-sm flex flex-col flex-1 min-w-[280px] max-w-[320px] overflow-hidden ${dragOverStatus === statusKey ? 'ring-2 ring-indigo-300' : ''}`}
                            onDragOver={(e) => { e.preventDefault(); setDragOver(statusKey); }}
                            onDrop={(e) => {
                                e.preventDefault();
                                setDragOver(null);
                                const taskId = e.dataTransfer.getData('text/plain');
                                const task = tasks.find((t) => String(t.id) === taskId);
                                if (task && task.status !== statusKey) {
                                    handleInlineUpdate(task.id, task.update_url, { status: statusKey }, (updated) => updateTask(task.id, { status: updated.status }));
                                }
                            }}
                        >
                            <div className={`px-4 py-3 border-b border-gray-100/50 flex items-center justify-between ${statusHeaderColors[statusKey]}`}>
                                <div className="flex items-center gap-2">
                                    <span className={`w-2.5 h-2.5 rounded-full ${statusBadgeColors[statusKey]}`}></span>
                                    <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wide">{statusLabel}</h4>
                                </div>
                                <span className="text-xs font-bold text-gray-600 bg-white/80 px-2 py-0.5 rounded-full border border-gray-200 shadow-sm">{grouped[statusKey].length}</span>
                            </div>
                            <div className="p-3 space-y-3 flex-1">{renderColumn(grouped[statusKey])}</div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
