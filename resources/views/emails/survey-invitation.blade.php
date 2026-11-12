@component('mail::message')
# {{ $survey->title }}

{{ $survey->purpose ?: __('Wir würden uns über Ihre Rückmeldung freuen — es dauert nur wenige Minuten.') }}

@component('mail::button', ['url' => $url])
{{ __('Zur Umfrage') }}
@endcomponent

{{ __('Der Link ist 30 Tage gültig und nur einmal verwendbar.') }}
@if ($survey->anonymous)
{{ __('Ihre Antworten werden anonym gespeichert und sind nicht auf Sie rückführbar.') }}
@endif
@if ($optOutUrl ?? null)

{{ __('Sie möchten keine Umfragen mehr von uns erhalten?') }} [{{ __('Umfragen abbestellen') }}]({{ $optOutUrl }})
@endif
@endcomponent
