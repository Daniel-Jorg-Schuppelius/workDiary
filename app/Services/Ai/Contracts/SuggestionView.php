<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SuggestionView.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use Illuminate\Support\Collection;

/** Sichtdaten offener KI-Vorschläge für Oberflächen anderer Module (MVP-863); Null-Bindung: nichts nutzbar, keine Vorschläge. */
interface SuggestionView {
    public function capabilityUsable(string $capability): bool;

    /**
     * @param  Collection<int, covariant \Illuminate\Database\Eloquent\Model>  $items
     * @return Collection<int, \App\Models\Ai\AiTextSuggestion> Subjekt-ID → offener Vorschlag
     */
    public function openSuggestionsFor(string $subjectType, Collection $items, ?string $capability = null): Collection;
}
