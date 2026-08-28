<x-mail::message>
# Welcome to American Home Coverage

Hi {{ $client->first_name }},

Thank you for choosing American Home Coverage. Your protection plan is now active.

**Policy number:** {{ $client->policy_number }}

You can sign in to your customer portal with the email address on this order and
your policy number as the password.

<x-mail::button :url="route('login.user')">
Go to your account
</x-mail::button>

If you have any questions, just reply to this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
