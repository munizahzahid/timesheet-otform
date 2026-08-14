# TSSB Portal User Manual

---

## Table of Contents

1. [Introduction](#introduction)
2. [Login](#login)
3. [Dashboard](#dashboard)
4. [Timesheet](#timesheet)
5. [OT Form](#ot-form)
6. [History](#history)
7. [Project Management](#project-management)
8. [Approvals](#approvals)
9. [All Records](#all-records)
10. [Training Attendance](#training-attendance)
11. [Profile](#profile)
12. [FAQ](#faq)

---

## Introduction

TSSB Portal is a system for managing timesheets, overtime requests, and projects. You can use it to:

- Submit monthly timesheets
- Request overtime (OT) approval
- Track approval status
- View your history
- Manage projects (if you're a project manager)

---

## Login

### How to Login

1. Open your web browser and go to the system URL
2. Enter your **email address**
3. Enter your **password**
4. Click **Log In**

### Signature Tips

When a form asks for your signature, you only need to type the part of your name before `BIN`, `BINTI`, `B`, or `BT`. The system will auto-complete the rest.

**Example:** For **AHMAD FAIZAL BIN ALI**, type only `AHMAD FAIZAL`

---

## Dashboard

The Dashboard is your home page. It shows:

- **Quick Links** - Fast access to Timesheets, OT Forms, and Projects
- **Summary Cards** - Your pending, submitted, and approved items
- **Charts** - Visual breakdown of your OT hours
- **Project Status** - Active project progress (if applicable)

---

## Timesheet

### Creating a Timesheet

1. Click **HR** → **Timesheet**
2. Click **+ New Timesheet**
3. Select **month** and **year**
4. Click **Create**

### Filling in Your Timesheet

1. Click **Edit** on your timesheet
2. **Upload Attendance** - Upload your attendance PDF or Excel file
3. **Admin Hours** - Normal and OT hours fill automatically
4. **Project Rows** - Add project codes and enter hours:
   - **Normal NC** - Normal Non-Chargeable hours
   - **Normal COBQ** - Normal Chargeable on Bill Quotation hours
   - **OT NC** - Overtime Non-Chargeable hours
   - **OT COBQ** - Overtime Chargeable on Bill Quotation hours
5. Values save automatically as you type
6. Click **Submit** when done
7. Use **Print**, **Export Excel**, or **Export PDF** to download

### Attendance Rules

| Reason Code | Meaning | What to Do |
|-------------|---------|------------|
| **ABS** | Absent without leave/MC | Enter **0** hours |
| **ML** | Medical Leave | Enter **7 or 8** hours on MC/LEAVE row |
| **EL** | Emergency Leave | Enter **7 or 8** hours on MC/LEAVE row |
| **AL** | Annual Leave | Enter **7 or 8** hours on MC/LEAVE row |
| **PH** | Public Holiday | Enter **0** hours |
| **RES** | Rest day | Enter **0** hours |

### Timesheet Status

- **Draft** - Still editable, not submitted
- **Pending HOD** - Waiting for Head of Department approval
- **Pending L1** - Waiting for Assistant Manager approval
- **Pending L2** - Waiting for Manager approval
- **Pending L3** - Waiting for Senior Manager/CEO approval
- **Approved** - Fully approved
- **Rejected** - Returned for corrections

---

## OT Form

### Creating an OT Form

1. Click **HR** → **OT Form**
2. Click **+ OT Form Baru**
3. Select:
   - **Month** and **year**
   - **Form type** (Executive / Non-Executive)
   - **Company**
4. Click **Create**

### Filling in Your OT Form

1. Click **Edit** on your OT form
2. **Plan** - Enter planned start/end times for expected overtime
3. **Actual** - Enter actual start/end times, or click **Auto-Fill from Attendance**
   - *Note: Upload attendance PDF in your timesheet first*
4. Click **Save** to save progress
5. Click **Submit for Approval** when ready
6. Use **Print**, **Export Excel**, or **Export PDF** to download

### Form Types

- **Executive** - Simplified form for executive staff
- **Non-Executive** - Detailed form with additional fields

### OT Form Status

- **Draft** - Not yet submitted
- **Pending** - Waiting for approval
- **Pending Manager** - Waiting for manager approval
- **Pending HR** - Waiting for HR review
- **Approved** - Approved
- **Completed** - Fully processed
- **Rejected** - Rejected with remarks
- **Returned** - Returned by HR for correction

---

## History

The **History** page shows your past submissions:

- Submitted timesheets
- Submitted OT forms
- Approval status and dates
- Rejection remarks

---

## Project Management

*Available to project managers and admins*

### Executive Dashboard

1. Click **Project Management** → **Executive Dashboard**
2. View:
   - Total, active, completed, and delayed projects
   - Staff allocation timeline
   - Budget vs actual
   - Task status distribution

### Creating a Project

1. Click **Project Management** → **List of Project**
2. Click **+ New Project**
3. Fill in project code, name, client, dates, and team
4. Click **Save**

### Project Phases

1. Open a project and go to **Phases** tab
2. Click **+ New Phase**
3. Enter phase name, dates, and order
4. Click **Save**

### Project Tasks

1. Open a project and go to **Tasks** tab
2. Click **+ New Task**
3. Enter task name, assign user, set dates and weight (0-100)
4. Click **Save**

**Note:** Task weights within the same phase must not exceed 100% total.

### Gantt Chart

1. Open a project and go to **Schedule** tab
2. View project timeline with phases and tasks
3. Colors: Blue = Plan, Orange = Revise, Green = Actual

**Gantt Chart Features:**
- **Three-Lane Timeline**: Plan (blue), Revise (orange), Actual (green) bars
- **Drag to Resize**: Drag bar edges to change dates
- **Drag to Move**: Drag bar center to move entire task
- **Dependency Lines**: Visual connections between dependent tasks
- **Today Indicator**: Vertical line showing current date
- **Progress Shadows**: Colored fill showing today's position on timeline
- **Phase Auto-Update**: Phase dates automatically follow subtask dates

**Phase-Task Relationship:**
- Phase plan dates automatically update to match the earliest/latest subtask dates
- When you change a subtask's plan end date, the phase end date automatically extends
- This allows flexible planning while maintaining timeline integrity
- The phase bar shadow shows today's date position, not the progress percentage

**Task Dependencies:**
- Set predecessor tasks to create dependencies
- **End-to-Start**: Task cannot start until predecessor completes
- **Start-to-Start**: Task can start when predecessor starts
- Dependencies are enforced when changing status or dates

### Task Progress

- Progress is calculated automatically from actual start/end dates
- You cannot edit progress directly - update the dates instead
- Phase and project progress are weighted averages of task progress
- Task weights determine how much each task contributes to phase progress

### Comments and Attachments

- Open a task to add comments
- Upload attachments for reference

---

## Approvals

*For managers and approvers*

### Timesheet Approvals

1. Click **Approvals** → **Timesheet Approvals**
2. Click **Review** on a timesheet
3. Review details, sign if required
4. Click **Approve** or **Reject** with remarks

### OT Form Approvals

1. Click **Approvals** → **OT Approvals**
2. Click **Review** on an OT form
3. Review planned vs actual hours
4. Click **Approve** or **Reject** with remarks
5. HR can also **Forward** or **Return** forms

### Pending Tracker

1. Click **Approvals** → **Pending Tracker**
2. See all items awaiting your action

---

## All Records

*For managers and above*

View historical data:

- **Timesheets** - All submitted timesheets
- **Timesheet Summary** - Monthly summary report
- **OT Forms** - All submitted OT forms
- **OT Summary** - Overtime summary

Use export buttons to download reports.

---

## Training Attendance

*For HR and admins*

1. Click **HR** → **Training Attendance**
2. Click **+ New Training** to create a session
3. Enter training details (title, date, time, venue, trainer)
4. Mark attendees or allow staff to mark attendance
5. Export attendance as PDF

---

## Profile

### Update Profile

1. Click **Account** → **Profile**
2. Update name, short name, email, or category
3. Click **Save**

### Change Password

1. Click **Account** → **Profile**
2. Go to **Password** section
3. Enter current password
4. Enter and confirm new password
5. Click **Save**

### Delete Account

1. Click **Account** → **Profile**
2. Go to **Delete Account** section
3. Confirm the action
   *Note: This cannot be undone*

---

## FAQ

### Q: How do I auto-fill OT time from attendance?
A: Upload the attendance PDF in your timesheet first. Then in the OT form, click **Auto-Fill from Attendance**.

### Q: What's the difference between Executive and Non-Executive OT forms?
A: Executive forms are simplified. Non-Executive forms include detailed breakdown and additional fields.

### Q: Can I delete a submitted timesheet or OT form?
A: No. Only draft items can be deleted. Ask an approver to reject or return it.

### Q: How do I know my approval status?
A: Status is shown on the Timesheet, OT Form, History, and Pending Tracker pages.

### Q: Who can approve my timesheets and OT forms?
A: Approval depends on your role and the approvers in your user profile. Common approvers are HOD, L1 (Assistant Manager), L2 (Manager), L3 (Senior Manager/CEO), and HR.

### Q: What should I do if my timesheet is rejected?
A: Read the rejection remarks, make corrections, and resubmit.

### Q: Why is my attendance not auto-filling correctly?
A: Make sure the file is in the correct Infotech format. Check that clock in/out rows are clear and reason codes are readable.

### Q: Can I edit task progress directly?
A: No. Progress is calculated from actual start/end dates. Update the dates and the system will recalculate.

---

## Technical Support

If you need help:
- Click the **?** help button at the bottom right
- Email: support@talentsynergy.com
- Contact IT Support

---

## Document Version

Version: 2.0  
Last Updated: August 2026
