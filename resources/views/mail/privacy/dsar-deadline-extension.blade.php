{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dsar-deadline-extension.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('dsar.extension_mail.headline') }}

{{ __('dsar.extension_mail.intro', ['nr' => $requestNumber, 'date' => $deadlineDate]) }}

@component('mail::panel')
{{ __('dsar.extension_mail.reason') }}

{{ $reason }}
@endcomponent

{{ __('dsar.extension_mail.rights') }}

@if ($organizationName !== '')
{{ $organizationName }}
@endif
@endcomponent
