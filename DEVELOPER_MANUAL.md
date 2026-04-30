# TSSB Portal - Developer Manual

## Table of Contents

1. [System Overview](#system-overview)
2. [Tech Stack](#tech-stack)
3. [Project Structure](#project-structure)
4. [Installation](#installation)
5. [Configuration](#configuration)
6. [Database Schema](#database-schema)
7. [Authentication & Authorization](#authentication--authorization)
8. [Key Features](#key-features)
9. [API Endpoints](#api-endpoints)
10. [Excel Export Services](#excel-export-services)
11. [Desknet Integration](#desknet-integration)
12. [Testing](#testing)
13. [Deployment](#deployment)
14. [Troubleshooting](#troubleshooting)

---

## System Overview

TSSB Portal is a Laravel-based web application for managing employee timesheets and overtime (OT) forms. The system integrates with Desknet for staff and project data synchronization.

### Key Features
- Timesheet management with attendance PDF upload
- OT form submission and approval workflow
- Multi-level approval system (HOD, L1, L2, L3)
- Excel export functionality
- Desknet API integration
- Role-based access control

---

## Tech Stack

- **Backend Framework**: Laravel 10.x
- **Frontend**: Blade Templates, Tailwind CSS, Alpine.js
- **Database**: MySQL/MariaDB
- **PHP Version**: 8.1+
- **Excel Library**: PhpSpreadsheet
- **PDF Processing**: Custom PDF parsing for attendance data

---

## Project Structure

```
Timesheet_Website/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── TimesheetController.php
│   │   │   ├── OtFormController.php
│   │   │   ├── ApprovalController.php
│   │   │   └── Admin/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Timesheet.php
│   │   ├── OtForm.php
│   │   ├── ProjectCode.php
│   │   └── DesknetSyncLog.php
│   ├── Services/
│   │   ├── TimesheetExcelExport.php
│   │   └── OtFormExcelExport.php
│   └── Policies/
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   ├── timesheets/
│   │   ├── ot-forms/
│   │   ├── approvals/
│   │   ├── admin/
│   │   └── components/
│   └── css/
├── routes/
│   ├── web.php
│   └── api.php
└── public/
    └── images/
```

---

## Installation

### Prerequisites
- PHP 8.1 or higher
- Composer
- MySQL/MariaDB
- Node.js & NPM (for asset compilation, if needed)

### Steps

1. **Clone the repository**
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

4. **Configure database**
   Edit `.env` file:
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

6. **Seed data (optional)**
   ```bash
   php artisan db:seed
   ```

7. **Link storage**
   ```bash
   php artisan storage:link
   ```

8. **Start development server**
   ```bash
   php artisan serve
   ```

---

## Configuration

### Environment Variables

Key environment variables in `.env`:

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
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@talentsynergy.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Config Files

- `config/app.php` - Application configuration
- `config/auth.php` - Authentication settings
- `config/filesystems.php` - Storage configuration

---

## Database Schema

### Users Table
```sql
- id
- name (string)
- email (string, unique)
- password (hashed)
- role (enum: admin, user)
- designation (string)
- department_id (foreign key)
- employee_code (string)
- can_approve_ot_form_level_1 (boolean)
- can_approve_timesheet_hod (boolean)
- can_approve_timesheet_l1 (boolean)
- can_approve_timesheet_l2 (boolean)
- can_approve_timesheet_l3 (boolean)
- reports_to_id (foreign key to users)
- created_at, updated_at
```

### Timesheets Table
```sql
- id
- user_id (foreign key)
- month (integer)
- year (integer
- status (enum: draft, pending_hod, pending_l1, pending_l2, pending_l3, approved, rejected)
- admin_hours (json)
- project_rows (json)
- attendance_pdf_path (string)
- hod_signature (string)
- l1_signature (string)
- l2_signature (string)
- l3_signature (string)
- hod_rejected_at (timestamp)
- l1_rejected_at (timestamp)
- l2_rejected_at (timestamp)
- l3_rejected_at (timestamp)
- rejected_remarks (text)
- created_at, updated_at
```

### OT Forms Table
```sql
- id
- user_id (foreign key)
- month (integer)
- year (integer)
- form_type (enum: executive, non_executive)
- company (string)
- status (enum: draft, pending, approved, rejected)
- planned_data (json)
- actual_data (json)
- executive_plan (json, for executive type)
- non_executive_plan (json, for non-executive type)
- manager_signature (string)
- manager_rejected_at (timestamp)
- rejected_remarks (text)
- created_at, updated_at
```

### Project Codes Table
```sql
- id
- code (string, unique)
- name (string)
- client (string)
- manager (string)
- year (integer)
- status (enum: active, inactive)
- desknet_id (string)
- created_at, updated_at
```

---

## Authentication & Authorization

### Authentication
- Laravel's built-in authentication system
- Session-based authentication
- Password hashing using bcrypt

### Authorization
- Role-based access control using Policies
- User roles: `admin`, `user`
- Approval permissions:
  - `canApproveOTFormLevel1()`
  - `canApproveTimesheetHOD()`
  - `canApproveTimesheetL1()`
  - `canApproveTimesheetL2()`
  - `canApproveTimesheetL3()`

### Middleware
- `auth` - Require authentication
- `admin` - Require admin role
- Timesheet submission restrictions based on status

---

## Key Features

### Timesheet Management

**Creating a Timesheet**
```php
// Route: POST /timesheets
public function store(Request $request)
{
    $validated = $request->validate([
        'month' => 'required|integer|min:1|max:12',
        'year' => 'required|integer|min:2020|max:2100',
    ]);
    
    // Check if timesheet already exists
    $existing = Timesheet::where('user_id', auth()->id())
        ->where('month', $validated['month'])
        ->where('year', $validated['year'])
        ->first();
    
    if ($existing) {
        return back()->with('error', 'Timesheet already exists');
    }
    
    $timesheet = Timesheet::create([
        'user_id' => auth()->id(),
        'month' => $validated['month'],
        'year' => $validated['year'],
        'status' => 'draft',
    ]);
    
    return redirect()->route('timesheets.edit', $timesheet);
}
```

**Auto-Fill from Attendance PDF**
```php
// Parses uploaded PDF to extract clock in/out times
// Stores in admin_hours JSON structure
```

### OT Form Management

**Executive Plan**
- Simplified structure for executive staff
- Planned vs Actual hours tracking
- Auto-fill from attendance data

**Non-Executive Plan**
- Additional fields for non-executive staff
- More detailed breakdown of OT hours

### Approval Workflow

**Timesheet Approval Levels**
1. HOD (Head of Department)
2. L1 (Assistant Manager)
3. L2 (Manager)
4. L3 (Senior Manager/GM/CEO)

**OT Form Approval**
- Single-level approval by manager
- Can reject with remarks

---

## API Endpoints

### Timesheets

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/timesheets` | List user's timesheets |
| POST | `/timesheets` | Create new timesheet |
| GET | `/timesheets/{id}` | View timesheet details |
| PUT | `/timesheets/{id}` | Update timesheet |
| POST | `/timesheets/{id}/submit` | Submit for approval |
| POST | `/timesheets/{id}/approve-hod` | HOD approval |
| POST | `/timesheets/{id}/approve-l1` | L1 approval |
| POST | `/timesheets/{id}/approve-l2` | L2 approval |
| POST | `/timesheets/{id}/approve-l3` | L3 approval |
| POST | `/timesheets/{id}/reject-hod` | HOD rejection |
| DELETE | `/timesheets/{id}` | Delete draft timesheet |

### OT Forms

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/ot-forms` | List user's OT forms |
| POST | `/ot-forms` | Create new OT form |
| GET | `/ot-forms/{id}` | View OT form details |
| PUT | `/ot-forms/{id}` | Update OT form |
| POST | `/ot-forms/{id}/submit` | Submit for approval |
| POST | `/ot-forms/{id}/approve` | Manager approval |
| POST | `/ot-forms/{id}/reject` | Manager rejection |
| DELETE | `/ot-forms/{id}` | Delete draft OT form |

### Approvals

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/approvals/timesheets` | List pending timesheet approvals |
| GET | `/approvals/timesheets/{id}` | View timesheet for approval |
| GET | `/approvals/ot-forms` | List pending OT form approvals |
| GET | `/approvals/ot-forms/{id}` | View OT form for approval |

### Admin

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/users` | List all users |
| GET | `/admin/settings` | System settings |
| POST | `/admin/settings` | Update system settings |
| GET | `/admin/project-codes` | List project codes |
| POST | `/admin/desknet-sync/staff` | Sync staff from Desknet |
| POST | `/admin/desknet-sync/projects` | Sync projects from Desknet |
| POST | `/admin/desknet-sync/all` | Sync all data from Desknet |

---

## Excel Export Services

### TimesheetExcelExport

Located at `app/Services/TimesheetExcelExport.php`

**Features:**
- Generates Excel files for timesheet submission
- Includes staff information, project hours, and admin hours
- Supports both executive and non-executive plans
- Auto-calculates totals and summaries

**Usage:**
```php
$export = new TimesheetExcelExport($timesheet);
$export->generate();
return $export->download();
```

### OtFormExcelExport

Located at `app/Services/OtFormExcelExport.php`

**Features:**
- Generates Excel files for OT form submission
- Separate methods for executive and non-executive plans
- Includes planned vs actual hours comparison
- Auto-calculates OT totals

**Executive Plan:**
```php
public function buildExecutive($sheet, $form)
{
    // Executive-specific Excel structure
    // Includes planned/actual comparison
}
```

**Non-Executive Plan:**
```php
public function buildNonExecutive($sheet, $form)
{
    // Non-executive Excel structure
    // Additional fields for detailed breakdown
}
```

---

## Desknet Integration

### API Configuration

Desknet API credentials configured in `.env`:
```env
DESKNET_API_KEY=your_api_key
DESKNET_API_BASE_URL=https://desknet.example.com/api
DESKNET_SYNC_ENABLED=true
```

### Sync Services

**Staff Sync**
```php
// Endpoint: /admin/desknet-sync/staff
// Fetches staff data from Desknet
// Updates/creates users in local database
```

**Project Codes Sync**
```php
// Endpoint: /admin/desknet-sync/projects
// Fetches project codes from Desknet
// Updates/creates project codes in local database
```

### Sync Logs

All sync operations are logged in `desknet_sync_logs` table:
- `status` (success/failure)
- `type` (staff/projects)
- `records_processed`
- `error_message`
- `completed_at`

---

## Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
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

### Production Deployment Checklist

1. **Environment Configuration**
   - Set `APP_ENV=production`
   - Set `APP_DEBUG=false`
   - Configure production database
   - Set production URLs

2. **Database**
   - Run migrations: `php artisan migrate --force`
   - Seed initial data if needed

3. **Optimization**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. **Permissions**
   - Storage directory must be writable
   - Bootstrap cache directory must be writable

5. **Queue Workers** (if using queues)
   ```bash
   php artisan queue:work
   ```

### Server Requirements

- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- Composer
- Required PHP extensions: mbstring, openssl, pdo, tokenizer, xml

---

## Troubleshooting

### Common Issues

**Issue: Sidebar appears white instead of navy blue**
- Solution: Clear browser cache and hard refresh (Ctrl+Shift+R)
- The sidebar uses inline styles to force navy blue color

**Issue: Help button appears white**
- Solution: The help button uses inline style `background-color: #1e3a8a`
- If still white, check browser console for CSS conflicts

**Issue: Attendance PDF not parsing**
- Solution: Ensure PDF is in the correct format from Infotech
- Check file permissions in storage directory
- Verify PDF parsing library is installed

**Issue: Desknet sync failing**
- Solution: Verify API credentials in `.env`
- Check API endpoint is accessible
- Review sync logs in database for error messages

**Issue: Signature auto-complete not working**
- Solution: Ensure user's name format is correct (contains BIN/BINTI/B/BT)
- Check JavaScript console for errors
- Verify Auth::user()->name is returning correct value

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

---

## Security Considerations

1. **SQL Injection** - Use Laravel's query builder/Eloquent ORM
2. **XSS** - Blade automatically escapes output
3. **CSRF** - Laravel CSRF protection enabled on all forms
4. **Authentication** - Use Laravel's built-in auth system
5. **Authorization** - Use policies for permission checks
6. **File Uploads** - Validate file types and sizes
7. **API Keys** - Store in environment variables, never commit to git

---

## Version History

- **v1.0** - Initial release with timesheet and OT form management
- **v1.1** - Added Desknet integration
- **v1.2** - Added multi-level approval system
- **v1.3** - Added Excel export functionality
- **v1.4** - UI redesign with navy blue theme and Malay language support

---

## Support

For technical issues or questions:
- Developer: [Developer Email]
- Documentation: [Documentation Repository]
- Issue Tracker: [GitHub Issues]

---

## License

Copyright © 2026 Talent Synergy Sdn Bhd. All rights reserved.
