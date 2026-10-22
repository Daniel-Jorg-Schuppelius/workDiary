@component('mail::message')
@if ($request->status === \App\Enums\Calendar\AppointmentRequestStatus::Proposed)
# {{ __('appointment.mail.proposed_title') }}

{{ __('appointment.mail.proposed_body', ['service' => $request->service_label, 'wish' => $request->start_at?->orgTz()->format('d.m.Y H:i'), 'date' => $request->proposed_start_at?->orgTz()->format('d.m.Y H:i')]) }}

@if ($request->proposal_note)
{{ $request->proposal_note }}
@endif

@if ($request->portal_user_id)
@component('mail::button', ['url' => route('customer.appointments.index')])
{{ __('appointment.mail.proposed_action') }}
@endcomponent
@endif
@elseif ($request->status === \App\Enums\Calendar\AppointmentRequestStatus::Confirmed)
# {{ $rescheduled ? __('appointment.mail.rescheduled_title') : __('Ihr Termin ist bestätigt') }}

{{ __(':service am :date Uhr.', ['service' => $request->service_label, 'date' => $request->start_at?->orgTz()->format('d.m.Y H:i')]) }}

{{ __('Der Termin liegt als Kalenderdatei bei — ein Klick, und er steht in Ihrem Kalender.') }}
@else
# {{ __('Ihr Terminwunsch lässt sich leider nicht einrichten') }}

{{ __(':service am :date Uhr.', ['service' => $request->service_label, 'date' => $request->start_at?->orgTz()->format('d.m.Y H:i')]) }}

@if ($request->decline_reason)
{{ __('Grund: :reason', ['reason' => $request->decline_reason]) }}
@endif

{{ __('Gern können Sie im Portal ein anderes Zeitfenster anfragen.') }}
@endif
@endcomponent
