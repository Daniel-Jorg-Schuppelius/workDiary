<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractCarryForwardCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract\Cockpit;

use App\Models\Contract\{Contract, ContractObligation};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\CarryForward\CarryForwardCockpitList;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Offene Merker an wiederkehrenden Vertragspflichten des Kunden oder
 * Lieferanten (Feature 201, MVP-1646): Reihe Vertrag × Pflichtart, nur Verträge,
 * die der Betrachter öffnen darf.
 */
final class ContractCarryForwardCockpitPanel implements CockpitPanel {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly CarryForwardCockpitList $list,
    ) {}

    public function order(): int {
        return 21;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer || $subject instanceof Supplier;
    }

    public function render(Model $subject, User $viewer): ?View {
        $gate = Gate::forUser($viewer);
        if (! $this->features->routeEnabled('contracts.show') || ! $gate->allows('viewAny', Contract::class)) {
            return null;
        }
        $contracts = Contract::query()
            ->where($subject instanceof Customer ? 'customer_id' : 'supplier_id', $subject->getKey())
            ->whereHas('obligations', static fn ($q) => $q->where('recurring', true))
            ->with(['obligations' => static fn ($q) => $q->where('recurring', true)])
            ->get()
            ->filter(static fn (Contract $contract): bool => $gate->allows('view', $contract));

        $series = [];
        foreach ($contracts as $contract) {
            foreach ($contract->obligations as $obligation) {
                /** @var ContractObligation $obligation */
                $key = $obligation->carryForwardSeries();
                if ($key !== null) {
                    $series[$key] ??= [
                        'label' => $contract->title . ' · ' . $obligation->kind->label(),
                        'url' => route('contracts.show', $contract),
                    ];
                }
            }
        }

        return $this->list->render((int) $subject->getAttribute('organization_id'), $series, 'cockpit-carry-forward-contracts', (string) __('carry_forward.cockpit.contracts'));
    }
}
