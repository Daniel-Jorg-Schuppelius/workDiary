{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : timesheet-signature-requested.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('Stundenzettel zur Gegenzeichnung') }}

{{ __('Bitte prüfen und signieren Sie den Stundenzettel:') }}

@component('mail::button', ['url' => $signUrl])
{{ __('Stundenzettel öffnen') }}
@endcomponent

@component('mail::panel')
{{ $timesheet->project?->name }} · {{ optional($timesheet->work_date)->fdate() }}
@endcomponent

{{ __('Danke!') }}
@endcomponent
