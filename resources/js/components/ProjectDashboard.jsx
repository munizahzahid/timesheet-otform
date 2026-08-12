import { useEffect, useRef } from 'react';

const colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];

const formatNumber = (n) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const truncate = (name, len = 25) => {
    const value = name || '';
    return value.length > len ? value.substring(0, len) + '...' : value;
};

const pct = (part, total) => (total > 0 ? (part / total) * 100 : 0);

const Card = ({ title, subtitle, className = '', children }) => (
    <div className={`bg-white border border-gray-100 rounded-2xl shadow-sm p-4 ${className}`}>
        {title && (
            <div className="mb-3">
                <h3 className="text-sm font-semibold text-gray-800">{title}</h3>
                {subtitle && <p className="text-xs text-gray-400 mt-0.5">{subtitle}</p>}
            </div>
        )}
        {children}
    </div>
);

const EmptyBox = ({ message }) => (
    <div className="text-center py-10 bg-gray-50 rounded-xl">
        <p className="text-sm text-gray-400">{message}</p>
    </div>
);

const StackedBar = ({ segments }) => {
    const total = segments.reduce((a, s) => a + s.value, 0);
    if (total <= 0) return <EmptyBox message="No data available." />;
    return (
        <div>
            <div className="flex flex-wrap gap-x-3 gap-y-1 mb-2 justify-center">
                {segments.map(s => (
                    <span key={s.label} className="inline-flex items-center gap-1.5 text-[10px] text-gray-600">
                        <span className="w-2 h-2 rounded-sm inline-block" style={{ backgroundColor: s.color }}></span>
                        {s.label}
                    </span>
                ))}
            </div>
            <div className="flex h-7 rounded-lg overflow-hidden">
                {segments.map(s => {
                    const w = (s.value / total) * 100;
                    if (w <= 0) return null;
                    return (
                        <div key={s.label}
                            className="flex items-center justify-center text-white text-[10px] font-semibold transition-all"
                            style={{ width: `${w}%`, backgroundColor: s.color }}
                            title={`${s.label}: ${s.value} (${w.toFixed(1)}%)`}>
                            {w >= 7 ? `${w.toFixed(1)}%` : ''}
                        </div>
                    );
                })}
            </div>
            <div className="flex justify-between text-[9px] text-gray-400 mt-1 px-0.5">
                <span>0%</span><span>20%</span><span>40%</span><span>60%</span><span>80%</span><span>100%</span>
            </div>
        </div>
    );
};

export default function ProjectDashboard({
    totalProjects = 0,
    activeProjects = 0,
    completedProjects = 0,
    delayedProjects = 0,
    staffTimeline = [],
    weekCount = 0,
    weekLabels = [],
    totalBudgetPlan = 0,
    totalBudgetActual = 0,
    budgetVariance = 0,
    budgetYear = '',
    availableYears = [],
    budgetProjects = [],
    progressProjects = [],
    projectTaskStatusData = [],
    dashboardUrl = '',
}) {
    const budgetChartRef = useRef(null);
    const progressChartRef = useRef(null);
    const projectTaskStatusRef = useRef(null);
    const budgetUtilizationRef = useRef(null);

    const completionRate = pct(completedProjects, totalProjects);
    const budgetUtilization = pct(totalBudgetActual, totalBudgetPlan);
    const budgetRemaining = Math.max(0, totalBudgetPlan - totalBudgetActual);

    useEffect(() => {
        if (!window.Chart) return;
        if (window.ChartDataLabels) {
            window.Chart.register(window.ChartDataLabels);
        }
        const charts = [];

        // Budget Plan vs Actual (vertical bars)
        if (budgetProjects.length > 0 && budgetChartRef.current) {
            const labels = budgetProjects.map(p => truncate(p.project_name, 16));
            const plan = budgetProjects.map(p => parseFloat(p.project_value) || 0);
            const actual = budgetProjects.map(p => parseFloat(p.actual_cost) || 0);

            charts.push(new window.Chart(budgetChartRef.current, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Budget (Plan)', data: plan, backgroundColor: '#3B82F6', borderRadius: 5, barPercentage: 0.7 },
                        { label: 'Actual Cost', data: actual, backgroundColor: '#22C55E', borderRadius: 5, barPercentage: 0.7 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } },
                        tooltip: {
                            backgroundColor: '#1F2937', padding: 10, cornerRadius: 8,
                            callbacks: {
                                label: (context) => `${context.dataset.label}: ${context.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
                            },
                        },
                        datalabels: {
                            color: '#374151',
                            font: { weight: 'bold', size: 11 },
                            anchor: 'end',
                            align: 'top',
                            offset: 4,
                            formatter: (v) => v.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }),
                        },
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 45, minRotation: 45, font: { size: 11 } } },
                        y: { beginAtZero: true, grid: { color: '#F3F4F6' }, padding: { top: 20 } },
                    },
                },
            }));
        }

        // Budget Utilization half-donut (top right)
        if (totalBudgetPlan > 0 && budgetUtilizationRef.current) {
            const actual = parseFloat(totalBudgetActual) || 0;
            const remaining = parseFloat(budgetRemaining) || 0;
            const total = actual + remaining;

            const centerTextPlugin = {
                id: 'centerText',
                afterDraw(chart) {
                    const { ctx } = chart;
                    ctx.save();
                    ctx.font = 'bold 14px sans-serif';
                    ctx.fillStyle = '#1F2937';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(`${budgetUtilization.toFixed(1)}%`, chart.width / 2, chart.height / 2);
                    ctx.restore();
                },
            };

            charts.push(new window.Chart(budgetUtilizationRef.current, {
                type: 'doughnut',
                data: {
                    labels: ['Actual Cost', 'Remaining'],
                    datasets: [{
                        data: [actual, remaining],
                        backgroundColor: [actual > totalBudgetPlan ? '#EF4444' : '#22C55E', '#3B82F6'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    circumference: 180,
                    rotation: -90,
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 10 } } },
                        tooltip: {
                            backgroundColor: '#1F2937', padding: 10, cornerRadius: 8,
                            callbacks: {
                                label: (c) => {
                                    const value = c.parsed;
                                    const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${c.label}: ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} (${pct}%)`;
                                },
                            },
                        },
                    },
                },
                plugins: [centerTextPlugin],
            }));
        }

        // Progress Summary (vertical grouped bars, bottom-left)
        if (progressProjects.length > 0 && progressChartRef.current) {
            charts.push(new window.Chart(progressChartRef.current, {
                type: 'bar',
                data: {
                    labels: progressProjects.map(p => truncate(p.project_name)),
                    datasets: [
                        { label: 'Plan Progress', data: progressProjects.map(p => p.overall_plan_progress || 0), backgroundColor: '#93C5FD', borderRadius: 5 },
                        { label: 'Actual Progress', data: progressProjects.map(p => p.overall_actual_progress || 0), backgroundColor: '#22C55E', borderRadius: 5 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } },
                        tooltip: { backgroundColor: '#1F2937', padding: 10, cornerRadius: 8 },
                        datalabels: {
                            color: '#374151',
                            font: { weight: 'bold', size: 11 },
                            anchor: 'end',
                            align: 'top',
                            offset: 4,
                            formatter: (v) => `${v}%`,
                        },
                    },
                    scales: {
                        y: { beginAtZero: true, max: 100, grid: { color: '#F3F4F6' }, title: { display: true, text: 'Progress (%)' }, padding: { top: 20 } },
                        x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 45, minRotation: 45 } },
                    },
                },
            }));
        }

        // Task status breakdown per active project (stacked horizontal bar)
        if (projectTaskStatusData.length > 0 && projectTaskStatusRef.current) {
            const labels = projectTaskStatusData.map(p => truncate(p.project_name, 20));
            const statusKeys = ['not_started', 'in_progress', 'completed', 'on_hold', 'cancelled'];
            const statusLabels = { not_started: 'Not Started', in_progress: 'In Progress', completed: 'Completed', on_hold: 'On Hold', cancelled: 'Cancelled' };
            const statusColors = { not_started: '#9CA3AF', in_progress: '#3B82F6', completed: '#22C55E', on_hold: '#F59E0B', cancelled: '#EF4444' };

            const barValuePlugin = {
                id: 'barValues',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    ctx.save();
                    ctx.font = 'bold 10px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillStyle = '#ffffff';
                    chart.data.datasets.forEach((dataset, i) => {
                        const meta = chart.getDatasetMeta(i);
                        meta.data.forEach((bar, j) => {
                            const value = dataset.data[j];
                            if (value > 0) {
                                const center = bar.getCenterPoint();
                                ctx.fillText(value, center.x, center.y);
                            }
                        });
                    });
                    ctx.restore();
                },
            };

            charts.push(new window.Chart(projectTaskStatusRef.current, {
                type: 'bar',
                plugins: [barValuePlugin],
                data: {
                    labels,
                    datasets: statusKeys.map(key => ({
                        label: statusLabels[key],
                        data: projectTaskStatusData.map(p => p[key] || 0),
                        backgroundColor: statusColors[key],
                        borderRadius: 2,
                    })),
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } },
                        tooltip: {
                            backgroundColor: '#1F2937',
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                footer: (items) => {
                                    const total = items.reduce((sum, it) => sum + (it.raw || 0), 0);
                                    return `Total: ${total} tasks`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: { beginAtZero: true, stacked: true, grid: { color: '#F3F4F6' } },
                        y: { stacked: true, grid: { display: false }, ticks: { autoSkip: false, font: { size: 11 } } },
                    },
                },
            }));
        }

        return () => charts.forEach(c => c.destroy());
    }, []);

    return (
        <>
            <div className="grid grid-cols-12 gap-4">
                {/* Top left: mini cards + stacked bars */}
                <div className="col-span-12 lg:col-span-8 flex flex-col gap-4">
                    {/* Top mini cards row */}
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                            <p className="text-xs text-gray-500 mb-1.5 font-medium">Budget Year</p>
                            <form method="GET" action={dashboardUrl}>
                                <select name="budget_year" defaultValue={budgetYear ?? 'all'} onChange={(e) => e.target.form.submit()}
                                        className="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="all">All</option>
                                    {availableYears.map((year) => (
                                        <option key={year} value={year}>{year}</option>
                                    ))}
                                </select>
                            </form>
                        </div>
                        <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                            <p className="text-xs text-gray-500 font-medium">Completion Rate</p>
                            <p className="text-xl font-bold text-gray-900 mt-1.5">{completionRate.toFixed(1)}%</p>
                        </div>
                        <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                            <p className="text-xs text-gray-500 font-medium">Delayed</p>
                            <p className={`text-xl font-bold mt-1.5 ${delayedProjects > 0 ? 'text-red-600' : 'text-gray-900'}`}>{delayedProjects}</p>
                        </div>
                    </div>

                    {/* Middle row: compact stats + half-donut */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        <Card title="Projects">
                            <div className="grid grid-cols-2 gap-y-2 gap-x-2">
                                <div><p className="text-xl font-bold text-gray-900">{totalProjects}</p><p className="text-xs text-gray-500 mt-0.5">Total</p></div>
                                <div><p className="text-xl font-bold text-green-600">{completedProjects}</p><p className="text-xs text-gray-500 mt-0.5">Completed</p></div>
                                <div><p className="text-xl font-bold text-blue-600">{activeProjects}</p><p className="text-xs text-gray-500 mt-0.5">Active</p></div>
                                <div><p className="text-xl font-bold text-red-600">{delayedProjects}</p><p className="text-xs text-gray-500 mt-0.5">Delayed</p></div>
                            </div>
                        </Card>
                        <Card title="Budget">
                            <div className="grid grid-cols-2 gap-y-2 gap-x-2">
                                <div><p className="text-base font-bold text-gray-900 break-all">{formatNumber(totalBudgetPlan)}</p><p className="text-xs text-gray-500 mt-0.5">Plan</p></div>
                                <div><p className="text-base font-bold text-green-600 break-all">{formatNumber(totalBudgetActual)}</p><p className="text-xs text-gray-500 mt-0.5">Actual</p></div>
                                <div><p className={`text-base font-bold break-all ${budgetVariance >= 0 ? 'text-blue-600' : 'text-red-600'}`}>{budgetVariance >= 0 ? '+' : ''}{formatNumber(budgetVariance)}</p><p className="text-xs text-gray-500 mt-0.5">Variance</p></div>
                                <div><p className="text-base font-bold text-gray-900">{budgetUtilization.toFixed(1)}%</p><p className="text-xs text-gray-500 mt-0.5">Utilized</p></div>
                            </div>
                        </Card>
                        <Card title="Budget Utilization" className="!p-3">
                            {totalBudgetPlan > 0 ? (
                                <div style={{ height: 190 }}>
                                    <canvas ref={budgetUtilizationRef}></canvas>
                                </div>
                            ) : (
                                <EmptyBox message="No budget data available." />
                            )}
                        </Card>
                    </div>
                </div>

                {/* Top right: task status */}
                <div className="col-span-12 lg:col-span-4 flex flex-col">
                    <Card title="Task Status by Project" subtitle="Active projects task breakdown" className="flex-1">
                        {projectTaskStatusData.length > 0 ? (
                            <div style={{ height: 280 }}>
                                <canvas ref={projectTaskStatusRef}></canvas>
                            </div>
                        ) : (
                            <EmptyBox message="No active projects with tasks to display." />
                        )}
                    </Card>
                </div>

                {/* Side-by-side scrollable charts */}
                <div className="col-span-12 lg:col-span-6">
                    <Card title="Progress Summary" subtitle="Plan vs actual progress for active projects">
                        {progressProjects.length > 0 ? (
                            <div className="overflow-x-auto" style={{ maxWidth: '100%' }}>
                                <div style={{ height: 300, minWidth: progressProjects.length * 60 }}>
                                    <canvas ref={progressChartRef}></canvas>
                                </div>
                            </div>
                        ) : (
                            <EmptyBox message="No active projects to display progress for." />
                        )}
                    </Card>
                </div>

                <div className="col-span-12 lg:col-span-6">
                    <Card title="Budget Plan vs Actual" subtitle="All projects for the selected year">
                        {budgetProjects.length > 0 ? (
                            <div className="overflow-x-auto" style={{ maxWidth: '100%' }}>
                                <div style={{ height: 300, minWidth: budgetProjects.length * 60 }}>
                                    <canvas ref={budgetChartRef}></canvas>
                                </div>
                            </div>
                        ) : (
                            <EmptyBox message="No budget data available for the selected year." />
                        )}
                    </Card>
                </div>
            </div>

            {/* Staff Project Timeline (full width) */}
            <Card title="Staff Project Timeline" subtitle="Active project allocation by week" className="mt-4">
                {staffTimeline.length > 0 && weekCount > 0 ? (
                    <div className="overflow-x-auto rounded-xl border border-gray-100">
                        <div className="min-w-max">
                            <div className="flex border-b bg-gray-50 sticky top-0">
                                <div className="w-56 flex-shrink-0 p-2.5 font-semibold text-xs text-gray-600 uppercase tracking-wide sticky left-0 bg-gray-50 z-10">Staff</div>
                                <div className="flex">
                                    {weekLabels.map((week, i) => (
                                        <div key={i} className={`flex-shrink-0 w-16 text-center text-[10px] py-2 border-l border-gray-100 ${week.isCurrentWeek ? 'bg-blue-50 font-semibold text-blue-700' : 'text-gray-500'}`}>
                                            {week.date}
                                        </div>
                                    ))}
                                </div>
                            </div>
                            {staffTimeline.map((staff, staffIndex) => (
                                <div key={staffIndex} className="flex border-b border-gray-100 hover:bg-gray-50 transition">
                                    <div className="w-56 flex-shrink-0 p-2.5 text-sm font-medium sticky left-0 bg-white z-10 border-r border-gray-100 flex items-center gap-2.5">
                                        <span className="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                            {(staff.name || '?').charAt(0).toUpperCase()}
                                        </span>
                                        <span className="truncate">{staff.name}</span>
                                    </div>
                                    <div className="flex relative" style={{ width: weekCount * 64, minHeight: Math.max(32, (staff.projects || []).length * 22 + 6) }}>
                                        {Array.from({ length: weekCount }).map((_, i) => (
                                            <div key={i} className={`absolute top-0 bottom-0 border-l ${weekLabels[i]?.isCurrentWeek ? 'border-blue-100 bg-blue-50/40' : 'border-gray-100'}`} style={{ left: i * 64, width: 64 }}></div>
                                        ))}
                                        {(staff.projects || []).map((project, projectIndex) => (
                                            <a key={projectIndex}
                                                href={project.url}
                                                className="absolute h-5 rounded-full text-white text-[10px] font-medium flex items-center px-2 overflow-hidden whitespace-nowrap shadow-sm transition hover:opacity-85 hover:shadow-md hover:scale-[1.02]"
                                                style={{
                                                    left: project.start_week * 64 + 3,
                                                    width: Math.max(60, project.duration_weeks * 64 - 6),
                                                    top: 3 + projectIndex * 22,
                                                    backgroundColor: colors[project.color_index ?? 0] ?? colors[0],
                                                }}
                                                title={`${project.name}: ${project.start_date} - ${project.end_date}`}
                                            >
                                                {project.name}
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : (
                    <EmptyBox message="No staff with active projects." />
                )}
            </Card>
        </>
    );
}
