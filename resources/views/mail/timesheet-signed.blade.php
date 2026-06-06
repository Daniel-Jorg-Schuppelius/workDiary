@component('mail::message')
# {{ __('Stundenzettel signiert') }}

{{ __('Der Stundenzettel wurde signiert. Das PDF finden Sie im Anhang.') }}

@component('mail::panel')
{{ $timesheet->project?->name }} · {{ optional($timesheet->work_date)->fdate() }}
@endcomponent
@endcomponent
