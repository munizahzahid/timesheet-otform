I want to add a new Project Management module into my existing HR system.

IMPORTANT HIGH-PRIORITY RULE
The existing HR part must NOT be disturbed at all.

That means:
- Do NOT change existing HR Timesheet logic
- Do NOT change existing OT Form logic
- Do NOT change existing HR routes, controllers, pages, or database behavior unless absolutely necessary
- Do NOT refactor or restructure the HR module
- Do NOT break any existing HR UI flow
- The Project module must be added as a separate admin-only module with minimal impact on the existing HR part

The only allowed integration with existing admin UI is:
1. add a new admin-only sidebar item called "Project"
2. add new admin-only Project routes/pages/components
3. reuse the existing admin layout if needed, but do not disturb HR module behavior

==================================================
CURRENT SYSTEM CONTEXT
==================================================

Current system already contains HR-related modules:
1. Timesheet
2. OT Form

Now I want to add a new module called:
PROJECT MANAGEMENT

This Project module is still DRAFT, so:
- it must be visible ONLY to ADMIN users
- non-admin users must not see the Project sidebar/menu
- non-admin users must not be able to access any Project page directly by URL

==================================================
MAIN NAVIGATION FLOW I WANT
==================================================

Add a new sidebar item called:
PROJECT

When admin clicks PROJECT in the sidebar:
- open the Project module
- the default first page should be the PROJECT EXECUTIVE DASHBOARD page

Inside the Project module page, BELOW the MAIN TOP BAR, add a SECONDARY TOP NAVBAR specifically for Project module.

This secondary Project navbar must contain at least:
1. Executive Dashboard
2. List of Project

Navigation behavior:
- Clicking "Executive Dashboard" opens the Project executive dashboard page
- Clicking "List of Project" opens a page showing all projects that have been added
- To view a project’s details, admin must click a project from the project list page
- Clicking a project in the list should open a Project Details page

So the Project module flow should be:

Sidebar:
- Project

Then inside Project module:
- Secondary top navbar:
  - Executive Dashboard
  - List of Project

Then page flow:
1. Admin clicks sidebar "Project"
2. System opens Project Executive Dashboard page by default
3. Admin can click "List of Project" in the Project top navbar
4. System opens Project List page
5. Admin clicks one project from the list
6. System opens Project Details page

==================================================
PROJECT MODULE PAGES NEEDED
==================================================

For now, I want the Project module to be structured into these pages:

1. Project Executive Dashboard page
   - default landing page when admin clicks sidebar Project
   - management overview / summary page for projects

2. Project List page
   - shows all projects that have been added
   - each project should be clickable

3. Project Details page
   - opens after admin clicks a project from the project list
   - this page will later contain:
     - project schedule / gantt chart
     - progress monitoring (plan vs actual)
     - project-level summary/dashboard

==================================================
IMPLEMENTATION STRATEGY
==================================================

Please implement this PHASE BY PHASE.
Do NOT build everything at once.
Do NOT jump ahead.
Start by analyzing the current codebase first, then implement only PHASE 1 first.
After finishing PHASE 1, stop and summarize the files changed, routes added, and what I should test.

Do NOT continue to later phases unless I ask.

==================================================
PHASE 1 — PROJECT MODULE SCAFFOLD (ADMIN ONLY)
==================================================

OBJECTIVE
Create a clean Project module shell without disturbing the HR module.

PHASE 1 REQUIREMENTS

A. Add admin-only sidebar item
- Add a new sidebar item called "Project"
- Show it ONLY to admin users
- Non-admin must not see it

B. Add admin-only Project routes
Create a clean route structure for Project module.
Use the existing route style of the system, but the module should support this flow:

1. Project Executive Dashboard page
2. Project List page
3. Project Details page

Suggested route structure:
- /admin/project
  -> Project Executive Dashboard page (default landing page)
- /admin/project/projects
  -> Project List page
- /admin/project/projects/{project}
  -> Project Details page

If needed, use route names consistent with the current project naming convention.

C. Add Project module secondary top navbar
Inside the Project module pages, below the main top bar, add a Project-specific navbar containing:
1. Executive Dashboard
2. List of Project

Behavior:
- Executive Dashboard -> /admin/project
- List of Project -> /admin/project/projects

D. Create page placeholders for Phase 1
Create these pages with clean placeholders:

1. Project Executive Dashboard page
   Content can be placeholder cards/sections for now such as:
   - Total Projects
   - Active Projects
   - Completed Projects
   - Delayed Projects
   - Recent Project Activity
   This is just a placeholder layout for now

2. Project List page
   For now, if there is no database yet, use placeholder content or static sample project cards/table
   Example:
   - Project A
   - Project B
   - Project C
   Each project entry should be clickable and lead to the Project Details page route

3. Project Details page
   For now, this can be a placeholder layout with section headings only:
   - Project Summary
   - Project Schedule / Gantt Chart
   - Progress Monitoring
   This page is where the real gantt and progress monitoring will be added later

E. Strict isolation from HR module
Important:
- keep all Project module files isolated from HR files as much as possible
- do not move HR code around
- do not rewrite Timesheet / OT code
- only touch shared layout/sidebar files if needed to add the new Project entry

==================================================
PHASE 2 — DATABASE DESIGN FOR PROJECT MANAGEMENT
==================================================

Do NOT implement this yet unless I ask later.
But structure the Project module so Phase 2 can be added cleanly.

Eventually I want these entities:

A) projects
Fields:
- id
- project_code nullable
- project_name
- description nullable
- status nullable
- start_date_plan nullable
- end_date_plan nullable
- start_date_actual nullable
- end_date_actual nullable
- start_date_revise nullable
- end_date_revise nullable
- overall_plan_progress default 0
- overall_actual_progress default 0
- created_by nullable
- timestamps

B) project_phases
Fields:
- id
- project_id
- phase_name
- phase_order
- start_date_plan nullable
- end_date_plan nullable
- start_date_actual nullable
- end_date_actual nullable
- start_date_revise nullable
- end_date_revise nullable
- progress_plan default 0
- progress_actual default 0
- timestamps

C) project_tasks
Fields:
- id
- project_id
- phase_id
- parent_task_id nullable
- task_name
- task_order
- assigned_to nullable
- progress_plan default 0
- progress_actual default 0
- progress_revise nullable
- start_date_plan nullable
- end_date_plan nullable
- start_date_actual nullable
- end_date_actual nullable
- start_date_revise nullable
- end_date_revise nullable
- status nullable
- remarks nullable
- timestamps

D) project_progress_logs
Fields:
- id
- project_id
- phase_id nullable
- task_id nullable
- log_type
- field_name nullable
- old_value nullable
- new_value nullable
- changed_by nullable
- notes nullable
- created_at

==================================================
PHASE 3 — EXECUTIVE DASHBOARD PAGE
==================================================

Do NOT implement yet unless I ask later.

This page is the default Project landing page when admin clicks sidebar "Project".

Eventually this Executive Dashboard page should show management monitoring such as:
- total projects
- active projects
- completed projects
- delayed projects
- project status overview
- progress summary across projects
- recent project updates

This page is a PROJECT-LEVEL dashboard across all projects, not the detail page for one specific project.

==================================================
PHASE 4 — PROJECT LIST PAGE
==================================================

Do NOT implement yet unless I ask later.

This page should show all added projects.
Admin can click a project to open Project Details page.

Expected list content later:
- project name
- project code
- status
- planned start/end
- actual progress summary
- quick action to open details

==================================================
PHASE 5 — PROJECT DETAILS PAGE
==================================================

Do NOT implement yet unless I ask later.

When admin clicks a project from Project List, open the Project Details page.

This page will later contain:
1. Project Summary
2. Project Schedule / Gantt Chart
3. Progress Monitoring (Plan vs Actual)

This page is where the gantt chart and detailed project monitoring will live.

==================================================
PHASE 6 — GANTT CHART REQUIREMENT FOR PROJECT DETAILS PAGE
==================================================

Do NOT implement yet unless I ask later.

The Project Details page must later support a gantt chart with this structure:

Task hierarchy:
- Project
  - Phase
    - Task / Subtask

Left table columns:
1. Task
2. Assigned To
3. Progress
4. Start
5. End

Each task should support:
- Plan start/end
- Actual start/end
- Revise start/end

Preferred Start display:
P: 01/01/2026
A: 03/01/2026
R: 05/01/2026

Preferred End display:
P: 07/01/2026
A: 08/01/2026
R: 10/01/2026

Timeline must later support:
- Plan bar
- Actual bar
- Revise bar

==================================================
PHASE 7 — PROGRESS MONITORING FOR PROJECT DETAILS PAGE
==================================================

Do NOT implement yet unless I ask later.

The Project Details page will later also contain Progress Monitoring with:
- overall planned progress
- overall actual progress
- variance
- delayed tasks
- revised tasks
- phase-level monitoring
- task-level monitoring

==================================================
TECHNICAL RULES
==================================================

1. Preserve HR module completely
- do not disturb HR module logic
- do not alter Timesheet/OT flow
- only integrate Project module through admin sidebar and new admin routes/pages

2. Access control
- Project module must be admin-only
- hide Project sidebar for non-admin
- block Project routes for non-admin

3. Code structure
- keep Project code modular and isolated
- if possible, group Project module files under a dedicated Project/Admin namespace or folder structure
- avoid mixing Project logic into HR module files

4. UI structure
- Project module should reuse the admin layout if appropriate
- add a Project-specific secondary navbar inside Project pages
- keep layout clean for future expansion

5. Future-proofing
- Project Details page must be designed so gantt chart and progress monitoring can be added later without rewriting the page structure

==================================================
WHAT I WANT YOU TO DO RIGHT NOW
==================================================

STEP A — ANALYZE CURRENT CODEBASE FIRST
Before coding, inspect the current codebase and identify:
1. current admin layout file
2. current sidebar/navigation structure
3. current route structure
4. current auth / role handling for admin
5. current frontend stack used by the app
6. current dashboard layout patterns
7. where Project module can be inserted with the least disturbance to HR module

Then briefly explain:
- which existing files need to be updated
- which new files will be created
- how you will isolate the Project module from the HR module
- whether there are any route/layout/authorization concerns

STEP B — IMPLEMENT ONLY PHASE 1
Implement ONLY these items:
1. admin-only sidebar item "Project"
2. admin-only Project routes
3. Project module secondary top navbar with:
   - Executive Dashboard
   - List of Project
4. Project Executive Dashboard page placeholder
5. Project List page placeholder
6. Project Details page placeholder

STEP C — STOP AFTER PHASE 1
After Phase 1 is complete, stop and show:
1. files created
2. files modified
3. routes added
4. any assumptions made
5. what I should test next

IMPORTANT
Do NOT continue to database, gantt, monitoring, or CRUD until I ask.