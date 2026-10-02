<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentChainSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Platform\{Organization, User};
use App\Services\Billing\Dto\DocumentChainItem;

/**
 * Quelle der Belegkette (MVP-1057): ein Arbeitsschritt zwischen Angebot,
 * Auftrag, Leistung und Rechnung, der noch aussteht. Module tragen ihre
 * Quellen im Manifest unter `extensions()` ein.
 */
interface DocumentChainSource {
    public function key(): string;

    public function label(): string;

    public function icon(): string;

    /** Route, auf die die Einträge führen — ihr Modul-Gate entscheidet, ob die Quelle erscheint. */
    public function routeName(): string;

    public function availableFor(User $user): bool;

    public function count(Organization $organization): int;

    /** @return list<DocumentChainItem> */
    public function items(Organization $organization, int $limit): array;
}
