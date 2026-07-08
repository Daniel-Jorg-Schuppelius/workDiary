@component('mail::message')
# {{ __('external.mail.heading') }}

{{ __('external.mail.intro', ['name' => $participant->name]) }}

@component('mail::button', ['url' => $accessUrl])
{{ __('external.mail.button') }}
@endcomponent

@component('mail::panel')
{{ __('external.mail.expires', ['date' => optional($participant->expires_at)->format('d.m.Y')]) }}
@endcomponent

{{ __('external.mail.note') }}
@endcomponent
