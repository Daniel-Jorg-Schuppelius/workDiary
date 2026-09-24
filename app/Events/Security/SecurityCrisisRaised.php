<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityCrisisRaised.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Security;

use App\Models\Crisis\CrisisCase;
use App\Models\Platform\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Massenangriff erkannt, Krisenfall eröffnet (Feature 070) — Stab alarmieren erst nach Commit. */
final class SecurityCrisisRaised implements ShouldDispatchAfterCommit {
    use Dispatchable;

    public function __construct(public readonly CrisisCase $case, public readonly User $actor) {}
}
