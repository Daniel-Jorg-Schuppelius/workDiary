{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : protocol-signature-request.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('protocol.mail.heading') }}

{{ $token->signer_name ? __('protocol.mail.greeting', ['name' => $token->signer_name]) : __('protocol.mail.greeting_plain') }}

{{ __('protocol.mail.intro', ['title' => $protocol->title, 'type' => $protocol->type->label()]) }}

@component('mail::button', ['url' => $signUrl])
{{ __('protocol.mail.button') }}
@endcomponent

@component('mail::panel')
{{ __('protocol.mail.expires', ['date' => $token->expires_at->fdate()]) }}
@endcomponent

{{ __('protocol.mail.note') }}
@endcomponent
