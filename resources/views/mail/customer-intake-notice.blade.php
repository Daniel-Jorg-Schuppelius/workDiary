{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-intake-notice.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('customer_intake.mail.' . $notice . '.heading') }}

{{ __('Hallo :name,', ['name' => $intake->submitter?->name ?? '']) }}

{{ __('customer_intake.mail.' . $notice . '.body', ['number' => $intake->number]) }}

@component('mail::panel')
**{{ __('customer_intake.field.number') }}:** {{ $intake->number }}

**{{ __('customer_intake.field.subject') }}:** {{ $intake->subject }}
@if ($notice === 'rejected' && $intake->rejection_reason !== null)

**{{ __('customer_intake.field.rejection_reason') }}:** {{ $intake->rejection_reason }}
@endif
@endcomponent

@component('mail::button', ['url' => route('customer.intakes.show', $intake)])
{{ __('customer_intake.mail.open_portal') }}
@endcomponent
@endcomponent
