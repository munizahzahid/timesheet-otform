# Timesheet & OT Management System — Development Plan

> **Tech Stack:** Laravel 11 (PHP 8.2+) · MySQL 8.0 (XAMPP) · Blade + Tailwind CSS + Alpine.js
> **Reference:** `SYSTEM_DESIGN_v2.md`

---

## Table of Contents

1. [Phase 0 — Project Setup & Foundation](#phase-0--project-setup--foundation)
2. [Phase 1 — Authentication & User Management](#phase-1--authentication--user-management)
3. [Phase 2 — Admin: Core Configuration](#phase-2--admin-core-configuration)
4. [Phase 3 — Desknet API Sync (Project Codes & Staff)](#phase-3--desknet-api-sync-project-codes--staff)
5. [Phase 4 — Timesheet: Create & Fill](#phase-4--timesheet-create--fill)
6. [Phase 5 — Excel Upload & Autofill](#phase-5--excel-upload--autofill)
7. [Phase 6 — Timesheet: Submission & Approval Workflow](#phase-6--timesheet-submission--approval-workflow)
8. [Phase 7 — OT Form: Create, Plan & Pre-Approval (Phase 1)](#phase-7--ot-form-create-plan--pre-approval-phase-1)
9. [Phase 8 — OT Form: Actual Entry & Post-Approval (Phase 2)](#phase-8--ot-form-actual-entry--post-approval-phase-2)
10. [Phase 9 — Notifications](#phase-9--notifications)
11. [Phase 10 — Reports & Export](#phase-10--reports--export)
12. [Phase 11 — Morning Assembly Integration](#phase-11--morning-assembly-integration)
13. [Phase 12 — Testing, QA & Deployment](#phase-12--testing-qa--deployment)
14. [Future Improvements](#future-improvements)

---

## Phase 0 — Project Setup & Foundation

**Goal:** Set up the Laravel project, configure the database, run all migrations, and establish the base UI layout.

### 0.1 Install Laravel

```bash
composer create-project laravel/laravel Timesheet_Website
cd Timesheet_Website
```

- Configure `.env` for MySQL (XAMPP):
  ```
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3307
  DB_DATABASE=timesheet_db
  DB_USERNAME=root
  DB_PASSWORD=
  ```

### 0.2 Install Frontend Dependencies

```bash
npm install
npm install -D tailwindcss @tailwindcss/forms postcss autoprefixer
npm install alpinejs
```

- Configure `tailwind.config.js` with content paths for Blade templates.
- Set up `resources/css/app.css` with Tailwind directives.
- Import Alpine.js in `resources/js/app.js`.

### 0.3 Create All Migrations

Create migrations **in dependency order** (matches `SYSTEM_DESIGN_v2.md` Section 6.2):

| Order | Migration | Table |
|-------|-----------|-------|
| 1 | `create_departments_table` | `departments` |
| 2 | `create_users_table` | `users` (extend default Laravel users) |
| 3 | `create_project_codes_table` | `project_codes` |
| 4 | `create_timesheets_table` | `timesheets` |
| 5 | `create_timesheet_day_metadata_table` | `timesheet_day_metadata` |
| 6 | `create_timesheet_admin_hours_table` | `timesheet_admin_hours` |
| 7 | `create_timesheet_project_rows_table` | `timesheet_project_rows` |
| 8 | `create_timesheet_project_hours_table` | `timesheet_project_hours` |
| 9 | `create_ot_forms_table` | `ot_forms` |
| 10 | `create_ot_form_entries_table` | `ot_form_entries` |
| 11 | `create_approval_logs_table` | `approval_logs` |
| 12 | `create_system_config_table` | `system_config` |
| 13 | `create_public_holidays_table` | `public_holidays` |
| 14 | `create_excel_uploads_table` | `excel_uploads` |
| 15 | `create_morning_assembly_log_table` | `morning_assembly_log` |
| 16 | `create_notifications_table` | `notifications` |
| 17 | `create_desknet_sync_log_table` | `desknet_sync_log` |

**Each migration must match the SQL DDL in `SYSTEM_DESIGN_v2.md` Section 6.2 exactly** — including column types, ENUMs, defaults, foreign keys, and indexes.

```bash
php artisan migrate
```

### 0.4 Seeders

| Seeder | Data |
|--------|------|
| `SystemConfigSeeder` | All rows from `INSERT INTO system_config` in design doc |
| `DepartmentSeeder` | Placeholder departments (will be synced from Desknet later) |
| `PublicHolidaySeeder` | Malaysia gazetted holidays for current year (see Phase 2) |
| `AdminUserSeeder` | One default admin account for initial login |

```bash
php artisan db:seed
```

### 0.5 Base Layout

- Create `resources/views/layouts/app.blade.php`:
  - Sidebar navigation (collapsible)
  - Top bar with user name, role, notifications bell
  - Main content area
  - Footer
- Company logo is **UI-only** (stored as static asset in `public/images/logo.png`) — not in database.
- Set up route groups: `web`, `auth`, `admin`.

### 0.6 Eloquent Models

Create models with relationships for **all 17 tables** (no `companies` table — company info is UI-only):

| Model | Key Relationships |
|-------|-------------------|
| `User` | `belongsTo(Department)`, `hasMany(Timesheet)`, `hasMany(OtForm)`, `belongsTo(User, 'reports_to')` |
| `Department` | `hasMany(User)` |
| `ProjectCode` | — (standalone; `client` is a plain text field) |
| `Timesheet` | `belongsTo(User)`, `hasMany(TimesheetDayMetadata)`, `hasMany(TimesheetAdminHour)`, `hasMany(TimesheetProjectRow)` |
| `TimesheetDayMetadata` | `belongsTo(Timesheet)` |
| `TimesheetAdminHour` | `belongsTo(Timesheet)` |
| `TimesheetProjectRow` | `belongsTo(Timesheet)`, `belongsTo(ProjectCode)`, `hasMany(TimesheetProjectHour)` |
| `TimesheetProjectHour` | `belongsTo(TimesheetProjectRow)` |
| `OtForm` | `belongsTo(User)`, `hasMany(OtFormEntry)` (company_name is plain text) |
| `OtFormEntry` | `belongsTo(OtForm)`, `belongsTo(ProjectCode)` |
| `ApprovalLog` | `belongsTo(User, 'approver_id')` |
| `SystemConfig` | — |
| `PublicHoliday` | `belongsTo(User, 'created_by')` |
| `ExcelUpload` | `belongsTo(User)` |
| `MorningAssemblyLog` | `belongsTo(User)` |
| `Notification` | `belongsTo(User)` |
| `DesknetSyncLog` | `belongsTo(User, 'triggered_by')` |

### Deliverables (Phase 0)

- [ ] Laravel project initialized, `.env` configured
- [ ] All 18 migrations run successfully
- [ ] All seeders run, default data in place
- [ ] All 18 Eloquent models with relationships
- [ ] Base Blade layout with sidebar, topbar, responsive design
- [ ] Tailwind + Alpine.js working
- [ ] `php artisan serve` shows the app landing page

---

## Phase 1 — Authentication & User Management

**Goal:** Login/logout, role-based middleware, admin can assign roles and reporting hierarchy.

### 1.1 Authentication

- Use Laravel Breeze (Blade + Tailwind) for scaffolding:
  ```bash
  composer require laravel/breeze --dev
  php artisan breeze:install blade
  npm run build
  ```
- Customize login page with company branding (logo from `public/images/`).
- Remove registration route — users are created via Desknet sync, not self-registration.
- Customize `LoginController` to check `is_active` — inactive users cannot log in.

### 1.2 Role-Based Middleware

Create middleware `CheckRole`:

```php
// app/Http/Middleware/CheckRole.php
// Usage: Route::middleware('role:admin,manager_hod')
```

Register in `bootstrap/app.php` (Laravel 11).

### 1.3 Admin: User Role Management

Since user data comes from Desknet (Phase 3), the admin page here only handles **local-only fields**:

| Page | Route | Features |
|------|-------|----------|
| User List | `GET /admin/users` | Table with name, email, department, role, reports_to, status |
| Edit User | `GET /admin/users/{id}/edit` | Change **role** (dropdown), change **reports_to** (dropdown of users), toggle **is_active** |

- Name, email, staff_no, department are **read-only** (greyed out, labeled "Synced from Desknet").
- Add search & filter (by department, role, active/inactive).
- Pagination (15 per page).

### 1.4 Files to Create

```
app/Http/Middleware/CheckRole.php
app/Http/Controllers/Admin/UserController.php
resources/views/admin/users/index.blade.php
resources/views/admin/users/edit.blade.php
routes/web.php  (admin group)
```

### Deliverables (Phase 1)

- [ ] Login / logout working
- [ ] Registration disabled
- [ ] Inactive users blocked from login
- [ ] `CheckRole` middleware protecting admin routes
- [ ] Admin can view user list (search, filter, paginate)
- [ ] Admin can edit role & reports_to for any user
- [ ] Name, email, department shown as read-only

---

## Phase 2 — Admin: Core Configuration

**Goal:** System settings management and public holiday management with Malaysia calendar.

### 2.1 System Config Page

| Route | Action |
|-------|--------|
| `GET /admin/settings` | Show all `system_config` rows as editable form |
| `PUT /admin/settings` | Update changed values |

- Group settings by category in the UI:
  - **Working Hours:** working_start_time, ot_start_time, default_working_hours, friday_working_hours
  - **Rounding Rules:** late_rounding_minutes, ot_rounding_hours, lunch_break_minutes
  - **Desknet Integration:** desknet_api_url, desknet_api_key (masked input), desknet_sync_cron, desknet_sync_enabled
- Validation: time format, numeric ranges, valid cron expression.

### 2.2 Public Holiday Management

#### 2.2.1 Malaysia Holiday Seeder

Create `database/seeders/MalaysiaHolidaySeeder.php`:

- Contains an array of gazetted Malaysia public holidays for the current year.
- Includes both **fixed-date** (Merdeka Day, Malaysia Day, Labour Day, etc.) and **variable-date** holidays (Hari Raya Aidilfitri, Hari Raya Haji, Chinese New Year, Deepavali, Thaipusam, Nuzul Al-Quran, etc.).
- Fixed-date holidays: `is_recurring = 1`.
- Variable-date holidays: `is_recurring = 0` (must be updated yearly).
- All seeded as `source = 'gazetted'`, `created_by = NULL`.

Example seed data structure:
```php
[
    ['date' => '2026-01-01', 'name' => 'New Year\'s Day',        'recurring' => true],
    ['date' => '2026-01-29', 'name' => 'Thaipusam',              'recurring' => false],
    ['date' => '2026-02-01', 'name' => 'Federal Territory Day',  'recurring' => true],
    ['date' => '2026-02-17', 'name' => 'Chinese New Year',       'recurring' => false],
    ['date' => '2026-02-18', 'name' => 'Chinese New Year (2nd day)', 'recurring' => false],
    ['date' => '2026-03-22', 'name' => 'Nuzul Al-Quran',         'recurring' => false],
    ['date' => '2026-03-31', 'name' => 'Hari Raya Aidilfitri',   'recurring' => false],
    ['date' => '2026-04-01', 'name' => 'Hari Raya Aidilfitri (2nd day)', 'recurring' => false],
    ['date' => '2026-05-01', 'name' => 'Labour Day',             'recurring' => true],
    ['date' => '2026-05-26', 'name' => 'Wesak Day',              'recurring' => false],
    ['date' => '2026-06-02', 'name' => 'Agong Birthday',         'recurring' => false],
    ['date' => '2026-06-07', 'name' => 'Hari Raya Haji',         'recurring' => false],
    ['date' => '2026-06-08', 'name' => 'Hari Raya Haji (2nd day)', 'recurring' => false],
    ['date' => '2026-06-28', 'name' => 'Awal Muharram',          'recurring' => false],
    ['date' => '2026-08-31', 'name' => 'Merdeka Day',            'recurring' => true],
    ['date' => '2026-09-07', 'name' => 'Maulidur Rasul',         'recurring' => false],
    ['date' => '2026-09-16', 'name' => 'Malaysia Day',           'recurring' => true],
    ['date' => '2026-10-29', 'name' => 'Deepavali',              'recurring' => false],
    ['date' => '2026-12-25', 'name' => 'Christmas Day',          'recurring' => true],
    // ... add state-specific if needed
]
```

- Create an Artisan command `php artisan holidays:seed {year}` to seed holidays for a given year.

#### 2.2.2 Admin Holiday UI

| Route | Action |
|-------|--------|
| `GET /admin/holidays` | List holidays for selected year (default: current year) |
| `POST /admin/holidays` | Add new company holiday |
| `PUT /admin/holidays/{id}` | Edit holiday name/date |
| `DELETE /admin/holidays/{id}` | Delete (company holidays only; gazetted can be edited but not deleted) |

- UI: Calendar/table view showing:
  - Date, name, source badge (`gazetted` = blue, `company` = orange), is_recurring indicator.
  - Year dropdown to switch between years.
  - "Add Company Holiday" button → modal form (date picker, name).
  - Edit inline or via modal.
  - Delete button only for `source = 'company'`.

### 2.3 Files to Create

```
app/Http/Controllers/Admin/SettingsController.php
app/Http/Controllers/Admin/PublicHolidayController.php
app/Console/Commands/SeedHolidays.php
database/seeders/MalaysiaHolidaySeeder.php
resources/views/admin/settings/index.blade.php
resources/views/admin/holidays/index.blade.php
resources/views/admin/holidays/_form.blade.php  (partial for modal)
```

### Deliverables (Phase 2)

- [ ] System config page: view & edit all settings grouped by category
- [ ] Malaysia public holidays seeded for current year
- [ ] `php artisan holidays:seed {year}` command working
- [ ] Admin can view holidays by year
- [ ] Admin can add company holidays
- [ ] Admin can edit any holiday
- [ ] Admin can delete company holidays only
- [ ] Source badges (gazetted / company) displayed in UI

---

## Phase 3 — Desknet API Sync (Project Codes & Staff)

**Goal:** Fetch project codes and staff list from Desknet API, sync to local DB, log all sync activity.

### 3.1 Desknet Service Class

Create `app/Services/DesknetSyncService.php`:

```php
class DesknetSyncService
{
    public function syncAll(): DesknetSyncLog { ... }
    public function syncStaff(): DesknetSyncLog { ... }
    public function syncProjectCodes(): DesknetSyncLog { ... }
    public function syncDepartments(): DesknetSyncLog { ... }

    // Private helpers:
    private function callDesknetApi(string $endpoint): array { ... }
    private function upsertStaff(array $desknetData): array { ... }
    private function upsertProjectCodes(array $desknetData): array { ... }
    private function upsertDepartments(array $desknetData): array { ... }
    private function deactivateRemoved(string $table, array $activeDesknetIds): int { ... }
}
```

**Sync logic per entity:**
1. Call Desknet API → get list of records.
2. For each record:
   - Match by `desknet_id`.
   - If not found → INSERT (new record).
   - If found & data changed → UPDATE (name, email, etc.).
   - If found & data same → skip.
3. After processing all: any local record with `desknet_id` NOT in API response → SET `is_active = 0`.
4. For **users**: only sync read-only fields (name, email, staff_no, department_id). Do NOT overwrite `role` or `reports_to`.
5. For **new users**: set default password (hashed), `role = 'staff'`. Admin must assign role later.
6. Log results in `desknet_sync_log`.

### 3.2 Scheduled Sync (Cron)

Create `app/Console/Commands/SyncDesknet.php`:

```bash
php artisan desknet:sync          # full sync
php artisan desknet:sync --type=staff
php artisan desknet:sync --type=project_codes
php artisan desknet:sync --type=departments
```

Register in `routes/console.php`:
```php
Schedule::command('desknet:sync')
    ->cron(SystemConfig::get('desknet_sync_cron'))
    ->when(fn () => SystemConfig::get('desknet_sync_enabled') === '1');
```

**Note:** For XAMPP local dev, use Windows Task Scheduler or run `php artisan schedule:work` manually. In production, configure a proper cron job.

### 3.3 Admin Sync Dashboard

| Route | Action |
|-------|--------|
| `GET /admin/desknet-sync` | Show sync dashboard: last sync status, history, record counts |
| `POST /admin/desknet-sync/trigger` | Trigger manual sync (AJAX) |
| `GET /admin/desknet-sync/log/{id}` | View detailed sync log |

- UI shows:
  - Last sync: timestamp, status badge (success/partial/failed), records created/updated/deactivated.
  - "Sync Now" button (with loading spinner).
  - History table: last 20 syncs with pagination.
  - Error details if sync failed.

### 3.4 Admin: Project Codes (Read-Only View)

| Route | Action |
|-------|--------|
| `GET /admin/project-codes` | List all project codes (read-only, from Desknet) |

- Table: code, name, client, desknet_id, is_active, last_synced_at.
- Search by code, name, or client.
- Filter by active/inactive.
- No create/edit/delete buttons — all data is from Desknet.

### 3.5 Files to Create

```
app/Services/DesknetSyncService.php
app/Console/Commands/SyncDesknet.php
app/Http/Controllers/Admin/DesknetSyncController.php
app/Http/Controllers/Admin/ProjectCodeController.php
resources/views/admin/desknet-sync/index.blade.php
resources/views/admin/desknet-sync/show.blade.php
resources/views/admin/project-codes/index.blade.php
```

### Deliverables (Phase 3)

- [ ] `DesknetSyncService` class with upsert + deactivate logic
- [ ] `php artisan desknet:sync` command working
- [ ] Scheduled sync registered (cron-based)
- [ ] `desknet_sync_log` populated after each sync
- [ ] Admin sync dashboard: view status, trigger manual sync, view history
- [ ] Admin project codes page: read-only list with search & filter
- [ ] New users from Desknet get default password + `staff` role

---

## Phase 4 — Timesheet: Create & Fill

**Goal:** Staff can create a monthly timesheet and fill in the two-table matrix (Admin Job + Project Hours).

### 4.1 Timesheet List Page

| Route | Action |
|-------|--------|
| `GET /timesheets` | Show list of own timesheets (month/year, status, actions) |
| `POST /timesheets` | Create new draft timesheet for selected month/year |

- UI: Table showing month, year, status badge, created date, action buttons (View/Edit/Delete).
- "New Timesheet" button → dropdown for month + year, then create.
- Prevent duplicate: one timesheet per user per month (enforced by unique key).

### 4.2 Timesheet Edit Page (Main Matrix View)

| Route | Action |
|-------|--------|
| `GET /timesheets/{id}/edit` | Show full timesheet matrix for editing |
| `PUT /timesheets/{id}` | Save all timesheet data (auto-save or manual save) |

This is the **core UI** — must mirror the physical form exactly.

#### 4.2.1 Header Section

- Staff name (auto), Employee No (auto), Month/Year (auto), Department (auto).
- All read-only.

#### 4.2.2 Day Columns (1–31)

- Render columns for each day of the selected month.
- Color coding based on `timesheet_day_metadata.day_type`:
  - **White** = working day
  - **Yellow** = off day (Saturday/Sunday)
  - **Red** = public holiday (from `public_holidays` table)
- Day-of-week label above each column (MON, TUE, etc.).
- Available hours auto-calculated per day:
  - Working day (Mon–Thu) = 8 hrs
  - Friday = 7 hrs
  - Off day / Holiday = 0 hrs

#### 4.2.3 Upper Table — Admin Job (8 Fixed Rows)

| Row # | `admin_type` | Description |
|-------|-------------|-------------|
| 1 | `mc_leave` | MC / LEAVE — auto-populated from Excel upload |
| 2 | `late` | LATE — auto-populated from Excel upload |
| 3 | `morning_assy` | MORNING ASSY / ADMIN JOB — auto-populated from morning assembly |
| 4 | `five_s` | 5S |
| 5 | `ceramah_event` | CERAMAH AGAMA / EVENT / ADP |
| 6 | `iso` | ISO |
| 7 | `training` | TRAINING / SEMINAR / VISIT |
| 8 | `admin_category` | RFQ / MKT / PUR / R&D / A.S.S / TDR |

- Rows 1–3 are auto-filled (read-only or editable with warning).
- Rows 4–8 are manually editable.
- TOTAL ADMIN JOB row: computed SUM of all 8 rows per day.
- Each cell = `timesheet_admin_hours.hours` (decimal, e.g., 1.5).

#### 4.2.4 Lower Table — Project Hours (Dynamic Rows)

- "Add Project" button → creates a new `timesheet_project_rows` entry.
- Each project row has:
  - Project NO (auto-increment: 1, 2, 3...)
  - PROJECT CODE dropdown (from `project_codes` where `is_active = 1`)
  - Project Name (auto-filled from selected code)
  - **4 sub-rows per project:**
    - NORMAL → NC
    - NORMAL → COBQ
    - OT → NC
    - OT → COBQ
  - Each sub-row has cells for each day = `timesheet_project_hours` columns.
- "Remove Project" button per row.

#### 4.2.5 Summary Rows (Computed, Read-Only)

| Row | Calculation |
|-----|-------------|
| TOTAL EXTERNAL PROJECT | SUM(normal_nc + normal_cobq) across all projects per day |
| TOTAL WORKING HOURS | TOTAL ADMIN JOB + TOTAL EXTERNAL PROJECT |
| HOURS AVAILABLE | From `timesheet_day_metadata.available_hours` |
| OVERTIME | SUM(ot_nc + ot_cobq) across all projects per day |

#### 4.2.6 Save Mechanism

- **Auto-save** via AJAX: debounce 2 seconds after last cell edit.
- Or manual "Save" button.
- Save endpoint receives the full matrix as JSON (see design doc Section 6.3 mapping).
- Backend validates and upserts `timesheet_admin_hours` + `timesheet_project_hours`.

### 4.3 Business Rules (Backend Validation)

Implement in `app/Services/TimesheetCalculationService.php`:

- Available hours: 8 (Mon–Thu), 7 (Fri), 0 (off/holiday).
- Total Working Hours per day should not exceed Available Hours (warning, not hard block).
- OT rows should only have values on days where the staff actually worked OT (from Excel data).
- Late: ceiling to 30-min blocks (e.g., 10 min late → 0.5 hrs).
- OT: floor to whole hours (e.g., 1 hr 45 min → 1 hr) for timesheet (precise for OT form).

### 4.4 Files to Create

```
app/Http/Controllers/TimesheetController.php
app/Services/TimesheetCalculationService.php
resources/views/timesheets/index.blade.php
resources/views/timesheets/edit.blade.php
resources/views/timesheets/partials/_header.blade.php
resources/views/timesheets/partials/_upper_table.blade.php
resources/views/timesheets/partials/_lower_table.blade.php
resources/views/timesheets/partials/_summary_rows.blade.php
resources/js/timesheet-matrix.js  (Alpine.js component for interactive matrix)
```

### Deliverables (Phase 4)

- [ ] Timesheet list page with create/view/edit
- [ ] Full matrix edit page mirroring physical form
- [ ] Day columns with color coding (working/off/holiday)
- [ ] Upper table: 8 admin rows, editable cells, total row
- [ ] Lower table: dynamic project rows with 4 sub-rows each
- [ ] Project code dropdown populated from DB
- [ ] Summary rows auto-calculated
- [ ] Auto-save (debounced AJAX) or manual save
- [ ] Business rules: available hours, late rounding, OT floor
- [ ] Responsive horizontal scroll for the day matrix

---

## Phase 5 — PDF Upload & Autofill

**Goal:** Staff uploads the Infotech PDF attendance report, system parses it and auto-fills day metadata + upper table admin rows (1–8 with defaults).

### 5.1 Upload UI

| Route | Action |
|-------|--------|
| `GET /timesheets/{id}/upload` | Show upload form (or inline in edit page) |
| `POST /timesheets/{id}/upload-attendance` | Process uploaded PDF file |

- Accept `.pdf` (primary), `.xlsx`/`.xls` (legacy fallback).
- Auto-submit on file select / drag-drop.
- Show loading spinner during processing.
- Display flash messages (success/warning/error) above form.

### 5.2 PDF Parsing Service

Create `app/Services/PdfParsingService.php` (alongside existing `ExcelParsingService.php` for fallback):

- Use `smalot/pdfparser` or `spatie/pdf-to-text` for PDF text extraction:
  ```bash
  composer require smalot/pdfparser
  ```
- Parse the Infotech "Individual Attendance Report With Actual Clock" PDF format:
  - Extract employee info (Emp Code, Name)
  - Detect report period and validate against timesheet month/year
  - Each row = one day
  - Columns: Date, Day, Clk1-4, In, Out, Shift, Normal, Late, EarlyOut, Actual, OT, Reason

**Parsing algorithm** (from `SYSTEM_DESIGN_v2.md` Section 5.4):

```
For each row in PDF:
  1. Read date, day, time_in, time_out, reason
  2. Determine day_type:
     - Reason = "PH" → 'public_holiday'
     - Saturday/Sunday → 'off_day'
     - Reason = "CAL" or no clock data → 'mc' or 'leave'
     - Else → 'working'
  3. Calculate late_hours:
     - If time_in > 08:30 on working day:
       late_min = time_in - 08:30
       late_hours = ceil(late_min / 30) * 0.5
  4. Calculate ot_eligible_hours:
     - If time_out > 17:30:
       ot_min = time_out - 17:30
       ot_hours = floor(ot_min / 60)
  5. Calculate attendance_hours:
     - time_out - time_in - lunch_break (60 min)
  6. Calculate available_hours:
     - If Friday: 7
     - If Mon-Thu: 8
     - If off_day/holiday: 0
     - If mc/leave: 8 or 7 (based on day)
  7. Upsert into timesheet_day_metadata
  8. Auto-populate admin rows:
     - Row 1 (MC/LEAVE): 8 (Mon-Thu) or 7 (Fri) when no clock data
     - Row 2 (LATE): Calculated late hours (0.5 increments)
     - Row 3 (MORNING ASSY): Default 0.5 on working days (excl weekends/PH)
     - Row 4 (5S): Default 0.5 on working days (excl weekends/PH)
     - Row 5-7: Leave blank (staff fills manually)
     - Row 8: Leave blank (staff fills manually)
  9. TOTAL ADMIN JOB = sum(Row1..Row8) per day; if 0, display '0'
```

### 5.3 Post-Upload Response

Return to the edit page with:
- Success message + number of days processed.
- Warnings for anomalies (highlighted in UI).
- Day metadata populated → color coding applied.
- Upper table admin rows auto-filled (Row 1-4 with defaults, Row 5-8 blank).

### 5.4 Summary Row Formulas (auto-calculated in frontend)

| Row | Formula |
|-----|---------|
| **TOTAL ADMIN JOB** | Row1 + Row2 + Row3 + Row4 + Row5 + Row6 + Row7 + Row8 |
| **TOTAL EXTERNAL PROJECT** | Sum of all project sub-rows (normal_nc + normal_cobq + ot_nc + ot_cobq) per day |
| **TOTAL WORKING HOURS** | TOTAL ADMIN JOB + TOTAL EXTERNAL PROJECT |
| **HOURS AVAILABLE** | Mon-Thu: 8, Fri: 7, Sat-Sun: 0, Public Holiday: 0 |
| **OVERTIME** | TOTAL WORKING HOURS − HOURS AVAILABLE |

### 5.5 Files to Create / Update

```
app/Services/PdfParsingService.php        (NEW: PDF text extraction + parsing)
app/Services/ExcelParsingService.php      (EXISTING: legacy Excel fallback)
app/Http/Controllers/AttendanceUploadController.php  (renamed from ExcelUploadController)
resources/views/timesheets/partials/_upload.blade.php
```

### 5.6 Print Layout (Landscape A4)

- Print view route: `GET /timesheets/{id}/print`
- Matches physical form layout exactly
- Landscape A4 orientation
- Default 5 project columns (expandable if staff has more projects)
- All 8 admin rows displayed with same labels as physical form
- Summary rows at bottom
- Signatures section: PREPARED / CHECKED / APPROVED
- Notes section with working hours rules
- `@media print` CSS for proper page breaks and margins

### Deliverables (Phase 5)

- [ ] PDF upload UI (drag & drop or file picker, auto-submit)
- [ ] PDF parsing service: reads Infotech attendance report format
- [ ] Day metadata auto-populated (day_type, time_in/out, late, OT, available_hours)
- [ ] Upper table rows 1-4 auto-filled with defaults, rows 5-8 blank
- [ ] All admin rows use 0.5-increment dropdowns
- [ ] Summary row formulas implemented (Total Admin Job, Total External, Total Working, Overtime)
- [ ] Color coding updates after upload
- [ ] `attendance_uploads` table tracking upload history
- [ ] Warnings displayed for anomalies (period mismatch, missing data)
- [ ] Re-upload overwrites previous data (with confirmation)
- [ ] Print layout: landscape A4 matching physical form

---

## Phase 6 — Timesheet: Submission & Approval Workflow

**Goal:** Staff submits timesheet, L1 (Asst Mgr) reviews, L2 (Mgr/HOD) approves. Status transitions and approval logs.

### 6.1 Submission

| Route | Action |
|-------|--------|
| `POST /timesheets/{id}/submit` | Submit timesheet for approval |

**Pre-submit validation:**
- All working days must have hours filled (at least admin or project).
- Total Working Hours per day should match Available Hours (warning).
- At least one project row exists.
- Excel has been uploaded (day metadata exists).

On submit:
- `status` → `pending_l1`
- `submitted_at` → now
- Timesheet becomes **read-only** for staff.

### 6.2 Approval Pages

| Route | Action |
|-------|--------|
| `GET /approvals/timesheets` | List timesheets pending my approval |
| `GET /approvals/timesheets/{id}` | View timesheet for review (read-only matrix) |
| `POST /approvals/timesheets/{id}/approve` | Approve |
| `POST /approvals/timesheets/{id}/reject` | Reject (with remarks) |

**L1 Approval (Assistant Manager):**
- Sees timesheets from users where `reports_to = current_user_id` (or department-based).
- On approve: `status` → `pending_l2`, log in `approval_logs` (level=1, action=approved).
- On reject: `status` → `rejected_l1`, log in `approval_logs` (level=1, action=rejected).
  - Staff gets notification, can edit and resubmit.

**L2 Approval (Manager / HOD):**
- Sees timesheets with `status = pending_l2`.
- On approve: `status` → `approved`, log in `approval_logs` (level=2, action=approved).
- On reject: `status` → `rejected_l2`, log in `approval_logs`.
  - Staff gets notification, can edit and resubmit.

### 6.3 Status Badge Display

| Status | Badge Color | Label |
|--------|-------------|-------|
| `draft` | Gray | Draft |
| `pending_l1` | Yellow | Pending Review |
| `pending_l2` | Orange | Pending Approval |
| `approved` | Green | Approved |
| `rejected_l1` | Red | Rejected (L1) |
| `rejected_l2` | Red | Rejected (L2) |

### 6.4 Files to Create

```
app/Http/Controllers/ApprovalController.php
app/Services/ApprovalService.php
resources/views/approvals/timesheets/index.blade.php
resources/views/approvals/timesheets/show.blade.php
```

### Deliverables (Phase 6)

- [ ] Submit button with pre-validation
- [ ] Status transitions: draft → pending_l1 → pending_l2 → approved
- [ ] Rejection flow: back to staff for editing
- [ ] L1 approval page (list + review + approve/reject)
- [ ] L2 approval page (list + review + approve/reject)
- [ ] `approval_logs` populated on every action
- [ ] Timesheet read-only after submission
- [ ] Timesheet editable again after rejection
- [ ] Status badges on all pages

---

## Phase 7 — OT Form: Create, Plan & Pre-Approval (Phase 1)

**Goal:** Staff creates OT form (Executive or Non-Executive), fills plan, submits for pre-approval. Approval chain differs by form type.

### 7.0 Two OT Form Types

The system supports **two distinct OT form formats**:

| Aspect | Executive (OCF) | Non-Executive (BKLM) |
|--------|----------------|---------------------|
| **Language** | English | Bahasa Melayu |
| **Row Layout** | Dynamic rows (only OT days) | Fixed 31 rows (one per day of month) |
| **Extra Columns** | None | MAKAN, LEBIH 0-3 JAM, SHIFT, JENIS OT, PENGIRAAN OT |
| **Pre-Approval** | Staff → HOD → DGM/CEO | Staff → KAKITANGAN/EXEC./Asst. Mgr → HOD → DGM/CEO |
| **Post-Approval** | Staff → HOD | Staff → MGR/HOD → DGM/CEO |
| **OT Breakdown** | NORMAL DAY, REST DAY, PUBLIC HOLIDAY | OT 1–5 rate tiers |

The `form_type` field on `ot_forms` determines which layout and approval chain to use.

### 7.1 OT Form List Page

| Route | Action |
|-------|--------|
| `GET /ot-forms` | List own OT forms (month/year, form type, status, actions) |
| `POST /ot-forms` | Create new draft OT form for selected month/year + company + form type |

**Create form fields:**
- Month/Year (dropdown)
- Company name (tick one checkbox)
- Form type: Executive or Non-Executive (radio button)
- Section/Line (optional text)

### 7.2 OT Form Edit Page — Plan

| Route | Action |
|-------|--------|
| `GET /ot-forms/{id}/edit` | Show OT form for editing (layout depends on form_type) |
| `PUT /ot-forms/{id}` | Save OT form data |

**Executive Plan UI:**
- Table with columns: DATE, PARTICULARS, PLAN START, PLAN END, TOTAL HOURS.
- "Add Entry" button → new row.
- DATE: date picker (restricted to selected month).
- PARTICULARS: dropdown from `project_codes`.
- PLAN START: time picker (default 17:30, must be ≥ 17:30).
- PLAN END: time picker (must be > PLAN START).
- TOTAL HOURS: auto-calculated (end − start, as decimal).
- Grand total of planned hours at bottom.
- "Remove Entry" button per row.

**Non-Executive Plan UI (Bahasa Melayu labels):**
- Table with 31 pre-filled rows (TARIKH 1–31).
- Columns: TARIKH, TUGAS ATAU AKTIVITI, MASA DIRANCANG (MULA, TAMAT, JUMLAH).
- TUGAS ATAU AKTIVITI: dropdown from `project_codes` (only fill rows with OT).
- MULA: time picker.
- TAMAT: time picker.
- JUMLAH: auto-calculated.
- Extra columns visible but read-only during plan phase: MAKAN, LEBIH 0-3 JAM, SHIFT.
- Grand total (JUMLAH) at bottom.

### 7.3 Submit Plan for Pre-Approval

| Route | Action |
|-------|--------|
| `POST /ot-forms/{id}/submit-plan` | Submit plan for pre-approval |

**Validation (both types):**
- At least 1 entry with planned times exists.
- Every filled row has: date, project, start, end (end > start).
- Submission must be before 4:30 PM (system rule from design doc).

On submit:
- Executive: `status` → `pending_hod_pre`, `plan_submitted_at` → now
- Non-Executive: `status` → `pending_asst_mgr_pre`, `plan_submitted_at` → now

### 7.4 Pre-Approval

| Route | Action |
|-------|--------|
| `GET /approvals/ot-forms` | List OT forms pending my approval |
| `GET /approvals/ot-forms/{id}` | View OT form for review |
| `POST /approvals/ot-forms/{id}/approve-pre` | Approve pre-approval |
| `POST /approvals/ot-forms/{id}/reject-pre` | Reject pre-approval |

**Executive pre-approval chain:**
1. HOD (L1 pre): approve → `pending_ceo_pre`, reject → `rejected_pre`
2. DGM/CEO (L2 pre): approve → `pre_approved`, reject → `rejected_pre`

**Non-Executive pre-approval chain:**
1. KAKITANGAN/EXEC./Asst. Mgr (L1 pre): approve → `pending_hod_pre`, reject → `rejected_pre`
2. HOD (L2 pre): approve → `pending_ceo_pre`, reject → `rejected_pre`
3. DGM/CEO (L3 pre): approve → `pre_approved`, reject → `rejected_pre`

All actions logged in `approval_logs` with `phase = 'pre'`.

### 7.5 Files to Create

```
database/migrations/xxxx_create_ot_forms_table.php
database/migrations/xxxx_create_ot_form_entries_table.php
app/Models/OtForm.php
app/Models/OtFormEntry.php
app/Http/Controllers/OtFormController.php
app/Http/Controllers/OtApprovalController.php
app/Services/OtFormService.php
resources/views/ot-forms/index.blade.php
resources/views/ot-forms/edit.blade.php
resources/views/ot-forms/partials/_executive_plan.blade.php
resources/views/ot-forms/partials/_non_executive_plan.blade.php
resources/views/approvals/ot-forms/index.blade.php
resources/views/approvals/ot-forms/show.blade.php
```

### Deliverables (Phase 7)

- [ ] Database migration for `ot_forms` (with `form_type`) and `ot_form_entries` (with non-exec columns)
- [ ] OT form list page with create (select Executive or Non-Executive)
- [ ] Executive plan edit page: dynamic entry rows, time pickers, auto-calc total
- [ ] Non-Executive plan edit page: 31 fixed rows, BM labels, extra columns (MAKAN, SHIFT, etc.)
- [ ] Plan submit with validation
- [ ] Executive pre-approval: HOD → DGM/CEO
- [ ] Non-Executive pre-approval: Asst. Mgr → HOD → DGM/CEO
- [ ] Status transitions for both form types
- [ ] Rejection flow for pre-approval
- [ ] `approval_logs` with `phase = 'pre'`

---

## Phase 8 — OT Form: Actual Entry & Post-Approval (Phase 2)

**Goal:** After OT is done, staff fills actual hours, system categorizes by day type (Executive) or OT rate tiers (Non-Executive). Post-approval chain differs by form type.

### 8.1 Fill Actual Hours

After `status = pre_approved`, the edit page unlocks the **Actual** columns.

**Executive — Actual columns:**
- ACTUAL START: time picker.
- ACTUAL END: time picker.
- ACTUAL TOTAL HOURS: auto-calculated (end − start, precise decimal).
- Day type columns auto-categorized by system:
  - **NORMAL DAY** hours: if `entry_date` is a working day (Mon–Fri, not holiday).
  - **REST DAY** hours: if `entry_date` is Saturday or Sunday.
  - **PUBLIC HOLIDAY** hours: if `entry_date` is in `public_holidays` table.
- Staff cannot manually edit day type columns — system fills them.

**Non-Executive — Actual columns (BM labels):**
- MASA SEBENAR — MULA: time picker.
- MASA SEBENAR — TAMAT: time picker.
- MASA SEBENAR — JUMLAH: auto-calculated.
- MAKAN: checkbox (meal break taken, auto-set if OT > 3 hours).
- LEBIH 0-3 JAM: auto-calculated (flag if actual total > 3 hours).
- SHIFT: checkbox.
- JENIS OT: dropdown (NORMAL, TRAINING, KAIZEN, SS) — staff selects per row.
- PENGIRAAN OT: OT 1–5 rate tier hours — auto-calculated based on day type and hours.

Backend logic in `OtFormService`:
```php
// Executive: categorize by day type
public function categorizeByDayType(OtFormEntry $entry): void
{
    $date = $entry->entry_date;
    $hours = $entry->actual_total_hours;

    if (PublicHoliday::where('holiday_date', $date)->exists()) {
        $entry->public_holiday_hours = $hours;
    } elseif (in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
        $entry->rest_day_hours = $hours;
    } else {
        $entry->normal_day_hours = $hours;
    }
}

// Non-Executive: calculate OT rate tiers
public function calculateOtRates(OtFormEntry $entry): void
{
    $date = $entry->entry_date;
    $hours = $entry->actual_total_hours;

    // Auto-set meal break and over 3 hours flags
    $entry->over_3_hours = $hours > 3 ? 1 : 0;

    // Distribute hours into OT rate tiers based on day type and hours
    // OT 1-5 rate distribution logic TBD based on company policy
}
```

### 8.2 Submit Actual Claim

| Route | Action |
|-------|--------|
| `POST /ot-forms/{id}/submit-actual` | Submit actual hours for post-approval |

**Validation (both types):**
- All planned entries must have actual times filled.
- Actual End > Actual Start.

**Non-Executive additional validation:**
- JENIS OT must be selected for each filled row.

On submit:
- Executive: `status` → `pending_hod_post`, `actual_submitted_at` → now
- Non-Executive: `status` → `pending_mgr_post`, `actual_submitted_at` → now

### 8.3 Post-Approval

| Route | Action |
|-------|--------|
| `POST /approvals/ot-forms/{id}/approve-post` | Approve actual claim |
| `POST /approvals/ot-forms/{id}/reject-post` | Reject actual claim |

**Executive post-approval chain:**
1. HOD (L1 post): approve → `approved`, reject → `rejected_post`

**Non-Executive post-approval chain:**
1. MGR/HOD (L1 post): approve → `pending_ceo_post`, reject → `rejected_post`
2. DGM/CEO (L2 post): approve → `approved`, reject → `rejected_post`

All actions logged in `approval_logs` with `phase = 'post'`.

### 8.4 OT Form Summary (Footer)

**Executive footer:**
- TOTAL (HOURS) Plan: `SUM(planned_total_hours)`
- TOTAL (HOURS) Actual: `SUM(actual_total_hours)`
- NORMAL DAY total: `SUM(normal_day_hours)`
- REST DAY total: `SUM(rest_day_hours)`
- PUBLIC HOLIDAY total: `SUM(public_holiday_hours)`

**Non-Executive footer (BM labels):**
- JUMLAH (Plan): `SUM(planned_total_hours)`
- JUMLAH (Actual): `SUM(actual_total_hours)`
- JUMLAH JAM OT: `ot_forms.total_ot_hours`
- OT 1–5 column totals: `SUM(ot_rate_1)` .. `SUM(ot_rate_5)`

**Both:** Monthly OT claim limit check — warn if total exceeds RM 500.00.

### 8.5 Files to Create / Modify

```
resources/views/ot-forms/partials/_executive_actual.blade.php
resources/views/ot-forms/partials/_non_executive_actual.blade.php
resources/views/ot-forms/partials/_executive_footer.blade.php
resources/views/ot-forms/partials/_non_executive_footer.blade.php
(Extend existing OtFormController, OtApprovalController, OtFormService)
```

### Deliverables (Phase 8)

- [ ] Executive actual hours entry UI (unlocked after pre_approved)
- [ ] Non-Executive actual hours entry UI with BM labels, MAKAN, SHIFT, JENIS OT, PENGIRAAN OT
- [ ] Executive: auto day-type categorization (normal/rest/public_holiday)
- [ ] Non-Executive: auto OT rate tier calculation (OT 1–5)
- [ ] Submit actual claim with validation (both types)
- [ ] Executive post-approval: HOD approves/rejects
- [ ] Non-Executive post-approval: MGR/HOD → DGM/CEO approves/rejects
- [ ] Status transitions for both form types
- [ ] Rejection flow for post-approval
- [ ] `approval_logs` with `phase = 'post'`
- [ ] Executive summary footer with day-type totals
- [ ] Non-Executive summary footer with OT rate tier totals + JUMLAH JAM OT
- [ ] RM 500.00/month limit warning

---

## Phase 9 — Notifications

**Goal:** In-app notifications for approval requests, status changes, and sync alerts.

### 9.1 Notification Triggers

| Event | Recipient | Message |
|-------|-----------|---------|
| Timesheet submitted | L1 approver | "New timesheet from {name} for {month}/{year}" |
| Timesheet L1 approved | L2 approver | "Timesheet from {name} passed L1 review" |
| Timesheet approved | Staff | "Your timesheet for {month}/{year} has been approved" |
| Timesheet rejected | Staff | "Your timesheet for {month}/{year} was rejected: {remarks}" |
| OT plan submitted | HOD | "OT plan from {name} needs pre-approval" |
| OT plan pre-approved (HOD) | DGM/CEO | "OT plan from {name} needs final pre-approval" |
| OT plan pre-approved (all) | Staff | "Your OT plan is approved — you may proceed" |
| OT plan rejected (pre) | Staff | "Your OT plan was rejected: {remarks}" |
| OT actual submitted | HOD | "OT claim from {name} needs post-approval" |
| OT actual approved | Staff | "Your OT claim for {month}/{year} has been approved" |
| OT actual rejected (post) | Staff | "Your OT claim was rejected: {remarks}" |
| Desknet sync failed | Admin | "Desknet sync failed: {error}" |

### 9.2 Implementation

- Create `app/Services/NotificationService.php` — centralized notification creation.
- Bell icon in topbar with unread count (badge).
- Dropdown shows latest 10 notifications.
- "View All" → `/notifications` page with full list, mark as read.
- Mark all as read button.

### 9.3 Files to Create

```
app/Services/NotificationService.php
app/Http/Controllers/NotificationController.php
resources/views/notifications/index.blade.php
resources/views/layouts/partials/_notification_bell.blade.php
```

### Deliverables (Phase 9)

- [ ] Notifications created on all approval events
- [ ] Bell icon with unread count
- [ ] Dropdown preview of latest notifications
- [ ] Full notifications page
- [ ] Mark as read (individual + all)
- [ ] Link in notification navigates to the relevant form

---

## Phase 10 — Reports & Export

**Goal:** Admin and managers can view summary reports and export to Excel/PDF.

### 10.1 Report Types

| Report | Route | Access |
|--------|-------|--------|
| Monthly Timesheet Summary | `GET /reports/timesheets` | Admin, Mgr/HOD, CEO |
| OT Summary | `GET /reports/ot` | Admin, Mgr/HOD, CEO |
| Late Report | `GET /reports/late` | Admin, Mgr/HOD |
| MC/Leave Report | `GET /reports/mc-leave` | Admin, Mgr/HOD |
| Desknet Sync Audit | `GET /reports/desknet-sync` | Admin |

### 10.2 Filters & Grouping

All reports support:
- Date range (month/year).
- Department filter.
- Staff filter.
- Group by: department, individual.
- Export as Excel (`.xlsx`) or PDF.

### 10.3 Export Packages

```bash
composer require maatwebsite/excel   # already installed in Phase 5
composer require barryvdh/laravel-dompdf
```

### 10.4 Files to Create

```
app/Http/Controllers/ReportController.php
app/Exports/TimesheetSummaryExport.php
app/Exports/OtSummaryExport.php
app/Exports/LateReportExport.php
app/Exports/McLeaveReportExport.php
resources/views/reports/timesheets.blade.php
resources/views/reports/ot.blade.php
resources/views/reports/late.blade.php
resources/views/reports/mc-leave.blade.php
resources/views/reports/desknet-sync.blade.php
resources/views/reports/pdf/*.blade.php  (PDF templates)
```

### Deliverables (Phase 10)

- [ ] All 5 report pages with filters
- [ ] Data tables with sorting and pagination
- [ ] Export to Excel working
- [ ] Export to PDF working
- [ ] Role-based access to reports

---

## Phase 11 — Morning Assembly Integration

**Goal:** Integrate Google Form → Apps Script → System for morning assembly attendance tracking.

### 11.1 Google Form Setup (External)

- Google Form with fields: Email, Date, Attended (Yes/No).
- Google Apps Script on form submit → POST to system webhook.

### 11.2 Webhook Endpoint

| Route | Action |
|-------|--------|
| `POST /api/morning-assembly/webhook` | Receive attendance data from Google Apps Script |

- Validate incoming data (date, email, attended).
- Match email to `users.email`.
- Upsert into `morning_assembly_log`.
- Auto-update `timesheet_admin_hours` (admin_type = 'morning_assy') for the matching timesheet.

### 11.3 API Authentication

- Use a simple API token (stored in `system_config` as `morning_assembly_api_token`).
- Google Apps Script sends the token in the request header.

### 11.4 Files to Create

```
app/Http/Controllers/Api/MorningAssemblyController.php
routes/api.php
```

### Deliverables (Phase 11)

- [ ] Webhook endpoint receiving Google Form data
- [ ] `morning_assembly_log` populated
- [ ] Auto-update morning assembly hours in timesheet
- [ ] API token authentication
- [ ] Google Apps Script sample code provided

---

## Phase 12 — Testing, QA & Deployment

**Goal:** Comprehensive testing, bug fixes, and production readiness.

### 12.1 Testing Strategy

| Type | Tool | Coverage |
|------|------|----------|
| Unit Tests | PHPUnit | Models, Services (calculation, parsing, sync) |
| Feature Tests | PHPUnit | Controllers, routes, middleware, form submissions |
| Browser Tests | Laravel Dusk | Full workflow: login → create → fill → submit → approve |

**Priority test cases:**
1. Timesheet calculation service (late rounding, OT floor, available hours).
2. Excel parsing with edge cases (missing data, wrong format).
3. Desknet sync (new/update/deactivate logic).
4. Approval workflow state transitions (all valid and invalid transitions).
5. OT form two-phase flow (pre → actual → post).
6. Role-based access (ensure staff can't access admin, etc.).
7. Public holiday impact on day type categorization.

### 12.2 QA Checklist

- [ ] All forms validate correctly (client-side + server-side).
- [ ] No broken links or missing pages.
- [ ] Responsive on desktop (primary) and tablet.
- [ ] Cross-browser: Chrome, Firefox, Edge.
- [ ] Data integrity: no orphaned records, no duplicate timesheets.
- [ ] Performance: matrix page loads < 3 seconds with full month data.
- [ ] Security: CSRF protection, SQL injection prevention, XSS prevention.
- [ ] Error handling: graceful error pages, logged errors.

### 12.3 Deployment (XAMPP — Current)

For now, the app runs locally on XAMPP:

1. Copy project to `D:\XAMPP\htdocs\Timesheet_Website\`.
2. Configure `.env` with production DB credentials.
3. `composer install --optimize-autoloader --no-dev`
4. `php artisan config:cache`
5. `php artisan route:cache`
6. `php artisan view:cache`
7. `php artisan migrate --force`
8. `php artisan db:seed --force` (only for initial setup)
9. Configure Apache virtual host to point to `public/` directory.
10. Set up Windows Task Scheduler for `php artisan schedule:run` (for Desknet sync cron).

### Deliverables (Phase 12)

- [ ] Unit tests for all services
- [ ] Feature tests for all controllers
- [ ] Browser tests for critical workflows
- [ ] QA checklist passed
- [ ] Deployed on XAMPP and accessible

---

## Future Improvements

> These are NOT in scope for the current build. To be revisited after initial deployment.

| # | Improvement | Notes |
|---|------------|-------|
| 1 | **Move to cloud hosting** | AWS / DigitalOcean / shared hosting with proper cron |
| 2 | **Real-time notifications** | Laravel Echo + Pusher / WebSockets |
| 3 | **Email notifications** | SMTP integration for approval alerts |
| 4 | **Mobile responsive** | Full mobile support or PWA |
| 5 | **REST API** | Full API for potential mobile app |
| 6 | **Audit trail** | Detailed change log for every edit (who changed what, when) |
| 7 | **Dashboard analytics** | Charts for OT trends, project hours, department utilization |
| 8 | **Multi-company support** | If system expands to other Ingress companies |
| 9 | **Auto-populate variable holidays** | API integration for Malaysia holiday calendar (e.g., calendarific.com) |
| 10 | **Payroll integration** | Direct export to payroll system format |
| 11 | **SSO / LDAP** | Single sign-on with company directory |
| 12 | **Laravel Reverb** | Replace Pusher with self-hosted WebSockets |

---

## Summary: Phase Timeline

```
Phase 0  — Project Setup & Foundation              ██████░░░░░░░░░░░░░░  (Week 1)
Phase 1  — Authentication & User Management        ██████░░░░░░░░░░░░░░  (Week 1-2)
Phase 2  — Admin: Core Configuration               ████░░░░░░░░░░░░░░░░  (Week 2)
Phase 3  — Desknet API Sync                        ██████░░░░░░░░░░░░░░  (Week 2-3)
Phase 4  — Timesheet: Create & Fill                ██████████░░░░░░░░░░  (Week 3-5)
Phase 5  — Excel Upload & Autofill                 ██████░░░░░░░░░░░░░░  (Week 5-6)
Phase 6  — Timesheet: Submission & Approval        ██████░░░░░░░░░░░░░░  (Week 6-7)
Phase 7  — OT Form: Plan & Pre-Approval            ██████░░░░░░░░░░░░░░  (Week 7-8)
Phase 8  — OT Form: Actual & Post-Approval         ████░░░░░░░░░░░░░░░░  (Week 8-9)
Phase 9  — Notifications                           ████░░░░░░░░░░░░░░░░  (Week 9)
Phase 10 — Reports & Export                        ██████░░░░░░░░░░░░░░  (Week 9-10)
Phase 11 — Morning Assembly Integration            ████░░░░░░░░░░░░░░░░  (Week 10)
Phase 12 — Testing, QA & Deployment                ████████░░░░░░░░░░░░  (Week 10-12)
```

**Estimated Total: ~12 weeks (3 months) for a solo developer.**

---

> **How to use this plan:**
> 1. Work through phases sequentially — each builds on the previous.
> 2. Complete all deliverables (checkboxes) before moving to the next phase.
> 3. Commit to Git after each phase milestone.
> 4. Update this document as requirements evolve.
