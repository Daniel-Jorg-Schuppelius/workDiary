{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dsar-receipt.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('dsar.mail.headline') }}

{{ __('dsar.mail.intro', ['nr' => $requestNumber]) }}

@if ($deadlineDate !== '')
{{ __('dsar.mail.deadline', ['date' => $deadlineDate]) }}
@endif

@component('mail::button', ['url' => $confirmUrl])
{{ __('dsar.mail.confirm_button') }}
@endcomponent

@component('mail::panel')
{{ __('dsar.mail.confirm_note') }}
@endcomponent

{{ __('dsar.mail.not_you') }}

@if ($organizationName !== '')
{{ $organizationName }}
@endif
@endcomponent
