# TSSB Portal User Manual
## Timesheet & OT Form Management System

---

## Table of Contents

1. [Introduction](#introduction)
2. [Login](#login)
3. [Dashboard](#dashboard)
4. [Timesheet](#timesheet)
5. [OT Form](#ot-form)
6. [History](#history)
7. [Approvals](#approvals)
8. [Profile](#profile)
9. [Frequently Asked Questions (FAQ)](#frequently-asked-questions-faq)

---

## Introduction

TSSB Portal is a timesheet and overtime (OT) form management system for Talent Synergy Sdn Bhd. This system allows staff to:
- Manage monthly timesheets
- Submit OT requests
- Track approval status
- Manage personal profiles

---

## Login

### How to Login

1. Open your web browser and go to the system URL
2. Enter your email address
3. Enter your password
4. Click the "Sign In" button

### Security Features

- You only need to type your name before "BIN/BINTI/B/BT" to sign
- The system will automatically complete your full name
- Example: Name "AHMAD FAIZAL BIN ALI" → You only need to type "AHMAD FAIZAL"

---

## Dashboard

The Dashboard is your home page showing a quick summary of your account.

### Dashboard Features

- **Quick Links** - Quick access to Timesheets and OT Forms
- **Summary** - Overview of your timesheets and OT forms
- **Admin Cards** - For admin users, shows active users, project codes, and Desknet sync status

---

## Timesheet

### Creating a New Timesheet

1. Click menu "HR" → "Timesheet"
2. Click button "+ New Timesheet"
3. Select month and year
4. Click "Create"

### Filling in Timesheet

1. Click "Edit" on the timesheet you want to fill in
2. **Upload Attendance** - Upload your Infotech attendance PDF
3. **Admin Hours** - Hours are automatically populated from attendance data
4. **Project Rows** - Add project codes and enter hours:
   - Normal NC (Normal - Non-Chargeable)
   - Normal COBQ (Normal - Chargeable on Bill Quotation)
   - OT NC (Overtime - Non-Chargeable)
   - OT COBQ (Overtime - Chargeable on Bill Quotation)
5. **Auto-Save** - Changes are saved automatically as you type
6. **Submit** - Click "Submit" button to send for approval

### Infotech Attendance PDF Rules

When uploading attendance PDF from Infotech, the system will read attendance data and automatically fill hours based on the following rules:

#### Absent (ABS)
- If there is **no clock out** on that day → considered absent
- If there is **no clock in and clock out** on that day → considered absent
- If the reason on the attendance PDF writes **"ABS"** → absent
- **Action:** Enter **0** on that day's column. No need to fill in any hours

#### Leave (ML / EL / AL)
- If the reason on the attendance PDF writes:
  - **"ML"** — Medical Leave
  - **"EL"** — Emergency Leave
  - **"AL"** — Annual Leave
- **Action:** Enter **7 or 8 hours** on the **MC/LEAVE** row for that day

#### Rules Summary

| Condition | Reason | Action |
|-----------|--------|--------|
| No clock in & clock out | ABS | Enter 0 hours |
| No clock out only | ABS | Enter 0 hours |
| Medical leave | ML | Enter 7/8 hours on MC/LEAVE row |
| Emergency leave | EL | Enter 7/8 hours on MC/LEAVE row |
| Annual leave | AL | Enter 7/8 hours on MC/LEAVE row |
| Normal attendance | — | Hours automatically filled from clock in/out |

### Timesheet Status

- **Draft** - Not yet submitted, still editable
- **Pending** - Waiting for approval
- **Approved** - Approved by supervisor
- **Rejected** - Returned for corrections

### Tips

- Scroll horizontally to see all days of the month
- The summary row shows totals per day
- Make sure project hours match available hours for each day

---

## OT Form

### Creating a New OT Form

1. Click menu "HR" → "OT Form"
2. Click button "+ OT Form Baru"
3. Select:
   - Month and year
   - Form type (Executive / Non-Executive)
   - Company
4. Click "Create"

### Filling in OT Form

1. Click "Edit" on the form you want to fill in
2. **Plan** - Enter planned start/end times for anticipated OT
3. **Auto-Fill Actual** - Click the "Auto-Fill from Attendance" button to populate actual times from your uploaded attendance PDF
   *Note: Make sure you have uploaded the attendance PDF in your timesheet first*
4. **Save** - Click "Save" to save your progress
5. **Submit** - Click "Submit for Approval" when ready
6. **Print** - Use the "Print" button to print the form

### Form Types

- **Executive** - For executive-level staff
- **Non-Executive** - For non-executive staff with additional fields

### OT Form Status

- **Draft** - Not yet submitted
- **Pending** - Waiting for approval
- **Approved** - Approved by manager
- **Rejected** - Rejected with remarks

---

## History

The History page shows all your activities:
- Submitted timesheets
- Submitted OT forms
- Approval status
- Activity dates

---

## Approvals

For managers and admins with approval permissions:

### Timesheet Approvals

1. Click menu "Approvals" → "Timesheet Approvals"
2. Click "Review" to see full timesheet details
3. **Approve** - Sign and approve the timesheet
4. **Reject** - Reject with remarks for the staff to correct

### OT Approvals

1. Click menu "Approvals" → "OT Approvals"
2. Click "Review" to see full OT form details
3. **Approve** - Sign and approve the OT form
4. **Reject** - Reject with remarks for the staff to correct

---

## Profile

### Update Profile

1. Click menu "Account" → "Profile"
2. Click submenu "My Profile"
3. Update your name and email
4. Click "Save"

### Change Password

1. Click menu "Account" → "Profile"
2. Click submenu "My Profile"
3. Password section - Change your login password
4. Enter your current password
5. Enter your new password
6. Confirm your new password
7. Click "Save"

### Delete Account

1. Click menu "Account" → "Profile"
2. Delete Account section - Permanently remove your account
3 *Note: This action cannot be undone*

---

## Frequently Asked Questions (FAQ)

### Q: How to auto-fill OT time from attendance?
A: Make sure you have uploaded the attendance PDF in your timesheet first, then use the "Auto-Fill from Attendance" button in the OT form.

### Q: What is the difference between Executive and Non-Executive?
A: Executive form is for executive-level staff, while Non-Executive form is for non-executive staff with additional fields.

### Q: Can I delete a submitted timesheet?
A: No, only draft timesheets can be deleted.

### Q: How do I know my approval status?
A: Status is shown on the Timesheet, OT Form, and History pages.

### Q: Who can approve timesheets and OT?
A: Managers, GM, CEO, and admins with approval permissions.

### Q: What should I do if my timesheet is rejected?
A: Review the rejection remarks, make the necessary corrections, and resubmit.

### Q: How long does approval take?
A: It depends on your supervisor. Please contact your supervisor if there is a delay.

---

## Technical Support

If you encounter any issues or need assistance:
- Contact IT Support via the help button "?" at the bottom right of the screen
- Send an email to support@talentsynergy.com
- Call IT phone number: [Insert number]

---

## Document Version

Version: 1.0  
Last Updated: April 2026
