import React, { useState } from 'react';

const formatMoney = (n) => {
    if (n == null || n === '') return '';
    const value = Number(n);
    if (Number.isNaN(value)) return '';
    return value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const FormField = ({ label, name, value, onChange, type = 'text', error, rows = 2 }) => (
    <div>
        <label className="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{label}</label>
        {type === 'textarea' ? (
            <textarea
                name={name}
                value={value ?? ''}
                onChange={(e) => onChange(name, e.target.value)}
                rows={rows}
                className="w-full rounded-lg border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm transition"
            />
        ) : (
            <input
                type={type}
                name={name}
                value={value ?? ''}
                onChange={(e) => onChange(name, e.target.value)}
                className="w-full rounded-lg border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm transition"
            />
        )}
        {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
    </div>
);

const MoneyField = ({ label, name, value, onChange, error }) => (
    <div>
        <label className="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{label}</label>
        <input
            type="text"
            name={name}
            value={formatMoney(value)}
            onChange={(e) => {
                const raw = e.target.value.replace(/[^0-9.-]/g, '');
                onChange(name, raw === '' ? '' : raw);
            }}
            onBlur={(e) => {
                const raw = e.target.value.replace(/[^0-9.-]/g, '');
                onChange(name, raw === '' ? '' : Number(raw));
            }}
            className="w-full rounded-lg border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm transition"
        />
        {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
    </div>
);

const Select = ({ label, name, value, onChange, options, error }) => (
    <div>
        <label className="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{label}</label>
        <select
            name={name}
            value={value ?? ''}
            onChange={(e) => onChange(name, e.target.value)}
            className="w-full rounded-lg border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm transition"
        >
            {Object.entries(options).map(([k, v]) => (
                <option key={k} value={k}>{v}</option>
            ))}
        </select>
        {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
    </div>
);

const StaffPicker = ({ label, name, staffIdName, deptName, value, staffList, onChange, error }) => {
    const selected = staffList.find((s) => s.name === value);
    const isCustom = value && !selected;
    return (
        <div>
            <label className="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{label}</label>
            <select
                name={name}
                value={value ?? ''}
                onChange={(e) => {
                    const newValue = e.target.value;
                    const staff = staffList.find((s) => s.name === newValue);
                    onChange(name, newValue);
                    onChange(staffIdName, staff ? staff.staff_no : '');
                    onChange(deptName, staff ? staff.department || '' : '');
                }}
                className="w-full rounded-lg border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm transition"
            >
                <option value="">— Select Staff —</option>
                {staffList.map((s) => (
                    <option key={s.id} value={s.name}>{s.name} ({s.staff_no})</option>
                ))}
                {isCustom && <option value={value}>{value}</option>}
            </select>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
};

const Section = ({ title, children }) => (
    <div className="bg-white border border-gray-100 rounded-2xl shadow-sm">
        <div className="px-6 py-4 border-b border-gray-100">
            <h3 className="text-base font-semibold text-gray-800">{title}</h3>
        </div>
        <div className="p-6">{children}</div>
    </div>
);

const DateGroup = ({ label, start, end, startName, endName, onChange, errors }) => (
    <div className="bg-slate-50/70 rounded-xl p-4 border border-gray-100">
        <p className="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-3">{label}</p>
        <div className="grid grid-cols-2 gap-3">
            <FormField label="Start" name={startName} value={start} onChange={onChange} type="date" error={errors?.[startName]?.[0]} />
            <FormField label="End" name={endName} value={end} onChange={onChange} type="date" error={errors?.[endName]?.[0]} />
        </div>
    </div>
);

const ManagerGroup = ({ title, name, value, staffId, department, staffList, onChange, errors }) => (
    <div className="space-y-3">
        <StaffPicker
            label={title}
            name={name}
            staffIdName={`${name}_staff_id`}
            deptName={`${name}_department`}
            value={value}
            staffList={staffList}
            onChange={onChange}
            error={errors?.[name]?.[0]}
        />
        <FormField label="Staff ID" name={`${name}_staff_id`} value={staffId} onChange={onChange} error={errors?.[`${name}_staff_id`]?.[0]} />
        <FormField label="Department" name={`${name}_department`} value={department} onChange={onChange} error={errors?.[`${name}_department`]?.[0]} />
    </div>
);

export default function ProjectDetailsForm({ project, staffList, statusOptions, csrfToken, updateUrl, cancelUrl, redirectUrl }) {
    const [form, setForm] = useState({ ...project });
    const [errors, setErrors] = useState({});
    const [generalError, setGeneralError] = useState(null);
    const [loading, setLoading] = useState(false);

    const handleChange = (name, value) => {
        setForm((prev) => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (action) => {
        setLoading(true);
        setErrors({});
        setGeneralError(null);
        try {
            const res = await fetch(updateUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ ...form, action }),
            });
            if (res.ok) {
                window.location.href = redirectUrl;
            } else {
                const data = await res.json().catch(() => ({}));
                if (data.errors) setErrors(data.errors);
                if (data.message) setGeneralError(data.message);
            }
        } catch (e) {
            setGeneralError('Failed to save. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const onFormSubmit = (e) => {
        e.preventDefault();
        handleSubmit('save');
    };

    return (
        <form onSubmit={onFormSubmit} className="space-y-6">
            {generalError && (
                <div className="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 shadow-sm">
                    {generalError}
                </div>
            )}

            <Section title="Project Summary">
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Project Name" name="project_name" value={form.project_name} onChange={handleChange} error={errors?.project_name?.[0]} />
                            <FormField label="Project Code" name="project_code" value={form.project_code} onChange={handleChange} error={errors?.project_code?.[0]} />
                        </div>
                        <FormField label="Description" name="description" value={form.description} onChange={handleChange} type="textarea" rows={2} error={errors?.description?.[0]} />
                        <div className="grid grid-cols-2 gap-4">
                            <Select label="Status" name="status" value={form.status} onChange={handleChange} options={statusOptions} error={errors?.status?.[0]} />
                            <div>
                                <label className="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Created By</label>
                                <p className="text-sm font-medium text-gray-800">{project.created_by_name ?? '—'}</p>
                            </div>
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
                                <div className="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                    <div className="h-2.5 rounded-full shadow-sm bg-blue-500" style={{ width: `${Math.min(project.overall_plan_progress, 100)}%` }}></div>
                                </div>
                            </div>
                            <div>
                                <div className="flex justify-between text-xs mb-1">
                                    <span className="text-gray-600">Actual Progress</span>
                                    <span className="font-medium text-gray-900">{project.overall_actual_progress}%</span>
                                </div>
                                <div className="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                    <div className="h-2.5 rounded-full shadow-sm bg-emerald-500" style={{ width: `${Math.min(project.overall_actual_progress, 100)}%` }}></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mt-6 pt-6 border-t border-gray-100">
                    <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-4">Timeline</h4>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <DateGroup label="Planned" start={form.start_date_plan} end={form.end_date_plan} startName="start_date_plan" endName="end_date_plan" onChange={handleChange} errors={errors} />
                        <DateGroup label="Actual" start={form.start_date_actual} end={form.end_date_actual} startName="start_date_actual" endName="end_date_actual" onChange={handleChange} errors={errors} />
                        <DateGroup label="Revised" start={form.start_date_revise} end={form.end_date_revise} startName="start_date_revise" endName="end_date_revise" onChange={handleChange} errors={errors} />
                    </div>
                </div>
            </Section>

            <Section title="Desknet Project Details">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <ManagerGroup title="Project Manager" name="project_manager" value={form.project_manager} staffId={form.project_manager_staff_id} department={form.project_manager_department} staffList={staffList} onChange={handleChange} errors={errors} />
                    <ManagerGroup title="Project Deskman 1" name="deskman_1" value={form.deskman_1} staffId={form.deskman_1_staff_id} department={form.deskman_1_department} staffList={staffList} onChange={handleChange} errors={errors} />
                    <ManagerGroup title="Project Deskman 2" name="deskman_2" value={form.deskman_2} staffId={form.deskman_2_staff_id} department={form.deskman_2_department} staffList={staffList} onChange={handleChange} errors={errors} />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
                    <FormField label="Client" name="client" value={form.client} onChange={handleChange} error={errors?.client?.[0]} />
                    <FormField label="Attn" name="attn" value={form.attn} onChange={handleChange} error={errors?.attn?.[0]} />
                    <FormField label="PO No." name="po_no" value={form.po_no} onChange={handleChange} error={errors?.po_no?.[0]} />
                    <FormField label="Year" name="year" value={form.year} onChange={handleChange} type="number" error={errors?.year?.[0]} />
                    <MoneyField label="Project Value" name="project_value" value={form.project_value} onChange={handleChange} error={errors?.project_value?.[0]} />
                    <MoneyField label="Purchasing Budget 100%" name="purchasing_budget_100" value={form.purchasing_budget_100} onChange={handleChange} error={errors?.purchasing_budget_100?.[0]} />
                    <MoneyField label="Purchasing Budget 95%" name="purchasing_budget_95" value={form.purchasing_budget_95} onChange={handleChange} error={errors?.purchasing_budget_95?.[0]} />
                    <MoneyField label="Actual Cost" name="actual_cost" value={form.actual_cost} onChange={handleChange} error={errors?.actual_cost?.[0]} />
                    <FormField label="TIN" name="tin" value={form.tin} onChange={handleChange} error={errors?.tin?.[0]} />
                    <FormField label="Identification No" name="identification_no" value={form.identification_no} onChange={handleChange} error={errors?.identification_no?.[0]} />
                    <FormField label="Exemption Cert. No" name="exemption_cert_no" value={form.exemption_cert_no} onChange={handleChange} error={errors?.exemption_cert_no?.[0]} />
                    <FormField label="Contact No" name="contact_no" value={form.contact_no} onChange={handleChange} error={errors?.contact_no?.[0]} />
                    <FormField label="Email" name="email" value={form.email} onChange={handleChange} type="email" error={errors?.email?.[0]} />
                    <FormField label="Schedule Status" name="project_schedule_status" value={form.project_schedule_status} onChange={handleChange} error={errors?.project_schedule_status?.[0]} />
                </div>

                <div className="mt-6">
                    <FormField label="Full Address" name="full_address" value={form.full_address} onChange={handleChange} type="textarea" rows={2} error={errors?.full_address?.[0]} />
                </div>

                <div className="mt-6">
                    <p className="text-xs text-gray-500 uppercase tracking-wide mb-2">Payment Terms</p>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        {['term_1', 'term_2', 'term_3', 'term_4', 'term_5'].map((term, i) => (
                            <FormField key={term} label={`Term ${i + 1}`} name={term} value={form[term]} onChange={handleChange} error={errors?.[term]?.[0]} />
                        ))}
                    </div>
                </div>
            </Section>

            <div className="flex items-center justify-end gap-3 bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
                <a href={cancelUrl} className="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition shadow-sm">
                    Cancel
                </a>
                <button
                    type="button"
                    onClick={() => handleSubmit('save')}
                    disabled={loading}
                    className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 hover:shadow-md transition shadow-sm disabled:opacity-60"
                >
                    {loading ? 'Saving...' : 'Save Changes'}
                </button>
                <button
                    type="button"
                    onClick={() => handleSubmit('push_to_desknet')}
                    disabled={loading}
                    className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 hover:shadow-md transition shadow-sm disabled:opacity-60"
                >
                    {loading ? 'Saving...' : 'Save & Push to Desknet'}
                </button>
            </div>
        </form>
    );
}
