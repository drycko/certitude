@component('mail::message')
# Access Request Denied

Hello {{ $user->name }},

Your request for access to {{ current_tenant()->name ?? config('app.name') }} has been denied.

If you believe this is a mistake or have any questions, please feel free to contact our support team at {{ $tenantSupportEmail }} for further assistance.

Thanks,<br>
{{ current_tenant()->name ?? config('app.name') }}
@endcomponent