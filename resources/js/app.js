import './bootstrap';

import Alpine from 'alpinejs';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import ProjectDashboard from './components/ProjectDashboard';
import ProjectTabDashboard from './components/ProjectTabDashboard';
import ProjectGantt from './components/ProjectGantt';
import ProjectKanban from './components/ProjectKanban';
import ProjectDetails from './components/ProjectDetails';
import ProjectDetailsForm from './components/ProjectDetailsForm';
import ProjectList from './components/ProjectList';
import ProjectCalendar from './components/ProjectCalendar';
import MainDashboard from './components/MainDashboard';
import Sidebar from './components/Sidebar';

window.Alpine = Alpine;

Alpine.start();

const dashboardEl = document.getElementById('project-dashboard');
const propsEl = document.getElementById('project-dashboard-props');
if (dashboardEl && propsEl) {
    const props = JSON.parse(propsEl.textContent || '{}');
    const root = createRoot(dashboardEl);
    root.render(createElement(ProjectDashboard, props));
}

const tabDashboardEl = document.getElementById('project-tab-dashboard');
const tabPropsEl = document.getElementById('project-tab-dashboard-props');
if (tabDashboardEl && tabPropsEl) {
    const props = JSON.parse(tabPropsEl.textContent || '{}');
    const tabRoot = createRoot(tabDashboardEl);
    tabRoot.render(createElement(ProjectTabDashboard, props));
}

const ganttEl = document.getElementById('project-gantt');
const ganttPropsEl = document.getElementById('project-gantt-props');
if (ganttEl && ganttPropsEl) {
    const props = JSON.parse(ganttPropsEl.textContent || '{}');
    const ganttRoot = createRoot(ganttEl);
    ganttRoot.render(createElement(ProjectGantt, props));
}

const kanbanEl = document.getElementById('project-kanban');
const kanbanPropsEl = document.getElementById('project-kanban-props');
if (kanbanEl && kanbanPropsEl) {
    const props = JSON.parse(kanbanPropsEl.textContent || '{}');
    const kanbanRoot = createRoot(kanbanEl);
    kanbanRoot.render(createElement(ProjectKanban, props));
}

const detailsEl = document.getElementById('project-details');
const detailsPropsEl = document.getElementById('project-details-props');
if (detailsEl && detailsPropsEl) {
    const props = JSON.parse(detailsPropsEl.textContent || '{}');
    const detailsRoot = createRoot(detailsEl);
    detailsRoot.render(createElement(ProjectDetails, props));
}

const detailsFormEl = document.getElementById('project-details-form');
const detailsFormPropsEl = document.getElementById('project-details-form-props');
if (detailsFormEl && detailsFormPropsEl) {
    const props = JSON.parse(detailsFormPropsEl.textContent || '{}');
    const detailsFormRoot = createRoot(detailsFormEl);
    detailsFormRoot.render(createElement(ProjectDetailsForm, props));
}

const listEl = document.getElementById('project-list');
const listPropsEl = document.getElementById('project-list-props');
if (listEl && listPropsEl) {
    const props = JSON.parse(listPropsEl.textContent || '{}');
    const listRoot = createRoot(listEl);
    listRoot.render(createElement(ProjectList, props));
}

const calendarEl = document.getElementById('project-calendar');
const calendarPropsEl = document.getElementById('project-calendar-props');
if (calendarEl && calendarPropsEl) {
    const props = JSON.parse(calendarPropsEl.textContent || '{}');
    const calendarRoot = createRoot(calendarEl);
    calendarRoot.render(createElement(ProjectCalendar, props));
}

const mainDashboardEl = document.getElementById('main-dashboard');
const mainDashboardPropsEl = document.getElementById('main-dashboard-props');
if (mainDashboardEl && mainDashboardPropsEl) {
    const props = JSON.parse(mainDashboardPropsEl.textContent || '{}');
    const mainDashboardRoot = createRoot(mainDashboardEl);
    mainDashboardRoot.render(createElement(MainDashboard, props));
}

const timesheetEditEl = document.getElementById('timesheet-edit');
const timesheetEditPropsEl = document.getElementById('timesheet-edit-props');
if (timesheetEditEl && timesheetEditPropsEl) {
    const props = JSON.parse(timesheetEditPropsEl.textContent || '{}');
    const timesheetEditRoot = createRoot(timesheetEditEl);
    timesheetEditRoot.render(createElement(TimesheetEdit, props));
}

const sidebarEl = document.getElementById('sidebar-root');
const sidebarPropsEl = document.getElementById('sidebar-props');
if (sidebarEl && sidebarPropsEl) {
    const props = JSON.parse(sidebarPropsEl.textContent || '{}');
    const sidebarRoot = createRoot(sidebarEl);
    sidebarRoot.render(createElement(Sidebar, props));
}
