<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetDecommissioned.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Asset;

use App\Models\Asset\Asset;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Asset außer Betrieb genommen (MVP-1574): Module mit laufenden Pflichten am
 * Asset (Prüfwesen) schließen sie ab. Nach Commit.
 */
final class AssetDecommissioned implements ShouldDispatchAfterCommit {
    use Dispatchable;

    public function __construct(
        public readonly Asset $asset,
        public readonly ?int $actorUserId = null,
    ) {}
}
