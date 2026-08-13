import React from 'react';

const Field = ({ label, value }) => (
    <div>
        <label className="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{label}</label>
        <p className="text-sm font-medium text-gray-800">{value ?? '—'}</p>
    </div>
);

const money = (n) => {
    if (n == null || n === '') return null;
    const value = Number(n);
    if (Number.isNaN(value)) return n;
    return value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const Section = ({ title, children }) => (
    <div className="bg-white border border-gray-100 rounded-2xl shadow-sm">
        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 className="text-base font-semibold text-gray-800">{title}</h3>
        </div>
        <div className="p-6">{children}</div>
    </div>
);

const ProgressBar = ({ value, color }) => (
    <div className="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
        <div className={`h-2.5 rounded-full flex items-center justify-center shadow-sm ${color}`} style={{ width: `${Math.min(value, 100)}%` }}>
            <span className="text-[9px] font-semibold text-white" style={{ textShadow: '0 1px 2px rgba(0,0,0,0.3)' }}>{Math.round(value)}%</span>
        </div>
    </div>
);

const DateGroup = ({ label, start, end }) => (
    <div className="bg-slate-50/70 rounded-xl p-4 border border-gray-100">
        <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-3">{label}</p>
        <div className="grid grid-cols-2 gap-3">
            <Field label="Start" value={start} />
            <Field label="End" value={end} />
        </div>
    </div>
);

const AttachmentLink = ({ attachment, variant = 'indigo' }) => (
    <a
        href={attachment.url}
        target="_blank"
        rel="noreferrer"
        className={`inline-flex items-center gap-1.5 px-3 py-1.5 ${variant === 'indigo' ? 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' : 'bg-gray-50 text-gray-700 hover:bg-gray-100'} rounded-xl text-xs transition shadow-sm`}
    >
        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
        </svg>
        {attachment.name}
    </a>
);

export default function ProjectDetails({ project, editUrl }) {
    const manager = (name, staffId, dept) => (
        <div className="space-y-3">
            <Field label="Name" value={name} />
            <Field label="Staff ID" value={staffId} />
            <Field label="Department" value={dept} />
        </div>
    );

    return (
        <div className="space-y-6">
            <Section title="Project Summary">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Project Name" value={project.project_name} />
                            <Field label="Project Code" value={project.project_code} />
                        </div>
                        <Field label="Description" value={project.description} />
                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Status" value={project.status_label} />
                            <Field label="Created By" value={project.created_by_name} />
                        </div>
                    </div>
                    <div className="bg-slate-50/70 rounded-xl p-4 border border-gray-100">
                        <h4 className="text-[11px] font-semibold text-gray-700 uppercase tracking-wide mb-4">Overall Progress</h4>
                        <div className="space-y-4">
                            <div>
                                <div className="flex justify-between text-xs mb-1">
                                    <span className="text-gray-600">Plan Progress</span>
                                    <span className="font-medium text-gray-900">{project.overall_plan_progress}%</span>
                                </div>
                                <ProgressBar value={project.overall_plan_progress} color="bg-blue-500" />
                            </div>
                            <div>
                                <div className="flex justify-between text-xs mb-1">
                                    <span className="text-gray-600">Actual Progress</span>
                                    <span className="font-medium text-gray-900">{project.overall_actual_progress}%</span>
                                </div>
                                <ProgressBar value={project.overall_actual_progress} color="bg-emerald-500" />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mt-6 pt-6 border-t border-gray-100">
                    <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Timeline</h4>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <DateGroup label="Planned" start={project.start_date_plan_formatted} end={project.end_date_plan_formatted} />
                        <DateGroup label="Actual" start={project.start_date_actual_formatted} end={project.end_date_actual_formatted} />
                        <DateGroup label="Revised" start={project.start_date_revise_formatted} end={project.end_date_revise_formatted} />
                    </div>
                </div>

                {project.phases?.length > 0 && (
                    <div className="mt-6 pt-6 border-t border-gray-100">
                        <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Tasks ({project.phases.length})</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            {project.phases.map((phase) => (
                                <div key={phase.id} className="bg-slate-50/70 rounded-xl p-3 border border-gray-100">
                                    <div className="flex items-center justify-between mb-2">
                                        <p className="text-sm font-medium text-gray-800">{phase.phase_name}</p>
                                        <span className="text-xs text-gray-500">#{phase.phase_order}</span>
                                    </div>
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <ProgressBar value={phase.progress_plan} color="bg-blue-500" />
                                        </div>
                                        {phase.revise_progress !== null && (
                                            <div className="flex items-center gap-2">
                                                <ProgressBar value={phase.revise_progress} color="bg-orange-500" />
                                            </div>
                                        )}
                                        <div className="flex items-center gap-2">
                                            <ProgressBar value={phase.progress_actual} color="bg-emerald-500" />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </Section>

            <Section title="Desknet Project Details">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {manager(project.project_manager, project.project_manager_staff_id, project.project_manager_department)}
                    {manager(project.deskman_1, project.deskman_1_staff_id, project.deskman_1_department)}
                    {manager(project.deskman_2, project.deskman_2_staff_id, project.deskman_2_department)}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
                    <Field label="Client" value={project.client} />
                    <Field label="Attn" value={project.attn} />
                    <Field label="PO No." value={project.po_no} />
                    <Field label="Year" value={project.year} />
                    <Field label="Project Value" value={money(project.project_value)} />
                    <Field label="Purchasing Budget 100%" value={money(project.purchasing_budget_100)} />
                    <Field label="Purchasing Budget 95%" value={money(project.purchasing_budget_95)} />
                    <Field label="Actual Cost" value={money(project.actual_cost)} />
                    <Field label="TIN" value={project.tin} />
                    <Field label="Identification No" value={project.identification_no} />
                    <Field label="Exemption Cert. No" value={project.exemption_cert_no} />
                    <Field label="Contact No" value={project.contact_no} />
                    <Field label="Email" value={project.email} />
                    <Field label="Schedule Status" value={project.project_schedule_status} />
                </div>

                <div className="mt-6">
                    <Field label="Full Address" value={project.full_address} />
                </div>

                <div className="mt-6">
                    <p className="text-xs text-gray-500 uppercase tracking-wide mb-2">Payment Terms</p>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        {['term_1', 'term_2', 'term_3', 'term_4', 'term_5'].map((term, i) => (
                            <Field key={term} label={`Term ${i + 1}`} value={project[term]} />
                        ))}
                    </div>
                </div>
            </Section>

            {(project.attachment_po_customer?.length > 0 || project.other_attachments?.length > 0) && (
                <Section title="Attachments">
                    {project.attachment_po_customer?.length > 0 && (
                        <div className="mb-4">
                            <p className="text-xs text-gray-500 uppercase tracking-wide mb-2">PO Customer</p>
                            <div className="flex flex-wrap gap-2">
                                {project.attachment_po_customer.map((attachment, i) => (
                                    <AttachmentLink key={i} attachment={attachment} variant="indigo" />
                                ))}
                            </div>
                        </div>
                    )}
                    {project.other_attachments?.length > 0 && (
                        <div>
                            <p className="text-xs text-gray-500 uppercase tracking-wide mb-2">Other Attachments</p>
                            <div className="flex flex-wrap gap-2">
                                {project.other_attachments.map((attachment, i) => (
                                    <AttachmentLink key={i} attachment={attachment} variant="gray" />
                                ))}
                            </div>
                        </div>
                    )}
                </Section>
            )}

            {editUrl && (
                <div className="flex items-center justify-end bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
                    <a href={editUrl} className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 hover:shadow-md transition shadow-sm">
                        Edit Project
                    </a>
                </div>
            )}
        </div>
    );
}
