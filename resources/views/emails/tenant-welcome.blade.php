<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .content { background-color: #f9fafb; padding: 30px; }
        .credentials { background-color: white; padding: 20px; margin: 20px 0; border: 2px solid #6366f1; border-radius: 8px; }
        .button { display: inline-block; padding: 12px 30px; background-color: #6366f1; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .warning { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {{ config('app.name') }}!</h1>
        </div>
        <div class="content">
            <p>Hello {{ $contactPerson }},</p>
            
            <p>Your {{ config('app.name') }} account has been successfully created for <strong>{{ $tenantName }}</strong>.</p>
            
            <div class="credentials">
                <h3 style="margin-top: 0;">Your Login Credentials</h3>
                <p><strong>Login URL:</strong> <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>
                <p><strong>Email:</strong> {{ $email }}</p>
                <p><strong>Temporary Password:</strong> <code style="background: #e5e7eb; padding: 5px 10px; border-radius: 3px; font-size: 14px;">{{ $tempPassword }}</code></p>
                <p><strong>Domain:</strong> {{ $domain }}</p>
                <p><strong>Plan:</strong> {{ $plan }}</p>
            </div>

            <div class="warning">
                <strong>Important:</strong> Please change your password immediately after your first login for security purposes.
            </div>

            <div style="text-align: center;">
                <a href="{{ $loginUrl }}" class="button">Login Now</a>
            </div>

            <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>

            <p>Best regards,<br>The {{ config('app.name') }} Team</p>
        </div>
        <div class="footer">
            <p>This email was sent to {{ $email }}</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
