{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _notices.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Transparenz der Anfrage: ersetzte Tippfehler, mitgesuchte Synonyme,
     ignorierte Füllwörter. Erwartet: $parsed (ParsedSearchQuery). --}}
@if ($parsed->corrections !== [] || $parsed->synonyms !== [] || $parsed->ignored !== [])
    <div class="space-y-1" role="status">
        @foreach ($parsed->corrections as $word => $candidates)
            <div class="alert alert-warning py-2 text-sm">
                <x-icon name="spellcheck" />
                <span>{{ __('search.notice.corrections', ['word' => $word, 'candidates' => implode(', ', $candidates)]) }}</span>
            </div>
        @endforeach
        @if ($parsed->synonyms !== [])
            <p class="text-xs text-muted">
                {{ __('search.notice.synonyms', ['list' => collect($parsed->synonyms)->map(static fn (array $synonyms, string $term): string => $term . ' → ' . implode(', ', array_unique($synonyms)))->implode('; ')]) }}
            </p>
        @endif
        @if ($parsed->ignored !== [])
            <p class="text-xs text-muted">{{ __('search.notice.ignored', ['words' => implode(', ', $parsed->ignored)]) }}</p>
        @endif
    </div>
@endif
