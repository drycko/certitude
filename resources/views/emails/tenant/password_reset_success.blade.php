@component('mail::message')
# Password Reset Successful

Hello {{ $user->name }},

Your password has been successfully reset. You can now log in with your new password.

Thanks,
{{ current_tenant()->name ?? config('app.name') }}
@endcomponent