{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : management-digest.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('reporting.management.title') }}

{{ __('Hallo :name,', ['name' => $recipientName]) }}

{{ __('reporting.management.digest.intro', ['range' => $rangeLabel]) }}

@component('mail::button', ['url' => route('reports.management')])
{{ __('reporting.management.digest.action') }}
@endcomponent
@endcomponent
