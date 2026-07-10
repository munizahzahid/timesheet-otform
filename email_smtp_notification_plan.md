# Email SMTP Notification Implementation Plan

## Overview
Implement email notification system using SMTP for the timesheet application. This will allow sending automated emails for various events such as timesheet approvals, rejections, reminders, and other important notifications.

## Prerequisites
- Laravel application already set up
- SMTP credentials (host, port, username, password, encryption)
- Email templates design

## Implementation Steps

### 1. SMTP Configuration
**File:** `.env`
- Add SMTP configuration variables:
  ```
  MAIL_MAILER=smtp
  MAIL_HOST=smtp.gmail.com
  MAIL_PORT=587
  MAIL_USERNAME=tssbingress@gmail.com
  MAIL_PASSWORD=pcws fobr ogab hdna
  MAIL_ENCRYPTION=tls
  MAIL_FROM_ADDRESS=tssbingress@gmail.com
  MAIL_FROM_NAME="${APP_NAME}"
  ```

**File:** `config/mail.php`
- Verify default mailer is set to `smtp`
- Configure mailer settings if needed

### 2. Create Email Templates
**Directory:** `resources/views/emails/`

Create Blade templates for different notification types:
- `timesheet_approved.blade.php` - Notification when timesheet is approved
- `timesheet_rejected.blade.php` - Notification when timesheet is rejected
- `timesheet_reminder.blade.php` - Reminder to submit timesheet
- `timesheet_submission.blade.php` - Confirmation when timesheet is submitted
- `admin_notification.blade.php` - General admin notifications

Template structure:
```html
<!DOCTYPE html>
<html>
<head>
    <style>
        /* Email styling */
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ $subject }}</h1>
        <p>{{ $message }}</p>
        <!-- Additional content -->
    </div>
</body>
</html>
```

### 3. Create Mailable Classes
**Command:** `php artisan make:mail TimesheetApproved`
**Command:** `php artisan make:mail TimesheetRejected`
**Command:** `php artisan make:mail TimesheetReminder`
**Command:** `php artisan make:mail TimesheetSubmission`

**Directory:** `app/Mail/`

Each mailable class should:
- Extend `Illuminate\Mail\Mailable`
- Define public properties for data
- Implement `build()` method to set view and subject
- Include `with()` method to pass data to view

Example:
```php
class TimesheetApproved extends Mailable
{
    public $timesheet;
    public $user;

    public function __construct($timesheet, $user)
    {
        $this->timesheet = $timesheet;
        $this->user = $user;
    }

    public function build()
    {
        return $this->view('emails.timesheet_approved')
                    ->subject('Your Timesheet Has Been Approved')
                    ->with([
                        'timesheet' => $this->timesheet,
                        'user' => $this->user,
                    ]);
    }
}
```

### 4. Create Notification Classes
**Command:** `php artisan make:notification TimesheetApprovedNotification`
**Command:** `php artisan make:notification TimesheetRejectedNotification`
**Command:** `php artisan make:notification TimesheetReminderNotification`

**Directory:** `app/Notifications/`

Each notification class should:
- Extend `Illuminate\Notifications\Notification`
- Implement `via()` method to specify channels (mail, database)
- Implement `toMail()` method to return mailable
- Optionally implement `toDatabase()` for in-app notifications

Example:
```php
class TimesheetApprovedNotification extends Notification
{
    public $timesheet;

    public function __construct($timesheet)
    {
        $this->timesheet = $timesheet;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new TimesheetApproved($this->timesheet, $notifiable))
                    ->to($notifiable->email);
    }

    public function toArray($notifiable)
    {
        return [
            'timesheet_id' => $this->timesheet->id,
            'message' => 'Your timesheet has been approved',
        ];
    }
}
```

### 5. Database for Notifications (Optional)
**Migration:** Create notifications table if using database channel
```bash
php artisan notifications:table
php artisan migrate
```

Add `notifications` relationship to User model:
```php
public function notifications()
{
    return $this->morphMany(Notification::class, 'notifiable')->orderBy('created_at', 'desc');
}
```

### 6. Integrate Notifications into Workflow
**Files to modify:**
- `app/Http/Controllers/TimesheetController.php` (or relevant controller)
- `app/Services/TimesheetService.php` (if using service layer)

Trigger notifications at appropriate points:
- When timesheet is submitted → Send to admin
- When timesheet is approved → Send to staff
- When timesheet is rejected → Send to staff with reason
- When deadline approaches → Send reminder to staff

Example in controller:
```php
public function approve(Request $request, $id)
{
    $timesheet = Timesheet::find($id);
    $timesheet->status = 'approved';
    $timesheet->save();

    $timesheet->user->notify(new TimesheetApprovedNotification($timesheet));

    return response()->json(['message' => 'Timesheet approved']);
}
```

### 7. Queue Configuration (Recommended)
**File:** `.env`
```
QUEUE_CONNECTION=database
```

**Setup queue table:**
```bash
php artisan queue:table
php artisan migrate
```

**Run queue worker:**
```bash
php artisan queue:work
```

Update mailable classes to implement `ShouldQueue`:
```php
class TimesheetApproved extends Mailable implements ShouldQueue
{
    use Queueable;
    // ...
}
```

### 8. Email Testing
**Development testing:**
- Use services like Mailtrap, Mailhog, or Laravel's log driver for testing
- Set in `.env`: `MAIL_MAILER=log` or configure Mailtrap

**Test commands:**
```bash
php artisan tinker
// Test email sending
$user = User::first();
$user->notify(new TimesheetApprovedNotification($timesheet));
```

### 9. Error Handling
- Implement try-catch blocks for email sending
- Log failed email attempts
- Set up retry mechanism for failed emails
- Monitor queue failures

Example:
```php
try {
    $user->notify(new TimesheetApprovedNotification($timesheet));
} catch (\Exception $e) {
    Log::error('Email sending failed: ' . $e->getMessage());
}
```

### 10. User Preferences (Optional Enhancement)
**Migration:** Add email preferences to users table
```php
$table->boolean('email_notifications')->default(true);
$table->json('notification_preferences')->nullable();
```

Check preferences before sending:
```php
if ($user->email_notifications) {
    $user->notify(new TimesheetApprovedNotification($timesheet));
}
```

## Notification Types to Implement

### 1. Timesheet Submission
- **Recipient:** Admin/Manager
- **Trigger:** Staff submits timesheet
- **Content:** Staff name, month/year, total hours, link to review

### 2. Timesheet Approval
- **Recipient:** Staff
- **Trigger:** Admin approves timesheet
- **Content:** Approval confirmation, month/year, total hours

### 3. Timesheet Rejection
- **Recipient:** Staff
- **Trigger:** Admin rejects timesheet
- **Content:** Rejection reason, month/year, corrections needed

### 4. Submission Reminder
- **Recipient:** Staff who haven't submitted
- **Trigger:** Scheduled job (e.g., 3 days before deadline)
- **Content:** Reminder message, deadline date, link to submit

### 5. Overdue Reminder
- **Recipient:** Staff with overdue timesheets
- **Trigger:** Scheduled job (daily after deadline)
- **Content:** Overdue notice, list of missing months

## Scheduled Jobs Setup
**File:** `app/Console/Kernel.php`

Add scheduled commands:
```php
$schedule->command('timesheet:send-reminders')->dailyAt('09:00');
$schedule->command('timesheet:send-overdue-notifications')->dailyAt('10:00');
```

**Create commands:**
```bash
php artisan make:command SendTimesheetReminders
php artisan make:command SendOverdueNotifications
```

## Security Considerations
- Store SMTP credentials securely (use environment variables)
- Validate email addresses before sending
- Rate limit email sending to prevent spam
- Use TLS/SSL encryption
- Implement SPF/DKIM records for domain

## Testing Checklist
- [ ] SMTP configuration verified
- [ ] Email templates render correctly
- [ ] Mailable classes send emails successfully
- [ ] Notifications trigger at correct events
- [ ] Queue processing works
- [ ] Failed emails are logged
- [ ] Scheduled jobs run on time
- [ ] User preferences respected
- [ ] Email links work correctly
- [ ] Mobile email responsiveness tested

## Rollout Plan
1. **Phase 1:** Basic SMTP setup and single notification type (e.g., approval)
2. **Phase 2:** Add remaining notification types
3. **Phase 3:** Implement queue system
4. **Phase 4:** Add scheduled reminders
5. **Phase 5:** User preferences and advanced features

## Maintenance
- Monitor email delivery rates
- Update templates as needed
- Review failed email logs regularly
- Keep SMTP credentials updated
- Test after Laravel updates
