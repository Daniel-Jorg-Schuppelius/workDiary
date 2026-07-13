{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : time-export-delivery.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('wage_types.mail.heading') }}

{{ __('wage_types.mail.body', ['profile' => $export->profile, 'period' => $export->periodLabel()]) }}

@component('mail::panel')
{{ __('wage_types.mail.meta', ['rows' => $export->rows_count, 'hash' => (string) $export->payload_hash]) }}
@endcomponent
@endcomponent
