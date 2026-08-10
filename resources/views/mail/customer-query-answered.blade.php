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
