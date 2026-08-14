{{--
  Created on   : Mon Aug 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-query-answered.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('Antwort auf Ihre Rückfrage') }}

{{ __('Hallo :name,', ['name' => $query->asker_name ?? '']) }}

{{ __('zu Ihrer Rückfrage liegt eine Antwort vor:') }}

@component('mail::panel')
**{{ __('Ihre Rückfrage') }}:** {{ $query->question }}

**{{ __('Antwort') }}:** {{ $query->answer }}
@endcomponent

{{ __('Details finden Sie im Kundenportal unter „Rückfragen".') }}
@endcomponent
