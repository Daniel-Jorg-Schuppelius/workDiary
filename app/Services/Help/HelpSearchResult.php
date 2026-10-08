<?php
/*
 * Created on   : Thu Oct 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpSearchResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Help;

use App\Models\Platform\HelpTopic;
use Illuminate\Support\Collection;

/**
 * Sichtbare Treffer einer Hilfesuche, beste zuerst (MVP-1079). Die Themen
 * sind ohne Langtext geladen; `search_keyword` nennt den Suchbegriff, über den
 * ein Thema gefunden wurde, wenn der Titel das Wort nicht enthält.
 */
final class HelpSearchResult {
    /**
     * @param  Collection<int, HelpTopic>  $topics
     * @param  bool  $partial  kein Thema enthält alle Wörter; die Treffer enthalten einen Teil davon
     */
    public function __construct(
        public readonly HelpSearchQuery $query,
        public readonly Collection $topics,
        public readonly bool $partial,
    ) {}

    public function count(): int {
        return $this->topics->count();
    }

    public function isEmpty(): bool {
        return $this->topics->isEmpty();
    }
}
