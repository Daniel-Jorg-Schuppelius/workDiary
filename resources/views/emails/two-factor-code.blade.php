<x-mail::message>
# {{ __('Bestätigungscode') }}

{{ __('Verwenden Sie diesen Code, um Ihre Anmeldung zu bestätigen:') }}

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

{{ __('Der Code ist :minutes Minuten gültig. Wenn Sie diese Anmeldung nicht ausgelöst haben, ändern Sie bitte umgehend Ihr Passwort.', ['minutes' => $validMinutes]) }}

{{ __('Viele Grüße') }}<br>
{{ config('app.name') }}
</x-mail::message>
