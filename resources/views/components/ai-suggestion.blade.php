{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : ai-suggestion.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    <x-ai-suggestion> — die einheitliche „KI-Vorschlag"-Komponente
    (Feature 025, MVP-400; UX-Pattern-Katalog). Zeigt Original und
    Vorschlag nebeneinander, kennzeichnet Herkunft (Provider, Fallback,
    Cache) und bietet übernehmen / bearbeiten + übernehmen / verwerfen.
    KI schreibt nie still: erst der Submit des Übernehmen-Formulars
    ändert Fachdaten. Capability-Consumer (z. B. Feature 084) übergeben
    die Formular-Ziele; ohne acceptAction ist die Komponente rein
    darstellend.

    Props:
      original      → bisheriger Text (null = keine Gegenüberstellung)
      suggestion    → vorgeschlagener Text (Pflicht)
      provider      → Anzeigename der Provider-Verbindung
      fallback      → true, wenn die Fallback-Kette griff (sichtbar!)
      cached        → true, wenn aus dem Ergebnis-Cache
      acceptAction  → Route für „übernehmen" (POST; Feld $fieldName)
      rejectAction  → Route für „verwerfen" (POST)
      fieldName     → Name des Textfelds im Übernehmen-Formular
      editable      → true = Vorschlag vor Übernahme editierbar
      hint          → Fußnote für Vorschläge ohne Übernahme (Einsichten)
--}}
@props([
    'original' => null,
    'suggestion' => '',
    'provider' => null,
    'fallback' => false,
    'cached' => false,
    'acceptAction' => null,
    'rejectAction' => null,
    'fieldName' => 'suggestion',
    'editable' => true,
    'hint' => null,
])

@php $suggestionId = 'ai-suggestion-' . \Illuminate\Support\Str::random(6); @endphp

<section {{ $attributes->class(['rounded-box border border-info/30 bg-info/5 p-3 space-y-2']) }}
         aria-label="{{ __('ai.suggestion.title') }}">
    <header class="flex flex-wrap items-center gap-2 text-xs">
        <x-icon name="smart_toy" class="text-info" />
        <span class="font-semibold">{{ __('ai.suggestion.title') }}</span>
        @if ($provider !== null)
            <span class="badge badge-outline badge-xs font-mono">{{ $provider }}</span>
        @endif
        @if ($fallback)
            <span class="badge badge-warning badge-xs" title="{{ __('ai.suggestion.fallback_hint') }}">{{ __('ai.suggestion.fallback') }}</span>
        @endif
        @if ($cached)
            <span class="badge badge-ghost badge-xs">{{ __('ai.suggestion.cached') }}</span>
        @endif
    </header>

    <div class="grid grid-cols-1 gap-2 {{ $original !== null ? 'md:grid-cols-2' : '' }}">
        @if ($original !== null)
            <div>
                <div class="text-xs text-muted">{{ __('ai.suggestion.original') }}</div>
                <div class="rounded bg-base-200/60 p-2 text-sm whitespace-pre-wrap">{{ $original }}</div>
            </div>
        @endif
        <div>
            <div class="text-xs text-muted">{{ __('ai.suggestion.proposal') }}</div>
            @if ($acceptAction !== null && $editable)
                <textarea id="{{ $suggestionId }}" name="{{ $fieldName }}" form="{{ $suggestionId }}-accept"
                          rows="3" class="textarea textarea-bordered w-full text-sm">{{ $suggestion }}</textarea>
            @else
                <div class="rounded bg-base-100 border border-base-200 p-2 text-sm whitespace-pre-wrap">{{ $suggestion }}</div>
            @endif
        </div>
    </div>

    {{-- MVP-732: Einsichten (Welle 2/3) haben kein Schreibziel — sie zeigen nur
         „verwerfen". Der Fuß erscheint daher schon bei rejectAction allein. --}}
    @if ($acceptAction !== null || $rejectAction !== null)
        <footer class="flex flex-wrap items-center justify-end gap-2">
            @if ($rejectAction !== null)
                <form method="POST" action="{{ $rejectAction }}" class="inline">
                    @csrf
                    <x-button type="submit" tone="ghost" size="xs">{{ __('ai.suggestion.reject') }}</x-button>
                </form>
            @endif
            @if ($acceptAction !== null)
                <form method="POST" action="{{ $acceptAction }}" class="inline" id="{{ $suggestionId }}-accept">
                    @csrf
                    @unless ($editable)
                        <input type="hidden" name="{{ $fieldName }}" value="{{ $suggestion }}">
                    @endunless
                    <x-button type="submit" tone="primary" size="xs" icon="check">{{ __('ai.suggestion.accept') }}</x-button>
                </form>
            @endif
        </footer>
        @if ($acceptAction !== null)
            <p class="text-right text-xs text-muted">{{ __('ai.suggestion.edit_hint') }}</p>
        @elseif ($hint !== null)
            <p class="text-right text-xs text-muted">{{ $hint }}</p>
        @endif
    @endif
</section>
