<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceStatusOffboardingStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attendance\Offboarding;

use App\Models\Platform\User;
use App\Services\Attendance\PresenceStatusService;
use App\Services\Org\Contracts\OffboardingStep;

/**
 * Anwesenheitsstand (Feature 103, MVP-1669): die Selbstauskunft am Board
 * endet mit dem Austritt — kein „nicht stören“ einer Person, die nicht mehr
 * da ist, und keine Zeile, die bis zum Löschen des Kontos stehen bleibt.
 */
final class PresenceStatusOffboardingStep implements OffboardingStep {
    public function __construct(private readonly PresenceStatusService $statuses) {}

    public function blockers(User $member): array {
        return [];
    }

    public function onExit(User $member): void {
        $this->statuses->clear($member);
    }
}
