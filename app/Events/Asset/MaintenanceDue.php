<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenanceDue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Asset;

use App\Models\Asset\MaintenancePlan;
use App\Models\Platform\Organization;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Wartungsplan fällig, Aktion „Ticket" (Feature Wartung): Das Ticket legt der
 * Helpdesk an; ohne Helpdesk bleibt es bei den Terminscans. Nach Commit.
 */
final class MaintenanceDue implements ShouldDispatchAfterCommit {
    use Dispatchable;

    public function __construct(
        public readonly MaintenancePlan $plan,
        public readonly Organization $organization,
        /** Fälligkeitsschlüssel `<plan>:<datum>` — Idempotenz je Fälligkeit */
        public readonly string $externalId,
    ) {}
}
