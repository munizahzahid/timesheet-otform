import React, { useEffect, useState } from 'react';

const NavLink = ({ href, active, label, icon, collapsed, count, dot }) => (
    <a
        href={href}
        title={collapsed ? label : undefined}
        className={`flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all duration-200 group ${
            active ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg shadow-blue-900/40 border-l-4 border-blue-400 ring-1 ring-white/10' : 'text-gray-300 hover:bg-blue-800/70 hover:text-white hover:translate-x-0.5'
        }`}
    >
        <span className={`w-5 h-5 flex-shrink-0 ${active ? 'text-white' : 'text-gray-400 group-hover:text-white'}`}>{icon}</span>
        <span className="nav-label flex-1">{label}</span>
        {count > 0 && (
            <>
                <span className="badge-count inline-flex items-center justify-center flex-shrink-0 text-xs font-bold rounded-full bg-red-500 text-white w-5 h-5">{count}</span>
                <span className="badge-dot w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
            </>
        )}
        {dot && count === 0 && <span className="badge-dot w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>}
    </a>
);

const SubLink = ({ href, active, label, collapsed, icon }) => (
    <a
        href={href}
        title={collapsed ? label : undefined}
        className={`flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 ${
            active ? 'bg-blue-600 text-white shadow-md ring-1 ring-white/10' : 'text-gray-400 hover:bg-blue-800/70 hover:text-white hover:translate-x-0.5'
        }`}
    >
        <span className="w-4 h-4 flex-shrink-0">{icon}</span>
        <span className="nav-label">{label}</span>
    </a>
);

const SectionLabel = ({ children }) => (
    <div className="pt-4 pb-2 section-label">
        <p className="px-4 pb-2 text-[10px] font-bold text-blue-300/70 uppercase tracking-widest">{children}</p>
    </div>
);

const icons = {
    dashboard: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
    ),
    hr: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
    ),
    project: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
        </svg>
    ),
    history: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    ),
    admin: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
    ),
    pending: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
        </svg>
    ),
    ot: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    ),
    timesheet: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
        </svg>
    ),
    users: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
    ),
    projectCodes: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
        </svg>
    ),
    holidays: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
    ),
    sync: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
    ),
    audit: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
        </svg>
    ),
    settings: (
        <svg className="w-full h-full" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
    ),
};

export default function Sidebar({ logoUrl, user, routes, active, counts, isAdmin, showApprovals, adminOpen: adminOpenInitial, settingsOpen: settingsOpenInitial, csrfToken }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const [adminOpen, setAdminOpen] = useState(adminOpenInitial);
    const [settingsOpen] = useState(settingsOpenInitial);

    useEffect(() => {
        const stored = localStorage.getItem('sidebarCollapsed') === 'true';
        setCollapsed(stored);
    }, []);

    useEffect(() => {
        localStorage.setItem('sidebarCollapsed', collapsed);
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        const main = document.getElementById('main-content');
        if (main) main.classList.toggle('collapsed-main', collapsed);
    }, [collapsed]);

    useEffect(() => {
        const toggle = () => setSidebarOpen((s) => !s);
        const close = () => setSidebarOpen(false);
        window.addEventListener('toggle-sidebar', toggle);
        window.addEventListener('close-sidebar', close);
        return () => {
            window.removeEventListener('toggle-sidebar', toggle);
            window.removeEventListener('close-sidebar', close);
        };
    }, []);

    const toggleCollapse = () => setCollapsed((c) => !c);

    const toggleAdmin = () => {
        if (collapsed) {
            setCollapsed(false);
            setAdminOpen(true);
        } else {
            setAdminOpen((a) => !a);
        }
    };

    return (
        <>
            <aside
                className={`fixed top-0 left-0 h-screen w-64 z-30 text-gray-300 flex flex-col bg-gradient-to-b from-blue-900 to-slate-900 transform transition-all duration-300 ease-in-out shadow-2xl lg:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                } ${collapsed ? '!w-20' : '!w-64'}`}
            >
                <div className="brand-block flex items-center justify-between px-6 py-5 border-b border-blue-800/50 bg-blue-900/40 backdrop-blur-sm shadow-sm">
                    <a href={routes.dashboard} className="overflow-hidden">
                        <img src={logoUrl} alt="Talent Synergy Sdn Bhd" className="h-10 w-auto" />
                    </a>
                </div>

                <nav className="flex-1 overflow-y-auto px-4 py-4 space-y-1">
                    <NavLink href={routes.dashboard} active={active.dashboard} label="Dashboard" icon={icons.dashboard} collapsed={collapsed} />
                    <NavLink href={routes.timesheets} active={active.timesheets} label="HR" icon={icons.hr} collapsed={collapsed} count={counts.hrNewStatus} dot={counts.hrNewStatus > 0} />

                    {routes.project && (
                        <NavLink href={routes.project} active={active.project} label="Project" icon={icons.project} collapsed={collapsed} />
                    )}

                    <NavLink href={routes.history} active={active.history} label="History" icon={icons.history} collapsed={collapsed} />

                    {showApprovals && (
                        <>
                            <SectionLabel>Approvals</SectionLabel>
                            {(isAdmin || user.role === 'hr') && (
                                <NavLink href={routes.pendingTracker} active={active.pendingTracker} label="Pending Tracker" icon={icons.pending} collapsed={collapsed} />
                            )}
                            {(isAdmin || user.role === 'hr' || user.isOtApprover) && (
                                <NavLink href={routes.otApprovals} active={active.otApprovals} label="OT Approvals" icon={icons.ot} collapsed={collapsed} count={counts.pendingOtApproval} dot={counts.pendingOtApproval > 0} />
                            )}
                            {(isAdmin || user.isTimesheetApprover) && (
                                <NavLink href={routes.timesheetApprovals} active={active.timesheetApprovals} label="Timesheet Approvals" icon={icons.timesheet} collapsed={collapsed} count={counts.pendingTimesheetApproval} dot={counts.pendingTimesheetApproval > 0} />
                            )}
                        </>
                    )}

                    {isAdmin && (
                        <div>
                            <button
                                onClick={toggleAdmin}
                                title={collapsed ? 'Admin' : undefined}
                                className={`flex items-center justify-between w-full px-4 py-3 rounded-xl text-sm font-semibold transition-all duration-200 group ${
                                    active.admin ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg shadow-blue-900/40 border-l-4 border-blue-400 ring-1 ring-white/10' : 'text-gray-300 hover:bg-blue-800/70 hover:text-white'
                                }`}
                            >
                                <span className="flex items-center gap-3">
                                    <span className={`w-5 h-5 flex-shrink-0 ${active.admin ? 'text-white' : 'text-gray-400 group-hover:text-white'}`}>{icons.admin}</span>
                                    <span className="nav-label">Admin</span>
                                </span>
                                <svg
                                    className={`w-4 h-4 nav-label transition-transform duration-200 ${adminOpen ? 'rotate-90' : ''}`}
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                            <div className={`ml-4 mt-1 space-y-1 border-l-2 border-blue-800 pl-4 overflow-hidden transition-all duration-200 ${adminOpen ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'}`}>
                                <SubLink href={routes.adminUsers} active={active.adminUsers} label="Users" collapsed={collapsed} icon={icons.users} />
                                <SubLink href={routes.adminProjectCodes} active={active.adminProjectCodes} label="Project Codes" collapsed={collapsed} icon={icons.projectCodes} />
                                <SubLink href={routes.adminHolidays} active={active.adminHolidays} label="Holidays" collapsed={collapsed} icon={icons.holidays} />
                                <SubLink href={routes.adminDesknetSync} active={active.adminDesknetSync} label="Desknet Sync" collapsed={collapsed} icon={icons.sync} />
                                <SubLink href={routes.adminAudit} active={active.adminAudit} label="Audit Logs" collapsed={collapsed} icon={icons.audit} />
                            </div>
                        </div>
                    )}

                    <SectionLabel>Account</SectionLabel>

                    {isAdmin && (
                        <NavLink href={routes.adminSettings} active={active.adminSettings} label="System Settings" icon={icons.settings} collapsed={collapsed} />
                    )}
                </nav>

                <div className="profile-card flex-shrink-0 px-4 pt-4 pb-4 border-t border-blue-800/50 bg-blue-900/30">
                    <div className="flex items-center gap-3">
                        <a href={user.profileUrl} title="My Profile" className="flex-shrink-0">
                            <div className="w-10 h-10 bg-gradient-to-br from-blue-700 to-blue-900 rounded-full flex items-center justify-center text-white font-bold shadow-lg text-sm">
                                {user.initial}
                            </div>
                        </a>
                        <div className="profile-detail overflow-hidden">
                            <p className="text-sm font-bold text-white truncate">{user.name}</p>
                            <p className="text-xs text-blue-300/70 truncate">{user.designation}</p>
                        </div>
                        <div className="ml-auto flex items-center gap-1">
                            <button
                                onClick={toggleCollapse}
                                title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                                className="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-blue-700/70 focus:outline-none transition shadow-sm"
                            >
                                <svg className={`w-5 h-5 nav-label ${collapsed ? 'hidden' : 'block'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                                <svg className={`w-5 h-5 ${collapsed ? 'block' : 'hidden'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                            <form method="POST" action={user.logoutUrl} className="profile-detail">
                                <input type="hidden" name="_token" value={csrfToken} />
                                <button type="submit" title="Logout" className="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-blue-700/70 transition shadow-sm">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </aside>

            <div
                onClick={() => setSidebarOpen(false)}
                className={`fixed inset-0 bg-black/50 z-20 lg:hidden transition-opacity duration-200 ${
                    sidebarOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
                }`}
            ></div>
        </>
    );
}
