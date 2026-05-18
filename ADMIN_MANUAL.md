# TSSB Portal Admin Manual
## Timesheet & OT Form Management System - Administrator Guide

---

## Table of Contents

1. [Introduction](#introduction)
2. [User Management](#user-management)
3. [Project Codes](#project-codes)
4. [Holidays](#holidays)
5. [Desknet Sync](#desknet-sync)
6. [Audit Logs](#audit-logs)
7. [System Settings](#system-settings)
8. [Approvals](#approvals)
9. [Troubleshooting](#troubleshooting)

---

## Introduction

This admin manual is for system administrators who manage the TSSB Portal. As an admin, you have access to:

- User management and role assignment
- Project code management
- Holiday management
- Desknet integration and sync
- System audit logs
- System configuration settings
- Approval workflow management

---

## User Management

### Access User Management

1. Click menu "Admin" → "Users"
2. View all users in the system

### User List Features

The user list displays:
- **Staff No** - Employee staff number
- **Name** - User name and email
- **Department** - User's department
- **Designation** - Job title
- **Reports To** - Direct supervisor
- **TS Approver** - Timesheet approver (specific or role-based)
- **OT Exec** - OT form approver for executive staff
- **OT Non-Exec** - OT form approver for non-executive staff
- **Status** - Active/Inactive status

### Filtering Users

- **Search** - Filter by name, staff no, or email
- **Department** - Filter by department
- **Status** - Filter by active/inactive

### Editing Users

1. Click "Edit" on a user row
2. Update user information:
   - Name
   - Email
   - Staff No
   - Department
   - Designation
   - Role (Admin, Manager_HOD, Assistant_Manager, Staff, CEO)
   - Reports To (supervisor)
   - Timesheet Approver (specific user or role-based)
   - OT Approver - Executive (specific user or role-based)
   - OT Approver - Non-Executive (specific user or role-based)
   - Document Type Approvers (for different document types)
   - Status (Active/Inactive)
3. Click "Save"

### Viewing User History

1. Click "History" on a user row
2. View all timesheets and OT forms submitted by that user
3. Click on any item to view details

### User Roles

- **Admin** - Full system access, can manage all settings and users
- **CEO** - Can approve timesheets and OT forms
- **Manager_HOD** - Can approve timesheets and OT forms
- **Assistant_Manager** - Limited approval permissions
- **Staff** - Regular user, no approval permissions

### Approval Routes

Users can have specific approvers assigned or use role-based approval:
- **Role-based** - Approvers determined by user role hierarchy
- **Specific** - Specific user assigned as approver

---

## Project Codes

### Access Project Codes

1. Click menu "Admin" → "Project Codes"
2. View all project codes in the system

### Project Code List

The list displays:
- **Code** - Project code identifier
- **Name** - Project name
- **Status** - Active/Inactive
- **Last Synced** - Last sync date from Desknet

### Managing Project Codes

Project codes are typically synced from Desknet. However, you can:
- View all project codes
- Check sync status
- Manually add/edit project codes if needed

### Desknet Sync

Project codes are automatically synced from Desknet. See [Desknet Sync](#desknet-sync) section for details.

---

## Holidays

### Access Holidays

1. Click menu "Admin" → "Holidays"
2. View all public holidays

### Holiday List

The list displays:
- **Date** - Holiday date
- **Name** - Holiday name
- **Type** - Public holiday type
- **Status** - Active/Inactive

### Adding Holidays

1. Click "+ Add Holiday"
2. Enter holiday details:
   - Date
   - Name
   - Type
3. Click "Save"

### Editing Holidays

1. Click "Edit" on a holiday row
2. Update holiday details
3. Click "Save"

### Deleting Holidays

1. Click "Delete" on a holiday row
2. Confirm deletion

Holiday dates affect timesheet calculations - days marked as holidays automatically show 0 attendance hours.

---

## Desknet Sync

### Access Desknet Sync

1. Click menu "Admin" → "Desknet Sync"
2. View sync status and logs

### Sync Features

The Desknet sync integrates with Desknet AppSuite to:
- **Sync Staff List** - Import users, departments, and designations
- **Sync Project Codes** - Import project codes

### Sync Status

The dashboard shows:
- **Last Staff Sync** - Last successful staff sync date/time
- **Last Project Sync** - Last successful project codes sync date/time
- **Sync Logs** - History of sync attempts with status

### Testing Connection

1. Click "Test Connection" button
2. The system will test the Desknet API connection
3. Results show:
   - Connection status (OK/Failed)
   - HTTP status code
   - Error details if failed

### Running Manual Sync

1. Select sync type:
   - **Staff** - Sync staff list only
   - **Project Codes** - Sync project codes only
   - **All** - Sync both staff and project codes
2. Click "Run Sync"
3. Monitor sync progress in the logs

### Sync Configuration

Desknet sync settings are configured in System Settings:
- **Desknet API URL** - Desknet API endpoint
- **Desknet API Key** - Authentication key
- **Project Codes App ID** - Desknet app ID for project codes (default: 308)
- **Staff List App ID** - Desknet app ID for staff list (default: 29)

### Troubleshooting Desknet Sync

**HTTP 403 Error:**
- Invalid API key or Desknet External Connection disabled
- Verify API key in System Settings
- Check Desknet External Connection Settings in Desknet admin panel
- Ensure your server IP is whitelisted in Desknet

**Connection Timeout:**
- Check network connectivity to Desknet server
- Verify API URL is correct
- Check firewall settings

**Sync Partial Failure:**
- Check sync logs for specific errors
- Verify app IDs are correct
- Check Desknet database for missing records

---

## Audit Logs

### Access Audit Logs

1. Click menu "Admin" → "Audit Logs"
2. View all system activity logs

### Audit Log Features

The audit log displays:
- **Date/Time** - When the action occurred
- **User** - Who performed the action (name and email)
- **Action** - Type of action (Created/Updated/Deleted)
- **Model** - What was changed (e.g., User #2, Timesheet #15)
- **Description** - What happened
- **IP Address** - Where the action originated

### Filtering Audit Logs

- **Action** - Filter by action type (created/updated/deleted)
- **Model Type** - Filter by model type (User, Timesheet, etc.)
- **Date Range** - Filter by date range

### Audit Log Benefits

- **Security Monitoring** - Track who made changes and when
- **Accountability** - Know exactly which user performed each action
- **Troubleshooting** - Investigate issues by seeing what changed
- **Compliance** - Maintain audit trail for regulatory requirements
- **Fraud Detection** - Identify suspicious activity patterns
- **Forensic Analysis** - Trace back changes to identify issues

### Currently Tracked Models

- **User** - User creation, updates, and deletion

---

## System Settings

### Access System Settings

1. Click menu "Account" → "Profile"
2. Click submenu "System Settings" (admin only)

### Settings Groups

#### Work Hours
- **Default Work Start Time** - Default work day start time
- **Lunch Break Start** - Lunch break start time
- **Lunch Break End** - Lunch break end time
- **Default Hours (Mon-Thu)** - Standard work hours Monday-Thursday
- **Default Hours (Friday)** - Standard work hours Friday

#### Desknet Integration
- **Desknet API URL** - Desknet API endpoint
- **Desknet API Key** - Authentication key (masked)
- **Project Codes App ID** - Desknet app ID for project codes
- **Staff List App ID** - Desknet app ID for staff list
- **Desknet Sync Enabled** - Enable/disable automatic sync

#### Other Settings
- Additional system configuration options

### Saving Settings

1. Update settings as needed
2. Click "Save All Settings"
3. Settings are applied immediately

### Important Notes

- API keys are sensitive - keep them secure
- Work hours affect timesheet calculations
- Desknet settings must match your Desknet configuration

---

## Approvals

### Access Approvals

Admins with approval permissions can access:

1. **Timesheet Approvals** - Click menu "Approvals" → "Timesheet Approvals"
2. **OT Approvals** - Click menu "Approvals" → "OT Approvals"

### Timesheet Approvals

1. View pending timesheets
2. Click "Review" to see full details
3. Review timesheet data:
   - Attendance hours
   - Project hours
   - Total hours
4. **Approve** - Sign and approve the timesheet
5. **Reject** - Reject with remarks for staff to correct

### OT Approvals

1. View pending OT forms
2. Click "Review" to see full details
3. Review OT form data:
   - Planned vs actual hours
   - Total OT hours
   - Supporting documents
4. **Approve** - Sign and approve the OT form
5. **Reject** - Reject with remarks for staff to correct

### Approval Workflow

- Staff submit timesheets/OT forms
- Approvers review and approve/reject
- Approved items are finalized
- Rejected items return to staff for correction
- Audit logs track all approval actions

---

## Troubleshooting

### Common Issues

#### Users Cannot Login

1. Check user status is Active
2. Verify email and password are correct
3. Check if user account exists in system
4. Reset password if needed

#### Sync Fails

1. Test Desknet connection
2. Verify API credentials
3. Check Desknet External Connection is enabled
4. Review sync logs for specific errors

#### Timesheet Errors

1. Check user has valid project codes assigned
2. Verify attendance PDF is in correct format
3. Check work hours settings are correct
4. Review audit logs for error details

#### Approval Issues

1. Verify user has correct approval permissions
2. Check approval routes are configured
3. Ensure approvers are active users
4. Review approval logs for issues

### Getting Help

If you encounter issues:
1. Check audit logs for error details
2. Review system settings configuration
3. Check Desknet sync status
4. Contact IT support with error details

---

## Document Version

Version: 1.0  
Last Updated: May 2026