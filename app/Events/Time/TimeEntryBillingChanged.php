<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryBillingChanged.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Time;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Ein Zeiteintrag wurde angelegt, geändert oder gelöscht; Kundenkonten rechnen ihre Monatskette nach. */
final class TimeEntryBillingChanged implements ShouldDispatchAfterCommit {
    use Dispatchable;

    /** @param list<int> $projectIds betroffene Projekte (bei Umhängen alt und neu) */
    public function __construct(public readonly array $projectIds) {}
}
