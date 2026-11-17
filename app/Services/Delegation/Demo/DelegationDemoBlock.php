<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DelegationDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Delegation\Demo;

use App\Enums\Delegation\WorkDelegationKind;
use App\Models\Delegation\WorkDelegation;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Services\Delegation\WorkDelegationService;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use Illuminate\Validation\ValidationException;

/**
 * Vertretung zur Vorführung (Feature 202, MVP-1647): der zweite Demo-Nutzer
 * vertritt den dritten ab heute für eine Woche — Kopfhinweis, Arbeitsvorrat
 * und Liste zeigen sie sofort. Nur erfundene Demo-Personen.
 */
final class DelegationDemoBlock implements DemoBlock {
    public function __construct(private readonly WorkDelegationService $delegations) {}

    public function supports(DemoSeedContext $context): bool {
        return $context->users->count() >= 3;
    }

    public function seed(DemoSeedContext $context): array {
        $deputy = $context->users->get(1);
        $represented = $context->users->get(2);
        if (! $deputy instanceof User || ! $represented instanceof User) {
            return [];
        }
        $today = $this->delegations->today();

        try {
            $this->delegations->create(
                $represented,
                $deputy,
                $today,
                $today->addWeek(),
                WorkDelegationKind::Other,
                null,
                (string) __('delegation.demo.note'),
                // Die vertretene Person legt ihre Vertretung selbst an — der Seed-Auslöser
                // kann ein Plattform-Admin einer anderen Organisation sein.
                $represented,
            );
        } catch (ValidationException $e) {
            // Demo-Daten dürfen die Anlage der Organisation nicht kippen (etwa eine vorhandene Vertretung).
            report($e);

            return ['work_delegations' => 0];
        }

        return ['work_delegations' => 1];
    }

    public function purge(Organization $organization): void {
        WorkDelegation::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->delete();
    }
}
