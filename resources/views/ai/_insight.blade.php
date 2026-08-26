{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _insight.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Offene KI-Einschätzung der Wellen 2/3 (Feature 148, MVP-732).

    Reine Lesehilfen (Portal-Rückfrage verstehen, Plan-Ist erklären,
    Support-Diagnose erklären) haben KEIN Schreibziel — sie zeigen nur
    „verwerfen". Capabilities mit Übernahme (Belegsprache-Übersetzung,
    Kurznarrativ → Kommentar) übergeben zusätzlich $acceptAction.

    Erwartet: $suggestion (AiTextSuggestion)
    Optional: $acceptAction (string|null), $showOriginal (bool)
--}}
@php
    $insightAccept = $acceptAction ?? null;
    $insightOriginal = ($showOriginal ?? false) ? $suggestion->original : null;
@endphp
<x-ai-suggestion
    class="mt-3"
    :original="$insightOriginal"
    :suggestion="$suggestion->suggestion"
    :provider="$suggestion->provider"
    :fallback="$suggestion->fallback_used"
    :cached="$suggestion->from_cache"
    :accept-action="$insightAccept"
    :reject-action="route('ai.assist.reject', $suggestion)"
    :editable="$insightAccept !== null"
    :hint="__('ai.assist.insight_hint')"
    field-name="text" />
