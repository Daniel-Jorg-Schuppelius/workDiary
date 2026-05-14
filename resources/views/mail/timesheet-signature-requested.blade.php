@component('mail::message')
# {{ __('Stundenzettel zur Gegenzeichnung') }}

{{ __('Bitte prüfen und signieren Sie den Stundenzettel:') }}

@component('mail::button', ['url' => $signUrl])
{{ __('Stundenzettel öffnen') }}
@endcomponent

@component('mail::panel')
{{ $timesheet->project?->name }} · {{ optional($timesheet->work_date)->format('d.m.Y') }}
@endcomponent

{{ __('Danke!') }}
@endcomponent
