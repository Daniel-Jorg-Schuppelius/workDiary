{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _field_chips.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Feld-Chips eines KI-Strukturvorschlags (Feature 148, MVP-732):
    Kommunikationsnotiz (Betreff/Ergebnis/Folgeaktion) und DMS
    (Dokumentart/Titel/Fristen). Jeder Chip wird EINZELN per Klick über die
    reguläre Fachlogik übernommen — nie Auto-Apply.

    Erwartet: $suggestion (AiTextSuggestion), $chips (list<array{field,label}>)
--}}
@if (($chips ?? []) !== [])
<section class="mt-3 rounded-box border border-info/30 bg-info/5 p-3 space-y-2" aria-label="{{ __('ai.assist.structure_title') }}">
    <header class="flex flex-wrap items-center gap-2 text-xs">
        <x-icon name="smart_toy" class="text-info" />
        <span class="font-semibold">{{ __('ai.assist.structure_title') }}</span>
        <span class="badge badge-outline badge-xs font-mono">{{ $suggestion->provider }}</span>
        @if ($suggestion->fallback_used)
            <span class="badge badge-warning badge-xs" title="{{ __('ai.suggestion.fallback_hint') }}">{{ __('ai.suggestion.fallback') }}</span>
        @endif
        @if ($suggestion->from_cache)
            <span class="badge badge-ghost badge-xs">{{ __('ai.suggestion.cached') }}</span>
        @endif
    </header>
    <div class="flex flex-wrap items-center gap-2">
        @foreach ($chips as $chip)
            <form method="POST" action="{{ route('ai.assist.apply', $suggestion) }}" class="inline">
                @csrf
                <input type="hidden" name="field" value="{{ $chip['field'] }}">
                <button type="submit" class="badge badge-outline badge-info h-auto gap-1 whitespace-normal py-1 text-left transition-colors hover:bg-info hover:text-info-content"
                        title="{{ __('ai.suggestion.accept') }}">
                    <span class="text-muted">{{ __('ai.assist.field.' . $chip['field']) }}:</span>
                    <span>{{ $chip['label'] }}</span>
                </button>
            </form>
        @endforeach
        <form method="POST" action="{{ route('ai.assist.reject', $suggestion) }}" class="inline ml-auto">
            @csrf
            <x-button type="submit" tone="ghost" size="xs">{{ __('ai.suggestion.reject') }}</x-button>
        </form>
    </div>
    <p class="text-xs text-muted">{{ __('ai.assist.structure_hint') }}</p>
</section>
@endif
