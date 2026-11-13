<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerServiceManager.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Deadlines;

use App\Enums\Period\PeriodKind;
use App\Models\Classification\EntryType;
use App\Models\Customer\{Customer, CustomerService};
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Support\OrganizationContext;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Leistungsumfang eines Kunden pflegen (Feature 197, MVP-1630): Leistung
 * anlegen und beenden. Beendet wird mit `valid_until`, gelöscht wird nicht —
 * Leistungsplan (Feature 199) und Fristen vergangener Perioden lesen den
 * Stand von damals. Nach jeder Änderung rechnet der Jahreslauf die Fristen
 * des Kunden für die eingeschalteten Regelsätze neu.
 */
final class CustomerServiceManager {
    public function __construct(
        private readonly CustomerDeadlineGenerator $generator,
        private readonly CustomerDeadlineRuleSets $ruleSets,
    ) {}

    /**
     * Leistungen des Kunden, die am Tag gelten (Vorgabe: alle, auch beendete).
     *
     * @return Collection<int, CustomerService>
     */
    public function forCustomer(Customer $customer, ?CarbonInterface $activeOn = null): Collection {
        $query = CustomerService::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id);
        if ($activeOn !== null) {
            $query->validBetween($activeOn, $activeOn);
        }

        return $query->with('entryType:id,slug,label')
            ->orderByRaw('valid_until is not null')
            ->orderBy('entry_type_id')
            ->orderBy('valid_from')
            ->get();
    }

    public function add(
        Customer $customer,
        EntryType $entryType,
        ?string $productGroup,
        bool $mandatory,
        PeriodKind $periodKind,
        CarbonImmutable $validFrom,
        ?CarbonImmutable $validUntil,
        ?string $note,
        User $actor,
    ): CustomerService {
        if ((int) $entryType->organization_id !== (int) $customer->organization_id) {
            throw ValidationException::withMessages(['entry_type_id' => (string) __('validation.exists', ['attribute' => 'entry_type_id'])]);
        }
        if ($validUntil !== null && $validUntil->lessThan($validFrom)) {
            throw ValidationException::withMessages(['valid_until' => (string) __('customer_deadline.error.until_before_from')]);
        }

        $service = CustomerService::query()->create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'entry_type_id' => $entryType->id,
            'product_group_code' => $productGroup,
            'is_mandatory' => $mandatory,
            'period_kind' => $periodKind,
            'valid_from' => $validFrom->toDateString(),
            'valid_until' => $validUntil?->toDateString(),
            'note' => $note,
            'created_by' => $actor->id,
        ]);
        $this->resync($customer);

        return $service;
    }

    /** Leistung zum Stichtag beenden (einschließlich). */
    public function end(CustomerService $service, CarbonImmutable $validUntil): CustomerService {
        if ($validUntil->toDateString() < $service->valid_from->toDateString()) {
            throw ValidationException::withMessages(['valid_until' => (string) __('customer_deadline.error.until_before_from')]);
        }
        $service->update(['valid_until' => $validUntil->toDateString()]);
        $customer = $service->customer()->withoutGlobalScope(OrganizationScope::class)->first();
        if ($customer instanceof Customer) {
            $this->resync($customer);
        }

        return $service->refresh();
    }

    private function resync(Customer $customer): void {
        $organization = Organization::query()->find($customer->organization_id);
        if (! $organization instanceof Organization) {
            return;
        }
        OrganizationContext::run($organization, function () use ($organization, $customer): void {
            foreach ($this->ruleSets->enabled($organization) as $ruleSet) {
                $this->generator->syncCustomer($customer, $ruleSet);
            }
        });
    }
}
