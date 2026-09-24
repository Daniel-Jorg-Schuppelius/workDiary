<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CollectionScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Contracts;

use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Sammlungs-Facette der Suche (MVP-863): definiert von der Suche, gebunden von
 * der Wissensorganisation ({@see \App\Services\Collections\Search\CollectionSearchScope}).
 * Null-Bindung: keine Sammlungen, keine Facette.
 */
interface CollectionScope {
    /** @return list<int> sichtbare Sammlungs-IDs des Teilbaums */
    public function visibleSubtreeIds(User $viewer, ?int $organizationId, int $collectionId): array;

    /** @return list<array<string, mixed>> Sammlungsbaum für die Filterauswahl */
    public function tree(User $viewer): array;

    /** Sammlungstyp-Schlüssel eines Modells, null = nicht sammelbar. */
    public function keyFor(Model $model): ?string;
}
