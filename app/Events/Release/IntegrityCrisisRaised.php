<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrityCrisisRaised.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Release;

use App\Models\Crisis\CrisisCase;
use App\Models\Platform\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Integritätssperre hat einen Krisenfall eröffnet — Stab alarmieren erst nach Commit. */
final class IntegrityCrisisRaised implements ShouldDispatchAfterCommit {
    use Dispatchable;

    public function __construct(public readonly CrisisCase $case, public readonly User $actor) {}
}
