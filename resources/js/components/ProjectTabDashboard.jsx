import { useEffect, useRef } from 'react';

const circumference = 2 * Math.PI * 20;

const Card = ({ title, subtitle, className = '', children }) => (
    <div className={`bg-white border border-gray-100 rounded-2xl shadow-sm p-5 ${className}`}>
        {(title || subtitle) && (
            <div className="mb-4">
                {title && <h3 className="text-base font-semibold text-gray-800">{title}</h3>}
                {subtitle && <p className="text-xs text-gray-400 mt-0.5">{subtitle}</p>}
            </div>
        )}
        {children}
    </div>
);

const StatCard = ({ title, value, valueColor, children }) => (
    <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-4 flex items-center justify-between">
        <div>
            <p className="text-xs text-gray-500 font-medium uppercase tracking-wide">{title}</p>
            <p className={`text-2xl font-bold mt-1 ${valueColor}`}>{value}</p>
        </div>
        {children}
    </div>
);

const EmptyBox = ({ message }) => (
    <div className="text-center py-8 bg-gray-50 rounded-xl">
        <p className="text-sm text-gray-400">{message}</p>
    </div>
);

export default function ProjectTabDashboard({
    overallActualProgress = 0,
    overallPlanProgress = 0,
    variance = 0,
    delayedTasks = 0,
    totalTasks = 0,
    daysBehind = 0,
    taskStatusDistribution = [],
    phaseProgress = [],
}) {
    const pieRef = useRef(null);
    const barRef = useRef(null);

    useEffect(() => {
        if (!window.Chart) return;
        const charts = [];

        // Register datalabels plugin
        if (window.ChartDataLabels) {
            window.Chart.register(window.ChartDataLabels);
        }

        const visibleStatuses = taskStatusDistribution.filter(s => s.count > 0);

        if (visibleStatuses.length > 0 && pieRef.current) {
            charts.push(new window.Chart(pieRef.current, {
                type: 'pie',
                data: {
                    labels: visibleStatuses.map(s => s.label),
                    datasets: [{
                        data: visibleStatuses.map(s => s.count),
                        backgroundColor: visibleStatuses.map(s => s.color),
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            color: '#fff',
                            font: { weight: 'bold', size: 12 },
                            formatter: (value, context) => {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return percentage > 5 ? `${percentage}%` : '';
                            },
                        },
                    },
                },
            }));
        }

        if (phaseProgress.length > 0 && barRef.current) {
            charts.push(new window.Chart(barRef.current, {
                type: 'bar',
                data: {
                    labels: phaseProgress.map(p => p.name),
                    datasets: [
                        { label: 'Plan', data: phaseProgress.map(p => p.plan), backgroundColor: '#3b82f6', borderRadius: 5 },
                        { label: 'Actual', data: phaseProgress.map(p => p.actual), backgroundColor: '#22c55e', borderRadius: 5 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } },
                        tooltip: { backgroundColor: '#1F2937', padding: 10, cornerRadius: 8 },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            color: '#374151',
                            font: { weight: 'bold', size: 11 },
                            formatter: (value) => `${value}%`,
                        },
                    },
                    scales: {
                        y: { beginAtZero: true, max: 100, grid: { color: '#F3F4F6' } },
                        x: { grid: { display: false } },
                    },
                },
            }));
        }

        return () => charts.forEach(c => c.destroy());
    }, []);

    const filled = (overallActualProgress / 100) * circumference;
    const dashOffset = circumference - filled;

    return (
        <div className="space-y-4">
            {/* Top stat cards */}
            <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div className="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                    <p className="text-xs text-gray-500 font-medium uppercase tracking-wide mb-3">Overall Progress</p>
                    <div className="space-y-2">
                        <div>
                            <div className="flex justify-between text-xs mb-1">
                                <span className="text-gray-600">Plan</span>
                                <span className="font-semibold text-gray-900">{overallPlanProgress}%</span>
                            </div>
                            <div className="w-full bg-gray-200 rounded-full h-2">
                                <div className="bg-blue-500 h-2 rounded-full transition-all" style={{ width: `${overallPlanProgress}%` }}></div>
                            </div>
                        </div>
                        <div>
                            <div className="flex justify-between text-xs mb-1">
                                <span className="text-gray-600">Actual</span>
                                <span className="font-semibold text-gray-900">{overallActualProgress}%</span>
                            </div>
                            <div className="w-full bg-gray-200 rounded-full h-2">
                                <div className="bg-green-500 h-2 rounded-full transition-all" style={{ width: `${overallActualProgress}%` }}></div>
                            </div>
                        </div>
                    </div>
                </div>
                <StatCard title="Variance" value={`${variance >= 0 ? '+' : ''}${variance}%`} valueColor={variance >= 0 ? 'text-green-600' : 'text-red-600'} />
                <StatCard title="Days Behind" value={daysBehind} valueColor={daysBehind > 0 ? 'text-red-600' : 'text-gray-900'} />
                <StatCard title="Total Tasks" value={totalTasks} valueColor="text-gray-900" />
                <StatCard title="Overdue Subtask" value={delayedTasks} valueColor={delayedTasks > 0 ? 'text-red-600' : 'text-green-600'} />
            </div>

            {/* Subtask status cards */}
            <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                <Card title="Subtask Status Distribution" className="md:col-span-2">
                    {taskStatusDistribution.some(s => s.count > 0) ? (
                        <div className="flex items-center gap-5">
                            <div style={{ width: 150, height: 150 }}>
                                <canvas ref={pieRef}></canvas>
                            </div>
                            <div className="flex flex-col gap-2 text-xs">
                                {taskStatusDistribution.filter(s => s.count > 0).map(s => (
                                    <div key={s.status} className="flex items-center gap-2">
                                        <div className="w-3 h-3 rounded-sm" style={{ backgroundColor: s.color }}></div>
                                        <span className="text-gray-600 whitespace-nowrap">{s.label}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <EmptyBox message="No task data available." />
                    )}
                </Card>

                <Card title="Subtask Status Breakdown" className="md:col-span-3">
                    <table className="w-full text-xs">
                        <thead>
                            <tr className="border-b border-gray-200">
                                <th className="text-left py-2 px-2 font-bold text-gray-700 uppercase">Status</th>
                                <th className="text-center py-2 px-2 font-bold text-gray-700 uppercase">Count</th>
                                <th className="text-center py-2 px-2 font-bold text-gray-700 uppercase">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            {taskStatusDistribution.map(s => (
                                <tr key={s.status} className={s.bgClass}>
                                    <td className="py-2 px-2 font-medium text-gray-700">{s.label}</td>
                                    <td className="py-2 px-2 text-center text-gray-700">{s.count}</td>
                                    <td className="py-2 px-2 text-center text-gray-700">{totalTasks > 0 ? Math.round((s.count / totalTasks) * 100) : 0}%</td>
                                </tr>
                            ))}
                            <tr className="border-t-2 border-gray-300 font-bold">
                                <td className="py-2 px-2 text-gray-800">Total</td>
                                <td className="py-2 px-2 text-center text-gray-800">{totalTasks}</td>
                                <td className="py-2 px-2 text-center text-gray-800">100%</td>
                            </tr>
                        </tbody>
                    </table>
                </Card>
            </div>

            {/* Task progress bar chart */}
            <Card title="Task Progress" subtitle="Plan vs actual progress for each task">
                {phaseProgress.length > 0 ? (
                    <div style={{ height: 220 }}>
                        <canvas ref={barRef}></canvas>
                    </div>
                ) : (
                    <EmptyBox message="No phase data available." />
                )}
            </Card>

            {/* Task timeline */}
            {phaseProgress.length > 0 && (
                <Card title="Task Timeline" subtitle="Completion status of project tasks">
                    <div className="w-full bg-gray-200 rounded-full h-2.5 mb-5">
                        <div className="bg-blue-500 h-2.5 rounded-full transition-all" style={{ width: `${overallActualProgress}%` }}></div>
                    </div>
                    <div className="flex items-start justify-between">
                        {phaseProgress.map((phase, i) => (
                            <div key={i} className="flex flex-col items-center text-center flex-1 min-w-0 px-1">
                                <span className="text-gray-700 text-xs font-medium mb-1.5 truncate w-full">{phase.name}</span>
                                {phase.actual >= 100 ? (
                                    <div className="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center">
                                        <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                ) : phase.actual > 0 ? (
                                    <div className="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center">
                                        <span className="text-white text-[9px] font-bold">{phase.actual}%</span>
                                    </div>
                                ) : (
                                    <div className="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center">
                                        <div className="w-3 h-3 rounded-full bg-gray-400"></div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </Card>
            )}
        </div>
    );
}
