{{--
  Created on   : Fri Oct 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : four-eyes-note.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Hinweis in den Dialogen der Direktbuchungen (Phase 137, E9): Bei aktivem
  Vier-Augen-Prinzip entsteht ein Entwurf zur Freigabe statt einer Festbuchung.
  Rendert ohne Vier-Augen-Prinzip nichts.
--}}
@props(['text' => null])

@if ((bool) \App\Support\Setting::get(\App\Services\Accounting\Posting\PostingInboxService::FOUR_EYES_KEY, false))
    <div {{ $attributes->merge(['class' => 'alert bg-info/10 border-info/30 text-sm text-base-content']) }} role="note">
        <x-icon name="groups" />
        <span>{{ $text ?? __('accounting.inbox.four_eyes_direct_hint') }}</span>
    </div>
@endif
