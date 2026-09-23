<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroupCriteriaScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Club\ClubGroupMembershipStatus;
use App\Models\Club\ClubGroupMembership;
use App\Models\Organization;
use App\Services\Club\{ClubFeeService, ClubGroupService, ClubMemberService};
use App\Services\Notification\NotificationDispatcher;
use Carbon\CarbonImmutable;

/**
 * Vereinsgruppen (Feature 159, MVP-842): täglicher Abgleich der Alters-
 * kriterien je aktiver Zuordnung — Geburtstage und geänderte Grenzen
 * erzeugen Wechsel-/Prüfvorschläge, nie eine automatische Entfernung.
 * Zieht außerdem den Mitgliedschaftsstand für heute beginnende Abschnitte
 * nach. Benachrichtigung der Leitung folgt mit MVP-845.
 */
class ClubGroupCriteriaScan extends AbstractDeadlineScan {
    public function __construct(
        private readonly ClubGroupService $groups,
        private readonly ClubMemberService $members,
        private readonly ClubFeeService $fees,
    ) {}

    public function key(): string {
        return 'club';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        unset($dispatcher);
        $today = CarbonImmutable::today();
        $created = 0;

        $organizationIds = ClubGroupMembership::query()
            ->withoutGlobalScopes()
            ->where('status', ClubGroupMembershipStatus::Active->value)
            ->distinct()
            ->pluck('organization_id');

        foreach ($organizationIds as $organizationId) {
            $organization = Organization::query()->whereKey($organizationId)->first();
            if ($organization === null) {
                continue;
            }

            $this->members->syncCurrentKinds($organization, $today);
            $created += $this->groups->refreshProposals($organization, $today);
            // Beitragstarife mit Altersgrenzen (MVP-849): Markierung, kein automatischer Wechsel.
            $created += $this->fees->flagAgeMismatches($organization, $today);
        }

        if ($created > 0) {
            $options->info("club: {$created} Wechsel-/Prüfvorschläge angelegt.");
        }

        return $created;
    }
}
