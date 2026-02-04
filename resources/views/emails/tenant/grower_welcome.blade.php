@component('mail::message')
# Welcome to {{ current_tenant()->name ?? config('app.name') }} Portal

Dear {{ $user->name }},

You are registered to access {{ current_tenant()->name ?? config('app.name') }}'s web-based document management system for Quality Reports. You should receive an email with your login credentials; please also check your spam folders. Otherwise, please use the Login icon at {{ current_tenant()->domain }}/login and follow the prompts.
Your welcome email will include your login detail, including a temporary password which should be changed on the first login.

**Username:** {{ $user->email }}  
**Password:** {{ $temporaryPassword }}

When registering your new password, it should contain:
- Minimum 8 characters
- 1 capital letter (alpha character)
- 1 number (numeral character)
- 1 special character (eg. &, #, %, @ etc.)

In case of difficulty, please reset your password using the "forgot your password?" icon.

You may also request access using this link: {{ route('access.request.create') }}

Once registered, you will be able to login and use the filters, sort filters and search fields on the landing page to search for quality events and related documents. Documents can be viewed or downloaded.

We trust you will find this platform useful. In case of any queries, please contact the support inbox ({{ $tenantSupportEmail }}).

Kind Regards,  
{{ current_tenant()->name ?? config('app.name') }} Team
@endcomponent
