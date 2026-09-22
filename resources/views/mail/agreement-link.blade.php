{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : agreement-link.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('contract-signing.mail.' . $purpose->value . '.heading', ['kind' => $contract?->kind->label()]) }}

{{ __('contract-signing.mail.' . $purpose->value . '.body', ['organization' => $contract?->organization?->name, 'title' => $contract?->title]) }}

@component('mail::button', ['url' => $url])
{{ __('contract-signing.mail.' . $purpose->value . '.button') }}
@endcomponent

@component('mail::panel')
{{ $contract?->kind->label() }} · {{ $contract?->number }} · {{ $revision->label() }}
{{ __('contract-signing.link.expires', ['at' => $expiresAt->fdatetime()]) }}
@endcomponent

{{ __('contract-signing.mail.footer') }}
@endcomponent
