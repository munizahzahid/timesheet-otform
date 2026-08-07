import React, { useMemo, useState } from 'react';

const statusColors = {
    active: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    completed: 'bg-blue-100 text-blue-800 border-blue-200',
    delayed: 'bg-red-100 text-red-800 border-red-200',
    on_hold: 'bg-amber-100 text-amber-800 border-amber-200',
    cancelled: 'bg-gray-100 text-gray-800 border-gray-200',
};

const statusDot = {
    active: 'bg-emerald-500',
    completed: 'bg-blue-500',
    delayed: 'bg-red-500',
    on_hold: 'bg-amber-500',
    cancelled: 'bg-gray-500',
};

const statusLabel = (status) => {
    if (!status) return 'Not Set';
    return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
};

export default function ProjectList({ projects, success, addUrl }) {
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        return projects.filter((project) => {
            const matchesQuery = !q ||
                (project.project_name || '').toLowerCase().includes(q) ||
                (project.project_code || '').toLowerCase().includes(q) ||
                (project.description || '').toLowerCase().includes(q);
            const matchesStatus = statusFilter === 'all' || project.status === statusFilter;
            return matchesQuery && matchesStatus;
        });
    }, [projects, query, statusFilter]);

    const counts = useMemo(() => {
        const c = { all: projects.length };
        Object.keys(statusColors).forEach((s) => (c[s] = 0));
        projects.forEach((p) => {
            if (c[p.status] !== undefined) c[p.status]++;
        });
        return c;
    }, [projects]);

    return (
        <div className="space-y-6">
            {success && (
                <div className="bg-green-50 border border-green-100 border-l-4 border-l-green-400 rounded-xl p-4 text-sm text-green-700 shadow-sm">
                    {success}
                </div>
            )}

            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                {['all', ...Object.keys(statusColors)].map((status) => (
                    <button
                        key={status}
                        type="button"
                        onClick={() => setStatusFilter(status)}
                        className={`text-left px-4 py-3 rounded-xl border transition shadow-sm ${
                            statusFilter === status
                                ? 'bg-white border-indigo-200 ring-1 ring-indigo-200'
                                : 'bg-white border-gray-100 hover:border-gray-200'
                        }`}
                    >
                        <div className="flex items-center gap-2">
                            <span className={`w-2 h-2 rounded-full ${status === 'all' ? 'bg-indigo-500' : statusDot[status] || 'bg-gray-400'}`}></span>
                            <span className="text-xs font-semibold text-gray-500 uppercase tracking-wide">{status === 'all' ? 'All' : statusLabel(status)}</span>
                        </div>
                        <p className="text-2xl font-bold text-gray-800 mt-1">{counts[status] ?? 0}</p>
                    </button>
                ))}
            </div>

            <div className="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <p className="text-sm text-gray-600">
                    Showing <span className="font-semibold text-gray-900">{filtered.length}</span> of <span className="font-semibold text-gray-900">{projects.length}</span> projects
                </p>
                <div className="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <input
                        type="text"
                        placeholder="Search projects..."
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        className="w-full sm:w-64 rounded-lg border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm transition"
                    />
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="w-full sm:w-40 rounded-lg border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm transition"
                    >
                        <option value="all">All statuses</option>
                        {Object.keys(statusColors).map((s) => (
                            <option key={s} value={s}>{statusLabel(s)}</option>
                        ))}
                    </select>
                </div>
            </div>

            {filtered.length === 0 ? (
                <div className="bg-white border border-gray-100 rounded-2xl p-12 text-center shadow-sm">
                    <svg className="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                    <h3 className="mt-4 text-sm font-medium text-gray-900">
                        {projects.length === 0 ? 'No projects yet' : 'No projects found'}
                    </h3>
                    <p className="mt-1 text-sm text-gray-500">
                        {projects.length === 0 ? 'Get started by creating your first project.' : 'Try adjusting your search or filter.'}
                    </p>
                    {projects.length === 0 && (
                        <div className="mt-6">
                            <a href={addUrl} className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 hover:shadow-md transition shadow-sm">
                                <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add Project
                            </a>
                        </div>
                    )}
                </div>
            ) : (
                <div className="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm">
                    <table className="min-w-full divide-y divide-gray-100">
                        <thead className="bg-slate-50/70">
                            <tr>
                                <th className="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Project</th>
                                <th className="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                                <th className="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th className="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Plan Period</th>
                                <th className="px-6 py-3 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Progress</th>
                                <th className="px-6 py-3 text-right text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-100">
                            {filtered.map((project) => (
                                <tr key={project.id} className="hover:bg-slate-50/50 transition group">
                                    <td className="px-6 py-4">
                                        <a href={project.show_url} className="text-sm font-semibold text-gray-800 hover:text-indigo-600 transition">
                                            {project.project_name}
                                        </a>
                                        {project.description && (
                                            <p className="text-xs text-gray-500 mt-0.5 truncate max-w-xs">{project.description}</p>
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-600 font-medium">{project.project_code ?? '—'}</td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border ${statusColors[project.status] ?? 'bg-gray-100 text-gray-800 border-gray-200'}`}>
                                            {statusLabel(project.status)}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-600">
                                        {project.start_date_plan_formatted && project.end_date_plan_formatted
                                            ? `${project.start_date_plan_formatted} — ${project.end_date_plan_formatted}`
                                            : '—'}
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-2">
                                            <div className="w-24 bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                                <div className="h-2.5 rounded-full bg-emerald-500 shadow-sm" style={{ width: `${Math.min(project.overall_actual_progress, 100)}%` }}></div>
                                            </div>
                                            <span className="text-xs text-gray-600 font-medium">{project.overall_actual_progress}%</span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <a href={project.edit_url} className="text-indigo-600 hover:text-indigo-800 text-sm font-medium transition">Edit</a>
                                        <span className="text-gray-300 mx-1">|</span>
                                        <a href={project.show_url} className="text-gray-600 hover:text-gray-900 text-sm font-medium transition">View</a>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
