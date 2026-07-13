<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'OT Notification' }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4a5568; color: white; padding: 20px; text-align: center; }
        .content { background-color: #ffffff; padding: 30px; border: 1px solid #e2e8f0; }
        .footer { text-align: center; padding: 20px; color: #718096; font-size: 12px; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #4a5568; color: white; text-decoration: none; border-radius: 4px; }
        .details { background-color: #f7fafc; padding: 15px; margin: 15px 0; border-left: 4px solid #4a5568; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>OT Form Notification</h1>
        </div>
        <div class="content">
            @yield('content')
        </div>
        <div class="footer">
            <p>This is an automated notification from the Timesheet & OT System.</p>
            <p>Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
