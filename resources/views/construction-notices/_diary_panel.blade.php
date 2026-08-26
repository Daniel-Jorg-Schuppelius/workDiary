{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _diary_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  VOB/B-Schreiben zum Tagebucheintrag (Feature 062, MVP-728): der Eintrag ist
  der Anlass, das Schreiben der förmliche Nachweis. Nur sichtbar, wenn es
  welche gibt — der Anlegen-Weg bleibt die eigene Liste.
--}}
@php
    $constructionNotices = $diary->constructionNotices()->orderByDesc('occurred_on')->get();
@endphp

@if ($constructionNotices->isNotEmpty())
    <x-card class="mt-4">
        <h3 class="text-sm font-semibold mb-2">{{ __('construction.title') }}</h3>
        <ul class="space-y-1 text-sm">
            @foreach ($constructionNotices as $constructionNotice)
                <li class="flex flex-wrap items-center gap-2">
                    <a class="link font-medium" href="{{ route('construction-notices.show', $constructionNotice) }}">{{ $constructionNotice->displayNo() }}</a>
                    <span>{{ $constructionNotice->kind->label() }}</span>
                    <span class="text-base-content/70">{{ $constructionNotice->subject }}</span>
                    <x-status-badge :tone="$constructionNotice->isEditable() ? 'ghost' : 'success'" size="sm">{{ $constructionNotice->status->label() }}</x-status-badge>
                    @if ($constructionNotice->claims_time_extension)
                        <x-status-badge tone="warning" size="sm" outline>{{ __('construction.badge.time_extension') }}</x-status-badge>
                    @endif
                </li>
            @endforeach
        </ul>
        <p class="mt-2 text-xs text-muted">{{ __('construction.note.time_extension_short') }}</p>
    </x-card>
@endif
