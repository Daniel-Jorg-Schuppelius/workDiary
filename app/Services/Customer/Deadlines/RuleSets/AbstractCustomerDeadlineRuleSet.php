<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractCustomerDeadlineRuleSet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Deadlines\RuleSets;

use App\Enums\Fields\FieldType;
use App\Enums\Period\PeriodKind;
use App\Models\Customer\{Customer, CustomerService};
use App\Models\Platform\Organization;
use App\Services\Customer\Deadlines\Contracts\CustomerDeadlineRuleSet;
use App\Services\Fields\FieldDefinition;
use App\Support\HolidayRegions;
use App\Support\Period\PeriodKey;
use Illuminate\Support\Collection;

/**
 * Gemeinsames der Regelsätze (Feature 197): Region der Organisation als
 * Rückfall, Leistungsumfang des Kunden und Auswahlfelder mit übersetzten
 * Bezeichnungen.
 */
abstract class AbstractCustomerDeadlineRuleSet implements CustomerDeadlineRuleSet {
    public function kindLabel(string $kind): string {
        return $this->kinds()[$kind] ?? $kind;
    }

    /**
     * Feiertagsregion der Organisation des Kunden — Rückfall, wenn das
     * Bundesland fehlt. Gelesen an der Organisation selbst, nicht am
     * gebundenen Kontext (Jahreslauf, Konsole).
     */
    protected function organizationRegion(Customer $customer): string {
        $organization = Organization::query()->find($customer->organization_id);
        $value = $organization instanceof Organization ? data_get((array) ($organization->settings ?? []), 'holidays.provider') : null;

        return is_string($value) && HolidayRegions::isValid($value) ? $value : (string) config('holidays.provider', 'Germany');
    }

    /** Gültiges Bundesland aus einem Merkmal, sonst null. */
    protected function validRegion(mixed $value): ?string {
        return is_string($value) && $value !== '' && HolidayRegions::isValid($value) ? $value : null;
    }

    /**
     * Leistungen des Kunden einer Eintragsart (Slug), die im Jahr gelten.
     *
     * @return Collection<int, CustomerService>
     */
    protected function services(Customer $customer, string $entryTypeSlug, int $year): Collection {
        $start = PeriodKey::of(PeriodKind::Year, $year)->start();

        return CustomerService::query()->withoutGlobalScopes()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->whereHas('entryType', static fn ($q) => $q->withoutGlobalScopes()->where('slug', $entryTypeSlug))
            ->validBetween($start, $start->endOfYear())
            ->orderBy('valid_from')
            ->get();
    }

    /**
     * Gilt eine der Leistungen in der Periode (optional nur einer Produktgruppe)?
     *
     * @param  Collection<int, CustomerService>  $services
     */
    protected function coveredBy(Collection $services, PeriodKey $period, ?string $productGroup = null, bool $anyProductGroup = true): bool {
        return $services->contains(static fn (CustomerService $service): bool => $service->coversPeriod($period)
            && ($anyProductGroup || $service->product_group_code === $productGroup));
    }

    /**
     * Auswahlfeld mit übersetzten Bezeichnungen.
     *
     * @param  array<string, string>  $options  Wert → Bezeichnung
     */
    protected function choice(string $key, string $label, array $options, bool $multi = false, ?string $help = null, bool $required = false): FieldDefinition {
        return new FieldDefinition(
            key: $key,
            type: $multi ? FieldType::Multichoice : FieldType::Choice,
            label: $label,
            required: $required,
            options: array_keys($options),
            help: $help,
            optionLabels: $options,
            listed: true,
        );
    }

    protected function flag(string $key, string $label, ?string $help = null): FieldDefinition {
        return new FieldDefinition(key: $key, type: FieldType::Boolean, label: $label, help: $help, listed: true);
    }
}
