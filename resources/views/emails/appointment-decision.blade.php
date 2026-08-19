@component('mail::message')
@if ($request->status === \App\Models\AppointmentRequest::STATUS_CONFIRMED)
# {{ __('Ihr Termin ist bestätigt') }}

{{ __(':service am :date Uhr.', ['service' => $request->service_label, 'date' => $request->start_at?->format('d.m.Y H:i')]) }}

{{ __('Der Termin liegt als Kalenderdatei bei — ein Klick, und er steht in Ihrem Kalender.') }}
@else
# {{ __('Ihr Terminwunsch lässt sich leider nicht einrichten') }}

{{ __(':service am :date Uhr.', ['service' => $request->service_label, 'date' => $request->start_at?->format('d.m.Y H:i')]) }}

@if ($request->decline_reason)
{{ __('Grund: :reason', ['reason' => $request->decline_reason]) }}
@endif

{{ __('Gern können Sie im Portal ein anderes Zeitfenster anfragen.') }}
@endif
@endcomponent
