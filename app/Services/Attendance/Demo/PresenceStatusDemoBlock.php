<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceStatusDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attendance\Demo;

use App\Enums\Attendance\PresenceStatusKind;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Models\Time\PresenceStatus;
use App\Services\Attendance\PresenceStatusService;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/**
 * Selbstauskunft zur Vorführung (Feature 103, MVP-1669): die zweite
 * Demo-Person steht auf „Nicht stören“ (ohne Ende, damit der Stand die
 * Vorführung überdauert). Sichtbar wird er, sobald die Organisation das
 * Anwesenheits-Board einschaltet. Nur erfundene Demo-Personen.
 */
final class PresenceStatusDemoBlock implements DemoBlock {
    public function __construct(private readonly PresenceStatusService $presence) {}

    public function supports(DemoSeedContext $context): bool {
        return $context->users->count() >= 2;
    }

    public function seed(DemoSeedContext $context): array {
        $person = $context->users->get(1);
        if (! $person instanceof User) {
            return [];
        }
        $this->presence->set($person, PresenceStatusKind::DoNotDisturb, null);

        return ['presence_statuses' => 1];
    }

    public function purge(Organization $organization): void {
        PresenceStatus::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->delete();
        $this->presence->flush();
    }
}
