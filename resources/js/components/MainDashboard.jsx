import React, { useEffect, useRef } from 'react';

const colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1'];

const backgroundColors = (count) => Array.from({ length: count }, (_, i) => colors[i % colors.length]);

const Card = ({ children, className = '', title, subtitle }) => (
    <div className={`bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition overflow-hidden ${className}`}>
        {(title || subtitle) && (
            <div className="px-5 py-4 border-b border-gray-100 bg-slate-50/70">
                {title && <h4 className="text-[11px] font-bold text-gray-800 uppercase tracking-wide">{title}</h4>}
                {subtitle && <p className="text-xs text-gray-500 mt-0.5">{subtitle}</p>}
            </div>
        )}
        {children}
    </div>
);

const StatCard = ({ href, title, value, caption, color = 'blue' }) => {
    const top = {
        blue: 'border-t-blue-500',
        green: 'border-t-green-500',
        amber: 'border-t-amber-500',
        indigo: 'border-t-indigo-500',
        red: 'border-t-red-500',
    }[color] || 'border-t-blue-500';
    return (
        <a href={href} className={`flex-1 min-w-[140px] bg-white border border-gray-100 border-t-4 ${top} rounded-2xl p-4 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200`}>
            <h4 className="text-[10px] font-semibold text-gray-500 uppercase tracking-wide">{title}</h4>
            <p className="text-2xl font-extrabold text-gray-900 mt-2 leading-tight">{value}</p>
            <p className="text-[9px] text-gray-400 mt-1 leading-tight">{caption}</p>
        </a>
    );
};

const formatMonth = (month, year) => {
    const d = new Date(year, month - 1, 1);
    return d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
};

export default function MainDashboard({
    user,
    availableMonths,
    selectedMonth,
    selectedYear,
    selectedMonthNumber,
    isAdmin,
    canApproveTimesheets,
    canApproveOtForms,
    activeUsersCount,
    activeProjectsCount,
    lastSync,
    pendingTimesheetApprovalCount,
    pendingOtApprovalCount,
    activeTrainingSessions,
    recentActions,
    recentUpdates,
    otMonthlyData,
    otProjectData,
    otStaffData,
    dashboardUrl,
    routes,
}) {
    const monthlyRef = useRef(null);
    const projectRef = useRef(null);
    const staffRef = useRef(null);

    useEffect(() => {
        if (!window.Chart || !monthlyRef.current || !projectRef.current || !staffRef.current) return;
        if (window.ChartDataLabels) {
            window.Chart.register(window.ChartDataLabels);
        }

        const charts = [];

        if (monthlyRef.current) {
            charts.push(
                new window.Chart(monthlyRef.current, {
                    type: 'bar',
                    data: {
                        labels: otMonthlyData.map((d) => d.label),
                        datasets: [
                            {
                                label: 'Total OT Hours',
                                data: otMonthlyData.map((d) => d.hours),
                                backgroundColor: '#3B82F6',
                                borderRadius: 4,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            datalabels: { color: '#FFFFFF', font: { weight: 'bold', size: 11 }, formatter: (v) => v.toFixed(1) },
                            tooltip: { callbacks: { label: (c) => `${c.parsed.y.toFixed(2)} hours` } },
                        },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Hours' } },
                        },
                    },
                })
            );
        }

        if (projectRef.current) {
            charts.push(
                new window.Chart(projectRef.current, {
                    type: 'doughnut',
                    data: {
                        labels: otProjectData.map((d) => d.label),
                        datasets: [
                            {
                                data: otProjectData.map((d) => d.hours),
                                backgroundColor: backgroundColors(otProjectData.length),
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: { position: 'right' },
                            datalabels: { color: '#FFFFFF', font: { weight: 'bold', size: 11 }, formatter: (v) => v.toFixed(2) },
                            tooltip: {
                                callbacks: {
                                    label: (c) => {
                                        const total = c.dataset.data.reduce((a, b) => a + b, 0);
                                        const pct = total > 0 ? ((c.parsed / total) * 100).toFixed(1) : 0;
                                        return `${c.label}: ${c.parsed.toFixed(2)} hours (${pct}%)`;
                                    },
                                },
                            },
                        },
                    },
                })
            );
        }

        if (staffRef.current) {
            charts.push(
                new window.Chart(staffRef.current, {
                    type: 'bar',
                    data: {
                        labels: otStaffData.map((d) => d.label),
                        datasets: [
                            {
                                label: 'Total OT Hours',
                                data: otStaffData.map((d) => d.hours),
                                backgroundColor: backgroundColors(otStaffData.length),
                                borderRadius: 4,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            datalabels: { color: '#FFFFFF', font: { weight: 'bold', size: 11 }, anchor: 'center', align: 'center', formatter: (v) => v.toFixed(2) },
                            tooltip: { callbacks: { label: (c) => `${c.parsed.y.toFixed(2)} hours` } },
                        },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Hours' } },
                            x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 30, font: { size: 10 } } },
                        },
                    },
                })
            );
        }

        return () => charts.forEach((c) => c.destroy());
    }, [otMonthlyData, otProjectData, otStaffData]);

    const handleMonthChange = (value) => {
        window.location.href = `${dashboardUrl}?month=${value}`;
    };

    return (
        <div className="max-w-7xl mx-auto space-y-4">
            <div className="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-md p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-white">
                <div>
                    <h3 className="text-lg font-bold">Welcome, {user.name}</h3>
                    <p className="text-sm text-blue-100 mt-0.5">
                        Role: <span className="font-semibold">{user.role}</span>
                        {user.department && <span className="ml-1">| Department: <span className="font-semibold">{user.department}</span></span>}
                    </p>
                </div>
                {availableMonths.length > 0 && (
                    <div className="flex items-center gap-2">
                        <label htmlFor="month" className="text-xs font-medium text-blue-100">Filter OT charts:</label>
                        <select
                            id="month"
                            value={selectedMonth}
                            onChange={(e) => handleMonthChange(e.target.value)}
                            className="block rounded-lg border-0 bg-white/10 text-white text-xs py-1.5 px-2 focus:ring-2 focus:ring-white/30 focus:outline-none cursor-pointer"
                        >
                            {availableMonths.map((m) => (
                                <option key={m.value} value={m.value} className="text-gray-900">{m.label}</option>
                            ))}
                        </select>
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className="lg:col-span-3 flex flex-nowrap gap-3 overflow-x-auto pb-1">
                    {isAdmin && (
                        <>
                            <StatCard href={routes.adminUsers} title="Users" value={activeUsersCount} caption="Active users" color="indigo" />
                            <StatCard href={routes.adminProjectCodes} title="Project Codes" value={activeProjectsCount} caption="Active projects" color="blue" />
                            <StatCard href={routes.adminDesknetSync} title="Last Sync" value={lastSync} caption="Desknet sync status" color="amber" />
                        </>
                    )}
                    {canApproveTimesheets && (
                        <StatCard href={routes.timesheetApprovals} title="Pending Timesheet Approvals" value={pendingTimesheetApprovalCount} caption="Awaiting your approval" color="green" />
                    )}
                    {canApproveOtForms && (
                        <StatCard href={routes.otApprovals} title="Pending OT Approvals" value={pendingOtApprovalCount} caption="Awaiting your approval" color="red" />
                    )}
                </div>

                {activeTrainingSessions.length > 0 && (
                    <div className="lg:col-span-3">
                        <Card title="Training Sessions" subtitle="Active training sessions available for attendance">
                            <div className="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                {activeTrainingSessions.map((session) => (
                                    <div key={session.id} className="flex items-center justify-between p-4 bg-white border border-gray-100 rounded-2xl shadow-sm hover:shadow-md transition">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs font-bold text-gray-900">{session.name}</span>
                                                {session.is_active ? (
                                                    <span className="px-1.5 py-0.5 text-[10px] rounded-full bg-green-100 text-green-800 font-semibold">Active</span>
                                                ) : (
                                                    <span className="px-1.5 py-0.5 text-[10px] rounded-full bg-gray-100 text-gray-800 font-semibold">Inactive</span>
                                                )}
                                            </div>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {session.training_date} &middot; {session.time_in} - {session.time_out} &middot; {session.venue}
                                            </p>
                                        </div>
                                        <div>
                                            {session.is_active && !session.attended && (
                                                <a href={routes.trainingAttendance} className="px-2.5 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition">Mark</a>
                                            )}
                                            {session.attended && (
                                                <span className="px-2.5 py-1.5 bg-green-100 text-green-800 rounded-lg text-xs font-semibold">Attended</span>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    </div>
                )}

                {availableMonths.length > 0 && (
                    <div className="lg:col-span-3 grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div>
                            <Card title="OT Hours by Project" subtitle={formatMonth(selectedMonthNumber, selectedYear)}>
                                <div className="p-4 h-[32rem]">
                                    {otProjectData.length > 0 ? (
                                        <canvas ref={projectRef}></canvas>
                                    ) : (
                                        <p className="text-sm text-gray-400 text-center py-20">No OT project data.</p>
                                    )}
                                </div>
                            </Card>
                        </div>

                        <div className="flex flex-col gap-4">
                            <Card title="Total OT Hours by Month" subtitle="All approved OT forms">
                                <div className="p-4 h-60">
                                    {otMonthlyData.length > 0 ? (
                                        <div className="overflow-x-auto" style={{ maxWidth: '100%' }}>
                                            <div style={{ height: '100%', minWidth: otMonthlyData.length * 60 }}>
                                                <canvas ref={monthlyRef}></canvas>
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-400 text-center py-12">No monthly OT data.</p>
                                    )}
                                </div>
                            </Card>

                            <Card title="OT Hours by Staff" subtitle={formatMonth(selectedMonthNumber, selectedYear)}>
                                <div className="p-4 h-80">
                                    {otStaffData.length > 0 ? (
                                        <div className="overflow-x-auto" style={{ maxWidth: '100%' }}>
                                            <div style={{ height: '100%', minWidth: otStaffData.length * 60 }}>
                                                <canvas ref={staffRef}></canvas>
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-400 text-center py-12">No staff OT data.</p>
                                    )}
                                </div>
                            </Card>
                        </div>
                    </div>
                )}

                <Card title="Recent Actions" subtitle="Your recent Timesheet / OT Form activity">
                    <div className="p-4 h-52 overflow-y-auto">
                        {recentActions.length === 0 ? (
                            <p className="text-xs text-gray-400 text-center py-16">No recent actions.</p>
                        ) : (
                            <ul className="space-y-2">
                                {recentActions.map((action, i) => (
                                    <li key={i} className="flex items-start gap-3 p-2 rounded-xl hover:bg-slate-50/70 transition">
                                        <div className="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                            <svg className="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-xs font-bold text-gray-900 truncate">
                                                {action.type} <span className="text-gray-500 font-normal">{action.action}</span>
                                            </p>
                                            <p className="text-xs text-gray-500 truncate">{action.description} &middot; {action.time}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </Card>

                <Card title="Recent Updates" subtitle="Status changes on your Timesheets / OT Forms">
                    <div className="p-4 h-52 overflow-y-auto">
                        {recentUpdates.length === 0 ? (
                            <p className="text-xs text-gray-400 text-center py-16">No recent updates.</p>
                        ) : (
                            <ul className="space-y-2">
                                {recentUpdates.map((update, i) => (
                                    <li key={i} className="flex items-start gap-3 p-2 rounded-xl hover:bg-slate-50/70 transition">
                                        <div className="w-7 h-7 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                            <svg className="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-xs font-bold text-gray-900 truncate">
                                                {update.type} <span className="text-gray-500 font-normal">{update.action}</span>
                                            </p>
                                            {update.status_label && (
                                                <p className="text-xs text-gray-500 truncate">
                                                    {update.status_label}
                                                    {update.actor_name && <span> by {update.actor_name}</span>}
                                                    &middot; {update.time}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </Card>
            </div>
        </div>
    );
}
