<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqProgressToBill.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb\Chain;

use App\Enums\Gaeb\BoqItemStatus;
use App\Enums\User\Permission;
use App\Models\Gaeb\BillOfQuantity;
use App\Models\Platform\{Organization, User};
use App\Services\Billing\Contracts\DocumentChainSource;
use App\Services\Billing\Dto\DocumentChainItem;
use App\Services\Gaeb\BoqBillingService;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Collection;

/** Belegkette (MVP-1057): beauftragte LVs, deren bewerteter Leistungsstand über den bisherigen Abschlägen liegt. */
final class BoqProgressToBill implements DocumentChainSource {
    public function __construct(private readonly BoqBillingService $billing) {}

    public function key(): string {
        return 'boq_progress';
    }

    public function label(): string {
        return (string) __('invoicing.chain.boq_progress');
    }

    public function icon(): string {
        return 'construction';
    }

    public function routeName(): string {
        return 'bill-of-quantities.billing';
    }

    public function availableFor(User $user): bool {
        return $user->can(Permission::ProjectViewAny->value);
    }

    public function count(Organization $organization): int {
        return $this->open($organization)->count();
    }

    public function items(Organization $organization, int $limit): array {
        return array_values($this->open($organization)->take($limit)->map(fn (array $row): DocumentChainItem => new DocumentChainItem(
            title: $row['bill']->name,
            detail: (string) __('invoicing.chain.boq_detail', ['progress' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['proposal']['progress'], 0)]),
            url: route('bill-of-quantities.billing', $row['bill']),
            amount: Money::of(\CommonToolkit\Helper\Data\NumberHelper::toUSFormat($row['proposal']['amount'], 2), CurrencyCode::tryFrom($row['proposal']['currency']) ?? CurrencyCode::Euro),
        ))->all());
    }

    /** @return Collection<int, array{bill: BillOfQuantity, proposal: array{executed: float, previous: float, amount: float, progress: float, currency: string}}> */
    private function open(Organization $organization): Collection {
        return BillOfQuantity::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [BoqItemStatus::Ordered->value, BoqItemStatus::InProgress->value])
            ->whereHas('items.progress')
            ->orderBy('name')
            ->get()
            ->map(fn (BillOfQuantity $bill): array => ['bill' => $bill, 'proposal' => $this->billing->proposal($bill)])
            ->filter(fn (array $row): bool => $row['proposal']['amount'] > 0.005)
            ->values();
    }
}
