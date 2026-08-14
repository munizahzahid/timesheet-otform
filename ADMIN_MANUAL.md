# TSSB Portal Admin Manual

---

## Table of Contents

1. [Introduction](#introduction)
2. [User Management](#user-management)
3. [Project Management](#project-management)
4. [Project Codes](#project-codes)
5. [Holidays](#holidays)
6. [Desknet Sync](#desknet-sync)
7. [Audit Logs](#audit-logs)
8. [System Settings](#system-settings)
9. [Approvals](#approvals)
10. [Troubleshooting](#troubleshooting)

---

## Introduction

This manual is for system administrators who manage the TSSB Portal. As an admin, you can:

- Manage users and their roles
- Manage project codes
- Set up public holidays
- Sync data with Desknet
- View system activity logs
- Configure system settings
- Approve timesheets and OT forms

---

## User Management

### How to Access

1. Click **Admin** → **Users**

### What You Can Do

**View Users**
- See all staff in the system
- Search by name, staff number, or email
- Filter by department or status

**Edit User**
1. Click **Edit** on a user row
2. Update information:
   - Name, email, staff number
   - Department, designation
   - Role (Admin, Manager, Staff, etc.)
   - Reports to (supervisor)
   - Timesheet approver
   - OT approver
   - Status (Active/Inactive)
3. Click **Save**

**View User History**
1. Click **History** on a user row
2. See all timesheets and OT forms submitted by that user

### User Roles

- **Admin** - Full system access
- **CEO** - Can approve timesheets and OT forms
- **Manager_HOD** - Can approve timesheets and OT forms
- **Assistant_Manager** - Limited approval permissions
- **HR** - Review OT Form, View Pending Tracker, View Summary
- **Finance** - View Summary
- **Staff** - Regular user, no approval permissions

---

## Project Codes

### How to Access

1. Click **Admin** → **Project Codes**

### What You Can Do

- View all project codes
- Check sync status with Desknet
- Add or edit project codes manually if needed

**Note:** Project codes are usually synced automatically from Desknet. See [Desknet Sync](#desknet-sync) section.

---

## Project Management

### How to Access

1. Click **Project** → **Projects**
2. Select a project to view details

### What You Can Do

**Create Project**
1. Click **+ New Project**
2. Enter project details:
   - Project name
   - Project code
   - Start date (plan)
   - End date (plan)
   - Budget
   - Value
3. Click **Save**

**Manage Phases**
1. Go to a project's **Schedule** tab
2. Click **+ Add Phase**
3. Enter phase details:
   - Phase name
   - Phase order
   - Plan dates
   - Revise dates (optional)
   - Actual dates (optional)
4. Click **Save**

**Manage Tasks (Subtasks)**
1. Go to a project's **Schedule** tab
2. Click **+ Add Task**
3. Enter task details:
   - Task name
   - Assigned to
   - Weight (for progress calculation)
   - Plan dates
   - Revise dates (optional)
   - Actual dates (optional)
   - Predecessor task (for dependencies)
   - Dependency type (End-to-Start or Start-to-Start)
4. Click **Save**

**Gantt Chart Features**
- **Three-Lane Timeline**: Plan (blue), Revise (orange), Actual (green)
- **Drag to Resize**: Drag bar edges to change dates
- **Drag to Move**: Drag bar center to move entire task
- **Dependency Lines**: Visual connections between dependent tasks
- **Today Indicator**: Vertical line showing current date
- **Progress Shadows**: Colored fill showing today's position on timeline
- **Phase Auto-Update**: Phase dates automatically follow subtask dates

**Important Notes**:
- Phase plan dates automatically update to match the earliest/latest subtask dates
- Subtasks can have plan end dates beyond the current phase end date
- Phase will automatically extend to accommodate the latest subtask
- Progress is calculated based on task weights and completion status
- Dependencies prevent tasks from starting before predecessors complete

---

## Holidays

### How to Access

1. Click **Admin** → **Holidays**

### What You Can Do

**Add Holiday**
1. Click **+ Add Holiday**
2. Enter date, name, and type
3. Click **Save**

**Edit Holiday**
1. Click **Edit** on a holiday row
2. Update details
3. Click **Save**

**Delete Holiday**
1. Click **Delete** on a holiday row
2. Confirm deletion

**Important:** Holiday dates affect timesheet calculations - days marked as holidays automatically show 0 attendance hours.

---

## Desknet Sync

### What is Desknet Sync?

The system can pull staff list and project details from Desknet AppSuite automatically.

### How to Access

1. Click **Admin** → **Desknet Sync**

### What You Can Do

**Test Connection**
1. Click **Test Connection**
2. See if the system can connect to Desknet

**Run Manual Sync**
1. Choose what to sync:
   - **Staff** - Pulls user/staff data
   - **Project Codes** - Pulls project data
   - **All** - Syncs both
2. Click **Run Sync**
3. View results in the sync log

**View Sync Status**
- Last successful sync date/time
- History of sync attempts with status

### Automatic Sync

The system is set to sync automatically every day at 1:00 AM. Your server administrator needs to set up a cron job for this.

### Sync Settings

Configure these in **System Settings**:
- Desknet API URL
- Desknet API Key
- Project Codes App ID (default: 308)
- Staff List App ID (default: 29)

---

## Audit Logs

### How to Access

1. Click **Admin** → **Audit Logs**

### What You Can See

- **Date/Time** - When the action happened
- **User** - Who did it
- **Action** - Created/Updated/Deleted
- **Model** - What was changed (e.g., User #2)
- **Description** - What happened
- **IP Address** - Where it came from

### Filtering

- Filter by action type
- Filter by model type
- Filter by date range

### Why It's Useful

- Track who made changes and when
- Investigate issues
- Security monitoring
- Compliance records

---

## System Settings

### How to Access

1. Click **Account** → **Profile**
2. Click **System Settings** (admin only)

### Settings You Can Change

**Work Hours**
- Default work start time
- Lunch break start/end
- Standard work hours for Monday-Thursday
- Standard work hours for Friday

**Desknet Integration**
- Desknet API URL
- Desknet API Key
- Project Codes App ID
- Staff List App ID
- Enable/disable automatic sync

**Other Settings**
- Additional system configuration options

### Saving Changes

1. Update settings as needed
2. Click **Save All Settings**
3. Changes take effect immediately

**Important:** API keys are sensitive - keep them secure.

---

## Approvals

### Timesheet Approvals

1. Click **Approvals** → **Timesheet Approvals**
2. Click **Review** on a timesheet
3. Review the details
4. Click **Approve** to approve, or **Reject** to return with remarks

### OT Form Approvals

1. Click **Approvals** → **OT Approvals**
2. Click **Review** on an OT form
3. Review the planned vs actual hours
4. Click **Approve** or **Reject** with remarks

### Approval Workflow

- Staff submit timesheets/OT forms
- Approvers review and approve/reject
- Approved items are finalized
- Rejected items return to staff for correction
- All actions are logged in audit logs

---

## Troubleshooting

### Users Cannot Login

1. Check user status is **Active**
2. Verify email and password are correct
3. Check if user account exists
4. Reset password if needed

### Sync Fails

1. Test Desknet connection
2. Verify API credentials
3. Check Desknet External Connection is enabled
4. Review sync logs for errors

### Timesheet Errors

1. Check user has valid project codes
2. Verify attendance PDF is in correct format
3. Check work hours settings
4. Review audit logs

### Approval Issues

1. Verify user has correct approval permissions
2. Check approval routes are configured
3. Ensure approvers are active users
4. Review approval logs

### Getting Help

If you encounter issues:
1. Check audit logs for error details
2. Review system settings
3. Check Desknet sync status
4. Contact IT support with error details

---

## Document Version

Version: 2.0  
Last Updated: August 2026
