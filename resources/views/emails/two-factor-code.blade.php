{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : two-factor-code.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
