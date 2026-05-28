@php
    $statusName = fn ($s) => match ((int) $s) {
        -1 => __('Erledigt'),
        1 => __('Bestätigt'),
        2 => __('Offen'),
        3 => __('Problem'),
        default => '—',
    };
@endphp
@component('mail::message')
# {{ __('Status geändert') }}

{{ __('Der Status von Eintrag #:id wurde geändert:', ['id' => $entry->id]) }}

- **{{ __('Vorher') }}:** {{ $statusName($oldStatus) }}
- **{{ __('Jetzt') }}:** {{ $statusName($newStatus) }}

> {{ truncate($entry->content, 240) }}

@component('mail::button', ['url' => route('diary.show', $entry)])
{{ __('Eintrag öffnen') }}
@endcomponent

{{ __('Viele Grüße') }},<br>
{{ config('app.name') }}
@endcomponent
