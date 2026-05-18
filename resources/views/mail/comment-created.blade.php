@component('mail::message')
# {{ __('Neuer Kommentar') }}

{{ __(':name hat einen Kommentar zu Eintrag #:id hinterlassen:', [
    'name' => optional($comment->user)->name ?? __('Unbekannt'),
    'id' => $comment->commentable_id,
]) }}

> {{ $comment->body }}

@component('mail::button', ['url' => route('diary.show', $comment->commentable_id) . '#comments'])
{{ __('Eintrag öffnen') }}
@endcomponent

{{ __('Viele Grüße') }},<br>
{{ config('app.name') }}
@endcomponent
