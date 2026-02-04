@component('mail::message')
# Welcome to {{ current_tenant()->name ?? config('app.name') }} Portal

Dear {{ $user->name }},

You are registered to access {{ current_tenant()->name ?? config('app.name') }}’s web based document management system for Due Diligence documents. You should receive an email with your login credentials; please also check your spam folders. Otherwise, please use the Login icon at {{ current_tenant()->domain }}/login and follow the prompts.

Your welcome email will include your login detail, including a temporary password which should be changed on the first login.<br>
Username: **{{ $user->email }}**<br>
Temporary Password: **{{ $temporaryPassword }}**<br>
Url: {{ route('login') }}<br>

When registering your new password, it should contain:<br>
•	Minimum 8 characters<br>
•	1 capital letter (alpha character)<br>
•	1 number (numeral character)<br>
•	1 special character (eg. &, #, %, @ etc.)

In case of difficulty, please reset your password using the “forgot your password?” icon.

You may also request access using this link: {{ current_tenant()->domain }}/request-access.

Once registered, you will be able to login and use the filters on the landing page to search for documents. Documents can be viewed or downloaded. This platform will carry the following document types:<br>
•	Pesticide (residue) COAs<br>
•	Certificates<br>
•	Spray programs (PPP)<br>
•	Spray records (PPPL)<br>
•	Dole linked certificates (IFS Broker, ISO 9001:2015 and GlobalGAP Chain of Custody).

We trust you will find this platform useful. In case of any queries, please contact:<br>
•	Support ({{ $tenantSupportEmail }})<br>

Kind Regards,<br>
{{ current_tenant()->name ?? config('app.name') }} Team
@endcomponent