<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #6366f1; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9fafb; padding: 30px; }
        .detail { margin: 10px 0; padding: 10px; background-color: white; border-left: 3px solid #6366f1; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Tenant Created</h1>
        </div>
        <div class="content">
            <p>A new tenant has been created in {{ config('app.name') }}:</p>
            
            <div class="detail">
                <strong>Tenant Name:</strong> {{ $tenantName }}
            </div>
            <div class="detail">
                <strong>Email:</strong> {{ $tenantEmail }}
            </div>
            <div class="detail">
                <strong>Domain:</strong> {{ $tenantDomain }}
            </div>
            <div class="detail">
                <strong>Plan:</strong> {{ ucfirst($tenantPlan) }}
            </div>
            <div class="detail">
                <strong>Created At:</strong> {{ $createdAt }}
            </div>
        </div>
        <div class="footer">
            <p>This is an automated notification from {{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>
