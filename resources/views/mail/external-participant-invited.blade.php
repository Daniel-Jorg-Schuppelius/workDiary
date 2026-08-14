{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : external-participant-invited.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('external.mail.heading') }}

{{ __('external.mail.intro', ['name' => $participant->name]) }}

@component('mail::button', ['url' => $accessUrl])
{{ __('external.mail.button') }}
@endcomponent

@component('mail::panel')
{{ __('external.mail.expires', ['date' => optional($participant->expires_at)->format('d.m.Y')]) }}
@endcomponent

{{ __('external.mail.note') }}
@endcomponent
