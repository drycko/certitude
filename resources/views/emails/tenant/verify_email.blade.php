@component('mail::message')
# Verify Your Email Address
Hello {{ $user->name }},
Please click the button below to verify your email address.
@endcomponent
{{-- @component('mail::button', ['url' => route('verification.verify', $user->id)])
Verify Email Address
@endcomponent --}}

@endcomponent