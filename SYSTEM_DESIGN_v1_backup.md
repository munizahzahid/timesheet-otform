# Timesheet & Overtime (OT) Management System — Full System Design

> **Version:** 1.0  
> **Date:** 2026-03-30  
> **Status:** Draft — Awaiting form design input

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [User Roles & Permissions](#2-user-roles--permissions)
3. [Full System Workflow](#3-full-system-workflow)
4. [Database Schema](#4-database-schema)
5. [API Design](#5-api-design)
6. [Frontend Page Structure](#6-frontend-page-structure)
7. [Excel Parsing Logic](#7-excel-parsing-logic)
8. [Business Rules Engine](#8-business-rules-engine)
9. [Approval Workflow Logic](#9-approval-workflow-logic)
10. [Integration Architecture](#10-integration-architecture)
11. [Tech Stack & Infrastructure](#11-tech-stack--infrastructure)
12. [Security Considerations](#12-security-considerations)
13. [Future Considerations](#13-future-considerations)

---

## 1. System Overview

### 1.1 Purpose

A web-based internal application that allows staff to submit monthly timesheets and overtime (OT) forms, with automated attendance data population from Infotech Excel exports, business-rule-driven calculations, and a multi-level approval workflow.

### 1.2 High-Level Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                          FRONTEND (Browser)                         │
│   React / HTML+JS  ·  Form UI  ·  Dashboards  ·  Excel Upload      │
└──────────────────────────────┬──────────────────────────────────────┘
                               │ HTTP / REST API
┌──────────────────────────────▼──────────────────────────────────────┐
│                        BACKEND (Server)                             │
│   PHP (Laravel) or Node.js (Express)                                │
│   ┌────────────┐ ┌──────────────┐ ┌────────────┐ ┌──────────────┐  │
│   │ Auth Module │ │ Timesheet    │ │ OT Module  │ │ Admin Module │  │
│   │ (JWT/Sess) │ │ Module       │ │            │ │              │  │
│   └────────────┘ └──────────────┘ └────────────┘ └──────────────┘  │
│   ┌────────────┐ ┌──────────────┐ ┌────────────┐                   │
│   │ Excel      │ │ Approval     │ │ Integration│                   │
│   │ Parser     │ │ Engine       │ │ Service    │                   │
│   └────────────┘ └──────────────┘ └────────────┘                   │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
          ┌────────────────────┼─────────────────────┐
          ▼                    ▼                     ▼
   ┌─────────────┐   ┌────────────────┐   ┌──────────────────┐
   │   MySQL DB   │   │ File Storage   │   │ Google Apps      │
   │              │   │ (Excel files)  │   │ Script API       │
   └─────────────┘   └────────────────┘   └──────────────────┘
```

### 1.3 Key Design Principles

- **Minimize manual input** — autofill from Excel, dropdowns, and integrations
- **Enforce business rules** — late (30-min blocks), OT (full hours, after 6:30 PM)
- **User-friendly** — simple form-based UI for non-technical staff
- **Auditability** — every approval action is timestamped and logged

---

## 2. User Roles & Permissions

### 2.1 Role Definitions

| Role | Description | Access Level |
|------|-------------|--------------|
| **Staff** | Regular employee | Submit timesheets & OT forms, view own records |
| **Admin** | System administrator | Manage project codes, users, roles, configurations, view all reports |
| **Assistant Manager** | First-level approver (Timesheet) | Approve/reject timesheets from direct reports |
| **Manager / HOD** | Second-level approver (Timesheet) + First-level OT approver | Approve/reject timesheets & OT forms |
| **CEO** | Final OT approver | Approve/reject OT forms |

### 2.2 Permission Matrix

| Action | Staff | Admin | Asst. Manager | Manager/HOD | CEO |
|--------|:-----:|:-----:|:--------------:|:-----------:|:---:|
| Submit Timesheet | ✅ | ❌ | ✅ | ✅ | ❌ |
| Submit OT Form | ✅ | ❌ | ✅ | ✅ | ❌ |
| Upload Excel | ✅ | ✅ | ✅ | ✅ | ❌ |
| Approve Timesheet (L1) | ❌ | ❌ | ✅ | ❌ | ❌ |
| Approve Timesheet (L2) | ❌ | ❌ | ❌ | ✅ | ❌ |
| Approve OT (L1) | ❌ | ❌ | ❌ | ✅ | ❌ |
| Approve OT (L2) | ❌ | ❌ | ❌ | ❌ | ✅ |
| Manage Project Codes | ❌ | ✅ | ❌ | ❌ | ❌ |
| Manage Users & Roles | ❌ | ✅ | ❌ | ❌ | ❌ |
| Configure Working Rules | ❌ | ✅ | ❌ | ❌ | ❌ |
| View All Reports | ❌ | ✅ | ❌ | ✅ | ✅ |
| View Own Records | ✅ | ✅ | ✅ | ✅ | ✅ |

### 2.3 Reporting Hierarchy

```
Staff
 └─► Assistant Manager  (Timesheet L1 Approval)
      └─► Manager / HOD (Timesheet L2 Approval, OT L1 Approval)
           └─► CEO      (OT L2 Approval)
```

Each staff member is assigned to a **department** and linked to their reporting chain via the `users` table (`reports_to` field).

---

## 3. Full System Workflow

### 3.1 Monthly Timesheet Submission — Step by Step

```
┌───────────────────────────────────────────────────────────────────┐
│                    TIMESHEET SUBMISSION FLOW                      │
└───────────────────────────────────────────────────────────────────┘

Step 1: Staff logs in
         │
Step 2: Staff navigates to "Timesheet" → selects Month/Year
         │
Step 3: Staff uploads Infotech Excel file
         │
Step 4: System parses Excel file
         ├── Extracts: Date, Time In, Time Out
         ├── Validates data completeness
         └── Reports parsing errors (if any)
         │
Step 5: System applies Business Rules (autofill)
         ├── Detect missing punches → Mark as MC/Leave
         ├── Detect weekends → Mark as Off Day
         ├── Calculate Late Hours (30-min blocks from 8:30 AM)
         ├── Calculate OT Hours (full hours after 6:30 PM)
         ├── Autofill Attendance Hours
         └── Autofill Morning Assembly (from Google Form integration)
         │
Step 6: System displays pre-filled timesheet form
         │
Step 7: Staff reviews & adjusts (if needed)
         ├── Select Project Code (dropdown) for each day
         ├── Enter/adjust 5S hours
         ├── Enter Ceramah Agama / Event / ADP hours
         ├── Enter ISO hours
         ├── Enter Training / Seminar / Visit hours
         ├── Add OT entries (linked to Project Code → autofill Project Name)
         └── Make manual corrections if autofill is incorrect
         │
Step 8: Staff clicks "Submit"
         │
Step 9: System validates all required fields
         ├── Ensures Project Code selected for OT entries
         ├── Validates hour totals
         └── Checks business rule compliance
         │
Step 10: Timesheet status → "Pending L1 Approval"
         │
Step 11: Notification sent to Assistant Manager
         │
Step 12: Assistant Manager reviews & approves/rejects
         ├── If Approved → Status = "Pending L2 Approval"
         │    └── Notification sent to Manager/HOD
         └── If Rejected → Status = "Rejected (L1)"
              └── Notification sent to Staff (with rejection reason)
              └── Staff can edit & resubmit (go to Step 7)
         │
Step 13: Manager/HOD reviews & approves/rejects
         ├── If Approved → Status = "Approved"
         └── If Rejected → Status = "Rejected (L2)"
              └── Notification sent to Staff (with rejection reason)
              └── Staff can edit & resubmit (go to Step 7)
```

### 3.2 OT Form Submission — Step by Step

```
┌───────────────────────────────────────────────────────────────────┐
│                     OT FORM SUBMISSION FLOW                       │
└───────────────────────────────────────────────────────────────────┘

Step 1: Staff logs in
         │
Step 2: Staff navigates to "OT Form" → selects Month (e.g., DEC 2025)
         │
Step 3: Staff fills OT form
         ├── Date
         ├── Project Name (mandatory, dropdown)
         ├── Planned Start Time
         ├── Planned End Time
         ├── Actual Start Time
         ├── Actual End Time
         ├── Tick Company Name / Logo
         └── Total OT Hours → auto-calculated
              └── Rule: Only count after 6:30 PM, full hours only
         │
Step 4: Staff clicks "Submit"
         │
Step 5: System validates
         ├── Project Name is selected
         ├── Times are valid
         ├── OT calculation is correct
         └── At least 1 full hour of OT after 6:30 PM
         │
Step 6: OT Form status → "Pending L1 Approval"
         │
Step 7: Notification sent to Manager/HOD
         │
Step 8: Manager/HOD reviews & approves/rejects
         ├── If Approved → Status = "Pending L2 Approval"
         │    └── Notification sent to CEO
         └── If Rejected → Status = "Rejected (L1)"
              └── Notification sent to Staff
              └── Staff can edit & resubmit
         │
Step 9: CEO reviews & approves/rejects
         ├── If Approved → Status = "Approved"
         └── If Rejected → Status = "Rejected (L2)"
              └── Notification sent to Staff
              └── Staff can edit & resubmit
```

### 3.3 Admin Workflow

```
┌───────────────────────────────────────────────────────────────────┐
│                       ADMIN MANAGEMENT FLOW                       │
└───────────────────────────────────────────────────────────────────┘

A. Project Code Management
   1. Admin logs in → navigates to "Project Codes"
   2. Admin can:
      ├── Create new Project Code (code, name, company, status)
      ├── Edit existing Project Code
      ├── Deactivate / Delete Project Code
      └── View all Project Codes with filters

B. User & Role Management
   1. Admin navigates to "Users"
   2. Admin can:
      ├── Create new user (name, email, department, role)
      ├── Assign reporting hierarchy (reports_to)
      ├── Edit user details & role
      └── Deactivate user accounts

C. System Configuration
   1. Admin navigates to "Settings"
   2. Admin can configure:
      ├── Working Start Time (default: 8:30 AM)
      ├── OT Start Time (default: 6:30 PM)
      ├── Late Rounding Block (default: 30 minutes)
      ├── OT Rounding Block (default: 1 hour)
      └── Public holidays calendar

D. Reports
   1. Admin navigates to "Reports"
   2. Available reports:
      ├── Monthly Timesheet Summary (by department)
      ├── OT Summary Report (by department / project)
      ├── Late Report
      ├── Leave / MC Report
      └── Export to Excel / PDF
```

### 3.4 Excel Upload & Autofill Workflow (Detailed)

```
┌───────────────────────────────────────────────────────────────────┐
│                    EXCEL PROCESSING PIPELINE                      │
└───────────────────────────────────────────────────────────────────┘

  [User uploads .xlsx/.xls file]
         │
         ▼
  ┌─────────────────┐
  │  1. VALIDATION   │
  │  - File type ok? │
  │  - File size ok? │
  │  - Has headers?  │
  └────────┬────────┘
           │ Pass
           ▼
  ┌─────────────────────┐
  │  2. PARSE HEADERS    │
  │  - Find "Date" col   │
  │  - Find "Time In"    │
  │  - Find "Time Out"   │
  └────────┬─────────────┘
           │
           ▼
  ┌─────────────────────────┐
  │  3. EXTRACT ROWS         │
  │  For each row:           │
  │  - Parse date            │
  │  - Parse time in (HH:MM) │
  │  - Parse time out (HH:MM)│
  │  - Flag missing values   │
  └────────┬─────────────────┘
           │
           ▼
  ┌─────────────────────────────────────┐
  │  4. APPLY BUSINESS RULES (per day)   │
  │                                      │
  │  IF (no time_in AND no time_out):    │
  │     → status = "MC/Leave"            │
  │     → mc_leave_hours = 8             │
  │                                      │
  │  IF (day is Saturday or Sunday):     │
  │     → status = "Off Day"             │
  │     → (still allow work entry)       │
  │                                      │
  │  IF (time_in > 08:30):              │
  │     → late_minutes = time_in - 08:30 │
  │     → late_hours = ceil_to_half_hour │
  │     Example: 8:42 AM → 12 min late   │
  │              → rounds to 0.5 hr      │
  │     Example: 9:05 AM → 35 min late   │
  │              → rounds to 1.0 hr      │
  │                                      │
  │  IF (time_out > 18:30):             │
  │     → ot_minutes = time_out - 18:30  │
  │     → ot_hours = floor(ot_minutes/60)│
  │     Example: 19:45 → 75 min          │
  │              → OT = 1 hr             │
  │     Example: 20:35 → 125 min         │
  │              → OT = 2 hrs            │
  │                                      │
  │  attendance_hours = time_out - time_in│
  │     (minus lunch break if applicable) │
  └────────┬─────────────────────────────┘
           │
           ▼
  ┌─────────────────────────┐
  │  5. POPULATE TIMESHEET   │
  │  - Fill all 30/31 rows   │
  │  - Autofill calculated   │
  │    values into form      │
  │  - Highlight anomalies   │
  └──────────────────────────┘
```

---

## 4. Database Schema

### 4.1 Entity Relationship Diagram

```
┌──────────────┐       ┌──────────────────┐       ┌──────────────────┐
│    users     │       │   departments    │       │  project_codes   │
├──────────────┤       ├──────────────────┤       ├──────────────────┤
│ id (PK)      │──┐    │ id (PK)          │       │ id (PK)          │
│ name         │  │    │ name             │       │ code (UNIQUE)    │
│ email        │  │    │ created_at       │       │ name             │
│ password     │  ├───▶│                  │       │ company          │
│ role         │  │    └──────────────────┘       │ is_active        │
│ department_id│──┘                                │ created_at       │
│ reports_to   │──┐                                └──────────────────┘
│ is_active    │  │                                        │
│ created_at   │  │    ┌──────────────────┐                │
└──────────────┘  └───▶│    users         │                │
       │               └──────────────────┘                │
       │                                                   │
       ▼                                                   │
┌──────────────────┐     ┌───────────────────────┐         │
│   timesheets     │     │  timesheet_entries     │         │
├──────────────────┤     ├───────────────────────┤         │
│ id (PK)          │◄────│ timesheet_id (FK)     │         │
│ user_id (FK)     │     │ id (PK)               │         │
│ month            │     │ date                  │         │
│ year             │     │ day_type              │         │
│ status           │     │ time_in               │         │
│ current_level    │     │ time_out              │         │
│ submitted_at     │     │ late_hours            │         │
│ created_at       │     │ ot_hours              │         │
│ updated_at       │     │ mc_leave_hours        │         │
└──────────────────┘     │ attendance_hours      │         │
       │                 │ morning_assy_hours    │         │
       │                 │ admin_job_hours       │         │
       │                 │ five_s_hours          │         │
       │                 │ ceramah_event_hours   │         │
       │                 │ iso_hours             │         │
       │                 │ training_hours        │         │
       │                 │ project_code_id (FK)──│─────────┘
       │                 │ remarks               │
       │                 └───────────────────────┘
       │
       │              ┌───────────────────────┐
       │              │  timesheet_ot_entries  │
       │              ├───────────────────────┤
       └─────────────▶│ id (PK)               │
                      │ timesheet_id (FK)     │
                      │ date                  │
                      │ project_code_id (FK)──│──► project_codes
                      │ project_name          │  (autofilled)
                      │ ot_hours              │
                      │ remarks               │
                      └───────────────────────┘

┌──────────────────────┐
│      ot_forms        │
├──────────────────────┤      ┌───────────────────────┐
│ id (PK)              │◄─────│   ot_form_entries     │
│ user_id (FK)         │      ├───────────────────────┤
│ month                │      │ id (PK)               │
│ year                 │      │ ot_form_id (FK)       │
│ status               │      │ date                  │
│ current_level        │      │ project_code_id (FK)  │
│ company_id (FK)      │      │ project_name          │
│ submitted_at         │      │ planned_start_time    │
│ created_at           │      │ planned_end_time      │
│ updated_at           │      │ actual_start_time     │
└──────────────────────┘      │ actual_end_time       │
                              │ total_ot_hours        │
                              │ remarks               │
                              └───────────────────────┘

┌───────────────────────┐
│    approval_logs      │
├───────────────────────┤
│ id (PK)               │
│ entity_type           │  ("timesheet" or "ot_form")
│ entity_id             │  (FK to timesheets.id or ot_forms.id)
│ approver_id (FK)      │──► users
│ level                 │  (1 = L1, 2 = L2)
│ action                │  ("approved" / "rejected")
│ remarks               │
│ acted_at              │
└───────────────────────┘

┌───────────────────────┐      ┌───────────────────────┐
│     companies         │      │  system_config        │
├───────────────────────┤      ├───────────────────────┤
│ id (PK)               │      │ id (PK)               │
│ name                  │      │ config_key            │
│ logo_path             │      │ config_value          │
│ is_active             │      │ description           │
│ created_at            │      │ updated_at            │
└───────────────────────┘      └───────────────────────┘

┌───────────────────────┐
│   public_holidays     │
├───────────────────────┤
│ id (PK)               │
│ date                  │
│ name                  │
│ year                  │
│ created_at            │
└───────────────────────┘

┌───────────────────────┐
│   excel_uploads       │
├───────────────────────┤
│ id (PK)               │
│ user_id (FK)          │
│ file_name             │
│ file_path             │
│ month                 │
│ year                  │
│ status                │  ("processing" / "completed" / "error")
│ error_message         │
│ uploaded_at           │
└───────────────────────┘

┌────────────────────────┐
│  morning_assembly_log  │
├────────────────────────┤
│ id (PK)                │
│ user_id (FK)           │
│ date                   │
│ attended (BOOLEAN)     │
│ source                 │  ("google_form" / "manual")
│ synced_at              │
└────────────────────────┘

┌───────────────────────┐
│    notifications      │
├───────────────────────┤
│ id (PK)               │
│ user_id (FK)          │
│ type                  │
│ title                 │
│ message               │
│ link                  │
│ is_read (BOOLEAN)     │
│ created_at            │
└───────────────────────┘
```

### 4.2 Table Definitions (SQL)

```sql
-- ============================================================
-- USERS & ORGANIZATION
-- ============================================================

CREATE TABLE departments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    email           VARCHAR(150) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('staff','admin','assistant_manager','manager_hod','ceo') NOT NULL DEFAULT 'staff',
    department_id   INT,
    reports_to      INT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (reports_to)    REFERENCES users(id)
);

CREATE TABLE companies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    logo_path   VARCHAR(255),
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- PROJECT CODES
-- ============================================================

CREATE TABLE project_codes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50) UNIQUE NOT NULL,
    name        VARCHAR(200) NOT NULL,
    company_id  INT,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

-- ============================================================
-- TIMESHEETS
-- ============================================================

CREATE TABLE timesheets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    month           TINYINT NOT NULL,           -- 1-12
    year            SMALLINT NOT NULL,
    status          ENUM('draft','pending_l1','pending_l2','approved','rejected_l1','rejected_l2') DEFAULT 'draft',
    current_level   TINYINT DEFAULT 0,          -- 0=draft, 1=L1, 2=L2
    submitted_at    TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_month (user_id, month, year),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE timesheet_entries (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id        INT NOT NULL,
    entry_date          DATE NOT NULL,
    day_type            ENUM('working','off_day','public_holiday','mc','leave') DEFAULT 'working',
    time_in             TIME NULL,
    time_out            TIME NULL,
    late_hours          DECIMAL(4,1) DEFAULT 0.0,    -- 30-min blocks (0.5 increments)
    ot_hours            INT DEFAULT 0,                -- whole hours only
    mc_leave_hours      DECIMAL(4,1) DEFAULT 0.0,
    attendance_hours    DECIMAL(4,1) DEFAULT 0.0,
    morning_assy_hours  DECIMAL(4,1) DEFAULT 0.0,
    admin_job_hours     DECIMAL(4,1) DEFAULT 0.0,
    five_s_hours        DECIMAL(4,1) DEFAULT 0.0,
    ceramah_event_hours DECIMAL(4,1) DEFAULT 0.0,
    iso_hours           DECIMAL(4,1) DEFAULT 0.0,
    training_hours      DECIMAL(4,1) DEFAULT 0.0,
    project_code_id     INT NULL,
    remarks             TEXT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_entry (timesheet_id, entry_date),
    FOREIGN KEY (timesheet_id)   REFERENCES timesheets(id) ON DELETE CASCADE,
    FOREIGN KEY (project_code_id) REFERENCES project_codes(id)
);

CREATE TABLE timesheet_ot_entries (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id        INT NOT NULL,
    entry_date          DATE NOT NULL,
    project_code_id     INT NOT NULL,
    project_name        VARCHAR(200),            -- autofilled from project_codes
    ot_hours            INT NOT NULL DEFAULT 0,  -- whole hours only
    remarks             TEXT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (timesheet_id)    REFERENCES timesheets(id) ON DELETE CASCADE,
    FOREIGN KEY (project_code_id) REFERENCES project_codes(id)
);

-- ============================================================
-- OT FORMS
-- ============================================================

CREATE TABLE ot_forms (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    month           TINYINT NOT NULL,
    year            SMALLINT NOT NULL,
    company_id      INT NULL,
    status          ENUM('draft','pending_l1','pending_l2','approved','rejected_l1','rejected_l2') DEFAULT 'draft',
    current_level   TINYINT DEFAULT 0,
    submitted_at    TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id),
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE ot_form_entries (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    ot_form_id          INT NOT NULL,
    entry_date          DATE NOT NULL,
    project_code_id     INT NOT NULL,
    project_name        VARCHAR(200) NOT NULL,
    planned_start_time  TIME NOT NULL,
    planned_end_time    TIME NOT NULL,
    actual_start_time   TIME NOT NULL,
    actual_end_time     TIME NOT NULL,
    total_ot_hours      INT NOT NULL DEFAULT 0,  -- whole hours only
    remarks             TEXT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ot_form_id)      REFERENCES ot_forms(id) ON DELETE CASCADE,
    FOREIGN KEY (project_code_id) REFERENCES project_codes(id)
);

-- ============================================================
-- APPROVAL SYSTEM
-- ============================================================

CREATE TABLE approval_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    entity_type     ENUM('timesheet','ot_form') NOT NULL,
    entity_id       INT NOT NULL,
    approver_id     INT NOT NULL,
    level           TINYINT NOT NULL,              -- 1 or 2
    action          ENUM('approved','rejected') NOT NULL,
    remarks         TEXT NULL,
    acted_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (approver_id) REFERENCES users(id)
);

-- ============================================================
-- SYSTEM CONFIGURATION
-- ============================================================

CREATE TABLE system_config (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    config_key      VARCHAR(100) UNIQUE NOT NULL,
    config_value    VARCHAR(255) NOT NULL,
    description     VARCHAR(255),
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default configuration values
INSERT INTO system_config (config_key, config_value, description) VALUES
('working_start_time', '08:30', 'Official work start time (HH:MM)'),
('ot_start_time', '18:30', 'OT counting starts after this time (HH:MM)'),
('late_rounding_minutes', '30', 'Late rounding block in minutes'),
('ot_rounding_hours', '1', 'OT rounding block in hours'),
('lunch_break_minutes', '60', 'Lunch break duration in minutes'),
('default_working_hours', '8', 'Standard working hours per day');

CREATE TABLE public_holidays (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL,
    name        VARCHAR(150) NOT NULL,
    year        SMALLINT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_holiday (holiday_date)
);

-- ============================================================
-- EXCEL UPLOADS TRACKING
-- ============================================================

CREATE TABLE excel_uploads (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    file_name       VARCHAR(255) NOT NULL,
    file_path       VARCHAR(500) NOT NULL,
    month           TINYINT NOT NULL,
    year            SMALLINT NOT NULL,
    status          ENUM('processing','completed','error') DEFAULT 'processing',
    error_message   TEXT NULL,
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================================
-- MORNING ASSEMBLY INTEGRATION
-- ============================================================

CREATE TABLE morning_assembly_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    log_date    DATE NOT NULL,
    attended    TINYINT(1) DEFAULT 0,
    source      ENUM('google_form','manual') DEFAULT 'google_form',
    synced_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assembly (user_id, log_date),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================================
-- NOTIFICATIONS
-- ============================================================

CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    type        VARCHAR(50) NOT NULL,
    title       VARCHAR(200) NOT NULL,
    message     TEXT,
    link        VARCHAR(500),
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================

CREATE INDEX idx_timesheet_user     ON timesheets(user_id, month, year);
CREATE INDEX idx_timesheet_status   ON timesheets(status);
CREATE INDEX idx_ot_form_user       ON ot_forms(user_id, month, year);
CREATE INDEX idx_ot_form_status     ON ot_forms(status);
CREATE INDEX idx_approval_entity    ON approval_logs(entity_type, entity_id);
CREATE INDEX idx_notification_user  ON notifications(user_id, is_read);
CREATE INDEX idx_assembly_date      ON morning_assembly_log(log_date);
```

---

## 5. API Design

### 5.1 API Overview

**Base URL:** `/api/v1`  
**Authentication:** JWT Bearer Token (or PHP Session)  
**Content Type:** `application/json` (except file uploads: `multipart/form-data`)

### 5.2 Authentication Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | `/auth/login` | User login | No |
| POST | `/auth/logout` | User logout | Yes |
| GET | `/auth/me` | Get current user profile | Yes |
| PUT | `/auth/password` | Change password | Yes |

#### POST `/auth/login`

**Request:**
```json
{
    "email": "staff@company.com",
    "password": "********"
}
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "token": "eyJhbGciOi...",
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "staff@company.com",
            "role": "staff",
            "department": "Engineering"
        }
    }
}
```

### 5.3 Timesheet Endpoints

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/timesheets` | List user's timesheets | Staff, Approver |
| GET | `/timesheets/:id` | Get timesheet detail with entries | Staff, Approver |
| POST | `/timesheets` | Create new timesheet (draft) | Staff |
| PUT | `/timesheets/:id` | Update timesheet entries | Staff |
| POST | `/timesheets/:id/submit` | Submit for approval | Staff |
| POST | `/timesheets/:id/upload-excel` | Upload & parse Excel | Staff |
| GET | `/timesheets/pending` | List timesheets pending my approval | Approver |
| POST | `/timesheets/:id/approve` | Approve timesheet | Approver |
| POST | `/timesheets/:id/reject` | Reject timesheet | Approver |

#### POST `/timesheets/:id/upload-excel`

**Request:** `multipart/form-data`
```
file: <Excel file .xlsx/.xls>
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "upload_id": 42,
        "records_parsed": 31,
        "warnings": [
            { "date": "2025-12-05", "message": "No Time In / Time Out detected — marked as MC/Leave" },
            { "date": "2025-12-06", "message": "Saturday — marked as Off Day" }
        ],
        "entries": [
            {
                "date": "2025-12-01",
                "day_type": "working",
                "time_in": "08:25:00",
                "time_out": "19:45:00",
                "late_hours": 0.0,
                "ot_hours": 1,
                "attendance_hours": 8.0,
                "mc_leave_hours": 0.0
            }
        ]
    }
}
```

#### POST `/timesheets/:id/submit`

**Request:**
```json
{
    "entries": [
        {
            "date": "2025-12-01",
            "day_type": "working",
            "time_in": "08:25:00",
            "time_out": "19:45:00",
            "late_hours": 0.0,
            "ot_hours": 1,
            "mc_leave_hours": 0.0,
            "attendance_hours": 8.0,
            "morning_assy_hours": 0.5,
            "admin_job_hours": 0.0,
            "five_s_hours": 0.0,
            "ceramah_event_hours": 0.0,
            "iso_hours": 0.0,
            "training_hours": 0.0,
            "project_code_id": 5,
            "remarks": ""
        }
    ],
    "ot_entries": [
        {
            "date": "2025-12-01",
            "project_code_id": 5,
            "ot_hours": 1,
            "remarks": "Urgent deadline"
        }
    ]
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Timesheet submitted for approval",
    "data": {
        "timesheet_id": 10,
        "status": "pending_l1"
    }
}
```

#### POST `/timesheets/:id/approve`

**Request:**
```json
{
    "remarks": "Looks good."
}
```

**Response (200):**
```json
{
    "success": true,
    "message": "Timesheet approved at Level 1",
    "data": {
        "timesheet_id": 10,
        "new_status": "pending_l2"
    }
}
```

### 5.4 OT Form Endpoints

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/ot-forms` | List user's OT forms | Staff, Approver |
| GET | `/ot-forms/:id` | Get OT form detail with entries | Staff, Approver |
| POST | `/ot-forms` | Create new OT form (draft) | Staff |
| PUT | `/ot-forms/:id` | Update OT form entries | Staff |
| POST | `/ot-forms/:id/submit` | Submit for approval | Staff |
| GET | `/ot-forms/pending` | List OT forms pending my approval | Approver |
| POST | `/ot-forms/:id/approve` | Approve OT form | Approver |
| POST | `/ot-forms/:id/reject` | Reject OT form | Approver |

#### POST `/ot-forms/:id/submit`

**Request:**
```json
{
    "month": 12,
    "year": 2025,
    "company_id": 1,
    "entries": [
        {
            "date": "2025-12-03",
            "project_code_id": 5,
            "planned_start_time": "18:30:00",
            "planned_end_time": "21:00:00",
            "actual_start_time": "18:30:00",
            "actual_end_time": "20:45:00",
            "total_ot_hours": 2
        }
    ]
}
```

### 5.5 Project Codes Endpoints (Admin)

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/project-codes` | List all project codes | All |
| GET | `/project-codes/:id` | Get single project code | All |
| POST | `/project-codes` | Create project code | Admin |
| PUT | `/project-codes/:id` | Update project code | Admin |
| DELETE | `/project-codes/:id` | Deactivate project code | Admin |

#### POST `/project-codes`

**Request:**
```json
{
    "code": "PRJ-2025-001",
    "name": "Factory Renovation Phase 2",
    "company_id": 1
}
```

### 5.6 Admin Endpoints

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/admin/users` | List all users | Admin |
| POST | `/admin/users` | Create user | Admin |
| PUT | `/admin/users/:id` | Update user | Admin |
| DELETE | `/admin/users/:id` | Deactivate user | Admin |
| GET | `/admin/config` | Get system configuration | Admin |
| PUT | `/admin/config` | Update system configuration | Admin |
| GET | `/admin/holidays` | List public holidays | Admin |
| POST | `/admin/holidays` | Add public holiday | Admin |
| DELETE | `/admin/holidays/:id` | Remove public holiday | Admin |

### 5.7 Reports Endpoints

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/reports/timesheet-summary` | Monthly timesheet summary | Admin, Manager, CEO |
| GET | `/reports/ot-summary` | OT summary by project/department | Admin, Manager, CEO |
| GET | `/reports/late-summary` | Late report | Admin, Manager |
| GET | `/reports/leave-mc-summary` | Leave/MC report | Admin, Manager |
| GET | `/reports/export` | Export report as Excel/PDF | Admin, Manager, CEO |

**Query Parameters (common):**
```
?month=12&year=2025&department_id=3&format=pdf
```

### 5.8 Integration Endpoints

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| POST | `/integration/morning-assembly/sync` | Sync morning assembly from Google | System/Admin |
| GET | `/integration/morning-assembly/:userId/:date` | Get assembly status for user/date | Staff |

### 5.9 Notification Endpoints

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/notifications` | Get user's notifications | All |
| PUT | `/notifications/:id/read` | Mark notification as read | All |
| PUT | `/notifications/read-all` | Mark all as read | All |

---

## 6. Frontend Page Structure

### 6.1 Page Map

```
/                                   → Redirect to /dashboard
/login                              → Login Page
/dashboard                          → Dashboard (role-based)
│
├── /timesheet                      → Timesheet List (My Timesheets)
│   ├── /timesheet/new              → Create New Timesheet (select month/year)
│   ├── /timesheet/:id              → View/Edit Timesheet
│   │   └── [Upload Excel]          → Inline Excel upload & parse
│   └── /timesheet/:id/print        → Print-friendly view
│
├── /ot-form                        → OT Form List (My OT Forms)
│   ├── /ot-form/new                → Create New OT Form
│   ├── /ot-form/:id                → View/Edit OT Form
│   └── /ot-form/:id/print          → Print-friendly view
│
├── /approvals                      → Pending Approvals (Approver only)
│   ├── /approvals/timesheets       → Pending Timesheets
│   └── /approvals/ot-forms         → Pending OT Forms
│
├── /admin                          → Admin Panel
│   ├── /admin/project-codes        → Project Code CRUD
│   ├── /admin/users                → User Management
│   ├── /admin/config               → System Configuration
│   ├── /admin/holidays             → Public Holiday Management
│   └── /admin/reports              → Reports & Export
│
├── /profile                        → User Profile & Password Change
└── /notifications                  → Notification Center
```

### 6.2 Page Descriptions

#### Login Page (`/login`)
- Email & password form
- Remember me checkbox
- Redirect to dashboard on success

#### Dashboard (`/dashboard`)
Role-based content:
- **Staff:** Quick links (New Timesheet, New OT Form), recent submissions, pending items
- **Approver:** Pending approvals count, recent approval activity
- **Admin:** System stats, quick links to management pages

#### Timesheet View/Edit (`/timesheet/:id`)
Main components:
- **Header:** Staff name, department, month/year, status badge
- **Excel Upload Button:** Upload .xlsx → triggers parse → populates form
- **Monthly Grid/Table:** One row per day of the month
  - Columns: Date | Day | Day Type | Time In | Time Out | Late Hrs | OT Hrs | MC/Leave Hrs | Attendance Hrs | Morning Assy | Admin Job | Project Code (dropdown) | 5S | Ceramah/Event | ISO | Training | Remarks
  - Autofilled cells are highlighted (light blue background)
  - Editable cells have input fields / dropdowns
  - Weekend rows are shaded grey
  - MC/Leave rows are shaded orange
- **OT Section (within Timesheet):**
  - Sub-table for OT entries linked to dates
  - Columns: Date | Project Code (dropdown) | Project Name (autofill) | OT Hours | Remarks
- **Summary Row:** Totals for each hour column
- **Action Buttons:** Save Draft | Submit | Cancel

#### OT Form View/Edit (`/ot-form/:id`)
Main components:
- **Header:** Staff name, month (dropdown, e.g., "DEC 2025"), company tick (logo selection)
- **Entries Table:**
  - Columns: Date | Project Name (dropdown, mandatory) | Planned Start | Planned End | Actual Start | Actual End | Total OT Hours (auto-calc) | Remarks
- **Add Row Button**
- **Summary:** Total planned hours, total actual OT hours
- **Action Buttons:** Save Draft | Submit | Cancel

#### Approval Page (`/approvals/timesheets` or `/approvals/ot-forms`)
- Table listing pending items: Staff Name | Department | Month | Submitted At | Status
- Click to expand/view full details
- Approve / Reject buttons with optional remarks dialog

### 6.3 UI Component Library

| Component | Usage |
|-----------|-------|
| **DataTable** | Timesheet grid, OT entries, approval lists |
| **Dropdown/Select** | Project Code, month selector, day type |
| **DatePicker** | Date fields |
| **TimePicker** | Time In/Out, OT times |
| **FileUpload** | Excel upload with drag-and-drop |
| **StatusBadge** | Draft (grey), Pending (yellow), Approved (green), Rejected (red) |
| **Modal/Dialog** | Approval remarks, confirmation dialogs |
| **Toast/Notification** | Success/error messages |
| **Tabs** | Timesheet vs OT section within timesheet page |

---

## 7. Excel Parsing Logic

### 7.1 Supported Formats

- `.xlsx` (Excel 2007+)
- `.xls` (Legacy Excel)

### 7.2 Library Recommendations

| Stack | Library |
|-------|---------|
| PHP | PhpSpreadsheet (`phpoffice/phpspreadsheet`) |
| Node.js | SheetJS (`xlsx`) or `exceljs` |

### 7.3 Parsing Algorithm (Pseudocode)

```
FUNCTION parseExcel(file, targetMonth, targetYear):

    workbook = loadWorkbook(file)
    sheet = workbook.getActiveSheet()

    // Step 1: Detect header row
    headerRow = findRowContaining(["Date", "Time In", "Time Out"])
    IF headerRow == NULL:
        THROW "Invalid format: required columns not found"

    // Step 2: Map column indices
    dateCol    = findColumnIndex(headerRow, "Date")
    timeInCol  = findColumnIndex(headerRow, "Time In")
    timeOutCol = findColumnIndex(headerRow, "Time Out")

    // Step 3: Extract data rows
    entries = []
    FOR each row AFTER headerRow:
        dateValue  = parseDate(row[dateCol])
        timeIn     = parseTime(row[timeInCol])      // may be NULL
        timeOut    = parseTime(row[timeOutCol])      // may be NULL

        IF dateValue is NULL OR dateValue.month != targetMonth:
            CONTINUE

        entry = new TimesheetEntry()
        entry.date    = dateValue
        entry.timeIn  = timeIn
        entry.timeOut = timeOut

        entries.push(entry)

    // Step 4: Apply business rules
    config = getSystemConfig()
    WORK_START  = config.working_start_time      // 08:30
    OT_START    = config.ot_start_time           // 18:30
    LATE_BLOCK  = config.late_rounding_minutes   // 30
    OT_BLOCK    = config.ot_rounding_hours       // 1

    holidays = getPublicHolidays(targetYear)

    FOR each entry IN entries:
        dayOfWeek = entry.date.getDayOfWeek()

        // --- Day Type Detection ---
        IF entry.date IN holidays:
            entry.dayType = "public_holiday"
        ELSE IF dayOfWeek == SATURDAY OR dayOfWeek == SUNDAY:
            entry.dayType = "off_day"
        ELSE IF entry.timeIn == NULL AND entry.timeOut == NULL:
            entry.dayType = "mc"           // or "leave"
            entry.mcLeaveHours = 8.0
        ELSE:
            entry.dayType = "working"

        // --- Late Calculation ---
        IF entry.timeIn != NULL AND entry.dayType == "working":
            IF entry.timeIn > WORK_START:
                lateMinutes = diffInMinutes(WORK_START, entry.timeIn)
                entry.lateHours = ceilToBlock(lateMinutes, LATE_BLOCK) / 60
                // Example: 12 min → ceil(12/30)=1 block → 0.5 hr
                // Example: 35 min → ceil(35/30)=2 blocks → 1.0 hr
            ELSE:
                entry.lateHours = 0.0

        // --- OT Calculation ---
        IF entry.timeOut != NULL:
            IF entry.timeOut > OT_START:
                otMinutes = diffInMinutes(OT_START, entry.timeOut)
                entry.otHours = floor(otMinutes / 60)
                // Example: 75 min → floor(75/60) = 1 hr
                // Example: 125 min → floor(125/60) = 2 hrs
                // Example: 55 min → floor(55/60) = 0 hrs (no OT)
            ELSE:
                entry.otHours = 0

        // --- Attendance Hours ---
        IF entry.timeIn != NULL AND entry.timeOut != NULL:
            totalMinutes = diffInMinutes(entry.timeIn, entry.timeOut)
            lunchBreak = config.lunch_break_minutes   // 60 min
            entry.attendanceHours = (totalMinutes - lunchBreak) / 60

    RETURN entries

// --- Helper: Ceiling to block ---
FUNCTION ceilToBlock(minutes, blockSize):
    RETURN ceil(minutes / blockSize) * blockSize
    // 12 min, block=30 → ceil(12/30)=1 → 1*30=30 min → 0.5 hr
    // 35 min, block=30 → ceil(35/30)=2 → 2*30=60 min → 1.0 hr
    // 61 min, block=30 → ceil(61/30)=3 → 3*30=90 min → 1.5 hr
```

### 7.4 OT Calculation for OT Form (Pseudocode)

```
FUNCTION calculateOTFormHours(actualStartTime, actualEndTime):
    OT_START = "18:30"

    // OT only counts after 6:30 PM
    effectiveStart = MAX(actualStartTime, OT_START)

    IF effectiveStart >= actualEndTime:
        RETURN 0    // No valid OT

    otMinutes = diffInMinutes(effectiveStart, actualEndTime)
    RETURN floor(otMinutes / 60)   // whole hours only
```

### 7.5 Validation Rules

| Check | Rule | Error Message |
|-------|------|---------------|
| File type | Must be .xlsx or .xls | "Invalid file type. Please upload an Excel file." |
| File size | Max 5 MB | "File too large. Maximum 5 MB allowed." |
| Required columns | "Date", "Time In", "Time Out" headers must exist | "Missing required columns in Excel file." |
| Date range | Dates must match selected month/year | "Date {date} does not match selected month." |
| Time format | Valid time values (HH:MM) | "Invalid time format on row {n}." |
| Duplicate dates | No duplicate dates in same upload | "Duplicate entry for date {date}." |

---

## 8. Business Rules Engine

### 8.1 Rules Summary

| Rule | Logic | Output |
|------|-------|--------|
| **Late Detection** | If `time_in > 08:30` on a working day | `late_hours` in 0.5-hr blocks (ceiling) |
| **OT Detection** | If `time_out > 18:30` | `ot_hours` in whole hours (floor) |
| **MC/Leave** | If no `time_in` AND no `time_out` on working day | `day_type = mc/leave`, `mc_leave_hours = 8` |
| **Off Day** | If Saturday or Sunday | `day_type = off_day` (staff can still log work) |
| **Public Holiday** | If date is in `public_holidays` table | `day_type = public_holiday` |
| **Attendance Hours** | `time_out - time_in - lunch_break` | Decimal hours |
| **Morning Assembly** | Pulled from Google Form integration | `morning_assy_hours` autofill |
| **OT ↔ Project** | Every OT entry must have a Project Code | Mandatory validation |
| **Project Name Autofill** | When Project Code is selected | `project_name` auto-populated from `project_codes.name` |

### 8.2 Late Calculation Examples

| Time In | Minutes Late | Blocks (30 min) | Late Hours |
|---------|:------------:|:----------------:|:----------:|
| 08:25 | 0 | 0 | 0.0 |
| 08:30 | 0 | 0 | 0.0 |
| 08:31 | 1 | 1 | 0.5 |
| 08:42 | 12 | 1 | 0.5 |
| 08:59 | 29 | 1 | 0.5 |
| 09:00 | 30 | 1 | 0.5 |
| 09:01 | 31 | 2 | 1.0 |
| 09:35 | 65 | 3 | 1.5 |
| 10:00 | 90 | 3 | 1.5 |
| 10:01 | 91 | 4 | 2.0 |

### 8.3 OT Calculation Examples

| Time Out | Minutes After 18:30 | OT Hours (floor) |
|----------|:--------------------:|:-----------------:|
| 18:00 | 0 | 0 |
| 18:30 | 0 | 0 |
| 18:45 | 15 | 0 |
| 19:29 | 59 | 0 |
| 19:30 | 60 | 1 |
| 19:45 | 75 | 1 |
| 20:30 | 120 | 2 |
| 20:35 | 125 | 2 |
| 21:30 | 180 | 3 |

---

## 9. Approval Workflow Logic

### 9.1 Timesheet Approval — State Machine

```
                    ┌─────────┐
                    │  DRAFT  │ (initial state)
                    └────┬────┘
                         │ [Staff submits]
                         ▼
                  ┌──────────────┐
           ┌──── │  PENDING_L1  │ ◄──────────────┐
           │     └──────┬───────┘                 │
           │            │                         │
    [Asst. Mgr         [Asst. Mgr                │
     rejects]           approves]                 │
           │            │                         │
           ▼            ▼                         │
  ┌──────────────┐  ┌──────────────┐              │
  │ REJECTED_L1  │  │  PENDING_L2  │              │
  └──────┬───────┘  └──────┬───────┘              │
         │                 │                      │
  [Staff edits     [Mgr/HOD        [Mgr/HOD      │
   & resubmits]     approves]       rejects]      │
         │                 │            │          │
         └─────────────────┼────┐       ▼          │
                           │    │  ┌──────────────┐│
                           │    │  │ REJECTED_L2  ││
                           │    │  └──────┬───────┘│
                           │    │         │         │
                           ▼    │  [Staff edits     │
                    ┌──────────┐│   & resubmits]────┘
                    │ APPROVED ││
                    └──────────┘│
                                │
```

**State Transitions:**

| From | Trigger | To | Actor |
|------|---------|----|----|
| `draft` | Submit | `pending_l1` | Staff |
| `pending_l1` | Approve | `pending_l2` | Assistant Manager |
| `pending_l1` | Reject | `rejected_l1` | Assistant Manager |
| `rejected_l1` | Resubmit | `pending_l1` | Staff |
| `pending_l2` | Approve | `approved` | Manager/HOD |
| `pending_l2` | Reject | `rejected_l2` | Manager/HOD |
| `rejected_l2` | Resubmit | `pending_l1` | Staff |

### 9.2 OT Form Approval — State Machine

```
                    ┌─────────┐
                    │  DRAFT  │
                    └────┬────┘
                         │ [Staff submits]
                         ▼
                  ┌──────────────┐
           ┌──── │  PENDING_L1  │ ◄──────────────┐
           │     └──────┬───────┘                 │
           │            │                         │
    [Mgr/HOD           [Mgr/HOD                  │
     rejects]           approves]                 │
           │            │                         │
           ▼            ▼                         │
  ┌──────────────┐  ┌──────────────┐              │
  │ REJECTED_L1  │  │  PENDING_L2  │              │
  └──────┬───────┘  └──────┬───────┘              │
         │                 │                      │
  [Staff edits      [CEO           [CEO           │
   & resubmits]      approves]      rejects]      │
         │                 │            │          │
         └─────────────────┼────┐       ▼          │
                           │    │  ┌──────────────┐│
                           │    │  │ REJECTED_L2  ││
                           │    │  └──────┬───────┘│
                           │    │         │         │
                           ▼    │  [Staff edits     │
                    ┌──────────┐│   & resubmits]────┘
                    │ APPROVED ││
                    └──────────┘│
                                │
```

**State Transitions:**

| From | Trigger | To | Actor |
|------|---------|----|----|
| `draft` | Submit | `pending_l1` | Staff |
| `pending_l1` | Approve | `pending_l2` | Manager/HOD |
| `pending_l1` | Reject | `rejected_l1` | Manager/HOD |
| `rejected_l1` | Resubmit | `pending_l1` | Staff |
| `pending_l2` | Approve | `approved` | CEO |
| `pending_l2` | Reject | `rejected_l2` | CEO |
| `rejected_l2` | Resubmit | `pending_l1` | Staff |

### 9.3 Approval Logic (Backend Pseudocode)

```
FUNCTION approveEntity(entityType, entityId, approverId, action, remarks):

    entity = getEntity(entityType, entityId)   // timesheet or ot_form
    approver = getUser(approverId)

    // Validate approver has permission for current level
    IF entityType == "timesheet":
        IF entity.status == "pending_l1":
            REQUIRE approver.role == "assistant_manager"
            REQUIRE approver.id == getReportsTo(entity.user_id)
        ELSE IF entity.status == "pending_l2":
            REQUIRE approver.role == "manager_hod"
            REQUIRE approver manages entity.user_id's department

    ELSE IF entityType == "ot_form":
        IF entity.status == "pending_l1":
            REQUIRE approver.role == "manager_hod"
        ELSE IF entity.status == "pending_l2":
            REQUIRE approver.role == "ceo"

    // Perform action
    IF action == "approved":
        IF entity.current_level == 1:
            entity.status = "pending_l2"
            entity.current_level = 2
            notifyNextApprover(entity)
        ELSE IF entity.current_level == 2:
            entity.status = "approved"
            notifyStaff(entity, "approved")

    ELSE IF action == "rejected":
        IF entity.current_level == 1:
            entity.status = "rejected_l1"
        ELSE IF entity.current_level == 2:
            entity.status = "rejected_l2"
        notifyStaff(entity, "rejected", remarks)

    // Log the action
    INSERT INTO approval_logs (entity_type, entity_id, approver_id, level, action, remarks)
    VALUES (entityType, entityId, approverId, entity.current_level, action, remarks)

    SAVE entity
```

---

## 10. Integration Architecture

### 10.1 Morning Assembly Integration (Google Form → System)

```
┌───────────────────┐       ┌────────────────────┐       ┌──────────────┐
│   Google Form     │──────▶│  Google Apps Script │──────▶│  Desknet     │
│  (Staff checks in)│       │  (onFormSubmit)     │       │  (existing)  │
└───────────────────┘       └────────┬───────────┘       └──────────────┘
                                     │
                                     │ HTTP POST (webhook)
                                     ▼
                            ┌──────────────────┐
                            │  Timesheet System │
                            │  /api/v1/         │
                            │  integration/     │
                            │  morning-assembly/ │
                            │  sync             │
                            └──────────────────┘
```

**Flow:**
1. Staff submits morning assembly attendance via Google Form
2. Google Apps Script triggers `onFormSubmit`
3. Apps Script sends data to Desknet (existing flow)
4. Apps Script **also** sends HTTP POST to Timesheet System's webhook endpoint
5. System stores attendance in `morning_assembly_log` table
6. When timesheet is generated, system auto-fills `morning_assy_hours` from this log

**Apps Script Snippet (Outbound to Timesheet System):**
```javascript
function onFormSubmit(e) {
    // Existing Desknet integration code...

    // NEW: Send to Timesheet System
    var payload = {
        email: e.values[1],       // Staff email from form
        date: e.values[0],        // Timestamp/date
        attended: true
    };

    var options = {
        method: "post",
        contentType: "application/json",
        payload: JSON.stringify(payload),
        headers: {
            "X-API-Key": "YOUR_INTEGRATION_API_KEY"
        }
    };

    UrlFetchApp.fetch("https://your-system.com/api/v1/integration/morning-assembly/sync", options);
}
```

### 10.2 Scheduled Sync (Alternative/Backup)

If webhook is unreliable, run a CRON job / scheduled task:

```
Every day at 9:00 AM:
  1. Call Google Sheets API to read today's form responses
  2. Match staff emails to user accounts
  3. Upsert into morning_assembly_log
  4. Log sync status
```

---

## 11. Tech Stack & Infrastructure

### 11.1 Recommended Stack

| Layer | Technology | Rationale |
|-------|-----------|-----------|
| **Frontend** | React (Vite) + TailwindCSS | Modern, component-based, fast dev cycle |
| **UI Components** | shadcn/ui + Lucide Icons | Polished, accessible, pre-built components |
| **Backend** | PHP (Laravel) **or** Node.js (Express) | Laravel preferred for XAMPP env; Node.js for JS-only stack |
| **Database** | MySQL 8.0 | Already available on XAMPP, relational, well-suited |
| **Excel Parsing** | PhpSpreadsheet (PHP) / SheetJS (Node) | Robust, well-maintained Excel libraries |
| **Authentication** | JWT (API) or Laravel Sanctum (SPA) | Stateless auth for REST API |
| **File Storage** | Local filesystem (XAMPP) | Simple, no cloud dependency |
| **Integration** | Google Apps Script + REST webhook | Leverages existing Google Form setup |

### 11.2 Development Environment

```
XAMPP (Windows)
├── Apache (web server)
├── MySQL (database)
├── PHP 8.x (backend)
└── htdocs/
    └── Timesheet_Website/
        ├── backend/          (Laravel or plain PHP API)
        │   ├── app/
        │   ├── routes/
        │   ├── database/
        │   └── ...
        ├── frontend/         (React app)
        │   ├── src/
        │   ├── public/
        │   └── ...
        └── uploads/          (Excel files)
```

### 11.3 Deployment Architecture (Production)

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Browser    │────▶│  Apache/Nginx│────▶│  PHP/Node    │
│   (Staff)    │     │  (Reverse    │     │  (Backend)   │
└──────────────┘     │   Proxy)     │     └──────┬───────┘
                     └──────────────┘            │
                                                 ▼
                                          ┌──────────────┐
                                          │    MySQL     │
                                          │   Database   │
                                          └──────────────┘
```

---

## 12. Security Considerations

| Area | Measure |
|------|---------|
| **Authentication** | Password hashing (bcrypt), JWT with expiry, session timeout |
| **Authorization** | Role-based middleware on every API endpoint |
| **Input Validation** | Server-side validation on all inputs; sanitize Excel data |
| **File Upload** | Whitelist extensions (.xlsx, .xls), scan for macros, size limit |
| **SQL Injection** | Parameterized queries / ORM (Eloquent) |
| **XSS** | React auto-escapes; CSP headers |
| **CSRF** | CSRF tokens on form submissions (if using sessions) |
| **API Keys** | Integration API key stored in environment variables |
| **Audit Trail** | All approval actions logged with timestamp and user ID |
| **Data Access** | Staff can only see own records; approvers see direct reports only |

---

## 13. Future Considerations

- **Mobile-responsive** design for tablet/phone access
- **Email notifications** (SMTP integration for approval alerts)
- **Dashboard analytics** (charts: OT trends, late trends, project hours breakdown)
- **Bulk approval** for managers with many direct reports
- **Digital signature** on approved timesheets
- **Calendar integration** for leave/MC tracking
- **API rate limiting** and request throttling
- **PDF generation** of approved timesheets for archival
- **Multi-language support** (English / Bahasa Malaysia)

---

## Appendix A: Data Flow Summary

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│  Excel   │───▶│  Parser  │───▶│ Business │───▶│ Timesheet│───▶│ Approval │
│  Upload  │    │  Engine  │    │  Rules   │    │  Form    │    │  Chain   │
└──────────┘    └──────────┘    └──────────┘    └──────────┘    └──────────┘
                                     ▲                               │
                                     │                               ▼
                              ┌──────────┐                    ┌──────────┐
                              │  System  │                    │  Final   │
                              │  Config  │                    │  Status  │
                              └──────────┘                    └──────────┘

┌──────────┐    ┌──────────┐    ┌──────────┐
│  Google  │───▶│  Apps    │───▶│ Assembly │───▶ Autofill morning_assy_hours
│  Form    │    │  Script  │    │   Log    │
└──────────┘    └──────────┘    └──────────┘
```

---

## Appendix B: Glossary

| Term | Definition |
|------|-----------|
| **Timesheet** | Monthly attendance and work-hour record per staff |
| **OT Form** | Overtime claim form for hours worked beyond standard time |
| **HOD** | Head of Department |
| **MC** | Medical Certificate (sick leave) |
| **5S** | Workplace organization methodology (Sort, Set, Shine, Standardize, Sustain) |
| **ADP** | Attendance-related program/event |
| **Ceramah Agama** | Religious talk/event |
| **ISO** | ISO standards-related work hours |
| **Morning Assembly** | Daily morning briefing/roll call |
| **Infotech** | Third-party attendance system that exports Excel reports |
| **Desknet** | Existing internal communication/workflow platform |

---

> **Next Steps:**  
> 1. Provide timesheet form design & OT form design → UI mockup  
> 2. Finalize tech stack decision (PHP Laravel vs Node.js)  
> 3. Set up project structure & database  
> 4. Begin development (Sprint 1: Auth + Project Codes + Excel Parser)
