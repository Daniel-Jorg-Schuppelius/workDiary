{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : comment-created.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
