# Timesheet & Overtime Management System — Design Document (v2)

> **Version:** 3.0  
> **Status:** Design Phase  
> **Last Updated:** 2026-04-09  
> **Scope:** Sections 1–6 (Overview → Database Schema / ERD)

---

## Table of Contents

1. [Overview](#1-overview)
2. [Timesheet Form Layout (Physical Form Analysis)](#2-timesheet-form-layout-physical-form-analysis)
3. [OT Form Layout (Physical Form Analysis)](#3-ot-form-layout-physical-form-analysis)
4. [User Roles & Permissions](#4-user-roles--permissions)
5. [System Workflow](#5-system-workflow)
6. [Database Schema (ERD)](#6-database-schema-erd)

---

## 1. Overview

### 1.1 Purpose

A web-based Timesheet & Overtime (OT) Management System for INGRESS TALENT SYNERGY SDN BHD.
Staff submit monthly timesheets that track admin/overhead hours and project hours across each
working day. Approvers review and approve. Project codes and staff list are synchronized from
**Desknet** via API (single source of truth). Admins manage system configuration and monitor sync.

### 1.2 Scope

- Monthly timesheet submission (two-table layout mirroring physical form)
- OT form submission (separate approval chain)
- PDF upload for attendance autofill (Time In / Time Out from Infotech PDF report)
- Morning assembly integration (Google Form → Apps Script → System)
- Project codes & staff list synced from **Desknet API** (not manually managed)
- Multi-level approval workflow

### 1.3 Key Design Principles

| # | Principle |
|---|-----------|
| 1 | Mirror the physical form exactly: **Upper Table** (Admin Job) + **Lower Table** (Project Hours) |
| 2 | Each project has 4 sub-rows: **NORMAL×NC**, **NORMAL×COBQ**, **OT×NC**, **OT×COBQ** |
| 3 | Minimize manual input — autofill from PDF upload and integrations |
| 4 | **Friday = 7 hours**, other weekdays = **8 hours** (exclude OT) |
| 5 | OT starts after **5:30 PM (17:30)**, counted in **whole hours only** (floor) |
| 6 | Late calculated in **30-minute blocks** (ceiling) |
| 7 | All totals are auto-calculated; staff only enter hours in cells |
| 8 | Submission deadline: 2nd working day, end of each month |
| 9 | **Desknet is the master** for project codes & staff list — system syncs via API, no manual CRUD |
| 10 | **Print-ready** — landscape A4 print layout matching the physical form exactly, default 5 project columns |

---

## 2. Timesheet Form Layout (Physical Form Analysis)

### 2.1 Overall Structure

The physical Daily Time Sheet form consists of:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  INGRESS TALENT SYNERGY SDN BHD                                             │
│  DAILY TIME SHEET                          PREPARED | CHECKED | APPROVED    │
│                                                                             │
│  MONTH: ___________    NAME: ________________________    EMP NO: _____      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─── UPPER TABLE: ADMIN JOB ──────────────────────────────────────────┐    │
│  │ NO │ ADMIN JOB                  │ Day 1 │ Day 2 │ ... │ Day 31 │ TOTAL │ │
│  │────┼────────────────────────────┼───────┼───────┼─────┼────────┼───────│ │
│  │  1 │ MC/LEAVE                   │       │       │     │        │       │ │
│  │  2 │ LATE                       │       │       │     │        │       │ │
│  │  3 │ MORNING ASSY / ADMIN JOB   │  0.5  │  0.5  │     │  0.5   │       │ │
│  │  4 │ 5S                         │  0.5  │  0.5  │     │  0.5   │       │ │
│  │  5 │ CERAMAH AGAMA / EVENT / ADP│       │       │     │        │       │ │
│  │  6 │ ISO                        │       │       │     │        │       │ │
│  │  7 │ TRAINING / SEMINAR / VISIT │       │       │     │        │       │ │
│  │  8 │ RFQ/MKT/PUR/R&D/A.S.S/TDR │   7   │   7   │     │   7    │       │ │
│  │────┼────────────────────────────┼───────┼───────┼─────┼────────┼───────│ │
│  │    │ TOTAL ADMIN JOB            │   8   │   8   │     │   8    │       │ │
│  └────┴────────────────────────────┴───────┴───────┴─────┴────────┴───────┘ │
│                                                                             │
│  ┌─── LOWER TABLE: PROJECT HOURS ──────────────────────────────────────┐    │
│  │ NO │ PROJECT CODE  │TIME/COST│ Day 1 │ Day 2 │ ... │ Day 31 │ TOTAL│    │
│  │────┼───────────────┼─────────┼───────┼───────┼─────┼────────┼──────│    │
│  │    │ TX/IT/11/023  │NORMAL│NC│       │       │     │        │      │    │
│  │  1 │               │      │CQ│       │       │     │        │      │    │
│  │    │ RFQ SIME DARBY│  OT  │NC│       │   2   │     │   4    │  6.0 │    │
│  │    │               │      │CQ│       │       │     │        │      │    │
│  │────┼───────────────┼─────────┼───────┼───────┼─────┼────────┼──────│    │
│  │    │               │NORMAL│NC│       │       │     │        │      │    │
│  │  2 │               │      │CQ│       │       │     │        │      │    │
│  │    │               │  OT  │NC│       │       │     │        │      │    │
│  │    │               │      │CQ│       │       │     │        │      │    │
│  │ ...│               │ ...    │       │       │     │        │      │    │
│  ├────┴───────────────┴─────────┼───────┼───────┼─────┼────────┼──────│    │
│  │ TOTAL EXTERNAL PROJECT       │   0   │   0   │     │   0    │  6.0 │    │
│  │ TOTAL WORKING HOURS          │   8   │   8   │     │   8    │      │    │
│  │ HOURS AVAILABLE (green)      │   8   │   8   │     │   7    │      │    │
│  │ OVERTIME                     │   0   │   0   │     │   0    │      │    │
│  └──────────────────────────────┴───────┴───────┴─────┴────────┴──────┘    │
│                                                                             │
│  NOTE:-                               LEGEND:                               │
│  NORMAL DAY (EXCLUDE OT): 8 HOURS    NC  = NORMAL COST                     │
│  FRIDAY ONLY (EXCLUDE OT): 7 HOURS   MKT = MARKETING                       │
│                                       PUR = PURCHASING                      │
│  PREPARED:  _________ (Staff)         R&D = RESEARCH & DEV                  │
│  CHECKED:   _________ (Asst Mgr)     COBQ = COST OF BAD QUALITY            │
│  APPROVED:  _________ (Mgr/HOD)      TDR = TENDER                          │
│                                       RFQ = REQUEST FOR QUOTATION           │
│  REMARKS:                             A.S.S = AFTER SALE SERVICE            │
│  SUBMIT TO FINANCE ON 2ND WORKING                                          │
│  DAYS END OF EACH MONTH                                                    │
│                                       PLEASE MAKE SURE TOTAL OF ALL EACH   │
│                                       OF THE ITEM                           │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Form Header

| Field | Description |
|-------|-------------|
| Company | INGRESS TALENT SYNERGY SDN BHD (with logo) |
| Title | DAILY TIME SHEET |
| MONTH | Month and Year (e.g., DECEMBER 2025) |
| NAME | Staff full name |
| EMP NO | Employee number (e.g., T107) |
| Signatures | PREPARED (Staff) · CHECKED (Asst Mgr) · APPROVED (Mgr/HOD) |

### 2.3 Day Columns

Each day of the month (1–31) is a column. The column header shows:
- **Row 1:** Day number (1, 2, 3, ... 31)
- **Row 2:** Day of week (MON, TUE, WED, THU, FRI, SAT, SUN)

The last column is **TOTAL** (sum across all days for that row).

### 2.4 Upper Table: Admin Job (8 Fixed Rows)

| Row # | Label | Description | Input Type | Typical Value |
|:-----:|-------|-------------|------------|:-------------:|
| 1 | MC/LEAVE | Medical certificate or leave day | Auto/Manual | 8 or 7 (full day) or 0 |
| 2 | LATE | Late arrival hours | Auto (from PDF) | 0.5 increments |
| 3 | MORNING ASSY / ADMIN JOB | Morning assembly + admin overhead | Auto/Manual | 0.5 |
| 4 | 5S | 5S activity hours | Dropdown (0.5 increments) | 0.5 |
| 5 | CERAMAH AGAMA / EVENT / ADP | Religious talk / events | Dropdown (0.5 increments) | 0 |
| 6 | ISO | ISO-related work | Dropdown (0.5 increments) | 0 |
| 7 | TRAINING / SEMINAR / VISIT | Training activities | Dropdown (0.5 increments) | 0 |
| 8 | RFQ/MKT/PUR/R&D/A.S.S/TDR | Catch-all admin/overhead category | Manual | Remaining hours |
| — | **TOTAL ADMIN JOB** | Sum of rows 1–8 per day | **Auto-calculated** | 8 (Mon-Thu) or 7 (Fri) |

**Auto-fill logic on PDF upload:**
- **Row 1 (MC/LEAVE):** 8 (Mon-Thu) or 7 (Fri) when no time_in/time_out on a weekday (MC/leave day)
- **Row 2 (LATE):** Late hours from time_in, calculated in 0.5 increments after 08:30
- **Row 3-4:** Default 0.5 on working days (Mon-Fri, excluding weekends and public holidays)
- **Row 5-7:** Default blank (0), staff fills manually if applicable
- **Row 8:** Staff fills manually (remaining admin hours as needed)
- All rows use **0.5 increment dropdowns**. Staff can edit all auto-filled values.
- If TOTAL ADMIN JOB = 0 for a day, display '0'.

### 2.5 Lower Table: Project Hours (Dynamic Rows)

Each project occupies **4 sub-rows**, organized as a 2×2 matrix:

| TIME type | COST type | Description |
|-----------|-----------|-------------|
| **NORMAL** | **NC** | Normal working hours — Normal Cost |
| **NORMAL** | **COBQ** | Normal working hours — Cost of Bad Quality |
| **OT** | **NC** | Overtime hours — Normal Cost |
| **OT** | **COBQ** | Overtime hours — Cost of Bad Quality |

**Per project block:**
```
┌────┬────────────────┬─────────┬───────┬───────┬───────┬───────┐
│ NO │ PROJECT CODE   │TIME│COST│ Day 1 │ Day 2 │  ...  │ TOTAL │
│    │ Project Name   │    │    │       │       │       │       │
├────┼────────────────┼────┼────┼───────┼───────┼───────┼───────┤
│    │                │NORM│ NC │  hrs  │  hrs  │  ...  │  sum  │
│  1 │ TX/IT/11/023   │    │COBQ│  hrs  │  hrs  │  ...  │  sum  │
│    │ RFQ SIME DARBY │ OT │ NC │  hrs  │  hrs  │  ...  │  sum  │
│    │                │    │COBQ│  hrs  │  hrs  │  ...  │  sum  │
└────┴────────────────┴────┴────┴───────┴───────┴───────┴───────┘
```

Staff can add **multiple projects** (1, 2, 3, ...). The physical form has ~5–6 pre-printed slots;
the digital system allows unlimited dynamic rows.

### 2.6 Summary Rows (Below Lower Table)

| Row | Formula | Description |
|-----|---------|-------------|
| **TOTAL EXTERNAL PROJECT** | Sum of all project sub-rows (normal_nc + normal_cobq + ot_nc + ot_cobq) per day | All project hours for that day |
| **TOTAL WORKING HOURS** | TOTAL ADMIN JOB + TOTAL EXTERNAL PROJECT | Combined admin + project hours |
| **HOURS AVAILABLE** | Mon–Thu: **8**, Fri: **7**, Sat/Sun: **0**, Public Holiday: **0** | From system config |
| **OVERTIME** | TOTAL WORKING HOURS − HOURS AVAILABLE | Positive = overtime worked; 0 if no excess |


### 2.7 Color Coding

| Color | Meaning | Applied To |
|-------|---------|------------|
| **Yellow** | Off Day (Saturday / Sunday) | Entire column for that day |
| **Red** | Public Holiday | Entire column for that day |
| **White** | Normal working day | Default |
| **Green** | Total Working Hours row highlight | Total Working Hours summary row only |
| **Light Blue** | Total External project row highlight |
| **LIght Blue** | Overtime row highlight |

### 2.8 Legend (Abbreviations)

| Code | Meaning |
|------|---------|
| **NC** | Normal Cost |
| **MKT** | Marketing |
| **PUR** | Purchasing |
| **R & D** | Research & Development |
| **COBQ** | Cost of Bad Quality |
| **TDR** | Tender |
| **RFQ** | Request for Quotation |
| **A.S.S** | After Sale Service |

### 2.9 Key Rules Printed on Form

| Rule | Value |
|------|-------|
| Normal Day working hours (exclude OT) | **8 hours** |
| Friday working hours (exclude OT) | **7 hours** |
| OT start time | **5:30 PM (17:30)** |
| OT counting | **Whole hours only** (floor) |
| Submission deadline | **2nd working day, end of each month** |

### 2.10 Signatures (Approval Mapping)

| Signature | Role | Maps to System Status |
|-----------|------|----------------------|
| **PREPARED** | Staff (submitter) | `status = pending_l1` |
| **CHECKED** | Assistant Manager | `approval_logs` level 1 approved |
| **APPROVED** | Manager / HOD | `approval_logs` level 2 approved |

---

## 3. OT Form Layout (Physical Form Analysis)

There are **two different OT form formats** depending on employee type:
- **Executive** — English, dynamic rows (only OT days listed)
- **Non-Executive** — Bahasa Melayu, 31 fixed rows (one per day of month), additional OT type & calculation columns

Both share the same two-phase approval concept but differ in layout, language, columns, and approval levels.

### 3.1 Form Type: Executive — OVERTIME CLAIM FORM (EXECUTIVE) ~ OCF

#### 3.1.1 Overall Structure

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                   INGRESS GROUP OF COMPANIES                                        │
│                                                                                     │
│  TITLE: OVERTIME CLAIM FORM (EXECUTIVE) ~ OCF               SERIAL NO: _________   │
│                                                                                     │
│  Company (tick one):                                                                │
│  □ INGRESS CORPORATION  □ INGRESS ENGINEERING  □ INGRESS PRECISION                  │
│  ☑ TALENT SYNERGY       □ ___________                                               │
│                                                                                     │
│  NAME: ______________________________    DEPARTMENT: __________   MONTH: _________  │
│  STAFF NO: _______                       SECTION/LINE: ________                     │
│                                                                                     │
├─────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                     │
│       ┌──── (A) ─────────────────┐  ┌── (B) ──────────┐  ┌──── Actual ────────────┤
│       │         PLAN             │  │ APPROVAL BEFORE  │  │                        │
│       │                          │  │    OVERTIME       │  │                        │
│  DATE │ PARTICULARS │START│ END  │TOTAL│EXEC│HOD│DGM/ │START│ END │TOTAL│           │
│       │             │     │      │HOURS│    │   │ CEO │     │     │HOURS│           │
│  ─────┼─────────────┼─────┼──────┼─────┼────┼───┼─────┼─────┼─────┼─────┤           │
│  14/3 │D01D Spot... │ 8.00│15.30 │ 7.50│ ✓  │ ✓ │  ✓  │ 7.52│17.01│ 9.00│          │
│  ─────┴─────────────┴─────┴──────┴─────┴────┴───┴─────┴─────┴─────┴─────┤           │
│                                                                          │          │
│  ┌──── (C) TOTAL HOURS ─────────────────────────────────────┐           │           │
│  │  NORMAL DAY  │  REST DAY  │  PUBLIC HOLIDAY              │           │           │
│  └──────────────┴────────────┴──────────────────────────────┘           │           │
│                                                                                     │
│  ┌──── (D) APPROVAL AFTER OVERTIME ────┐                                            │
│  │                                     │                                            │
│  │  TOTAL (HOURS): 23.00 (plan)        │   TOTAL (HOURS): 24.50 (actual)            │
│  │                                     │                                            │
│  │  Claimed by: _________ (Executive)  │                                            │
│  │  Approved by: _________ (HOD)       │                                            │
│  └─────────────────────────────────────┘                                            │
│                                                                                     │
│  NOTE:                                                                              │
│  1) Overtime submission should be presented to HOD/DGM/MD before 4.30 pm            │
│     for approval.                                                                   │
│  2) OT claim shall be submitted to Payroll Section every 05th of the month          │
│     and the maximum claim shall not exceed RM 500.00 per month.                     │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

#### 3.1.2 Executive Form Header

| Field | Description |
|-------|-------------|
| Company | INGRESS GROUP OF COMPANIES |
| Title | OVERTIME CLAIM FORM (EXECUTIVE) ~ OCF |
| Serial No | Auto-generated serial number |
| Company Selection | Tick one: INGRESS CORPORATION / ENGINEERING / PRECISION / TALENT SYNERGY (text label, UI-only — stored as `company_name` VARCHAR) |
| NAME | Staff full name |
| STAFF NO | Employee number (e.g., T094) |
| DEPARTMENT | Staff department (e.g., OPERATION) |
| MONTH | Month and Year of OT claim (e.g., MARCH 2026) |
| SECTION/LINE | Optional section/line info |

#### 3.1.3 Executive Sections

**Section (A): Plan** — Staff fills **before** doing OT work. Dynamic rows (only list days with OT).

| Column | Description | Input Type |
|--------|-------------|------------|
| **DATE** | Date of planned overtime (DD/MM/YYYY) | Date picker |
| **PARTICULARS** | Project name / description of OT work | Dropdown (from project_codes) |
| **PLAN START** | Planned OT start time | Time picker |
| **PLAN END** | Planned OT end time | Time picker |
| **TOTAL HOURS** | Planned End − Planned Start | Auto-calculated |

**Section (B): Approval Before Overtime** — Three signature columns. All must sign before OT.

| Column | Role | Description |
|--------|------|-------------|
| **EXEC.** | Executive (Staff) | Staff signs to confirm the OT plan |
| **HOD** | Head of Department | HOD approves the planned OT |
| **DGM/CEO/MD** | Deputy GM / CEO / MD | Final pre-approval |

**Actual columns** — Staff fills **after** completing OT work.

| Column | Description | Input Type |
|--------|-------------|------------|
| **ACTUAL START** | Actual OT start time | Time picker |
| **ACTUAL END** | Actual OT end time | Time picker |
| **TOTAL HOURS** | Actual End − Actual Start (precise) | Auto-calculated |

**Section (C): Total Hours Breakdown** — Categorized by day type:

| Sub-column | Description |
|------------|-------------|
| **NORMAL DAY** | OT hours worked on a normal working day (Mon–Fri) |
| **REST DAY** | OT hours worked on a rest day (Saturday / Sunday) |
| **PUBLIC HOLIDAY** | OT hours worked on a public holiday |

**Section (D): Approval After Overtime** — Signed after actual OT is completed.

| Field | Role | Description |
|-------|------|-------------|
| **Claimed by** | Executive (Staff) | Staff confirms actual hours worked |
| **Approved by** | HOD | HOD verifies and approves actual OT claim |

**Footer totals:**

| Total | Description |
|-------|-------------|
| TOTAL (HOURS) — Plan | Sum of all planned total hours |
| TOTAL (HOURS) — Actual | Sum of all actual total hours |

---

### 3.2 Form Type: Non-Executive — BORANG KERJA LEBIH MASA (BUKAN EKSEKUTIF)

#### 3.2.1 Overall Structure

```
┌────────────────────────────────────────────────────────────────────────────────────────────────┐
│                    KUMPULAN SYARIKAT INGRESS                                                    │
│                                                                                                │
│  TAJUK: BORANG KERJA LEBIH MASA (BUKAN EKSEKUTIF)                                             │
│                                                                                                │
│  Company (tick one):                                                                           │
│  □ INGRESS CORPORATION  □ INGRESS ENGINEERING  □ INGRESS PRECISION                             │
│  ☑ TALENT SYNERGY       □ ___________                                                          │
│                                                                                                │
│  NAMA: ______________________________    JABATAN: __________    BULAN: _________               │
│  NO. KT: _______   JAWATAN: _________   SEKSYEN/BAH.: ________                                │
│                                                                                                │
├──────┬──────────────┬─────────────────┬─────────────────┬─────┬──────┬─────┬─────────┬─────────┤
│      │              │ MASA DIRANCANG  │  MASA SEBENAR   │     │LEBIH │     │KELULUSAN│ JENIS OT│
│TARIKH│ TUGAS ATAU   ├─────┬─────┬─────┼─────┬─────┬─────┤MAKAN│ 0-3  │SHIFT├────┬────┤         │
│      │  AKTIVITI    │MULA │TAMAT│JMLH │MULA │TAMAT│JMLH │     │ JAM  │     │KAKI│HOD │DGM/     │
│      │              │     │     │     │     │     │     │     │      │     │TANGAN│   │CEO/MD    │
├──────┼──────────────┼─────┼─────┼─────┼─────┼─────┼─────┼─────┼──────┼─────┼────┼────┼─────────┤
│  1   │TX/OT/01/026..│ 8.00│15.30│ 7.50│ 8.08│15.41│ 7.5 │     │      │ /   │ ✓  │ ✓ │         │
│  2   │              │     │     │     │     │     │     │     │      │     │    │   │         │
│ ...  │              │     │     │     │     │     │     │     │      │     │    │   │         │
│  31  │              │     │     │     │     │     │     │     │      │     │    │   │         │
├──────┴──────────────┴─────┴─────┴─────┴─────┴─────┴─────┴─────┴──────┴─────┴────┴────┴─────────┤
│                                                                                                │
│  CONTINUED: JENIS OT + PENGIRAAN OT columns                                                   │
│  ┌──────────────────────────────────────────────────────────────────────────┐                   │
│  │ JENIS OT          │              PENGIRAAN OT                           │                   │
│  │ NORMAL │TRAINING│KAIZEN│ SS │  OT 1  │  OT 2  │  OT 3  │  OT 4 │ OT 5 │                   │
│  └────────┴────────┴──────┴────┴────────┴────────┴────────┴───────┴───────┘                   │
│                                                                                                │
│  NOTA:                                                                                         │
│  JUMLAH (Plan): 37.5          JUMLAH (Actual): 37.5                                           │
│                                                                                                │
│  DISEDIAKAN OLEH: _________ (STAFF)                                                           │
│  DISAHKAN OLEH:   _________ (MGR/HOD)                JUMLAH JAM OT: [____]                    │
│  DILULUSKAN OLEH: _________ (DGM/CEO)                                                         │
│                                                                                                │
│  1) Borang OT mesti sampai ke Jabatan Sumber Manusia (Unit Payroll)                           │
│     selewat-lewatnya pada atau sebelum 5hb. setiap bulan (bulan berikutnya).                  │
└────────────────────────────────────────────────────────────────────────────────────────────────┘
```

#### 3.2.2 Non-Executive Form Header (Bahasa Melayu)

| Field (BM) | Field (EN) | Description |
|------------|------------|-------------|
| TAJUK | Title | BORANG KERJA LEBIH MASA (BUKAN EKSEKUTIF) |
| Company | Company | Same tick-one selection as Executive form |
| NAMA | Name | Staff full name |
| NO. KT | Staff No | Employee number (e.g., T005) |
| JAWATAN | Position | Staff position (e.g., SUPERVISOR) |
| JABATAN | Department | Staff department (e.g., OPERATION) |
| BULAN | Month | Month and Year of OT claim (e.g., MARCH 2026) |
| SEKSYEN/BAH. | Section/Line | Section or department subdivision (e.g., MACHINIST) |

#### 3.2.3 Non-Executive Columns (Bahasa Melayu)

**Key difference:** Non-executive form has **31 fixed rows** (one per day of the month), not dynamic rows.

| Column (BM) | Column (EN) | Description | Input Type |
|-------------|-------------|-------------|------------|
| **TARIKH** | Date | Day number 1–31 (pre-filled) | Fixed |
| **TUGAS ATAU AKTIVITI** | Tasks/Activities | Project name / description of OT work | Dropdown (from project_codes) |
| **MASA DIRANCANG — MULA** | Plan Start | Planned OT start time | Time picker |
| **MASA DIRANCANG — TAMAT** | Plan End | Planned OT end time | Time picker |
| **MASA DIRANCANG — JUMLAH** | Plan Total | Plan End − Plan Start | Auto-calculated |
| **MASA SEBENAR — MULA** | Actual Start | Actual OT start time | Time picker |
| **MASA SEBENAR — TAMAT** | Actual End | Actual OT end time | Time picker |
| **MASA SEBENAR — JUMLAH** | Actual Total | Actual End − Actual Start | Auto-calculated |
| **MAKAN** | Meal | Meal break deduction (if applicable) | Dropdown / Checkbox |
| **LEBIH 0-3 JAM** | Over 0-3 Hours | Flag if OT exceeds 3 hours (meal entitlement) | Auto / Checkbox |
| **SHIFT** | Shift | Shift indicator (tick if shift work) | Checkbox |

**Approval columns (KELULUSAN):**

| Column (BM) | Column (EN) | Role | Description |
|-------------|-------------|------|-------------|
| **KAKITANGAN/EXEC./ ASST. MGR** | Section/Asst. Mgr | L1 Pre-Approval | Asst. Mgr approves per-row |
| **HOD** | HOD | L2 Pre-Approval | HOD approves per-row |
| **DGM/CEO/MD** | DGM/CEO/MD | L3 Pre-Approval | Final pre-approval per-row |

**OT Type columns (JENIS OT):**

| Column (BM) | Column (EN) | Description |
|-------------|-------------|-------------|
| **NORMAL** | Normal | Regular overtime work |
| **TRAINING** | Training | OT for training activities |
| **KAIZEN** | Kaizen | OT for kaizen/improvement activities |
| **SS** | SS | OT for suggestion system activities |

**OT Calculation columns (PENGIRAAN OT):**

| Column (BM) | Column (EN) | Description |
|-------------|-------------|-------------|
| **OT 1** | OT Rate 1 | OT hours at rate 1 (e.g., 1.5x — normal day, first hours) |
| **OT 2** | OT Rate 2 | OT hours at rate 2 (e.g., 2.0x — normal day, excess hours) |
| **OT 3** | OT Rate 3 | OT hours at rate 3 (e.g., rest day rate) |
| **OT 4** | OT Rate 4 | OT hours at rate 4 (e.g., public holiday rate) |
| **OT 5** | OT Rate 5 | OT hours at rate 5 (e.g., special rate) |

#### 3.2.4 Non-Executive Footer (Bahasa Melayu)

| Field (BM) | Field (EN) | Role | Description |
|------------|------------|------|-------------|
| **JUMLAH** | Total | — | Sum of planned and actual total hours |
| **DISEDIAKAN OLEH** | Prepared by | STAFF | Staff confirms and signs |
| **DISAHKAN OLEH** | Verified by | MGR/HOD | Manager/HOD verifies and signs |
| **DILULUSKAN OLEH** | Approved by | DGM/CEO | DGM/CEO final approval |
| **JUMLAH JAM OT** | Total OT Hours | — | Final total OT hours for payroll |

---

### 3.3 Key Differences: Executive vs Non-Executive

| Aspect | Executive (OCF) | Non-Executive (BKLM) |
|--------|----------------|---------------------|
| **Language** | English | Bahasa Melayu |
| **Title** | OVERTIME CLAIM FORM (EXECUTIVE) ~ OCF | BORANG KERJA LEBIH MASA (BUKAN EKSEKUTIF) |
| **Row Layout** | Dynamic rows (only days with OT) | Fixed 31 rows (one per day of month) |
| **Plan Columns** | DATE, PARTICULARS, START, END, TOTAL | TARIKH (1-31), TUGAS, MULA, TAMAT, JUMLAH |
| **Actual Columns** | START, END, TOTAL | MULA, TAMAT, JUMLAH |
| **Extra Columns** | None | MAKAN (meal), LEBIH 0-3 JAM, SHIFT |
| **Pre-Approval** | EXEC (Staff), HOD, DGM/CEO | KAKITANGAN/EXEC./ASST. MGR, HOD, DGM/CEO |
| **OT Types** | None (single category) | NORMAL, TRAINING, KAIZEN, SS |
| **OT Calculation** | NORMAL DAY, REST DAY, PUBLIC HOLIDAY | OT 1, OT 2, OT 3, OT 4, OT 5 |
| **Post-Approval** | Claimed by (Executive), Approved by (HOD) | DISEDIAKAN OLEH (Staff), DISAHKAN OLEH (MGR/HOD), DILULUSKAN OLEH (DGM/CEO) |
| **Post-Approval Levels** | 2 levels (Staff + HOD) | 3 levels (Staff + MGR/HOD + DGM/CEO) |

### 3.4 Two-Phase Approval Flow (Both Forms)

Both forms share the same two-phase concept but differ in approval levels:

```
═══════════════════════════════════════════════════════════════════════════════
  EXECUTIVE                                    NON-EXECUTIVE
═══════════════════════════════════════════════════════════════════════════════

PHASE 1: PRE-APPROVAL (Before OT)
──────────────────────────────────
Staff fills Plan (A)                           Staff fills Plan (31 rows)
    │                                               │
    ▼                                               ▼
Staff signs as EXEC (B)                        (No separate staff sign step)
    │                                               │
    ▼                                               ▼
HOD approves plan (B)                          BAHAGIAN/Asst. Mgr approves (per-row)
    │                                               │
    ▼                                               ▼
DGM/CEO approves plan (B)                     HOD approves (per-row)
                                                    │
                                                    ▼
                                               DGM/CEO approves (per-row)

Status: "Pre-Approved"                        Status: "Pre-Approved"
→ Staff may proceed with OT                   → Staff may proceed with OT

PHASE 2: POST-APPROVAL (After OT)
──────────────────────────────────
Staff fills Actual times                      Staff fills Actual times (31 rows)
    │                                               │
    ▼                                               ▼
Staff claims as Executive (D)                 DISEDIAKAN OLEH (Staff)
    │                                               │
    ▼                                               ▼
HOD approves actual (D)                       DISAHKAN OLEH (MGR/HOD)
                                                    │
                                                    ▼
                                               DILULUSKAN OLEH (DGM/CEO)

Status: "Approved"                            Status: "Approved"
→ Submitted to Payroll                        → Submitted to Payroll
```

### 3.5 Key Rules (Both Forms)

| Rule | Value |
|------|-------|
| OT submission to HOD/DGM/MD | Before **4:30 PM** for approval |
| OT claim submission to Payroll | Every **5th of the month** |
| Maximum OT claim per month | **RM 500.00** |
| OT hours calculation | Actual End − Actual Start (precise, not floored) |

### 3.6 Signatures (Approval Mapping)

**Executive:**

| Phase | Signature | Role | Maps to System |
|-------|-----------|------|----------------|
| **(B) Before OT** | EXEC. | Staff | `ot_status = pending_hod_pre` |
| **(B) Before OT** | HOD | Head of Dept | `approval_logs` level 1 pre-approved |
| **(B) Before OT** | DGM/CEO | Deputy GM / CEO | `approval_logs` level 2 pre-approved → `ot_status = pre_approved` |
| **(D) After OT** | Claimed by | Staff (Executive) | `ot_status = pending_hod_post` |
| **(D) After OT** | Approved by | HOD | `approval_logs` level 1 post-approved → `ot_status = approved` |

**Non-Executive:**

| Phase | Signature (BM) | Role | Maps to System |
|-------|----------------|------|----------------|
| **Pre-Approval** | BAHAGIAN/PENC. ASST. MGR | Asst. Mgr | `approval_logs` level 1 pre-approved |
| **Pre-Approval** | HOD | Head of Dept | `approval_logs` level 2 pre-approved |
| **Pre-Approval** | DGM/CEO/MD | DGM/CEO | `approval_logs` level 3 pre-approved → `ot_status = pre_approved` |
| **Post-Approval** | DISEDIAKAN OLEH | Staff | `ot_status = pending_mgr_post` |
| **Post-Approval** | DISAHKAN OLEH | MGR/HOD | `approval_logs` level 1 post-approved |
| **Post-Approval** | DILULUSKAN OLEH | DGM/CEO | `approval_logs` level 2 post-approved → `ot_status = approved` |

### 3.7 Example Data

**Executive Example:**

| DATE | PARTICULARS | PLAN START | PLAN END | PLAN TOTAL | ACTUAL START | ACTUAL END | ACTUAL TOTAL |
|------|-------------|:----------:|:--------:|:----------:|:------------:|:----------:|:------------:|
| 14/3/2026 | D01D Spot Welding Line TX/IT/07/012 | 8.00 | 15.30 | 7.50 | 7.52 | 17.01 | 9.00 |
| 15/3/2026 | D01D Spot Welding Line TX/IT/07/012 | 8.00 | 15.30 | 7.50 | 7.56 | 17.46 | 9.50 |
| **TOTALS** | | | | **23.00** | | | **24.50** |

**Non-Executive Example:**

| TARIKH | TUGAS | PLAN MULA | PLAN TAMAT | PLAN JUMLAH | ACTUAL MULA | ACTUAL TAMAT | ACTUAL JUMLAH |
|--------|-------|:---------:|:----------:|:-----------:|:-----------:|:------------:|:-------------:|
| 1 | TX/OT/01/026 Heavy Duty Roller Conveyer | 8.00 | 15.30 | 7.50 | 8.08 | 15.41 | 7.5 |
| 7 | TX/OT/01/026 Heavy Duty Roller Conveyer | 8.00 | 15.30 | 7.50 | 8.01 | 15.42 | 7.5 |
| 8 | TX/OT/01/026 Heavy Duty Roller Conveyer | 8.00 | 15.30 | 7.50 | 8.00 | 15.32 | 7.5 |
| **JUMLAH** | | | | **37.5** | | | **37.5** |

---

## 4. User Roles & Permissions

### 4.1 Role Definitions

| Role | Code | Description |
|------|------|-------------|
| **Staff** | `staff` | Fills and submits timesheets & OT forms |
| **Admin** | `admin` | Manages system config, holidays; monitors Desknet sync for project codes & staff |
| **Assistant Manager** | `assistant_manager` | L1 approver for timesheets (CHECKED) |
| **Manager / HOD** | `manager_hod` | L2 approver for timesheets (APPROVED); L1 approver for OT forms |
| **CEO** | `ceo` | L2 approver for OT forms |

**Admin, Assistant Manager, Manager / HOD, CEO is also staff**

### 4.2 Permission Matrix

| Action | Staff | Admin | Asst Mgr | Mgr/HOD | CEO |
|--------|:-----:|:-----:|:--------:|:-------:|:---:|
| Create/edit own timesheet | ✓ | ✓ | ✓ | ✓ | ✓ |
| Upload PDF (own) | ✓ | ✓ | ✓ | ✓ | ✓ |
| Submit timesheet | ✓ | ✓ | ✓ | ✓ | ✓ |
| Create/edit own OT form | ✓ | ✓ | ✓ | ✓ | ✓ |
| Submit OT form | ✓ | ✓ | ✓ | ✓ | ✓ |
| Approve/reject timesheets L1 | | | ✓ | | |
| Approve/reject timesheets L2 | | | | ✓ | |
| Approve/reject OT forms L1 | | | | ✓ | |
| Approve/reject OT forms L2 | | | | | ✓ |
| View project codes (from Desknet) | | ✓ | | | |
| Trigger / monitor Desknet sync | | ✓ | | | |
| Override user role (local only) | | ✓ | | | |
| Manage system config | | ✓ | | | |
| Manage public holidays | | ✓ | | | |
| View reports | | ✓ | | ✓ | ✓ |

### 4.3 Reporting Hierarchy

```
Staff
  └── reports to → Assistant Manager (L1 Timesheet)
                      └── reports to → Manager / HOD (L2 Timesheet, L1 OT)
                                          └── reports to → CEO (L2 OT)
```

---

## 5. System Workflow

### 5.1 Monthly Timesheet Submission — Step by Step

```
Step 1: Staff navigates to "New Timesheet"
        ├── Selects Month & Year
        └── System creates a draft timesheet record
        │
        ▼
Step 2: System generates the timesheet matrix
        ├── Creates day columns (1–31) for the selected month
        ├── Labels each column with day-of-week (MON, TUE, ...)
        ├── Marks Saturdays & Sundays as Off Day (yellow)
        ├── Marks Public Holidays as Holiday (red)
        └── Sets HOURS AVAILABLE per day:
              ├── Mon–Thu working day → 8
              ├── Friday working day  → 7
              └── Off Day / Holiday   → 0
        │
        ▼
Step 3: Staff uploads PDF attendance report (from Infotech)
        ├── System parses PDF (Date, Day, Time In, Time Out, Reason)
        ├── For each day → stores day metadata:
        │     ├── time_in, time_out
        │     ├── Detect missing punches / "CAL" reason → Mark as MC/Leave
        │     ├── Detect "PH" reason → Mark as Public Holiday
        │     ├── Calculate Late Hours (30-min blocks from 08:30)
        │     ├── Calculate OT-eligible Hours (full hours after 17:30)
        │     └── Calculate Attendance Hours (time_out - time_in - lunch)
        ├── Auto-populate Upper Table (Admin Job rows):
        │     ├── Row 1 (MC/LEAVE): 8 or 7 hrs for MC/Leave days
        │     ├── Row 2 (LATE): Computed late hours (0.5 increments)
        │     ├── Row 3-4: Default 0.5 on working days (excl weekends/PH)
        │     └── Row 5-8: Left blank (staff fills manually)
        └── Display warnings for anomalies
        │
        ▼
Step 4: Staff fills the Upper Table (Admin Job)
        ├── Row 3: Morning Assy / Admin Job (verify autofill, typically 0.5)
        ├── Row 4: 5S hours (dropdown, 0.5-hr increments)
        ├── Row 5: Ceramah Agama / Event / ADP (dropdown, 0.5-hr increments)
        ├── Row 6: ISO hours (dropdown, 0.5-hr increments)
        ├── Row 7: Training / Seminar / Visit (dropdown, 0.5-hr increments)
        └── Row 8: RFQ/MKT/PUR/R&D/A.S.S/TDR (remaining hours, auto-suggested)
        │
        ▼
Step 5: Staff fills the Lower Table (Project Hours)
        ├── Click "Add Project" → select Project Code (dropdown)
        │     └── Project Name auto-populated from project_codes table
        ├── For each project, fill hours per day in 4 sub-rows:
        │     ├── NORMAL → NC (normal working hours at normal cost)
        │     ├── NORMAL → COBQ (normal hours at cost of bad quality)
        │     ├── OT → NC (overtime hours at normal cost)
        │     └── OT → COBQ (overtime hours at cost of bad quality)
        ├── OT sub-rows constrained by OT-eligible hours from day metadata
        └── Can add multiple projects (repeat)
        │
        ▼
Step 6: System auto-calculates summary rows
        ├── TOTAL ADMIN JOB = sum of rows 1–8 per day
        ├── TOTAL EXTERNAL PROJECT = sum of all project (Normal + OT) hours (NC+COBQ) per day
        ├── TOTAL WORKING HOURS = TOTAL ADMIN JOB + TOTAL EXTERNAL PROJECT
        ├── OVERTIME = total working hours - hours available
        └── Validate: TOTAL WORKING HOURS should ≈ HOURS AVAILABLE
        │
        ▼
Step 7: Staff reviews the completed timesheet
        ├── Check all day columns are filled
        ├── Check totals match expected values
        └── Make manual corrections if needed
        │
        ▼
Step 8: Staff clicks "Submit"
        │
        ▼
Step 9: System validates
        ├── Every working day must have hours totaling HOURS AVAILABLE
        ├── OT hours ≤ OT-eligible hours per day
        ├── All project rows must have a valid Project Code
        └── No negative values
        │
        ▼
Step 10: Timesheet status → "Pending L1 Approval" (CHECKED)
        │
        ▼
Step 11: Assistant Manager reviews & approves/rejects
        ├── If approved → status = "Pending L2 Approval"
        └── If rejected → status = "Rejected L1" → Staff edits & resubmits
        │
        ▼
Step 12: Manager / HOD reviews & approves/rejects
        ├── If approved → status = "Approved" (APPROVED)
        └── If rejected → status = "Rejected L2" → Staff edits & resubmits
```

### 5.2 OT Form Submission — Two-Phase Approval

The OT form follows the physical OCF which has **two distinct phases**:
- **Phase 1 (B): Pre-Approval** — plan is approved BEFORE overtime work happens
- **Phase 2 (D): Post-Approval** — actual hours are claimed AFTER overtime work is done

```
═══════════════════════════════════════════════════════════
  PHASE 1: PRE-APPROVAL — Sections (A) and (B)
═══════════════════════════════════════════════════════════

Step 1: Staff navigates to "New OT Form"
        ├── Selects Month/Year (dropdown)
        └── Selects Company name (tick one checkbox — stored as text, no FK)
        │
        ▼
Step 2: System creates draft OT form
        │
        ▼
Step 3: Staff fills PLAN entries — Section (A)
        ├── Date (date picker)
        ├── Particulars / Project Name (mandatory, dropdown from project_codes)
        ├── Plan Start Time (dropdown, e.g., 17:30)
        ├── Plan End Time (dropdown, e.g., 19:30)
        └── Plan Total Hours → auto-calculated (End − Start)
        │
        ▼
Step 4: Staff clicks "Submit Plan for Pre-Approval"
        │
        ▼
Step 5: System validates plan
        ├── Every row has a Project Name selected
        ├── Plan Start ≥ 17:30 (OT start time)
        ├── Plan End > Plan Start
        └── At least 1 entry exists
        │
        ▼
Step 6: Status → "pending_hod_pre"
        │
        ▼
Step 7: HOD reviews & approves/rejects plan — Section (B)
        ├── If approved → status = "pending_ceo_pre"
        └── If rejected → status = "rejected_pre" → Staff edits & resubmits
        │
        ▼
Step 8: DGM/CEO reviews & approves/rejects plan — Section (B)
        ├── If approved → status = "pre_approved"
        │     └── Staff is now authorized to perform OT
        └── If rejected → status = "rejected_pre" → Staff edits & resubmits

═══════════════════════════════════════════════════════════
  PHASE 2: POST-APPROVAL — Sections (C) and (D)
═══════════════════════════════════════════════════════════

Step 9:  After OT work is done, Staff fills ACTUAL entries — Section (C)
         ├── Actual Start Time (dropdown)
         ├── Actual End Time (dropdown)
         ├── Actual Total Hours → auto-calculated (End − Start, precise)
         └── System auto-categorizes into day type:
               ├── NORMAL DAY hours (if date is Mon–Fri working day)
               ├── REST DAY hours (if date is Saturday / Sunday)
               └── PUBLIC HOLIDAY hours (if date is in public_holidays)
         │
         ▼
Step 10: Staff clicks "Submit Actual Claim"
         │
         ▼
Step 11: System validates actual entries
         ├── Actual times filled for every planned row
         ├── Actual Start ≥ 17:30
         ├── Actual End > Actual Start
         └── Day type categorization is correct
         │
         ▼
Step 12: Status → "pending_hod_post"
         │
         ▼
Step 13: HOD reviews & approves/rejects actual claim — Section (D)
         ├── If approved → status = "approved" → Ready for payroll
         └── If rejected → status = "rejected_post" → Staff corrects & resubmits
```

### 5.3 Admin Workflow

```
A. Desknet Sync — Project Codes & Staff List
   ┌─────────────────────────────────────────────────────────────┐
   │  Desknet is the MASTER source of truth for:                 │
   │    • Project codes (code, name, client)                    │
   │    • Staff list (name, department, staff no)         │
   │                                                             │
   │  The system does NOT allow manual create/edit/delete        │
   │  of project codes or user accounts. All data comes          │
   │  from Desknet via API sync.                                 │
   └─────────────────────────────────────────────────────────────┘

   A1. Automatic Sync (Scheduled)
       ├── System calls Desknet API on a schedule (e.g., daily at 01:00 AM)
       ├── Fetches latest project codes list
       │     ├── New codes → INSERT into project_codes (is_active = 1)
       │     ├── Updated codes → UPDATE name, client
       │     └── Removed codes → SET is_active = 0 (soft deactivate)
       ├── Fetches latest staff list
       │     ├── New staff → INSERT into users (role = 'staff' default)
       │     ├── Updated staff → UPDATE name, department_id
       │     └── Removed staff → SET is_active = 0 (soft deactivate)
       └── Logs sync result in desknet_sync_log

   A2. Manual Sync Trigger
       1. Admin navigates to "Desknet Sync"
       2. Admin can:
          ├── View last sync timestamp & status
          ├── Click "Sync Now" to trigger immediate sync
          ├── View sync history / error logs
          └── Review changes (new / updated / deactivated records)

   A3. Admin Overrides (Local Only)
       ├── Admin can assign/change user ROLE
       │     (Desknet provides staff info, but roles are managed locally)
       ├── Admin can set "reports_to" hierarchy
       │     (Approval chain is local to this system)
       └── Admin CANNOT edit name, department, staff_no, project codes
             (these are read-only, sourced from Desknet)

B. System Configuration
   1. Admin navigates to "Settings"
   2. Admin can configure:
      ├── Working Start Time (default: 08:30)
      ├── OT Start Time (default: 17:30)
      ├── Late Rounding Block (default: 30 minutes)
      ├── OT Rounding Block (default: 1 hour)
      ├── Normal Day Working Hours (default: 8)
      ├── Friday Working Hours (default: 7)
      ├── Desknet API URL & credentials
      └── Sync schedule (cron expression)

D. Public Holiday Management
   ┌─────────────────────────────────────────────────────────────┐
   │  Malaysia gazetted public holidays are PRE-SEEDED into the  │
   │  system each year. Admin can also add company-specific      │
   │  holidays (e.g., replacement holidays, company events).     │
   └─────────────────────────────────────────────────────────────┘

   D1. Yearly Auto-Seed (Malaysia Calendar)
       ├── On system init or when a new year begins,
       │   system seeds Malaysia gazetted holidays (source = 'gazetted')
       ├── Known fixed-date holidays are marked is_recurring = 1
       │     (e.g., Merdeka Day 31 Aug, Malaysia Day 16 Sep, Labour Day 1 May)
       ├── Variable-date holidays (e.g., Hari Raya, Deepavali, Chinese New Year)
       │   are seeded based on official gazette for that year
       └── Admin is notified to review & confirm seeded holidays

   D2. Admin Holiday Management
       1. Admin navigates to "Public Holidays"
       2. Admin sees calendar view with:
          ├── Gazetted holidays (auto-seeded, editable)
          └── Company holidays (admin-added)
       3. Admin can:
          ├── Add company holiday (source = 'company')
          │     e.g., "Company Annual Dinner", "Replacement Holiday"
          ├── Edit any holiday name or date
          ├── Delete company holidays
          └── View by year (dropdown)

E. Reports
   1. Admin navigates to "Reports"
   2. Available reports:
      ├── Monthly timesheet summary by department
      ├── OT summary by project / department
      ├── Late report
      ├── MC/Leave report
      ├── Desknet sync audit log
      └── Export as Excel / PDF
```

### 5.4 PDF Upload & Autofill Workflow

```
┌──────────────────────────────┐
│  1. STAFF UPLOADS PDF        │
│  - File: .pdf                │
│  - Source: Infotech system   │
│    "Individual Attendance    │
│     Report With Actual Clock"│
│  - Contains: Date, Day,     │
│    Clk1-4, In, Out, Reason  │
└──────────┬───────────────────┘
           │
           ▼
┌──────────────────────────────┐
│  2. VALIDATE & EXTRACT TEXT  │
│  - Check file type = .pdf   │
│  - Check file size ≤ 5 MB   │
│  - Extract text from PDF    │
│    (smalot/pdfparser or      │
│     spatie/pdf-to-text)      │
│  - Detect employee info      │
│  - Detect period (month/yr)  │
│  - Warn on period mismatch   │
└──────────┬───────────────────┘
           │
           ▼
┌──────────────────────────────┐
│  3. PARSE ROWS               │
│  - Extract Date, Day,        │
│    Time In, Time Out, Reason │
│    per row                   │
│  - Reason: PH (holiday),     │
│    RES (rest day),           │
│    CAL (leave/MC)            │
│  - Skip non-matching         │
│    month rows                │
└──────────┬───────────────────┘
           │
           ▼
┌─────────────────────────────┐
│  4. APPLY BUSINESS RULES    │
│                             │
│  FOR EACH DAY:              │
│  ─────────────              │
│  Day Type:                  │
│    Reason=PH → holiday      │
│    SAT/SUN → off_day        │
│    Reason=CAL / no punch    │
│      → mc/leave             │
│    Else → working           │
│                             │
│  Available Hours:           │
│    Friday → 7               │
│    Other weekday → 8        │
│    Off/Holiday → 0          │
│                             │
│  Late (working days only):  │
│    IF time_in > 08:30:      │
│      late_min = time_in     │
│                 - 08:30     │
│      late_hrs = ceil to     │
│        30-min blocks / 60   │
│    Example: 09:05 → 35 min  │
│             → rounds to 1.0 │
│                             │
│  OT Eligible:               │
│    IF time_out > 17:30:     │
│      ot_min = time_out      │
│               - 17:30       │
│      ot_hrs = floor(        │
│        ot_min / 60)         │
│    Example: 19:45 → 135 min │
│             → OT = 2 hrs    │
│                             │
│  Attendance:                │
│    time_out - time_in       │
│    - lunch (60 min)         │
└──────────┬──────────────────┘
           │
           ▼
┌─────────────────────────────┐
│  5. POPULATE DAY METADATA   │
│  + ADMIN ROWS               │
│  - Fill day_metadata for    │
│    all days in month         │
│  - Auto-populate admin rows:│
│    Row 1: MC/LEAVE (8/7 if  │
│      MC day)                 │
│    Row 2: LATE (calculated)  │
│    Row 3-4: 0.5 on workdays │
│    Row 5-8: blank            │
│  - Highlight anomalies      │
│  - Return warnings to UI    │
└─────────────────────────────┘
```

---

## 6. Database Schema (ERD)

### 6.1 Entity-Relationship Diagram

```
                    ┌──────────────────────────┐
                    │   desknet_sync_log       │
                    ├──────────────────────────┤
                    │ id (PK)                  │
                    │ sync_type (ENUM)         │
                    │ trigger_type (ENUM)      │
                    │ triggered_by (FK→users)  │
                    │ status (ENUM)            │
                    │ records_created          │
                    │ records_updated          │
                    │ records_deactivated      │
                    │ error_message            │
                    │ started_at               │
                    │ completed_at             │
                    └──────────────────────────┘

    Data synced from Desknet API (read-only fields marked with ⇐)

    ⇐ = synced from Desknet (read-only)
    [local] = managed within this system

┌────────────────────────┐        ┌────────────────────────┐
│      departments       │        │     project_codes      │
├────────────────────────┤        ├────────────────────────┤
│ id (PK)                │        │ id (PK)               │
│ desknet_id (UNIQUE) ⇐  │        │ desknet_id (UNIQUE) ⇐ │
│ name ⇐                 │        │ code (UNIQUE) ⇐       │
│ is_active              │        │ name ⇐                │
│ last_synced_at         │        │ client ⇐ (VARCHAR)    │
│ created_at             │        │ is_active             │
└────────┬───────────────┘        │ last_synced_at        │
         │ 1                      │ created_at            │
         │                        │ updated_at            │
         │ ╱╲                     └───────┬───────────────┘
         │╱  ╲                            │
┌────────┴────────────────┐               │
│         users           │               │ 1
├─────────────────────────┤               │
│ id (PK)                 │               │ ╱╲
│ desknet_id (UNIQUE) ⇐   │               │╱  ╲
│ staff_no (UNIQUE) ⇐     │               │ *
│ name ⇐                  │               │
│ email (UNIQUE) ⇐        │     (No companies table — logo &
│ password_hash           │      company name are UI-only.
│ role (ENUM) [local]     │      project_codes.client is a
│ department_id (FK) ⇐    │      plain text field for the
│ reports_to (FK) [local] │      outside client/company name.)
│ is_active               │
│ last_synced_at          │
│ created_at              │
│ updated_at              │
└──┬──────────┬───────────┘                │
   │          │                            │
   │1         │1                           │1
   │          │                            │
   │ ╱╲      │ ╱╲                         │ ╱╲
   │╱  ╲     │╱  ╲                        │╱  ╲
   │ *       │ *                          │ *
   │          │                            │
┌──┴──────┐  ┌┴───────────────────┐   ┌────────┴────────────────┐
│timesheets│  │     ot_forms       │   │ timesheet_project_rows  │
├─────────┤  ├────────────────────┤   ├─────────────────────────┤
│id (PK)  │  │id (PK)             │   │ id (PK)                 │
│user_id  │  │user_id (FK)        │   │ timesheet_id (FK)       │
│(FK)     │  │month               │   │ project_code_id (FK)    │
│month    │  │year                │   │ project_name            │
│year     │  │form_type (ENUM)    │   │ row_order               │
│status   │  │  executive         │   │ created_at              │
│(ENUM)   │  │  non_executive     │   └────────┬────────────────┘
│current_ │  │company_name (text) │            │ 1
│ level   │  │section_line        │            │
│submitted│  │status (ENUM)       │            │ ╱╲
│_at      │  │  draft             │            │╱  ╲
│created_ │  │  pending_asst_mgr  │            │ *
│at       │  │    _pre            │            │
│updated_ │  │  pending_hod_pre   │  ┌─────────┴─────────────────┐
│at       │  │  pending_ceo_pre   │  │ timesheet_project_hours   │
└──┬──────┘  │  pre_approved      │  ├───────────────────────────┤
   │ 1       │  pending_actual    │  │ id (PK)                   │
   │         │  pending_mgr_post  │  │ project_row_id (FK)       │
   │         │  pending_hod_post  │  │ entry_date                │
   │         │  pending_ceo_post  │  │ normal_nc_hours           │
   │         │  approved          │  │ normal_cobq_hours         │
   │         │  rejected_pre      │  │ ot_nc_hours               │
   │         │  rejected_post     │  │ ot_cobq_hours             │
   │         │plan_submitted_at   │  │ created_at                │
   │         │actual_submitted_at │  │ updated_at                │
   │         │total_ot_hours      │  └───────────────────────────┘
   │         │created_at          │
   │         │updated_at          │
   │         └──┬─────────────────┘
   │            │ 1
   │            │
   │            │ ╱╲
   │            │╱  ╲
   │            │ *
   │  ┌────────┴──────────────────┐
   │  │    ot_form_entries        │
   │  ├───────────────────────────┤
   │  │ id (PK)                   │
   │  │ ot_form_id (FK)           │
   │  │ entry_date                │
   │  │ project_code_id (FK)      │
   │  │ project_name              │
   │  │ -- Plan --                │
   │  │ planned_start_time        │
   │  │ planned_end_time          │
   │  │ planned_total_hours       │
   │  │ -- Actual --              │
   │  │ actual_start_time (NULL)  │
   │  │ actual_end_time (NULL)    │
   │  │ actual_total_hours        │
   │  │ -- Exec: day type --      │
   │  │ normal_day_hours          │
   │  │ rest_day_hours            │
   │  │ public_holiday_hours      │
   │  │ -- Non-exec only --       │
   │  │ meal_break                │
   │  │ over_3_hours              │
   │  │ is_shift                  │
   │  │ ot_type (ENUM)            │
   │  │ ot_rate_1 .. ot_rate_5    │
   │  │ remarks                   │
   │  │ created_at                │
   │  │ updated_at                │
   │  └───────────────────────────┘
   │
   │ ╱╲
   │╱  ╲
   │ *
   │
   ├──────────────────────────────────────────────────┐
   │                                                  │
┌──┴──────────────────────┐   ┌───────────────────────┴─────┐
│ timesheet_day_metadata  │   │   timesheet_admin_hours     │
├─────────────────────────┤   ├─────────────────────────────┤
│ id (PK)                 │   │ id (PK)                     │
│ timesheet_id (FK)       │   │ timesheet_id (FK)           │
│ entry_date              │   │ admin_type (ENUM)           │
│ day_of_week             │   │ entry_date                  │
│ day_type (ENUM)         │   │ hours                       │
│ time_in                 │   │ created_at                  │
│ time_out                │   │ updated_at                  │
│ late_hours              │   └─────────────────────────────┘
│ ot_eligible_hours       │
│ attendance_hours        │   admin_type ENUM values:
│ available_hours         │   ┌────────────────────────────┐
│ morning_assy_hours      │   │ 'mc_leave'        (Row 1)  │
│ remarks                 │   │ 'late'            (Row 2)  │
│ created_at              │   │ 'morning_assy'    (Row 3)  │
│ updated_at              │   │ 'five_s'          (Row 4)  │
└─────────────────────────┘   │ 'ceramah_event'   (Row 5)  │
                              │ 'iso'             (Row 6)  │
                              │ 'training'        (Row 7)  │
                              │ 'admin_category'  (Row 8)  │
                              └────────────────────────────┘


┌─────────────────────────┐   ┌───────────────────────┐
│     approval_logs       │   │    system_config      │
├─────────────────────────┤   ├───────────────────────┤
│ id (PK)                 │   │ id (PK)               │
│ entity_type (ENUM)      │   │ config_key (UNIQUE)   │
│ entity_id               │   │ config_value          │
│ approver_id (FK→users)  │   │ description           │
│ phase (ENUM: pre/post)  │   │ updated_at            │
│ level (1 or 2)          │   └───────────────────────┘
│ action (ENUM)           │
│ remarks                 │
│ acted_at                │   ┌───────────────────────────┐
└─────────────────────────┘   │     public_holidays       │
                              ├───────────────────────────┤
┌─────────────────────────┐   │ id (PK)                   │
│  attendance_uploads     │   │ holiday_date (UNIQUE)     │
├─────────────────────────┤   │ name                      │
│ id (PK)                 │   │ year                      │
│ user_id (FK)            │   │ source (gazetted/company) │
│ file_name               │   │ is_recurring              │
│ file_path               │   │ created_by (FK→users)     │
│ month                   │   │ created_at                │
│ year                    │   │ updated_at                │
│ file_type (pdf/xlsx)    │   └───────────────────────────┘
│ status (ENUM)           │
│ error_message           │   ┌───────────────────────┐
│ uploaded_at             │   │ morning_assembly_log  │
└─────────────────────────┘   ├───────────────────────┤
                              │ id (PK)               │
                              │ user_id (FK)          │
                              │ log_date              │
                              │ attended (BOOLEAN)    │
┌─────────────────────────┐   │ source (ENUM)         │
│     notifications       │   │ synced_at             │
├─────────────────────────┤   └───────────────────────┘
│ id (PK)                 │
│ user_id (FK)            │
│ type                    │
│ title                   │
│ message                 │
│ link                    │
│ is_read (BOOLEAN)       │
│ created_at              │
└─────────────────────────┘
```

### 6.2 Table Definitions (SQL)

```sql
-- ============================================================
-- USERS & ORGANIZATION
-- ============================================================

CREATE TABLE departments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    desknet_id  VARCHAR(100) UNIQUE NULL,       -- Desknet external ID
    name        VARCHAR(100) NOT NULL,           -- Synced from Desknet
    is_active   TINYINT(1) DEFAULT 1,
    last_synced_at TIMESTAMP NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    desknet_id      VARCHAR(100) UNIQUE NULL,       -- Desknet external ID (source of truth)
    staff_no        VARCHAR(20) UNIQUE NULL,         -- Staff number from Desknet (e.g., T107)
    name            VARCHAR(150) NOT NULL,           -- Synced from Desknet (read-only)
    email           VARCHAR(150) UNIQUE NOT NULL,    -- Synced from Desknet (read-only)
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('staff','admin','assistant_manager','manager_hod','ceo') NOT NULL DEFAULT 'staff',  -- Local only
    department_id   INT,                             -- Synced from Desknet (read-only)
    reports_to      INT NULL,                        -- Local only (approval chain)
    is_active       TINYINT(1) DEFAULT 1,            -- Sync: removed in Desknet → 0
    last_synced_at  TIMESTAMP NULL,                  -- Last Desknet sync timestamp
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (reports_to)    REFERENCES users(id)
);

-- ============================================================
-- PROJECT CODES
-- ============================================================

CREATE TABLE project_codes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    desknet_id      VARCHAR(100) UNIQUE NULL,       -- Desknet external ID (source of truth)
    code            VARCHAR(50) UNIQUE NOT NULL,     -- Synced from Desknet (read-only)
    name            VARCHAR(200) NOT NULL,           -- Synced from Desknet (read-only)
    client          VARCHAR(200) NULL,               -- Client / outside company name (text, no FK)
    is_active       TINYINT(1) DEFAULT 1,            -- Sync: removed in Desknet → 0
    last_synced_at  TIMESTAMP NULL,                  -- Last Desknet sync timestamp
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

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
-- Stores attendance data from PDF/Excel + computed business rules.
-- This is the "column info" for each day in the matrix.
-- ============================================================

CREATE TABLE timesheet_day_metadata (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id        INT NOT NULL,
    entry_date          DATE NOT NULL,
    day_of_week         VARCHAR(3) NOT NULL,         -- MON, TUE, WED, THU, FRI, SAT, SUN
    day_type            ENUM('working','off_day','public_holiday','mc','leave') DEFAULT 'working',
    time_in             TIME NULL,                   -- from PDF/Excel
    time_out            TIME NULL,                   -- from PDF/Excel
    late_hours          DECIMAL(4,1) DEFAULT 0.0,    -- 30-min blocks (0.5 increments)
    ot_eligible_hours   INT DEFAULT 0,               -- whole hours, from time_out after 17:30
    attendance_hours    DECIMAL(4,1) DEFAULT 0.0,    -- time_out - time_in - lunch
    available_hours     DECIMAL(4,1) DEFAULT 8.0,    -- 8 normal, 7 Friday, 0 off/holiday
    morning_assy_hours  DECIMAL(4,1) DEFAULT 0.0,    -- from Google Form integration
    remarks             TEXT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_day (timesheet_id, entry_date),
    FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE
);

-- ============================================================
-- TIMESHEET ADMIN HOURS (Upper Table: 8 fixed row types per day)
-- One record per admin_type × date combination.
-- ============================================================

CREATE TABLE timesheet_admin_hours (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id    INT NOT NULL,
    admin_type      ENUM(
                        'mc_leave',         -- Row 1: MC/LEAVE
                        'late',             -- Row 2: LATE
                        'morning_assy',     -- Row 3: MORNING ASSY / ADMIN JOB
                        'five_s',           -- Row 4: 5S
                        'ceramah_event',    -- Row 5: CERAMAH AGAMA / EVENT / ADP
                        'iso',              -- Row 6: ISO
                        'training',         -- Row 7: TRAINING / SEMINAR / VISIT
                        'admin_category'    -- Row 8: RFQ/MKT/PUR/R&D/A.S.S/TDR
                    ) NOT NULL,
    entry_date      DATE NOT NULL,
    hours           DECIMAL(4,1) DEFAULT 0.0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_admin_day (timesheet_id, admin_type, entry_date),
    FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE
);

-- ============================================================
-- TIMESHEET PROJECT ROWS (Lower Table: one per project)
-- Staff can add multiple projects. Each project is a numbered
-- block on the form with 4 sub-rows.
-- ============================================================

CREATE TABLE timesheet_project_rows (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id        INT NOT NULL,
    project_code_id     INT NOT NULL,
    project_name        VARCHAR(200),            -- autofilled from project_codes.name
    row_order           INT DEFAULT 0,           -- display order (1, 2, 3...)
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (timesheet_id)    REFERENCES timesheets(id) ON DELETE CASCADE,
    FOREIGN KEY (project_code_id) REFERENCES project_codes(id)
);

-- ============================================================
-- TIMESHEET PROJECT HOURS (Lower Table: hours per project per day)
-- Each record = one project × one day, with 4 values matching
-- the 4 sub-rows: NORMAL×NC, NORMAL×COBQ, OT×NC, OT×COBQ.
-- ============================================================

CREATE TABLE timesheet_project_hours (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    project_row_id      INT NOT NULL,
    entry_date          DATE NOT NULL,
    normal_nc_hours     DECIMAL(4,1) DEFAULT 0.0,    -- NORMAL row, NC column
    normal_cobq_hours   DECIMAL(4,1) DEFAULT 0.0,    -- NORMAL row, COBQ column
    ot_nc_hours         DECIMAL(4,1) DEFAULT 0.0,    -- OT row, NC column (whole hours in practice)
    ot_cobq_hours       DECIMAL(4,1) DEFAULT 0.0,    -- OT row, COBQ column (whole hours in practice)
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_project_day (project_row_id, entry_date),
    FOREIGN KEY (project_row_id) REFERENCES timesheet_project_rows(id) ON DELETE CASCADE
);

-- ============================================================
-- OT FORMS
-- ============================================================

CREATE TABLE ot_forms (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    month           TINYINT NOT NULL,
    year            SMALLINT NOT NULL,
    form_type       ENUM('executive','non_executive') NOT NULL DEFAULT 'executive',
                    -- 'executive'     = OVERTIME CLAIM FORM (EXECUTIVE) ~ OCF (English)
                    -- 'non_executive' = BORANG KERJA LEBIH MASA (BUKAN EKSEKUTIF) (BM)
    company_name    VARCHAR(150) NULL,              -- UI label (e.g., 'TALENT SYNERGY'), no FK
    section_line    VARCHAR(150) NULL,              -- SECTION/LINE (exec) or SEKSYEN/BAH. (non-exec)
    -- Two-phase approval: pre-approval before OT, post-approval after OT
    -- Executive:     pre = EXEC → HOD → DGM/CEO; post = Executive → HOD
    -- Non-Executive: pre = Asst.Mgr → HOD → DGM/CEO; post = Staff → MGR/HOD → DGM/CEO
    status          ENUM(
                        'draft',                 -- Staff filling plan
                        'pending_asst_mgr_pre',  -- Non-exec: waiting Asst. Mgr pre-approval
                        'pending_hod_pre',       -- Waiting HOD pre-approval
                        'pending_ceo_pre',       -- HOD pre-approved, waiting DGM/CEO
                        'pre_approved',          -- All pre-approvals done → staff may do OT
                        'pending_actual',        -- Staff filling actual hours
                        'pending_mgr_post',      -- Non-exec: waiting MGR/HOD post-approval
                        'pending_hod_post',      -- Exec: waiting HOD post-approval
                        'pending_ceo_post',      -- Non-exec: waiting DGM/CEO post-approval
                        'approved',              -- Fully approved → ready for payroll
                        'rejected_pre',          -- Rejected during pre-approval
                        'rejected_post'          -- Rejected during post-approval
                    ) DEFAULT 'draft',
    plan_submitted_at   TIMESTAMP NULL,          -- When plan was submitted for pre-approval
    actual_submitted_at TIMESTAMP NULL,          -- When actual was submitted for post-approval
    total_ot_hours      DECIMAL(6,2) DEFAULT 0.00, -- Non-exec: JUMLAH JAM OT (final total)
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)
);

CREATE TABLE ot_form_entries (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    ot_form_id              INT NOT NULL,
    entry_date              DATE NOT NULL,
    project_code_id         INT NULL,                    -- NULL for non-exec rows with no OT
    project_name            VARCHAR(200) NULL,           -- PARTICULARS / TUGAS ATAU AKTIVITI
    -- Plan: MASA DIRANCANG (BM) / PLAN (EN)
    planned_start_time      TIME NULL,
    planned_end_time        TIME NULL,
    planned_total_hours     DECIMAL(5,2) DEFAULT 0.00,   -- Plan End − Plan Start
    -- Actual: MASA SEBENAR (BM) / ACTUAL (EN) — filled after OT
    actual_start_time       TIME NULL,
    actual_end_time         TIME NULL,
    actual_total_hours      DECIMAL(5,2) DEFAULT 0.00,   -- Actual End − Actual Start
    -- Executive only: Total Hours breakdown by day type (Section C)
    normal_day_hours        DECIMAL(5,2) DEFAULT 0.00,   -- OT on normal working day
    rest_day_hours          DECIMAL(5,2) DEFAULT 0.00,   -- OT on Saturday / Sunday
    public_holiday_hours    DECIMAL(5,2) DEFAULT 0.00,   -- OT on public holiday
    -- Non-executive only columns
    meal_break              TINYINT(1) DEFAULT 0,        -- MAKAN (meal break taken)
    over_3_hours            TINYINT(1) DEFAULT 0,        -- LEBIH 0-3 JAM (exceeds 3 hours)
    is_shift                TINYINT(1) DEFAULT 0,        -- SHIFT indicator
    -- Non-executive: JENIS OT (OT Type) — select one per row
    ot_type                 ENUM('normal','training','kaizen','ss') NULL,
    -- Non-executive: PENGIRAAN OT (OT Calculation) — hours per rate tier
    ot_rate_1               DECIMAL(5,2) DEFAULT 0.00,   -- OT 1 (e.g., 1.5x normal day)
    ot_rate_2               DECIMAL(5,2) DEFAULT 0.00,   -- OT 2 (e.g., 2.0x normal day excess)
    ot_rate_3               DECIMAL(5,2) DEFAULT 0.00,   -- OT 3 (e.g., rest day rate)
    ot_rate_4               DECIMAL(5,2) DEFAULT 0.00,   -- OT 4 (e.g., public holiday rate)
    ot_rate_5               DECIMAL(5,2) DEFAULT 0.00,   -- OT 5 (e.g., special rate)
    remarks                 TEXT NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
    phase           ENUM('pre','post') NOT NULL,   -- pre = before OT (B), post = after OT (D)
    level           TINYINT NOT NULL,               -- 1=HOD, 2=DGM/CEO (pre) or 1=HOD (post)
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

INSERT INTO system_config (config_key, config_value, description) VALUES
('working_start_time',    '08:30', 'Official work start time (HH:MM)'),
('ot_start_time',         '17:30', 'OT counting starts after this time (HH:MM)'),
('late_rounding_minutes', '30',    'Late rounding block in minutes'),
('ot_rounding_hours',     '1',     'OT rounding block in hours'),
('lunch_break_minutes',   '60',    'Lunch break duration in minutes'),
('default_working_hours', '8',     'Standard working hours per day (Mon-Thu)'),
('friday_working_hours',  '7',     'Working hours on Friday (excl. OT)'),
('desknet_api_url',       '',      'Desknet API base URL'),
('desknet_api_key',       '',      'Desknet API key / token (encrypted)'),
('desknet_sync_cron',     '0 1 * * *', 'Desknet sync schedule (cron expression, default: daily 01:00 AM)'),
('desknet_sync_enabled',  '1',     'Enable/disable automatic Desknet sync (1=on, 0=off)');

-- ============================================================
-- PUBLIC HOLIDAYS
-- ============================================================

CREATE TABLE public_holidays (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date    DATE NOT NULL,
    name            VARCHAR(150) NOT NULL,
    year            SMALLINT NOT NULL,
    source          ENUM('gazetted','company') NOT NULL DEFAULT 'gazetted',
                    -- 'gazetted'  = Malaysia public holiday (auto-seeded from calendar)
                    -- 'company'   = Company-specific leave / replacement holiday (admin-added)
    is_recurring    TINYINT(1) DEFAULT 0,      -- 1 = repeats every year (e.g., Merdeka Day)
    created_by      INT NULL,                  -- NULL = system-seeded, user_id = admin-added
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_holiday (holiday_date),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ============================================================
-- ATTENDANCE UPLOADS TRACKING (PDF or Excel)
-- ============================================================

CREATE TABLE attendance_uploads (
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
-- DESKNET SYNC LOG
-- ============================================================

CREATE TABLE desknet_sync_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sync_type       ENUM('project_codes','staff','departments','full') NOT NULL,
    trigger_type    ENUM('scheduled','manual') NOT NULL,    -- scheduled cron or admin-triggered
    triggered_by    INT NULL,                               -- user_id if manual, NULL if scheduled
    status          ENUM('running','success','partial','failed') NOT NULL,
    records_created INT DEFAULT 0,
    records_updated INT DEFAULT 0,
    records_deactivated INT DEFAULT 0,
    error_message   TEXT NULL,
    started_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at    TIMESTAMP NULL,
    FOREIGN KEY (triggered_by) REFERENCES users(id)
);

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================

CREATE INDEX idx_timesheet_user       ON timesheets(user_id, month, year);
CREATE INDEX idx_timesheet_status     ON timesheets(status);
CREATE INDEX idx_day_meta_ts          ON timesheet_day_metadata(timesheet_id, entry_date);
CREATE INDEX idx_admin_hours_ts       ON timesheet_admin_hours(timesheet_id, entry_date);
CREATE INDEX idx_project_row_ts       ON timesheet_project_rows(timesheet_id);
CREATE INDEX idx_project_hours_row    ON timesheet_project_hours(project_row_id, entry_date);
CREATE INDEX idx_ot_form_user         ON ot_forms(user_id, month, year);
CREATE INDEX idx_ot_form_status       ON ot_forms(status);
CREATE INDEX idx_approval_entity      ON approval_logs(entity_type, entity_id);
CREATE INDEX idx_notification_user    ON notifications(user_id, is_read);
CREATE INDEX idx_assembly_date        ON morning_assembly_log(log_date);
CREATE INDEX idx_desknet_sync_type    ON desknet_sync_log(sync_type, status);
CREATE INDEX idx_user_desknet_id      ON users(desknet_id);
CREATE INDEX idx_project_desknet_id   ON project_codes(desknet_id);
CREATE INDEX idx_dept_desknet_id      ON departments(desknet_id);
```

### 6.3 How the Database Maps to the Physical Form

```
Physical Form                         Database Tables
─────────────                         ─────────────────

HEADER
  Name, Emp No, Month/Year        →   timesheets (master record)
                                        .user_id → users.name, users.id
                                        .month, .year

DAY COLUMNS (1–31)                →   timesheet_day_metadata (one row per day)
  Day number                            .entry_date
  Day of week                           .day_of_week (MON, TUE, ...)
  Color coding                          .day_type (working/off_day/public_holiday/mc/leave)
  Available hours                       .available_hours (8, 7, or 0)
  [Hidden: Time In/Out from PDF]        .time_in, .time_out
  [Hidden: Computed values]             .late_hours, .ot_eligible_hours, .attendance_hours

UPPER TABLE: ADMIN JOB            →   timesheet_admin_hours
  Row 1: MC/LEAVE                       admin_type = 'mc_leave'
  Row 2: LATE                           admin_type = 'late'
  Row 3: MORNING ASSY / ADMIN JOB       admin_type = 'morning_assy'
  Row 4: 5S                             admin_type = 'five_s'
  Row 5: CERAMAH AGAMA / EVENT / ADP    admin_type = 'ceramah_event'
  Row 6: ISO                            admin_type = 'iso'
  Row 7: TRAINING / SEMINAR / VISIT     admin_type = 'training'
  Row 8: RFQ/MKT/PUR/R&D/A.S.S/TDR     admin_type = 'admin_category'
  TOTAL ADMIN JOB                       Computed: SUM(hours) per day

LOWER TABLE: PROJECT HOURS        →   timesheet_project_rows + timesheet_project_hours
  Project NO (1, 2, 3...)               timesheet_project_rows.row_order
  PROJECT CODE                          timesheet_project_rows.project_code_id
  Project Name                          timesheet_project_rows.project_name (autofill)
  NORMAL → NC (hrs per day)             timesheet_project_hours.normal_nc_hours
  NORMAL → COBQ (hrs per day)           timesheet_project_hours.normal_cobq_hours
  OT → NC (hrs per day)                 timesheet_project_hours.ot_nc_hours
  OT → COBQ (hrs per day)              timesheet_project_hours.ot_cobq_hours
  TOTAL per project                     Computed: SUM across all days

SUMMARY ROWS                      →   All computed in frontend/backend
  TOTAL EXTERNAL PROJECT                SUM(normal_nc + normal_cobq) across all projects per day
  TOTAL WORKING HOURS                   TOTAL ADMIN JOB + TOTAL EXTERNAL PROJECT
  HOURS AVAILABLE                       From timesheet_day_metadata.available_hours
  OVERTIME                              SUM(ot_nc + ot_cobq) across all projects per day

SIGNATURES                        →   timesheets.status + approval_logs
  PREPARED  = Staff submitted           status → pending_l1
  CHECKED   = L1 approved               approval_logs (level=1, action=approved)
  APPROVED  = L2 approved               approval_logs (level=2, action=approved)

═══════════════════════════════════════════════════════════════
  OT FORM → Database Mapping (Both Types)
═══════════════════════════════════════════════════════════════

HEADER (both)
  Form Type                       →   ot_forms.form_type ('executive' or 'non_executive')
  Company (tick one)              →   ot_forms.company_name (VARCHAR, text label)
  Name, Staff No, Month          →   ot_forms.user_id → users (name, id)
                                        .month, .year
  Section/Line                    →   ot_forms.section_line

PLAN (both)                      →   ot_form_entries
  DATE / TARIKH                         .entry_date
  PARTICULARS / TUGAS                   .project_code_id → project_codes.id
                                        .project_name (autofilled)
  START / MULA                          .planned_start_time
  END / TAMAT                           .planned_end_time
  TOTAL / JUMLAH                        .planned_total_hours (auto-calc)

PRE-APPROVAL                     →   ot_forms.status + approval_logs (phase='pre')
  Executive:
    EXEC. signs                         status → pending_hod_pre
    HOD approves                        approval_logs (phase=pre, level=1)
    DGM/CEO approves                    approval_logs (phase=pre, level=2) → pre_approved
  Non-Executive:
    Asst. Mgr approves                  status → pending_asst_mgr_pre → pending_hod_pre
    HOD approves                        approval_logs (phase=pre, level=2)
    DGM/CEO approves                    approval_logs (phase=pre, level=3) → pre_approved

ACTUAL (both)                    →   ot_form_entries (updated after OT)
  ACTUAL START / MASA SEBENAR MULA      .actual_start_time
  ACTUAL END / MASA SEBENAR TAMAT       .actual_end_time
  ACTUAL TOTAL / JUMLAH                 .actual_total_hours (auto-calc)

EXECUTIVE ONLY — DAY TYPE BREAKDOWN
  NORMAL DAY column                     .normal_day_hours
  REST DAY column                       .rest_day_hours
  PUBLIC HOLIDAY column                 .public_holiday_hours

NON-EXECUTIVE ONLY — EXTRA COLUMNS
  MAKAN (meal)                          .meal_break
  LEBIH 0-3 JAM (over 3 hrs)           .over_3_hours
  SHIFT                                 .is_shift
  JENIS OT (OT type)                    .ot_type ('normal','training','kaizen','ss')
  PENGIRAAN OT (OT calc)               .ot_rate_1 .. ot_rate_5

POST-APPROVAL                   →   ot_forms.status + approval_logs (phase='post')
  Executive:
    Claimed by (Executive)              status → pending_hod_post
    Approved by (HOD)                   approval_logs (phase=post, level=1) → approved
  Non-Executive:
    DISEDIAKAN OLEH (Staff)             status → pending_mgr_post
    DISAHKAN OLEH (MGR/HOD)             approval_logs (phase=post, level=1) → pending_ceo_post
    DILULUSKAN OLEH (DGM/CEO)           approval_logs (phase=post, level=2) → approved

FOOTER TOTALS                    →   All computed in frontend/backend
  TOTAL (HOURS) Plan / JUMLAH          SUM(planned_total_hours)
  TOTAL (HOURS) Actual / JUMLAH        SUM(actual_total_hours)
  Executive: NORMAL DAY total           SUM(normal_day_hours)
  Executive: REST DAY total             SUM(rest_day_hours)
  Executive: PUBLIC HOLIDAY total       SUM(public_holiday_hours)
  Non-Exec: JUMLAH JAM OT              ot_forms.total_ot_hours
```

---

> **Next Steps (to be added in subsequent sections):**
> 7. API Design (endpoints & payloads for matrix structure + OT two-phase + Desknet sync)
> 8. Frontend Page Structure (matrix UI layout + OT form UI + Desknet sync dashboard + print layout)
> 9. PDF/Excel Parsing Logic (pseudocode & validation rules)
> 10. Business Rules Engine (late/OT calculation, admin row defaults, summary formulas)
> 11. Approval Workflow Logic (state machines for both Timesheet & OT)
> 12. Integration Architecture (Desknet API sync + Google Form / Morning Assembly)
> 13. Tech Stack & Infrastructure
> 14. Security Considerations
> 15. Future Considerations
