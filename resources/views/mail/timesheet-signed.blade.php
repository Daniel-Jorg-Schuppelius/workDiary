{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : timesheet-signed.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('Stundenzettel signiert') }}

{{ __('Der Stundenzettel wurde signiert. Das PDF finden Sie im Anhang.') }}

@component('mail::panel')
{{ $timesheet->project?->name }} · {{ optional($timesheet->work_date)->fdate() }}
@endcomponent
@endcomponent
