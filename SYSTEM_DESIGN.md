# Timesheet & Overtime (OT) Management System — Full System Design

> **Version:** 3.0  
> **Date:** 2026-04-09  
> **Status:** Draft — Timesheet form revised; PDF upload; print layout

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Timesheet Form Layout (Physical Form Analysis)](#2-timesheet-form-layout)
3. [User Roles & Permissions](#3-user-roles--permissions)
4. [Full System Workflow](#4-full-system-workflow)
5. [Database Schema](#5-database-schema)
6. [API Design](#6-api-design)
7. [Frontend Page Structure](#7-frontend-page-structure)
8. [PDF/Excel Parsing Logic](#8-pdfexcel-parsing-logic)
9. [Business Rules Engine](#9-business-rules-engine)
10. [Approval Workflow Logic](#10-approval-workflow-logic)
11. [Integration Architecture](#11-integration-architecture)
12. [Tech Stack & Infrastructure](#12-tech-stack--infrastructure)
13. [Security Considerations](#13-security-considerations)
14. [Future Considerations](#14-future-considerations)

---

## 1. System Overview

### 1.1 Purpose

A web-based internal application that allows staff to submit monthly timesheets and overtime (OT) forms, with automated attendance data population from Infotech PDF attendance reports, business-rule-driven calculations, and a multi-level approval workflow.

### 1.2 High-Level Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                          FRONTEND (Browser)                         │
│   React / HTML+JS  ·  Form UI  ·  Dashboards  ·  PDF Upload         │
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
│   │ PDF        │ │ Approval     │ │ Integration│                   │
│   │ Parser     │ │ Engine       │ │ Service    │                   │
│   └────────────┘ └──────────────┘ └────────────┘                   │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
          ┌────────────────────┼─────────────────────┐
          ▼                    ▼                     ▼
   ┌─────────────┐   ┌────────────────┐   ┌──────────────────┐
   │   MySQL DB   │   │ File Storage   │   │ Google Apps      │
   │              │   │ (PDF files)    │   │ Script API       │
   └─────────────┘   └────────────────┘   └──────────────────┘
```

### 1.3 Key Design Principles

- **Project × Day matrix layout** — timesheet mirrors the physical form (projects as rows, days 1–31 as columns)
- **Minimize manual input** — autofill from PDF attendance report, dropdowns, and integrations
- **Enforce business rules** — late (30-min blocks), OT (full hours, after 5:30 PM)
- **Friday rule** — working hours = 7 hrs on Fridays vs 8 hrs on other days
- **Print-ready** — landscape A4 print layout matching the physical form exactly
- **User-friendly** — simple form-based UI for non-technical staff
- **Auditability** — every approval action is timestamped and logged

---

## 2. Timesheet Form Layout (Physical Form Analysis)

> Based on the actual physical "Daily Time Sheet" form used by the company (Ingress Sdn Bhd).

### 2.1 Form Orientation & Structure

The timesheet is a **landscape-oriented matrix** where:
- **Columns** = Days of the month (1–31) + **TOTAL** column
- **Rows** = Project allocations + Activity/overhead categories + Summary

This is a **Project × Day matrix**, NOT a day-based list. Staff distribute their daily hours across multiple projects and activity categories.

### 2.2 Form Sections (Top to Bottom)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ HEADER                                                                       │
│  Company Logo & Name (INGRESS SDN BHD)                                       │
│  Title: "DAILY TIME SHEET"                                                   │
│  Month/Year: e.g., NOVEMBER 2023                                             │
│  Staff Name: e.g., SYED MOHAMED TAUFIQ BIN SYED ANWAR ALY                   │
│  Employee No / Staff ID                                                      │
│  Department / Section                                                        │
├─────────────────────────────────────────────────────────────────────────────┤
│ DAY INFO ROW                                                                 │
│  Day numbers:  1  │  2  │  3  │  4  │ ... │ 31  │ TOTAL                     │
│  Day names:   SAT │ SUN │ MON │ TUE │ ... │ WED │                           │
├─────────────────────────────────────────────────────────────────────────────┤
│ PROJECT SECTION (repeats per project)                                        │
│                                                                              │
│  Project NO.: 1                                                              │
│  PROJECT CODE: TXA/TY1/0023                                                 │
│  Project Name: RFQ SIME DARBY                                               │
│  ┌─────────────────────────────────────────────────────────────┐             │
│  │ NORMAL hrs │ 8 │   │ 8 │ 8 │ ... │   │ total              │             │
│  │ OT hrs     │   │   │   │ 2 │ ... │   │ total              │             │
│  └─────────────────────────────────────────────────────────────┘             │
│                                                                              │
│  Project NO.: 2                                                              │
│  PROJECT CODE: (another code)                                                │
│  Project Name: (another name)                                                │
│  ┌─────────────────────────────────────────────────────────────┐             │
│  │ NORMAL hrs │ ...                                            │             │
│  │ OT hrs     │ ...                                            │             │
│  └─────────────────────────────────────────────────────────────┘             │
│                                                                              │
│  (more projects as needed...)                                                │
├─────────────────────────────────────────────────────────────────────────────┤
│ ACTIVITY / OVERHEAD CATEGORY ROWS (fixed rows, not project-specific)         │
│                                                                              │
│  Each category has NORMAL + OT sub-rows:                                     │
│  ┌──────────────────────────┬──────────────────────────────────┐             │
│  │ NORMAL COST              │  hrs per day...  │ total         │             │
│  │ MARKETING                │  hrs per day...  │ total         │             │
│  │ PURCHASING               │  hrs per day...  │ total         │             │
│  │ RESEARCH & DEV           │  hrs per day...  │ total         │             │
│  │ CODB (Cost of Bad Qty)   │  hrs per day...  │ total         │             │
│  │ TENDER                   │  hrs per day...  │ total         │             │
│  │ RFQ                      │  hrs per day...  │ total         │             │
│  │ A.A.S                    │  hrs per day...  │ total         │             │
│  │ REQUEST FOR QUOTATION    │  hrs per day...  │ total         │             │
│  │ INTERNAL SERVICE         │  hrs per day...  │ total         │             │
│  └──────────────────────────┴──────────────────────────────────┘             │
├─────────────────────────────────────────────────────────────────────────────┤
│ ADMIN JOB ROWS (8 fixed rows, auto-filled + editable)                        │
│                                                                              │
│  Row 1: │ MC / LEAVE                   │  hrs per day...  │ total │          │
│  Row 2: │ LATE                         │  hrs per day...  │ total │          │
│  Row 3: │ MORNING ASSY / ADMIN JOB     │  hrs per day...  │ total │  (0.5)   │
│  Row 4: │ 5S                           │  hrs per day...  │ total │  (0.5)   │
│  Row 5: │ CERAMAH AGAMA / EVENT / ADP  │  hrs per day...  │ total │  (blank) │
│  Row 6: │ ISO                          │  hrs per day...  │ total │  (blank) │
│  Row 7: │ TRAINING / SEMINAR / VISIT   │  hrs per day...  │ total │  (blank) │
│  Row 8: │ RFQ/MKT/PUR/R&D/A.S.S/TDR   │  hrs per day...  │ total │  (blank) │
│  TOTAL ADMIN JOB = Row1+Row2+Row3+Row4+Row5+Row6+Row7+Row8                  │
│                                                                              │
│  Auto-fill logic on PDF upload:                                              │
│  - Row 1: 8 (Mon-Thu) or 7 (Fri) when no time_in/out on a weekday           │
│  - Row 2: Late hours from time_in (0.5 increments after 08:30)              │
│  - Row 3-4: Default 0.5 on working days (Mon-Fri, excl PH)                  │
│  - Row 5-7: Default blank (0), staff fills manually                          │
│  - Row 8: Staff fills manually                                               │
│  All rows use 0.5 increment dropdowns. Staff can edit all values.            │
├─────────────────────────────────────────────────────────────────────────────┤
│ SUMMARY SECTION (auto-calculated)                                            │
│                                                                              │
│  │ TOTAL EXTERNAL PROJECT  │  = sum(Normal NC + Normal COBQ                 │
│  │                          │    + OT NC + OT COBQ) per project per day     │
│  │ TOTAL WORKING HOURS     │  = TOTAL ADMIN JOB + TOTAL EXTERNAL PROJECT    │
│  │ HOURS AVAILABLE          │  Mon-Thu: 8, Fri: 7, Sat-Sun: 0, PH: 0       │
│  │ OVERTIME                 │  = TOTAL WORKING HOURS - HOURS AVAILABLE      │
│  │ REMARKS                 │  per day...  │                     │             │
├─────────────────────────────────────────────────────────────────────────────┤
│ NOTES (bottom-left)                        │ SIGNATURES (top-right)       │
│                                            │                                 │
│  NORMAL DAY (EXCLUDE OT): 8 HOURS         │  PREPARED:  _________ (Staff)   │
│  FRIDAY ONLY (EXCLUDE OT): 7 HOURS      │  CHECKED:   _________ (Asst Mgr)│
│  OVERTIME:  after 5:30 PM, full hours only │  APPROVED:  _________ (Mgr/HOD)             │                                 │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 2.3 Color Coding (Visual Indicators)

| Color | Meaning | Applied To |
|-------|---------|------------|
| **Yellow** | Off Day (Saturday / Sunday) | Entire column for that day |
| **Red / Pink** | Public Holiday | Entire column for that day |
| **Green** | MC / Leave | Entire column or individual cells for that day |
| **White** | Normal working day | Default |

### 2.4 Legend

| Code | Meaning |
|------|---------|
| **MC** | Medical Certificate (sick leave) — no attendance |
| **MKT** | Marketing-related work |
| **COMB** | Combined / mixed work category |

### 2.5 Key Rules Printed on Form

| Rule | Value |
|------|-------|
| Normal Day (excl. OT) | 8 hours |
| Friday Only (excl. OT) | 7 hours |
| OT starts after | 5:30 PM |
| OT counting | Full hours only (floor) |
| Late rounding | 30-minute blocks (ceiling) |

### 2.6 How Staff Fills the Form

1. Each day column (1–31) has the day of week shown (MON, TUE, etc.)
2. For each project the staff works on, they enter NORMAL hours and OT hours in the corresponding day columns
3. For overhead/activity work (not tied to a specific project), they enter hours in the fixed activity category rows
4. Weekend columns (SAT/SUN) are highlighted yellow — staff can still enter hours if they worked
5. MC/Leave days are highlighted green — no hours entered
6. The TOTAL column on the far right sums each row across all days
7. Summary rows at the bottom aggregate totals across all projects and categories

### 2.7 Implications for Digital System

| Physical Form Aspect | Digital System Design Decision |
|---|---|
| Landscape matrix (projects × days) | Web form must replicate this grid layout with scrollable columns |
| Multiple projects per staff per month | Database must support N projects per timesheet |
| NORMAL + OT sub-rows per project | Each project entry stores both normal_hours and ot_hours per day |
| Fixed activity/overhead rows | System has a configurable list of activity categories (admin-managed) |
| TOTAL column (auto-sum) | Frontend auto-calculates row totals in real-time |
| Color-coded columns | Frontend applies background colors based on day type |
| PDF autofill | PDF attendance report populates the attendance metadata (time in/out, late, OT eligible hours) but staff still allocates hours to projects manually |
| Signature flow | Maps to digital approval workflow: PREPARED=Submit, CHECKED=L1 Approve, APPROVED=L2 Approve |
| Print layout | Landscape A4, matches physical form exactly, default 5 project columns (expandable) |

---

## 3. User Roles & Permissions

### 3.1 Role Definitions

| Role | Description | Access Level |
|------|-------------|--------------|
| **Staff** | Regular employee | Submit timesheets & OT forms, view own records |
| **Admin** | System administrator | Manage project codes, users, roles, configurations, view all reports |
| **Assistant Manager** | First-level approver (Timesheet) | Approve/reject timesheets from direct reports |
| **Manager / HOD** | Second-level approver (Timesheet) + First-level OT approver | Approve/reject timesheets & OT forms |
| **CEO** | Final OT approver | Approve/reject OT forms |

### 3.2 Permission Matrix

| Action | Staff | Admin | Asst. Manager | Manager/HOD | CEO |
|--------|:-----:|:-----:|:--------------:|:-----------:|:---:|
| Submit Timesheet | ✅ | ❌ | ✅ | ✅ | ❌ |
| Submit OT Form | ✅ | ❌ | ✅ | ✅ | ❌ |
| Upload PDF | ✅ | ✅ | ✅ | ✅ | ❌ |
| Approve Timesheet (L1) | ❌ | ❌ | ✅ | ❌ | ❌ |
| Approve Timesheet (L2) | ❌ | ❌ | ❌ | ✅ | ❌ |
| Approve OT (L1) | ❌ | ❌ | ❌ | ✅ | ❌ |
| Approve OT (L2) | ❌ | ❌ | ❌ | ❌ | ✅ |
| Manage Project Codes | ❌ | ✅ | ❌ | ❌ | ❌ |
| Manage Users & Roles | ❌ | ✅ | ❌ | ❌ | ❌ |
| Configure Working Rules | ❌ | ✅ | ❌ | ❌ | ❌ |
| View All Reports | ❌ | ✅ | ❌ | ✅ | ✅ |
| View Own Records | ✅ | ✅ | ✅ | ✅ | ✅ |

### 3.3 Reporting Hierarchy

```
Staff
 └─► Assistant Manager  (Timesheet L1 Approval)
      └─► Manager / HOD (Timesheet L2 Approval, OT L1 Approval)
           └─► CEO      (OT L2 Approval)
```

Each staff member is assigned to a **department** and linked to their reporting chain via the `users` table (`reports_to` field).

---

## 4. Full System Workflow

### 4.1 Monthly Timesheet Submission — Step by Step

```
┌───────────────────────────────────────────────────────────────────┐
│                    TIMESHEET SUBMISSION FLOW                      │
│             (Project × Day Matrix Structure)                      │
└───────────────────────────────────────────────────────────────────┘

Step 1: Staff logs in
         │
Step 2: Staff navigates to "Timesheet" → selects Month/Year
         │
Step 3: System generates empty timesheet matrix
         ├── Creates day columns (1–31) with day-of-week labels
         ├── Marks Saturday/Sunday columns as Off Day (yellow)
         ├── Marks public holidays as Holiday (red/pink)
         └── Sets default available hours per day:
              ├── Mon–Thu: 8 hrs
              ├── Friday:  7 hrs
              └── Sat/Sun: 0 hrs (unless staff enters hours)
         │
Step 4: Staff uploads Infotech attendance PDF file
         │
Step 5: System parses PDF file
         ├── Extracts: Date, Time In, Time Out
         ├── Validates data completeness
         └── Reports parsing errors (if any)
         │
Step 6: System applies Business Rules (autofill per day column)
         ├── Detect missing punches → Mark column as MC/Leave (orange)
         ├── Calculate Late Hours (30-min blocks from 8:30 AM)
         ├── Calculate OT-eligible Hours (full hours after 5:30 PM)
         ├── Calculate Attendance Hours per day
         ├── Auto-fill admin rows:
         │    ├── Row 1 (MC/LEAVE): 8/7 hrs when no clock data
         │    ├── Row 2 (LATE): 0.5 increments based on time_in
         │    ├── Row 3-4: default 0.5 on working days
         │    └── Row 5-8: blank (staff fills manually)
         └── Store time_in / time_out / late / ot metadata per day
         │
Step 7: System displays the timesheet matrix form
         ├── Day metadata row (time_in, time_out, late, attendance)
         │    shown as read-only info bar at top of grid
         ├── Project rows: empty, ready for staff to add projects
         ├── Activity/overhead category rows: pre-populated (fixed)
         ├── Additional tracking rows: pre-populated (fixed)
         ├── Summary rows: auto-calculated
         └── Color-coded day columns applied
         │
Step 8: Staff fills in project hours (the main work)
         ├── Click "Add Project" → select Project Code (dropdown)
         │    → Project Name autofills from database
         ├── For each project row, enter NORMAL hours and OT hours
         │    per day column (OT hours must not exceed OT-eligible)
         ├── Edit admin rows (all use 0.5-hr dropdown increments):
         │    ├── Row 1: MC/LEAVE (auto-filled, editable)
         │    ├── Row 2: LATE (auto-filled, editable)
         │    ├── Row 3: MORNING ASSY / ADMIN JOB (default 0.5, editable)
         │    ├── Row 4: 5S (default 0.5, editable)
         │    ├── Row 5: CERAMAH AGAMA / EVENT / ADP (blank, editable)
         │    ├── Row 6: ISO (blank, editable)
         │    ├── Row 7: TRAINING / SEMINAR / VISIT (blank, editable)
         │    └── Row 8: RFQ/MKT/PUR/R&D/A.S.S/TDR (blank, editable)
         ├── TOTAL ADMIN JOB = sum(Row1..Row8) per day
         ├── TOTAL column auto-sums each row across all days
         ├── Summary rows auto-calculate:
         │    ├── TOTAL EXTERNAL PROJECT = sum(NormalNC + NormalCOBQ + OT_NC + OT_COBQ) per project
         │    ├── TOTAL WORKING HOURS = TOTAL ADMIN JOB + TOTAL EXTERNAL PROJECT
         │    ├── HOURS AVAILABLE: Mon-Thu=8, Fri=7, Sat-Sun=0, PH=0
         │    └── OVERTIME = TOTAL WORKING HOURS - HOURS AVAILABLE
         └── REMARKS row: free text per day
         │
Step 9: Staff clicks "Submit"
         │
Step 10: System validates
         ├── Every OT hour entry has a linked Project Code
         ├── Daily total hours do not exceed attendance hours + OT
         ├── No hours entered on MC/Leave days (unless overridden)
         ├── Required fields are complete
         └── Business rule compliance checked
         │
Step 11: Timesheet status → "Pending L1 Approval" (PREPARED)
         │
Step 12: Notification sent to Assistant Manager
         │
Step 13: Assistant Manager reviews & approves/rejects (CHECKED)
         ├── If Approved → Status = "Pending L2 Approval"
         │    └── Notification sent to Manager/HOD
         └── If Rejected → Status = "Rejected (L1)"
              └── Notification sent to Staff (with rejection reason)
              └── Staff can edit & resubmit (go to Step 8)
         │
Step 14: Manager/HOD reviews & approves/rejects (APPROVED)
         ├── If Approved → Status = "Approved"
         └── If Rejected → Status = "Rejected (L2)"
              └── Notification sent to Staff (with rejection reason)
              └── Staff can edit & resubmit (go to Step 8)
```

### 4.2 OT Form Submission — Step by Step

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
         ├── Planned Start Time (dropdown)
         ├── Planned End Time (dropdown)
         ├── Actual Start Time (dropdown)
         ├── Actual End Time (dropdown)
         ├── Tick Company Name / Logo
         └── Total OT Hours → auto-calculated
              └── Rule: Only count after 5:30 PM, full hours only
         │
Step 4: Staff clicks "Submit"
         │
Step 5: System validates
         ├── Project Name is selected
         ├── Times are valid
         ├── OT calculation is correct
         └── At least 1 full hour of OT after 5:30 PM
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

### 4.3 Admin Workflow

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

C. Activity Category Management
   1. Admin navigates to "Activity Categories"
   2. Admin can:
      ├── Create / Edit / Deactivate overhead categories
      │   (NORMAL COST, MARKETING, PURCHASING, R&D, CODB, TENDER, etc.)
      └── Categories appear as fixed rows on all timesheets

D. System Configuration
   1. Admin navigates to "Settings"
   2. Admin can configure:
      ├── Working Start Time (default: 8:30 AM)
      ├── OT Start Time (default: 5:30 PM)
      ├── Late Rounding Block (default: 30 minutes)
      ├── OT Rounding Block (default: 1 hour)
      ├── Normal Day Working Hours (default: 8.0)
      ├── Friday Working Hours (default: 7.5)
      └── Public holidays calendar

E. Reports
   1. Admin navigates to "Reports"
   2. Available reports:
      ├── Monthly Timesheet Summary (by department)
      ├── OT Summary Report (by department / project)
      ├── Late Report
      ├── Leave / MC Report
      └── Export to Excel / PDF
```

### 4.4 PDF Upload & Autofill Workflow (Detailed)

```
┌───────────────────────────────────────────────────────────────────┐
│                    PDF PROCESSING PIPELINE                         │
└───────────────────────────────────────────────────────────────────┘

  [User uploads .pdf attendance report]
         │
         ▼
  ┌─────────────────────┐
  │  1. VALIDATION       │
  │  - File type = .pdf  │
  │  - File size ≤ 5 MB  │
  │  - Is text-based PDF │
  └────────┬─────────────┘
           │ Pass
           ▼
  ┌──────────────────────────┐
  │  2. EXTRACT TEXT          │
  │  - Use PDF text parser    │
  │    (e.g. smalot/pdfparser │
  │     or spatie/pdf-to-text)│
  │  - Extract tabular data   │
  │    from each page         │
  └────────┬──────────────────┘
           │
           ▼
  ┌──────────────────────────────┐
  │  3. PARSE ROWS                │
  │  For each line/row:           │
  │  - Parse date (DD-MM-YYYY)    │
  │  - Parse Day name             │
  │  - Parse Time In (col "In")   │
  │  - Parse Time Out (col "Out") │
  │  - Parse "Reason" col (PH/RES/CAL) │
  │  - Flag missing values        │
  └────────┬──────────────────────┘
           │
           ▼
  ┌─────────────────────────────────────┐
  │  4. APPLY BUSINESS RULES (per day)   │
  │                                      │
  │  IF Reason = "PH":                   │
  │     → day_type = "public_holiday"    │
  │     → available_hours = 0            │
  │                                      │
  │  IF (day is Saturday or Sunday):     │
  │     → day_type = "off_day"           │
  │     → available_hours = 0            │
  │                                      │
  │  IF Reason = "CAL" or no clock data: │
  │     → day_type = "mc" or "leave"     │
  │     → mc_leave_hours = 8/7           │
  │                                      │
  │  IF (time_in > 08:30):              │
  │     → late_hours = ceil_to_half_hour │
  │                                      │
  │  attendance_hours = time_out - time_in│
  │     - 1 hour lunch break             │
  └────────┬─────────────────────────────┘
           │
           ▼
  ┌──────────────────────────────────┐
  │  5. POPULATE ADMIN ROWS           │
  │  - Row 1 (MC/LEAVE): 8/7 if MC   │
  │  - Row 2 (LATE): calculated       │
  │  - Row 3-4: default 0.5 workdays  │
  │  - Row 5-8: leave blank           │
  │  - Store day metadata             │
  │  - Highlight anomalies            │
  └───────────────────────────────────┘
```

---

## 5. Database Schema

### 5.1 Entity Relationship Diagram (Project × Day Matrix)

```
┌──────────────┐       ┌──────────────────┐       ┌──────────────────┐
│    users     │       │   departments    │       │  project_codes   │
├──────────────┤       ├──────────────────┤       ├──────────────────┤
│ id (PK)      │──┐    │ id (PK)          │       │ id (PK)          │
│ name         │  │    │ name             │       │ code (UNIQUE)    │
│ email        │  │    │ created_at       │       │ name             │
│ password     │  ├───▶│                  │       │ company_id (FK)  │
│ role         │  │    └──────────────────┘       │ is_active        │
│ department_id│──┘                                │ created_at       │
│ reports_to   │──┐                                └──────────────────┘
│ is_active    │  │                                        │
│ created_at   │  │    ┌──────────────────┐                │
└──────────────┘  └───▶│    users         │                │
       │               └──────────────────┘                │
       │                                                   │
       ▼                                                   │
┌──────────────────┐                                       │
│   timesheets     │                                       │
├──────────────────┤                                       │
│ id (PK)          │     ┌──────────────────────────────┐  │
│ user_id (FK)     │     │  timesheet_day_metadata      │  │
│ month            │     ├──────────────────────────────┤  │
│ year             │◄────│ timesheet_id (FK)            │  │
│ status           │     │ id (PK)                      │  │
│ current_level    │     │ entry_date                   │  │
│ submitted_at     │     │ day_of_week                  │  │
│ created_at       │     │ day_type (working/off/hol/mc)│  │
│ updated_at       │     │ time_in                      │  │
└──────────────────┘     │ time_out                     │  │
       │                 │ late_hours                    │  │
       │                 │ ot_eligible_hours             │  │
       │                 │ attendance_hours              │  │
       │                 │ available_hours (8 or 7.5)    │  │
       │                 │ morning_assy_hours            │  │
       │                 │ remarks                       │  │
       │                 └──────────────────────────────┘  │
       │                                                   │
       │  ┌─────────────────────────────────────┐          │
       ├─▶│  timesheet_project_rows             │          │
       │  ├─────────────────────────────────────┤          │
       │  │ id (PK)                             │          │
       │  │ timesheet_id (FK)                   │          │
       │  │ project_code_id (FK) ───────────────│──────────┘
       │  │ project_name (autofilled)           │
       │  │ row_order                           │
       │  └─────────────────────────────────────┘
       │         │
       │         ▼
       │  ┌─────────────────────────────────────┐
       │  │  timesheet_project_hours            │
       │  ├─────────────────────────────────────┤
       │  │ id (PK)                             │
       │  │ project_row_id (FK)                 │  ──► timesheet_project_rows
       │  │ entry_date                          │
       │  │ normal_hours  DECIMAL(4,1)          │
       │  │ ot_hours      INT                   │  (whole hours only)
       │  └─────────────────────────────────────┘
       │
       │  ┌─────────────────────────────────────┐
       ├─▶│  timesheet_activity_hours           │
       │  ├─────────────────────────────────────┤
       │  │ id (PK)                             │
       │  │ timesheet_id (FK)                   │
       │  │ activity_category_id (FK)           │  ──► activity_categories
       │  │ entry_date                          │
       │  │ hours  DECIMAL(4,1)                 │
       │  └─────────────────────────────────────┘
       │
       │  ┌─────────────────────────────────────┐
       └─▶│  timesheet_tracking_hours           │
          ├─────────────────────────────────────┤
          │ id (PK)                             │
          │ timesheet_id (FK)                   │
          │ tracking_type                       │  (morning_assy, five_s, ceramah,
          │ entry_date                          │   iso, training)
          │ hours  DECIMAL(4,1)                 │
          └─────────────────────────────────────┘

┌───────────────────────────┐
│  activity_categories      │  (admin-managed, appear as fixed rows)
├───────────────────────────┤
│ id (PK)                   │
│ name                      │  (NORMAL COST, MARKETING, PURCHASING, etc.)
│ display_order             │
│ is_active                 │
│ created_at                │
└───────────────────────────┘

┌──────────────────────┐
│      ot_forms        │
├──────────────────────┤      ┌───────────────────────┐
│ id (PK)              │◄─────│   ot_form_entries     │
│ user_id (FK)         │      ├───────────────────────┤
│ month                │      │ id (PK)               │
│ year                 │      │ ot_form_id (FK)       │
│ status               │      │ entry_date            │
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

┌───────────────────────┐      ┌───────────────────────┐
│   public_holidays     │      │  pdf_uploads         │
├───────────────────────┤      ├───────────────────────┤
│ id (PK)               │      │ id (PK)               │
│ holiday_date          │      │ user_id (FK)          │
│ name                  │      │ file_name             │
│ year                  │      │ file_path             │
│ created_at            │      │ month                 │
└───────────────────────┘      │ year                  │
                               │ file_type (pdf/xlsx)  │
                               │ status                │
                               │ error_message         │
                               │ uploaded_at           │
┌────────────────────────┐     │ uploaded_at           │
│  morning_assembly_log  │     └───────────────────────┘
├────────────────────────┤
│ id (PK)                │
│ user_id (FK)           │     ┌───────────────────────┐
│ log_date               │     │    notifications      │
│ attended (BOOLEAN)     │     ├───────────────────────┤
│ source                 │     │ id (PK)               │
│ synced_at              │     │ user_id (FK)          │
└────────────────────────┘     │ type                  │
                               │ title                 │
                               │ message               │
                               │ link                  │
                               │ is_read (BOOLEAN)     │
                               │ created_at            │
                               └───────────────────────┘
```

### 5.2 Table Definitions (SQL)

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
-- ACTIVITY CATEGORIES (admin-managed overhead rows on timesheet)
-- ============================================================

CREATE TABLE activity_categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,       -- e.g. NORMAL COST, MARKETING, PURCHASING
    display_order   INT DEFAULT 0,               -- controls row order on the form
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default activity categories (matching the physical form)
INSERT INTO activity_categories (name, display_order) VALUES
('NORMAL COST', 1),
('MARKETING', 2),
('PURCHASING', 3),
('RESEARCH & DEV', 4),
('CODB', 5),
('TENDER', 6),
('RFQ', 7),
('A.A.S', 8),
('REQUEST FOR QUOTATION', 9),
('INTERNAL SERVICE', 10);

-- ============================================================
-- TIMESHEETS (master record: one per staff per month)
-- ============================================================

CREATE TABLE timesheets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    month           TINYINT NOT NULL,           -- 1-12
    year            SMALLINT NOT NULL,
    status          ENUM('draft','pending_l1','pending_l2','approved','rejected_l1','rejected_l2') DEFAULT 'draft',
    current_level   TINYINT DEFAULT 0,          -- 0=draft, 1=L1(CHECKED), 2=L2(APPROVED)
    submitted_at    TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_month (user_id, month, year),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================================
-- TIMESHEET DAY METADATA (one row per day per timesheet)
-- Stores attendance data from Excel + computed business rules.
-- This is the "column header info" for each day in the matrix.
-- ============================================================

CREATE TABLE timesheet_day_metadata (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id        INT NOT NULL,
    entry_date          DATE NOT NULL,
    day_of_week         VARCHAR(3) NOT NULL,         -- MON, TUE, WED, THU, FRI, SAT, SUN
    day_type            ENUM('working','off_day','public_holiday','mc','leave') DEFAULT 'working',
    time_in             TIME NULL,                   -- from Excel
    time_out            TIME NULL,                   -- from Excel
    late_hours          DECIMAL(4,1) DEFAULT 0.0,    -- 30-min blocks (0.5 increments)
    ot_eligible_hours   INT DEFAULT 0,               -- whole hours, computed from time_out after 17:30
    attendance_hours    DECIMAL(4,1) DEFAULT 0.0,    -- time_out - time_in - lunch
    available_hours     DECIMAL(4,1) DEFAULT 8.0,    -- 8.0 normal, 7.0 Friday, 0 off day
    morning_assy_hours  DECIMAL(4,1) DEFAULT 0.0,    -- from Google Form integration
    remarks             TEXT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_day (timesheet_id, entry_date),
    FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE
);

-- ============================================================
-- TIMESHEET PROJECT ROWS (one row per project on the timesheet)
-- Staff can add multiple projects. Each project is a "row" on the form.
-- ============================================================

CREATE TABLE timesheet_project_rows (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id        INT NOT NULL,
    project_code_id     INT NOT NULL,
    project_name        VARCHAR(200),            -- autofilled from project_codes.name
    row_order           INT DEFAULT 0,           -- display order on form (1, 2, 3...)
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (timesheet_id)    REFERENCES timesheets(id) ON DELETE CASCADE,
    FOREIGN KEY (project_code_id) REFERENCES project_codes(id)
);

-- ============================================================
-- TIMESHEET PROJECT HOURS (hours per project per day)
-- Each cell in the project × day grid.
-- One record per project_row × date combination.
-- ============================================================

CREATE TABLE timesheet_project_hours (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    project_row_id      INT NOT NULL,
    entry_date          DATE NOT NULL,
    normal_hours        DECIMAL(4,1) DEFAULT 0.0,    -- normal working hours on this project
    ot_hours            INT DEFAULT 0,                -- OT hours on this project (whole hours only)
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_project_day (project_row_id, entry_date),
    FOREIGN KEY (project_row_id) REFERENCES timesheet_project_rows(id) ON DELETE CASCADE
);

-- ============================================================
-- TIMESHEET ACTIVITY HOURS (overhead category hours per day)
-- Each cell in the activity_category × day grid.
-- ============================================================

CREATE TABLE timesheet_activity_hours (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id            INT NOT NULL,
    activity_category_id    INT NOT NULL,
    entry_date              DATE NOT NULL,
    hours                   DECIMAL(4,1) DEFAULT 0.0,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_activity_day (timesheet_id, activity_category_id, entry_date),
    FOREIGN KEY (timesheet_id)         REFERENCES timesheets(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_category_id) REFERENCES activity_categories(id)
);

-- ============================================================
-- TIMESHEET TRACKING HOURS (additional tracking rows per day)
-- Fixed tracking types: morning_assy, five_s, ceramah, iso, training
-- ============================================================

CREATE TABLE timesheet_tracking_hours (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id        INT NOT NULL,
    tracking_type       ENUM('morning_assy','admin_job','five_s','ceramah_event','iso','training') NOT NULL,
    entry_date          DATE NOT NULL,
    hours               DECIMAL(4,1) DEFAULT 0.0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tracking_day (timesheet_id, tracking_type, entry_date),
    FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE
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
('ot_start_time', '17:30', 'OT counting starts after this time (HH:MM)'),
('late_rounding_minutes', '30', 'Late rounding block in minutes'),
('ot_rounding_hours', '1', 'OT rounding block in hours'),
('lunch_break_minutes', '60', 'Lunch break duration in minutes'),
('default_working_hours', '8', 'Standard working hours per day (Mon-Thu)'),
('friday_working_hours', '7', 'Working hours on Friday (excl. OT)');

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

CREATE TABLE pdf_uploads (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    file_name       VARCHAR(255) NOT NULL,
    file_path       VARCHAR(500) NOT NULL,
    month           TINYINT NOT NULL,
    year            SMALLINT NOT NULL,
    file_type       ENUM('pdf','xlsx','xls') DEFAULT 'pdf',
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

CREATE INDEX idx_timesheet_user       ON timesheets(user_id, month, year);
CREATE INDEX idx_timesheet_status     ON timesheets(status);
CREATE INDEX idx_day_meta_ts          ON timesheet_day_metadata(timesheet_id, entry_date);
CREATE INDEX idx_project_row_ts       ON timesheet_project_rows(timesheet_id);
CREATE INDEX idx_project_hours_row    ON timesheet_project_hours(project_row_id, entry_date);
CREATE INDEX idx_activity_hours_ts    ON timesheet_activity_hours(timesheet_id, entry_date);
CREATE INDEX idx_tracking_hours_ts    ON timesheet_tracking_hours(timesheet_id, entry_date);
CREATE INDEX idx_ot_form_user         ON ot_forms(user_id, month, year);
CREATE INDEX idx_ot_form_status       ON ot_forms(status);
CREATE INDEX idx_approval_entity      ON approval_logs(entity_type, entity_id);
CREATE INDEX idx_notification_user    ON notifications(user_id, is_read);
CREATE INDEX idx_assembly_date        ON morning_assembly_log(log_date);
```

### 5.3 How the Database Maps to the Physical Form

```
Physical Form                    Database Tables
─────────────                    ─────────────────
Header (name, month, etc.)   →   timesheets (master record)

Day columns (1-31)           →   timesheet_day_metadata (one row per day)
  - day of week                    .day_of_week
  - time in / time out             .time_in, .time_out (from Excel)
  - late hours                     .late_hours (computed)
  - OT eligible                    .ot_eligible_hours (computed)
  - attendance hours               .attendance_hours (computed)
  - available hours                .available_hours (8.0 or 7.0)
  - color coding                   .day_type (working/off/holiday/mc)

Project rows (1, 2, 3...)   →   timesheet_project_rows (one per project)
  - Project Code                   .project_code_id
  - Project Name                   .project_name (autofilled)
  - NORMAL hours per day           timesheet_project_hours.normal_hours
  - OT hours per day               timesheet_project_hours.ot_hours

Activity category rows       →   timesheet_activity_hours
  (NORMAL COST, MARKETING,        .activity_category_id (FK to activity_categories)
   PURCHASING, R&D, etc.)         .hours per day

Additional tracking rows     →   timesheet_tracking_hours
  (5S, Ceramah, ISO, etc.)        .tracking_type + .hours per day

TOTAL column                 →   Computed in frontend (SUM across days)
Summary rows                 →   Computed in frontend (SUM across rows per day)

Signatures                   →   timesheets.status + approval_logs
  PREPARED  = submitted            (status = pending_l1)
  CHECKED   = L1 approved          (approval_logs level=1, action=approved)
  APPROVED  = L2 approved          (approval_logs level=2, action=approved)
```

---

## 6. API Design

### 6.1 API Overview

**Base URL:** `/api/v1`  
**Authentication:** JWT Bearer Token (or PHP Session)  
**Content Type:** `application/json` (except file uploads: `multipart/form-data`)

### 6.2 Authentication Endpoints

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

### 6.3 Timesheet Endpoints

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/timesheets` | List user's timesheets | Staff, Approver |
| GET | `/timesheets/:id` | Get full timesheet (day metadata + project rows + activity + tracking) | Staff, Approver |
| POST | `/timesheets` | Create new timesheet (draft) for month/year | Staff |
| PUT | `/timesheets/:id` | Save project rows, activity hours, tracking hours (matrix payload) | Staff |
| POST | `/timesheets/:id/submit` | Submit for approval (validates, no body needed) | Staff |
| POST | `/timesheets/:id/upload-excel` | Upload & parse Excel → populates day_metadata | Staff |
| POST | `/timesheets/:id/add-project` | Add a new project row to the timesheet | Staff |
| DELETE | `/timesheets/:id/project-row/:rowId` | Remove a project row | Staff |
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
        "day_metadata": [
            {
                "date": "2025-12-01",
                "day_of_week": "MON",
                "day_type": "working",
                "time_in": "08:25:00",
                "time_out": "18:45:00",
                "late_hours": 0.0,
                "ot_eligible_hours": 1,
                "attendance_hours": 9.25,
                "available_hours": 8.0,
                "morning_assy_hours": 0.5
            },
            {
                "date": "2025-12-05",
                "day_of_week": "FRI",
                "day_type": "working",
                "time_in": "08:30:00",
                "time_out": "17:00:00",
                "late_hours": 0.0,
                "ot_eligible_hours": 0,
                "attendance_hours": 7.5,
                "available_hours": 7.5,
                "morning_assy_hours": 0.0
            }
        ]
    }
}
```

#### PUT `/timesheets/:id` (Save / Update)

**Request:** (project×day matrix payload)
```json
{
    "project_rows": [
        {
            "project_code_id": 5,
            "row_order": 1,
            "hours": [
                { "date": "2025-12-01", "normal_hours": 8.0, "ot_hours": 1 },
                { "date": "2025-12-02", "normal_hours": 4.0, "ot_hours": 0 }
            ]
        },
        {
            "project_code_id": 12,
            "row_order": 2,
            "hours": [
                { "date": "2025-12-02", "normal_hours": 4.0, "ot_hours": 0 }
            ]
        }
    ],
    "activity_hours": [
        { "activity_category_id": 1, "date": "2025-12-01", "hours": 2.0 },
        { "activity_category_id": 2, "date": "2025-12-03", "hours": 1.5 }
    ],
    "tracking_hours": [
        { "tracking_type": "five_s", "date": "2025-12-01", "hours": 0.5 },
        { "tracking_type": "ceramah_event", "date": "2025-12-04", "hours": 1.0 }
    ],
    "day_remarks": [
        { "date": "2025-12-01", "remarks": "Worked on deadline" }
    ]
}
```

#### POST `/timesheets/:id/submit`

**Request:**
```json
{}
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

### 6.4 OT Form Endpoints

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

### 6.5 Project Codes Endpoints (Admin)

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

### 6.6 Activity Categories Endpoints (Admin)

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/activity-categories` | List all active activity categories (ordered) | All |
| POST | `/activity-categories` | Create new category | Admin |
| PUT | `/activity-categories/:id` | Update category name/order | Admin |
| DELETE | `/activity-categories/:id` | Deactivate category | Admin |

### 6.7 Admin Endpoints

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

### 6.8 Reports Endpoints

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

### 6.9 Integration Endpoints

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| POST | `/integration/morning-assembly/sync` | Sync morning assembly from Google | System/Admin |
| GET | `/integration/morning-assembly/:userId/:date` | Get assembly status for user/date | Staff |

### 6.10 Notification Endpoints

| Method | Endpoint | Description | Roles |
|--------|----------|-------------|-------|
| GET | `/notifications` | Get user's notifications | All |
| PUT | `/notifications/:id/read` | Mark notification as read | All |
| PUT | `/notifications/read-all` | Mark all as read | All |

---

## 7. Frontend Page Structure

### 7.1 Page Map

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
│   ├── /admin/activity-categories  → Activity Category Management
│   ├── /admin/users                → User Management
│   ├── /admin/config               → System Configuration
│   ├── /admin/holidays             → Public Holiday Management
│   └── /admin/reports              → Reports & Export
│
├── /profile                        → User Profile & Password Change
└── /notifications                  → Notification Center
```

### 7.2 Page Descriptions

#### Login Page (`/login`)
- Email & password form
- Remember me checkbox
- Redirect to dashboard on success

#### Dashboard (`/dashboard`)
Role-based content:
- **Staff:** Quick links (New Timesheet, New OT Form), recent submissions, pending items
- **Approver:** Pending approvals count, recent approval activity
- **Admin:** System stats, quick links to management pages

#### Timesheet View/Edit (`/timesheet/:id`) — Matrix Layout

This is the core page, replicating the physical form as a scrollable matrix grid.

**Header Section:**
- Company logo, "DAILY TIME SHEET" title
- Staff name, Employee No, Department
- Month/Year selector, Status badge (Draft / Pending / Approved / Rejected)
- **PDF Upload Button:** Upload .pdf attendance report → triggers parse → populates day metadata + admin rows

**Day Column Headers (scrollable horizontally):**
- Row 1: Day numbers (1–31) + **TOTAL** column
- Row 2: Day-of-week labels (MON, TUE, WED, etc.)
- Color coding: **Yellow** = Off Day, **Red/Pink** = Holiday, **Green** = MC/Leave
- Row 3 (read-only info bar): Time In, Time Out, Late Hrs, OT Eligible, Attendance Hrs, Available Hrs
  - Autofilled from Excel parse; light blue background
  - Editable only if staff needs to override

**Project Section (dynamic rows):**
- **"Add Project" button** → opens dropdown to select Project Code → autofills Project Name
- For each added project:
  - Label: Project NO. (1, 2, 3...), PROJECT CODE, Project Name
  - NORMAL hours row: editable input cells per day column (decimal, e.g. 8.0, 4.5)
  - OT hours row: editable input cells per day column (whole hours only, constrained by OT eligible)
  - TOTAL column: auto-sums across all days
  - Remove project button (trash icon)

**Activity/Overhead Category Section (fixed rows):**
- One row per active `activity_category` (from admin config):
  - NORMAL COST, MARKETING, PURCHASING, R&D, CODB, TENDER, RFQ, A.A.S, etc.
- Each row has editable hour cells per day column
- TOTAL column: auto-sums

**Admin Job Section (8 fixed rows, all use 0.5-hr dropdown increments):**
- Row 1: MC / LEAVE (auto-filled on PDF upload, editable)
- Row 2: LATE (auto-filled on PDF upload, editable)
- Row 3: MORNING ASSY / ADMIN JOB (default 0.5 on working days, editable)
- Row 4: 5S (default 0.5 on working days, editable)
- Row 5: CERAMAH AGAMA / EVENT / ADP (blank, editable)
- Row 6: ISO (blank, editable)
- Row 7: TRAINING / SEMINAR / VISIT (blank, editable)
- Row 8: RFQ/MKT/PUR/R&D/A.S.S/TDR (blank, staff fills manually)
- **TOTAL ADMIN JOB** = sum(Row1..Row8) per day

**Summary Section (auto-calculated, read-only):**
- TOTAL EXTERNAL PROJECT per day = sum(Normal NC + Normal COBQ + OT NC + OT COBQ) per project
- TOTAL WORKING HOURS per day = TOTAL ADMIN JOB + TOTAL EXTERNAL PROJECT
- HOURS AVAILABLE per day: Mon-Thu=8, Fri=7, Sat-Sun=0, PH=0
- OVERTIME per day = TOTAL WORKING HOURS - HOURS AVAILABLE

**Remarks Row:** Free text input per day column

**Notes Section (bottom-left, read-only):**
- "NORMAL DAY (EXCLUDE OT): 8 HOURS"
- "FRIDAY ONLY (EXCLUDE OT): 7 HOURS"
- Summary totals

**Action Buttons:** Save Draft | Submit | Cancel | Print

#### Timesheet Print View (`/timesheet/:id/print`) — Landscape A4

Print-ready layout that matches the physical form exactly:
- **Orientation:** Landscape A4
- **Layout:** Matches physical "DAILY TIME SHEET" form
- **Default 5 project columns** (expandable if staff has more projects)
- **All admin rows (1-8)** displayed with same labels as physical form
- **Summary rows** at bottom: Total External Project, Total Working Hours, Hours Available, Overtime
- **Signatures section:** PREPARED / CHECKED / APPROVED with lines
- **Notes section:** Working hours rules
- **@media print CSS** ensures proper page breaks and margins
- Fits entirely on one landscape A4 page (maximizes space usage)

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

### 7.3 UI Component Library

| Component | Usage |
|-----------|-------|
| **DataTable** | Timesheet grid, OT entries, approval lists |
| **Dropdown/Select** | Project Code, month selector, day type |
| **DatePicker** | Date fields |
| **TimePicker** | Time In/Out, OT times |
| **FileUpload** | PDF upload with drag-and-drop |
| **StatusBadge** | Draft (grey), Pending (yellow), Approved (green), Rejected (red) |
| **Modal/Dialog** | Approval remarks, confirmation dialogs |
| **Toast/Notification** | Success/error messages |
| **Tabs** | Timesheet vs OT section within timesheet page |

---

## 8. PDF/Excel Parsing Logic

### 8.1 Supported Formats

- `.pdf` (Primary — Infotech "Individual Attendance Report With Actual Clock")
- `.xlsx` / `.xls` (Legacy fallback support)

### 8.2 Library Recommendations

| Stack | Library | Purpose |
|-------|---------|---------|
| PHP (PDF) | `smalot/pdfparser` or `spatie/pdf-to-text` | Extract text from PDF |
| PHP (Excel) | `maatwebsite/excel` + `phpoffice/phpspreadsheet` | Parse Excel fallback |

### 8.3 PDF Format (Infotech Attendance Report)

The PDF is a tabular report with:
- **Header:** Company name, report title, date printed, period (MM-YYYY to MM-YYYY)
- **Employee row:** Emp Code, Name, Designation
- **Column headers:** Date, Day, Clk1, Clk2, Clk3, Clk4, In, Out, Shift, Normal, Late, EarlyOut, Actual, OT columns, Reason
- **Data rows:** One row per day of the month
- **Reason column:** `RES` (rest day), `PH` (public holiday), `CAL` (calendar leave/MC)
- **Total row:** Aggregate totals at the bottom

### 8.4 Parsing Algorithm (Pseudocode)

```
FUNCTION parsePDF(file, targetMonth, targetYear):

    text = extractTextFromPDF(file)
    lines = splitIntoLines(text)

    // Step 1: Extract employee info
    empLine = findLineContaining("Emp Code")
    employeeCode = parseEmpCode(empLine)
    employeeName = parseName(empLine)

    // Step 2: Detect period
    periodLine = findLineContaining("Period")
    excelMonth, excelYear = parsePeriod(periodLine)
    IF excelMonth != targetMonth OR excelYear != targetYear:
        WARN "Period mismatch"

    // Step 3: Find header and parse data rows
    headerLine = findLineContaining("Date", "Day", "In", "Out")
    entries = []

    FOR each line AFTER headerLine:
        IF line contains "Total":
            BREAK

        tokens = splitByWhitespace(line)
        date   = parseDate(tokens[0])        // DD-MM-YYYY
        day    = tokens[1]                    // Mon, Tue, etc.
        timeIn = parseTime(tokens[timeInIdx]) // H.MM format (e.g. 7.46)
        timeOut= parseTime(tokens[timeOutIdx])// H.MM format
        reason = tokens[lastIdx]              // RES, PH, CAL, or blank

        IF date is NULL OR date.month != targetMonth:
            CONTINUE

        entry = { date, day, timeIn, timeOut, reason }
        entries.push(entry)

    // Step 4: Apply business rules
    config = getSystemConfig()
    WORK_START  = config.working_start_time      // 08:30
    NORMAL_HRS  = config.default_working_hours   // 8
    FRIDAY_HRS  = config.friday_working_hours    // 7
    LATE_BLOCK  = config.late_rounding_minutes   // 30

    FOR each entry IN entries:
        dayOfWeek = entry.date.getDayOfWeek()

        // --- Day Type Detection ---
        IF entry.reason == "PH":
            entry.dayType = "public_holiday"
            entry.availableHours = 0
        ELSE IF dayOfWeek == SATURDAY OR dayOfWeek == SUNDAY:
            entry.dayType = "off_day"
            entry.availableHours = 0
        ELSE IF entry.reason == "CAL" OR (timeIn == 0 AND timeOut == 0):
            entry.dayType = "mc"
            entry.availableHours = dayOfWeek == FRIDAY ? FRIDAY_HRS : NORMAL_HRS
        ELSE:
            entry.dayType = "working"
            entry.availableHours = dayOfWeek == FRIDAY ? FRIDAY_HRS : NORMAL_HRS

        // --- Late Calculation (0.5 increments) ---
        IF entry.timeIn != NULL AND entry.dayType == "working":
            IF entry.timeIn > WORK_START:
                lateMinutes = diffInMinutes(WORK_START, entry.timeIn)
                entry.lateHours = ceilToBlock(lateMinutes, LATE_BLOCK) / 60

        // --- Attendance Hours ---
        IF entry.timeIn != NULL AND entry.timeOut != NULL:
            totalMinutes = diffInMinutes(entry.timeIn, entry.timeOut)
            entry.attendanceHours = (totalMinutes - 60) / 60  // minus 1hr lunch

    // Step 5: Populate admin rows
    FOR each entry IN entries:
        IF entry.dayType == "mc":
            Row1_MC_LEAVE = entry.availableHours
        IF entry.lateHours > 0:
            Row2_LATE = entry.lateHours
        IF entry.dayType == "working" AND hasClockData:
            Row3_MORNING_ASSY = 0.5   // default
            Row4_5S = 0.5             // default
        Row5_to_Row8 = 0              // staff fills manually

    RETURN entries

// --- Helper: Ceiling to block ---
FUNCTION ceilToBlock(minutes, blockSize):
    RETURN ceil(minutes / blockSize) * blockSize
```

### 8.4 OT Calculation for OT Form (Pseudocode)

```
FUNCTION calculateOTFormHours(actualStartTime, actualEndTime):
    OT_START = "17:30"

    // OT only counts after 5:30 PM
    effectiveStart = MAX(actualStartTime, OT_START)

    IF effectiveStart >= actualEndTime:
        RETURN 0    // No valid OT

    otMinutes = diffInMinutes(effectiveStart, actualEndTime)
    RETURN floor(otMinutes / 60)   // whole hours only
```

### 8.5 Validation Rules

| Check | Rule | Error Message |
|-------|------|---------------|
| File type | Must be .xlsx or .xls | "Invalid file type. Please upload an Excel file." |
| File size | Max 5 MB | "File too large. Maximum 5 MB allowed." |
| Required columns | "Date", "Time In", "Time Out" headers must exist | "Missing required columns in Excel file." |
| Date range | Dates must match selected month/year | "Date {date} does not match selected month." |
| Time format | Valid time values (HH:MM) | "Invalid time format on row {n}." |
| Duplicate dates | No duplicate dates in same upload | "Duplicate entry for date {date}." |

---

## 9. Business Rules Engine

### 9.1 Rules Summary

| Rule | Logic | Output |
|------|-------|--------|
| **Late Detection** | If `time_in > 08:30` on a working day | `late_hours` in 0.5-hr blocks (ceiling) |
| **OT Detection** | If `time_out > 17:30` | `ot_eligible_hours` in whole hours (floor) |
| **MC/Leave** | If no `time_in` AND no `time_out` on working day | `day_type = mc/leave`, `available_hours = 0` |
| **Off Day** | If Saturday or Sunday | `day_type = off_day` (staff can still log work) |
| **Public Holiday** | If date is in `public_holidays` table | `day_type = public_holiday` |
| **Available Hours** | Mon–Thu: 8.0, Friday: 7.5, Off Day: 0 | `available_hours` per day column |
| **Attendance Hours** | `time_out - time_in - lunch_break` | Decimal hours |
| **Morning Assembly** | Pulled from Google Form integration | `morning_assy_hours` autofill |
| **OT ↔ Project** | Every OT entry must have a Project Code | Mandatory validation |
| **Project Name Autofill** | When Project Code is selected | `project_name` auto-populated from `project_codes.name` |

### 9.2 Late Calculation Examples

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

### 9.3 OT Calculation Examples

| Time Out | Minutes After 17:30 | OT Hours (floor) |
|----------|:--------------------:|:-----------------:|
| 17:00 | 0 | 0 |
| 17:30 | 0 | 0 |
| 17:45 | 15 | 0 |
| 18:29 | 59 | 0 |
| 18:30 | 60 | 1 |
| 18:45 | 75 | 1 |
| 19:30 | 120 | 2 |
| 19:35 | 125 | 2 |
| 20:30 | 180 | 3 |

---

## 10. Approval Workflow Logic

### 10.1 Timesheet Approval — State Machine

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

### 10.2 OT Form Approval — State Machine

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

### 10.3 Approval Logic (Backend Pseudocode)

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

## 11. Integration Architecture

### 11.1 Morning Assembly Integration (Google Form → System)

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

### 11.2 Scheduled Sync (Alternative/Backup)

If webhook is unreliable, run a CRON job / scheduled task:

```
Every day at 9:00 AM:
  1. Call Google Sheets API to read today's form responses
  2. Match staff emails to user accounts
  3. Upsert into morning_assembly_log
  4. Log sync status
```

---

## 12. Tech Stack & Infrastructure

### 12.1 Recommended Stack

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

### 12.2 Development Environment

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

### 12.3 Deployment Architecture (Production)

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

## 13. Security Considerations

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

## 14. Future Considerations

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
