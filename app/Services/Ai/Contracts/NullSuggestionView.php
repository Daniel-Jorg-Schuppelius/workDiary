<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullSuggestionView.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use Illuminate\Support\Collection;

final class NullSuggestionView implements SuggestionView {
    public function capabilityUsable(string $capability): bool {
        return false;
    }

    public function openSuggestionsFor(string $subjectType, Collection $items, ?string $capability = null): Collection {
        return collect();
    }
}
