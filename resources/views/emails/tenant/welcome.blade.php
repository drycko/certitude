@component('mail::message')
# Welcome to {{ current_tenant()->name ?? config('app.name') }} Portal

Hello {{ $user->name }},

Your account has been created. Your temporary password is:

**{{ $temporaryPassword }}**

Url: {{ route('login') }}

You will be prompted to change your password upon your first login.

Thanks,<br>
{{ current_tenant()->name ?? config('app.name') }}
@endcomponent