<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeHubCondition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections\Navigation;

use App\Models\Platform\{Organization, User};
use App\Services\Collections\CollectableTypes;
use App\Services\Navigation\Contracts\NavigationCondition;

/** Einstieg „Wissen" (MVP-820) nur, wenn die Person überhaupt einen Inhaltstyp sieht. */
final class KnowledgeHubCondition implements NavigationCondition {
    public function __construct(private readonly CollectableTypes $types) {}

    public function key(): string {
        return 'knowledge.hub';
    }

    public function passes(?User $user, ?Organization $organization): bool {
        return $user !== null && $this->types->availableKeys($user) !== [];
    }
}
