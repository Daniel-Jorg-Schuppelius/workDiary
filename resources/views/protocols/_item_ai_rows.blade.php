{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _item_ai_rows.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Offene KI-Vorschläge eines Protokollpunkts (Feature 143, MVP-711):
    Textvorschlag über <x-ai-suggestion> (übernehmen/bearbeiten/verwerfen)
    und Klassifikations-Chips (Schweregrad/Kategorie/Ergebnis) — jeder Chip
    wird erst per Klick über die reguläre Punkt-Erfassung übernommen.

    Erwartet: $item, $aiTextSuggestions, $aiClassifySuggestions, $aiColumns
--}}
@php
    $aiText = $aiTextSuggestions[$item->id] ?? null;
    $aiClassify = $aiClassifySuggestions[$item->id] ?? null;
    $aiChips = $aiClassify !== null ? \App\Services\Ai\Suggestions\ProtocolTextSuggestionService::classificationValues($aiClassify) : [];
@endphp
@if ($aiText !== null)
    <tr data-ai-suggestion-row>
        <td colspan="{{ $aiColumns }}" class="bg-info/5">
            <x-ai-suggestion
                :original="$aiText->original"
                :suggestion="$aiText->suggestion"
                :provider="$aiText->provider"
                :fallback="$aiText->fallback_used"
                :cached="$aiText->from_cache"
                :accept-action="route('ai.suggestions.accept', $aiText)"
                :reject-action="route('ai.suggestions.reject', $aiText)"
                field-name="text" />
        </td>
    </tr>
@endif
@if ($aiClassify !== null && $aiChips !== [])
    <tr data-ai-classification-row>
        <td colspan="{{ $aiColumns }}" class="bg-info/5">
            <section class="rounded-box border border-info/30 bg-info/5 p-3 space-y-2" aria-label="{{ __('ai.suggestion.classification_title') }}">
                <header class="flex flex-wrap items-center gap-2 text-xs">
                    <x-icon name="smart_toy" class="text-info" />
                    <span class="font-semibold">{{ __('ai.suggestion.classification_title') }}</span>
                    <span class="badge badge-outline badge-xs font-mono">{{ $aiClassify->provider }}</span>
                    @if ($aiClassify->fallback_used)
                        <span class="badge badge-warning badge-xs" title="{{ __('ai.suggestion.fallback_hint') }}">{{ __('ai.suggestion.fallback') }}</span>
                    @endif
                    @if ($aiClassify->from_cache)
                        <span class="badge badge-ghost badge-xs">{{ __('ai.suggestion.cached') }}</span>
                    @endif
                </header>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($aiChips as $chip)
                        <form method="POST" action="{{ route('ai.suggestions.apply', $aiClassify) }}" class="inline">
                            @csrf
                            <input type="hidden" name="kind" value="{{ $chip['kind'] }}">
                            <input type="hidden" name="value" value="{{ $chip['value'] }}">
                            <button type="submit" class="badge badge-outline badge-info gap-1 transition-colors hover:bg-info hover:text-info-content"
                                    title="{{ __('ai.suggestion.accept') }}">
                                <span class="text-muted">{{ __('ai.suggestion.kind.' . $chip['kind']) }}:</span>
                                <span>{{ $chip['label'] }}</span>
                            </button>
                        </form>
                    @endforeach
                    <form method="POST" action="{{ route('ai.suggestions.reject', $aiClassify) }}" class="inline ml-auto">
                        @csrf
                        <x-button type="submit" tone="ghost" size="xs">{{ __('ai.suggestion.reject') }}</x-button>
                    </form>
                </div>
                <p class="text-xs text-muted">{{ __('ai.suggestion.classification_hint') }}</p>
            </section>
        </td>
    </tr>
@endif
