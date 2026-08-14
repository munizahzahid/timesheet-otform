# TSSB Portal - Developer Manual

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Tech Stack](#tech-stack)
3. [Project Structure](#project-structure)
4. [Installation](#installation)
5. [Configuration](#configuration)
6. [Database Schema](#database-schema)
7. [Authentication & Authorization](#authentication--authorization)
8. [Key Features](#key-features)
9. [Controllers](#controllers)
10. [Services](#services)
11. [Models](#models)
12. [Routes](#routes)
13. [Email Notifications](#email-notifications)
14. [Telegram Notifications](#telegram-notifications)
15. [Console Commands](#console-commands)
16. [Frontend Components](#frontend-components)
17. [Testing](#testing)
18. [Deployment](#deployment)
19. [Troubleshooting](#troubleshooting)
20. [Redundant/Legacy Code](#redundantlegacy-code)

---

## System Overview

TSSB Portal is a Laravel-based web application for managing employee timesheets, overtime (OT) forms, and project management. The system integrates with Desknet for staff and project data synchronization and supports Telegram notifications.

### Core Modules

- **Timesheet Management** - Monthly timesheet submission with attendance PDF parsing
- **OT Form Management** - Overtime request submission with executive/non-executive forms
- **Approval Workflow** - Multi-level approval system (HOD, L1, L2, L3, HR)
- **Project Management** - Gantt chart, task weights, automatic progress calculation
- **HR Module** - Training attendance, record viewing, OT form review
- **Desknet Integration** - Automatic staff and project data sync
- **Notification System** - Email and Telegram notifications

---

## Tech Stack

- **Backend Framework**: Laravel 12.x
- **Frontend**: Blade Templates, Tailwind CSS, Alpine.js, React (for Gantt chart)
- **Database**: MySQL/MariaDB
- **PHP Version**: 8.2+
- **Excel Library**: PhpSpreadsheet
- **PDF Processing**: smalot/pdfparser
- **Gantt Chart Library**: gantt-task-react
- **Chart Library**: Chart.js

---

## Project Structure

```
Timesheet_Website/
├── app/
│   ├── Console/
│   │   └── Commands/          # Artisan commands
│   ├── Http/
│   │   ├── Controllers/       # All controllers
│   │   ├── Middleware/        # Custom middleware
│   │   └── Requests/          # Form request validation
│   ├── Mail/                  # Email mailable classes
│   ├── Models/                # Eloquent models
│   ├── Services/              # Business logic services
│   └── Observers/             # Model observers
├── database/
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── resources/
│   ├── views/                # Blade templates
│   │   ├── layouts/          # Layout templates
│   │   ├── admin/            # Admin views
│   │   ├── emails/           # Email templates
│   │   └── components/       # Blade components
│   └── js/                   # React components
├── routes/
│   ├── web.php               # Web routes
│   ├── api.php               # API routes
│   └── console.php           # Console routes
└── public/                   # Public assets
```

---

## Installation

### Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL/MariaDB
- Node.js & NPM

### Steps

1. **Clone repository**
   ```bash
   git clone <repository-url>
   cd Timesheet_Website
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database** in `.env`
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=timesheet_db
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Link storage**
   ```bash
   php artisan storage:link
   ```

7. **Build assets**
   ```bash
   npm run build
   ```

8. **Start server**
   ```bash
   php artisan serve
   ```

---

## Configuration

### Environment Variables

Key variables in `.env`:

```env
APP_NAME="TSSB Portal"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=timesheet_db
DB_USERNAME=root
DB_PASSWORD=

# Desknet Integration
DESKNET_API_KEY=your_api_key
DESKNET_API_BASE_URL=https://desknet.example.com/api
DESKNET_SYNC_ENABLED=true

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tssbingress@gmail.com
MAIL_PASSWORD="your_password"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tssbingress@gmail.com
MAIL_FROM_NAME="TSSB Portal"

# Telegram
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_chat_id
```

---

## Database Schema

### Core Tables

#### users
- Authentication and user profile data
- Role-based access control
- Approval chain configuration
- Telegram chat ID for notifications

#### timesheets
- Monthly timesheet records
- Status workflow (draft → pending_* → approved/rejected)
- Signature tracking for each approval level

#### timesheet_admin_hours
- Admin hours (normal, OT) per day
- Parsed from attendance PDF

#### timesheet_project_rows & timesheet_project_hours
- Project-specific hours per day
- Four buckets: normal_nc, normal_cobq, ot_nc, ot_cobq

#### timesheet_day_metadata
- Day type classification (working, off_day, public_holiday, mc, leave, absent)
- Available hours calculation
- Time in/out tracking

#### ot_forms
- OT form records
- Executive vs non-executive form types
- Status workflow (draft → pending_* → approved/completed/rejected/returned)

#### ot_form_entries
- Individual OT entries per day
- Hour split calculations (normal_day, rest_day, ph_hours)
- HR correction tracking

#### pm_projects (merged from project_codes)
- Project management data
- Desknet sync integration
- Budget and value tracking

#### pm_project_phases
- Project phases with dates and progress

#### pm_project_tasks
- Tasks with weights, dependencies, and progress
- Predecessor task relationships

#### project_progress_logs
- Change tracking for progress updates

#### training_sessions & training_attendances
- Training session management
- Staff attendance tracking

#### audit_logs
- System activity tracking
- Model change history

#### desknet_sync_logs
- Sync operation tracking
- Error logging

---

## Authentication & Authorization

### Authentication

- Laravel's built-in session-based authentication
- Password hashing using bcrypt
- Default admin user created by migration

### User Roles

- **admin** - Full system access
- **ceo** - Can approve timesheets and OT forms
- **manager_hod** - HOD approval permissions
- **assistant_manager** - L1 approval permissions
- **staff** - Regular user
- **hr** - HR review and record viewing
- **finance** - Financial reporting access

### Authorization Methods

Located in `app/Models/User.php`:

- `isAdmin()` - Admin check
- `isHR()` - HR role check
- `canApproveTimesheetHOD()` - HOD approval
- `canApproveTimesheetL1()` - L1 approval
- `canApproveTimesheetL2()` - L2 approval
- `canApproveTimesheetL3()` - L3 approval
- `canApproveOTFormLevel1()` - Manager OT approval
- `canApproveOTFormLevel2()` - CEO/GM OT approval
- `canReviewOTForm()` - HR review access
- `canViewAllRecords()` - Historical record access

---

## Project Management Module

### Gantt Chart Features

The project management module includes a Gantt chart for visualizing project timelines with the following features:

#### Three-Lane Timeline

- **Plan Lane (Blue)**: Planned dates with shadow showing today's position
- **Revise Lane (Orange)**: Revised dates with shadow showing today's position  
- **Actual Lane (Green)**: Actual execution dates with progress percentage

#### Task Dependencies

- **End-to-Start (ES)**: Successor cannot start until predecessor completes
- **Start-to-Start (SS)**: Successor can start when predecessor starts
- Dependency lines connect tasks visually in the Gantt chart
- Implemented in `TaskDependencyResolver` service

#### Phase-Task Date Synchronization

**Phase Plan Dates Follow Subtasks**:
- Phase `start_date_plan` automatically updates to the earliest task `start_date_plan`
- Phase `end_date_plan` automatically updates to the latest task `end_date_plan`
- Triggered on: task creation, update, deletion, and Gantt drag operations
- Implemented in `ProjectTaskController::updatePhasePlanDates()`

**Subtask Date Validation**:
- Subtasks can have plan end dates beyond the current phase plan end date
- Phase will automatically extend to accommodate the latest subtask end date
- Validation skipped for subtasks in `TaskDependencyResolver::validatePlanDateConstraints()`
- This allows flexible planning while maintaining timeline integrity

**Shadow Positioning**:
- Plan bar shadows follow today's date position (not progress percentage)
- Calculated including both start and end days for accuracy
- Applies to both subtask bars and phase bars
- Text display still shows calculated progress percentage based on subtask plan progress
- Implemented in `_gantt_task_row.blade.php` and `_gantt.blade.php`

#### Dependency Cascading

- **Plan Date Cascading**: When a task's plan dates change, successor tasks adjust based on dependency type
- **Actual Date Cascading**: When a task completes, successor tasks can start based on dependency rules
- **Status Cascading**: Status changes propagate to successors based on dependency type

#### Automatic Progress Calculation

- Weighted progress calculation based on task completion
- Phase progress = weighted average of task progress
- Project progress = weighted average of phase progress
- Implemented in `ProjectProgressCalculator` service

---

## Controllers

### TimesheetController

**Location**: `app/Http/Controllers/TimesheetController.php`

**Key Methods**:
- `index()` - List user's timesheets
- `store()` - Create new timesheet
- `edit()` - Edit timesheet view
- `save()` - Save timesheet data (AJAX)
- `destroy()` - Delete draft timesheet
- `print()` - Print view
- `exportExcel()` - Export to Excel
- `exportPdf()` - Export to PDF

### OtFormController

**Location**: `app/Http/Controllers/OtFormController.php`

**Key Methods**:
- `index()` - List user's OT forms
- `store()` - Create new OT form
- `edit()` - Edit OT form view
- `save()` - Save OT form data (AJAX)
- `submitPlan()` - Submit for approval
- `autoFillFromAttendance()` - Auto-fill from attendance
- `addEntry()` - Add OT entry
- `deleteEntry()` - Delete OT entry
- `exportExcel()` - Export to Excel
- `exportPdf()` - Export to PDF

### TimesheetApprovalController

**Location**: `app/Http/Controllers/TimesheetApprovalController.php`

**Key Methods**:
- `index()` - Pending timesheets
- `approved()` - Approved timesheets
- `show()` - Review timesheet
- `submit()` - Submit for approval
- `approveHOD()` - HOD approval
- `rejectHOD()` - HOD rejection
- `approveL1()` - L1 approval
- `rejectL1()` - L1 rejection
- `unsubmit()` - Unsubmit timesheet

### OtApprovalController

**Location**: `app/Http/Controllers/OtApprovalController.php`

**Key Methods**:
- `index()` - Pending OT forms
- `approved()` - Approved OT forms
- `show()` - Review OT form
- `approve()` - Approve OT form
- `reject()` - Reject OT form
- `hrForward()` - HR forward to CEO/GM
- `hrReturn()` - HR return to staff
- `hrEdit()` - HR edit OT entries

### Admin Controllers

**ProjectController** - Project CRUD, dashboard, calendar
**ProjectPhaseController** - Phase CRUD
**ProjectTaskController** - Task CRUD, Gantt updates, dependencies
**ProjectTaskCommentController** - Task comments
**ProjectTaskAttachmentController** - Task attachments
**UserController** - User management
**SystemConfigController** - System settings
**PublicHolidayController** - Holiday management
**DesknetSyncController** - Desknet sync
**AuditController** - Audit logs
**ProjectCodeController** - Project code management

### Other Controllers

**AllRecordController** - Historical records viewing
**TrainingAttendanceController** - Training session management
**PendingTrackerController** - Pending items tracker
**HistoryController** - User history
**ProfileController** - User profile management
**DashboardController** - Dashboard data

---

## Services

### PdfParsingService

**Location**: `app/Services/PdfParsingService.php`

**Purpose**: Parse Infotech attendance PDF files

**Key Logic**:
- Extracts clock in/out times
- Identifies reason codes (ABS, PH, CAL, RES, ML, EL, AL)
- Calculates available hours based on day type
- Returns structured attendance data

### ExcelParsingService

**Location**: `app/Services/ExcelParsingService.php`

**Purpose**: Parse Infotech attendance Excel files

**Key Logic**:
- Similar to PDF parsing but for Excel format
- Extracts reason codes from row text
- Applies same day type logic as PDF parser

### TimesheetCalculationService

**Location**: `app/Services/TimesheetCalculationService.php`

**Purpose**: Calculate timesheet totals and summaries

### OtAutoFillService

**Location**: `app/Services/OtAutoFillService.php`

**Purpose**: Auto-fill OT actual times from attendance data

### ProjectProgressCalculator

**Location**: `app/Services/ProjectProgressCalculator.php`

**Purpose**: Calculate project and phase progress

**Key Logic**:
- Task plan progress = elapsed plan days / total plan days
- Phase actual progress = weighted average of task actual progress
- Project actual progress = weighted average of all task actual progress
- Recalculates on task CRUD operations

### TaskDependencyResolver

**Location**: `app/Services/TaskDependencyResolver.php`

**Purpose**: Resolve task dependencies and calculate effective dates

**Key Logic**:
- Walks dependency graph recursively
- Detects cycles
- Shifts successor dates based on predecessor completion
- Respects `is_actual_start_manual` flag

### DesknetSyncService

**Location**: `app/Services/DesknetSyncService.php`

**Purpose**: Sync staff and project data from Desknet

**Key Logic**:
- Fetches data from Desknet API
- Creates/updates local records
- Preserves local fields (user roles, project description)
- Logs sync operations

### TelegramNotificationService

**Location**: `app/Services/TelegramNotificationService.php`

**Purpose**: Send Telegram notifications

**Key Logic**:
- Sends messages via Telegram Bot API
- Formats messages using templates
- Handles errors and retries

### TelegramMessageTemplates

**Location**: `app/Services/TelegramMessageTemplates.php`

**Purpose**: Format Telegram notification messages

### Excel Export Services

**TimesheetExcelExport** - Export timesheets to Excel
**OtFormExcelExport** - Export OT forms to Excel
**TimesheetSummaryExcelExport** - Export timesheet summary
**OtSummaryExcelExport** - Export OT summary
**GanttExcelExport** - Export Gantt chart to Excel

### Email Notification Services

**TimesheetEmailNotificationService** - Timesheet email notifications
**OtEmailNotificationService** - OT form email notifications

### GanttChangeLogger

**Location**: `app/Services/GanttChangeLogger.php`

**Purpose**: Log Gantt chart changes for audit trail

---

## Models

### User

**Location**: `app/Models/User.php`

**Key Relationships**:
- `timesheets()` - User's timesheets
- `otForms()` - User's OT forms
- `createdProjects()` - Projects created by user
- `assignedTasks()` - Tasks assigned to user

**Key Methods**:
- Role checks (isAdmin, isHR, etc.)
- Approval permission checks
- Telegram chat ID management

### Timesheet

**Location**: `app/Models/Timesheet.php`

**Key Relationships**:
- `user()` - Belongs to user
- `adminHours()` - Admin hours records
- `projectRows()` - Project rows
- `dayMetadata()` - Day metadata
- `approvalLogs()` - Approval history

**Key Methods**:
- `status_label` - Human-readable status
- `canEdit()` - Edit permission check
- `canSubmit()` - Submit permission check

### OtForm

**Location**: `app/Models/OtForm.php`

**Key Relationships**:
- `user()` - Belongs to user
- `entries()` - OT entries
- `approvalLogs()` - Approval history

**Key Methods**:
- `status_label` - Human-readable status
- `calculateTotalOtHours()` - Calculate total OT hours

### Project

**Location**: `app/Models/Project.php`

**Key Relationships**:
- `phases()` - Project phases
- `tasks()` - Project tasks
- `progressLogs()` - Progress change logs

**Key Methods**:
- `calculateProgress()` - Calculate project progress
- `scheduleVariance()` - Calculate schedule variance

### ProjectTask

**Location**: `app/Models/ProjectTask.php`

**Key Relationships**:
- `project()` - Belongs to project
- `phase()` - Belongs to phase
- `predecessorTask()` - Predecessor task
- `successorTasks()` - Successor tasks
- `assignedTo()` - Assigned user
- `comments()` - Task comments
- `attachments()` - Task attachments

**Key Methods**:
- `calculateProgress()` - Calculate task progress
- `effectiveStartDate()` - Calculate effective start date
- `effectiveEndDate()` - Calculate effective end date

### Other Models

**TimesheetAdminHour** - Admin hours per day
**TimesheetProjectRow** - Project row records
**TimesheetProjectHour** - Project hours per day
**TimesheetDayMetadata** - Day metadata
**OtFormEntry** - OT form entries
**ProjectPhase** - Project phases
**ProjectProgressLog** - Progress change logs
**TrainingSession** - Training sessions
**TrainingAttendance** - Training attendance
**AuditLog** - Audit logs
**DesknetSyncLog** - Sync logs
**PublicHoliday** - Public holidays
**SystemConfig** - System configuration

---

## Routes

### Web Routes

**Location**: `routes/web.php`

**Main Route Groups**:

1. **Auth Routes** - Login, registration, password reset
2. **Profile Routes** - Profile management, Telegram test
3. **Timesheet Routes** - Timesheet CRUD, upload, export
4. **Timesheet Approval Routes** - Submit, approve, reject
5. **OT Form Routes** - OT form CRUD, submit, export
6. **OT Approval Routes** - Approve, reject, HR actions
7. **Project Management Routes** - Project, phase, task CRUD
8. **Admin Routes** - User management, settings, holidays, sync
9. **Training Attendance Routes** - Training session management
10. **All Records Routes** - Historical record viewing
11. **Pending Tracker Routes** - Pending items tracker

### Key Route Patterns

```php
// Timesheets
Route::get('/timesheets', [TimesheetController::class, 'index']);
Route::post('/timesheets', [TimesheetController::class, 'store']);
Route::get('/timesheets/{timesheet}/edit', [TimesheetController::class, 'edit']);
Route::put('/timesheets/{timesheet}', [TimesheetController::class, 'save']);

// OT Forms
Route::get('/ot-forms', [OtFormController::class, 'index']);
Route::post('/ot-forms', [OtFormController::class, 'store']);
Route::get('/ot-forms/{otForm}/edit', [OtFormController::class, 'edit']);
Route::put('/ot-forms/{otForm}', [OtFormController::class, 'save']);

// Project Management
Route::prefix('project')->group(function () {
    Route::get('/', [ProjectController::class, 'dashboard']);
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::prefix('{project}/phases')->group(/* ... */);
        Route::prefix('{project}/tasks')->group(/* ... */);
    });
});

// Admin
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/settings', [SystemConfigController::class, 'index']);
    // ...
});
```

---

## Email Notifications

### Mailable Classes

**Location**: `app/Mail/`

**Timesheet Emails**:
- `TimesheetSubmittedMail.php` - Timesheet submitted notification
- `TimesheetApprovedMail.php` - Timesheet approved notification
- `TimesheetRejectedMail.php` - Timesheet rejected notification
- `TimesheetReminderMail.php` - Timesheet submission reminder

**OT Form Emails**:
- `OtSubmittedMail.php` - OT form submitted notification
- `OtApprovedMail.php` - OT form approved notification
- `OtRejectedMail.php` - OT form rejected notification
- `OtHrReturnedMail.php` - OT form returned by HR

**Project Task Emails**:
- `SubtaskStartReminder.php` - Task start reminder
- `SubtaskDueWarning.php` - Task due warning
- `SubtaskDeadlineAlert.php` - Task deadline alert

### Email Templates

**Location**: `resources/views/emails/`

**Subject Line Format**:
All email subjects should start with "TSSB Portal - " for consistency.

Example:
```php
return $this->subject("TSSB Portal - Timesheet Approved - {$this->monthYear}")
```

---

## Telegram Notifications

### TelegramNotificationService

**Location**: `app/Services/TelegramNotificationService.php`

**Configuration**:
- `TELEGRAM_BOT_TOKEN` in `.env`
- `TELEGRAM_CHAT_ID` in `.env` or per-user in `users.telegram_chat_id`

**Usage**:
```php
$telegram = new TelegramNotificationService();
$telegram->sendMessage($chatId, $message);
```

### TelegramMessageTemplates

**Location**: `app/Services/TelegramMessageTemplates.php`

**Purpose**: Format messages for different notification types

**Message Types**:
- Timesheet approval
- OT form approval
- Task reminders
- System notifications

---

## Console Commands

### Artisan Commands

**Location**: `app/Console/Commands/`

**Sync Commands**:
- `SyncDesknet.php` - Manual Desknet sync
- `DesknetDiagnoseApp.php` - Diagnose Desknet connection

**Reminder Commands**:
- `SendTimesheetReminders.php` - Send timesheet submission reminders
- `SendTaskReminders.php` - Send task deadline reminders

**Recalculation Commands**:
- `RecalculateProjectProgress.php` - Recalculate all project progress
- `RecalculateOtFormTotalHours.php` - Recalculate OT form totals
- `RecalculateAttendanceOt.php` - Recalculate attendance OT hours

**Utility Commands**:
- `InspectTaskWeights.php` - Inspect task weight configuration
- `TestTaskReminderEmail.php` - Test task reminder email

### Scheduled Commands

**Location**: `routes/console.php`

```php
// Daily Desknet sync at 1:00 AM
Schedule::command('desknet:sync --type=all')
    ->dailyAt('01:00')
    ->withoutOverlapping();

// Daily timesheet reminders at 9:00 AM
Schedule::command('timesheet:send-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// Daily task reminders at 8:00 AM
Schedule::command('tasks:send-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping();
```

---

## Frontend Components

### React Components

**Location**: `resources/js/components/`

**Key Components**:
- `ProjectDetailsForm.jsx` - Project details form with AJAX submission
- `Sidebar.jsx` - Navigation sidebar
- Gantt chart components (gantt-task-react)

### Blade Components

**Location**: `resources/views/components/`

- Layout components
- Form components
- Table components
- Modal components

### JavaScript Libraries

- **Alpine.js** - Interactive components
- **Chart.js** - Dashboard charts
- **gantt-task-react** - Gantt chart
- **Tailwind CSS** - Styling

---

## Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter TimesheetTest

# Run with coverage
php artisan test --coverage
```

### Test Structure

```
tests/
├── Unit/
│   ├── TimesheetTest.php
│   ├── OtFormTest.php
│   └── UserTest.php
└── Feature/
    ├── TimesheetSubmissionTest.php
    ├── ApprovalWorkflowTest.php
    └── DesknetSyncTest.php
```

---

## Deployment

### Production Checklist

1. **Environment Configuration**
   - Set `APP_ENV=production`
   - Set `APP_DEBUG=false`
   - Configure production database
   - Set production `APP_URL`
   - Configure Desknet variables
   - Configure mail settings
   - Configure Telegram settings

2. **Database**
   - Run migrations: `php artisan migrate --force`
   - Seed initial data if needed

3. **Optimization**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   npm run build
   ```

4. **Permissions**
   - `storage/` and `bootstrap/cache/` must be writable
   - Upload directory must be accessible

5. **Scheduled Tasks**
   ```bash
   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```

6. **Queue Workers** (if using queues)
   ```bash
   php artisan queue:work
   ```

### Server Requirements

- PHP 8.2+
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- Composer
- Required PHP extensions: mbstring, openssl, pdo, tokenizer, xml

---

## Troubleshooting

### Common Issues

**Sidebar appears white instead of navy blue**
- Clear browser cache and hard refresh (Ctrl+Shift+R)
- Check inline styles in sidebar template

**Attendance PDF not parsing**
- Ensure PDF is in correct Infotech format
- Check file permissions in storage directory
- Verify PDF parsing library is installed

**Desknet sync failing**
- Verify API credentials in `.env`
- Check API endpoint is accessible
- Review sync logs in database

**Signature auto-complete not working**
- Ensure user's name format is correct (contains BIN/BINTI/B/BT)
- Check JavaScript console for errors

**Project edit form shows "Failed to save"**
- Check if controller returns JSON for AJAX requests
- Verify `$request->wantsJson()` check is in place
- Check browser console for actual response

### Debug Mode

Enable debug mode in `.env`:
```env
APP_DEBUG=true
```

Check Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

---

## Redundant/Legacy Code

### Deprecated Files

**project_codes table** - Merged into `pm_projects` table
- Migration: `2026_07_22_000001_phase1_merge_project_codes_into_pm_projects.php`
- Migration: `2026_07_22_000003_phase3_drop_project_codes_table.php`
- All references should use `pm_projects` table

**Old approval flow** - Simplified OT form approval
- Migration: `2026_04_14_000001_simplify_ot_form_approval_flow.php`
- Old status codes replaced with simplified flow

**Legacy email subjects** - Some old email templates may still use "Timesheet & OT Form" prefix
- Should be updated to "TSSB Portal - " prefix
- Check all Mailable classes in `app/Mail/`

### Unused Controllers

**AttendanceUploadController** - May have overlapping functionality with ExcelUploadController
- Consider consolidating attendance upload logic

**ExcelUploadController** - Handles Excel uploads for timesheets
- Verify if this is still used or if PdfParsingService handles both

### Unused Models

**MorningAssemblyLog** - Model exists but may not be actively used
- Verify if morning assembly feature is still required

**Notification** - Model exists but may not be actively used
- Verify if in-app notifications are still required or if Telegram/Email are sufficient

### Legacy Migration Files

Many migration files are one-time data fixes that are no longer needed:
- `2026_07_14_101900_fix_ot_entry_split_values.php`
- `2026_07_14_105400_fix_june_public_holiday_routing.php`
- `2026_07_14_113100_fix_executive_rest_day_split.php`
- `2026_06_30_000002_regenerate_hr_remarks_for_ot_forms.php`

These can be considered for cleanup in future maintenance.

### Duplicate Route Patterns

Some routes may have duplicate functionality:
- Project routes exist under both `/project/*` and `/admin/project/*`
- Verify if both are needed or if one can be removed

### Unused Service Methods

Some services may have unused methods:
- Check `TimesheetCalculationService` for unused calculation methods
- Check `OtAutoFillService` for redundant logic with attendance parsing

---

## Code Standards

### PHP Standards
- Follow PSR-12 coding standards
- Use type hints where possible
- Document complex logic with comments

### Blade Templates
- Use component-based architecture
- Keep logic in controllers, not views
- Use Alpine.js for interactive components

### CSS
- Use Tailwind CSS classes
- Avoid custom CSS when possible
- Use inline styles only when forcing colors

### JavaScript
- Use React for complex components
- Use Alpine.js for simple interactions
- Follow ESLint rules

---

## Security Considerations

1. **SQL Injection** - Use Laravel's query builder/Eloquent ORM
2. **XSS** - Blade automatically escapes output
3. **CSRF** - Laravel CSRF protection enabled on all forms
4. **Authentication** - Use Laravel's built-in auth system
5. **Authorization** - Use role checks and permission helpers
6. **File Uploads** - Validate file types and sizes
7. **API Keys** - Store in environment variables, never commit to git
8. **Sensitive Data** - Never log passwords, API keys, or personal data

---

## Version History

- **v1.0** - Initial release with timesheet and OT form management
- **v1.1** - Added Desknet integration
- **v1.2** - Added multi-level approval system
- **v1.3** - Added Excel export functionality
- **v1.4** - UI redesign with navy blue theme
- **v1.5** - Added attendance parsing logic, audit logging
- **v1.6** - Merged project_codes into pm_projects, added project management module
- **v1.7** - Added Telegram notifications, training attendance
- **v2.0** - Fixed project edit form JSON response, updated documentation
- **v2.1** - Phase plan dates automatically follow subtask dates, Gantt shadow follows today's date

---

## Support

For technical issues or questions:
- Developer: munizahzahid@gmail.com

---

## License

Copyright © 2026 Talent Synergy Sdn Bhd. All rights reserved.
