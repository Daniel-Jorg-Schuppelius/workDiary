<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OwnershipConflictReplay.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Contracts;

use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\Organization;
use Illuminate\Database\Eloquent\Model;

/**
 * Spielt einen zurückgehaltenen Schreibvorgang einmal ab, wenn der
 * Integrations-Eingang einen Führerschaftskonflikt übernimmt (MVP-1592).
 * Der Guard lässt dabei den Bereich für genau diese Quelle durch; die
 * Klasse ruft denselben Schreibweg mit dem gespeicherten Stand erneut auf.
 * Kern-Module melden sie in `extensions()`, Plugins über
 * `ModuleRegistry::contribute()`.
 */
interface OwnershipConflictReplay {
    /** @return list<string> Konfliktarten, die diese Klasse abspielt */
    public function ownershipKinds(): array;

    /** Geschriebener Datensatz; null, wenn nichts mehr zu schreiben ist. */
    public function replayOwnershipConflict(Organization $organization, IntegrationInboxItem $item): ?Model;
}
