<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Demo;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Demo\DemoIndustry;
use App\Models\Customer\{Customer, CustomerDeadline};
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Services\Classification\ClassificationResolver;
use App\Services\Customer\Deadlines\{CustomerDeadlineProfileService, CustomerDeadlineRuleSets, CustomerDeadlineService};
use App\Services\Customer\Deadlines\RuleSets\{HomeCareDeadlineRuleSet, TaxAdvisoryDeadlineRuleSet};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Support\OrganizationContext;
use Carbon\CarbonImmutable;

/**
 * Kundenfristen zur Vorführung (Feature 197): erfundene Kunden mit
 * Fristmerkmalen — eine Bäckerei mit monatlicher Umsatzsteuer und
 * Dauerfristverlängerung, ein Planungsbüro mit Quartalsmeldung und
 * Zusammenfassender Meldung und, wo es den Pflegegrad gibt, eine
 * Pflegegeld-Kundin mit Pflegegrad 4. Die erste fällige Frist der Bäckerei ist
 * erledigt, damit Verlauf und Ampel etwas zeigen. Nur erfundene Namen.
 */
final class CustomerDeadlineDemoBlock implements DemoBlock {
    /** @var list<string> Namen der Demo-Kunden — Reset räumt nur diese. */
    public const array CUSTOMERS = ['Bäckerei Sonnenkorn (Demo)', 'Planungsbüro Lindenhof (Demo)', 'Erika Beispiel (Demo)'];

    public function __construct(
        private readonly CustomerDeadlineRuleSets $ruleSets,
        private readonly CustomerDeadlineProfileService $profiles,
        private readonly CustomerDeadlineService $deadlines,
        private readonly ClassificationResolver $classifications,
    ) {}

    public function supports(DemoSeedContext $context): bool {
        return in_array($context->industry, [null, DemoIndustry::Steuerberater, DemoIndustry::Pflege], true) && $context->users->isNotEmpty();
    }

    public function seed(DemoSeedContext $context): array {
        $organization = $context->organization;

        return OrganizationContext::run($organization, function () use ($context, $organization): array {
            $worker = $context->users->get(1) ?? $context->users->first();
            $responsible = $worker instanceof User ? $worker : null;
            $validFrom = CarbonImmutable::today()->startOfYear();
            $counts = ['customer_deadline_profiles' => 0];

            if ($context->industry !== DemoIndustry::Pflege) {
                $this->ruleSets->enable($organization, [TaxAdvisoryDeadlineRuleSet::KEY]);
                $tax = $this->ruleSets->get($organization, TaxAdvisoryDeadlineRuleSet::KEY);
                if ($tax !== null) {
                    $bakery = $this->customer($organization, self::CUSTOMERS[0], 'Musterstadt', $context->actor);
                    $this->profiles->save($bakery, $tax, [
                        'vat_interval' => 'monthly', 'vat_extension' => true, 'wage_tax_interval' => 'monthly',
                        'recapitulative_interval' => 'none', 'tax_advised' => true, 'tax_office_state' => 'Germany\\Bavaria', 'document_channel' => 'upload',
                    ], $validFrom, $responsible, $context->actor);
                    $office = $this->customer($organization, self::CUSTOMERS[1], 'Beispielhausen', $context->actor);
                    $this->profiles->save($office, $tax, [
                        'vat_interval' => 'quarterly', 'wage_tax_interval' => 'quarterly', 'recapitulative_interval' => 'quarterly',
                        'tax_advised' => true, 'document_channel' => 'portal',
                    ], $validFrom, $responsible, $context->actor);
                    $counts['customer_deadline_profiles'] += 2;

                    $first = CustomerDeadline::query()->where('customer_id', $bakery->id)->open()->orderBy('due_on')->first();
                    if ($first !== null) {
                        $this->deadlines->complete($first, $context->actor, (string) __('customer_deadline.demo.completed_note'));
                    }
                }
            }

            $grade = $this->classifications->resolveCode((int) $organization->id, ClassificationDomain::Pflegegrad, 'pg4');
            if ($grade !== null && $context->industry !== DemoIndustry::Steuerberater) {
                $this->ruleSets->enable($organization, [HomeCareDeadlineRuleSet::KEY]);
                $care = $this->ruleSets->get($organization, HomeCareDeadlineRuleSet::KEY);
                if ($care !== null) {
                    $client = $this->customer($organization, self::CUSTOMERS[2], 'Musterdorf', $context->actor);
                    $client->classifications()->syncWithoutDetaching([$grade->id]);
                    $this->profiles->save($client, $care, ['benefit' => 'care_allowance', 'quarterly_requested' => false], $validFrom, $responsible, $context->actor);
                    $counts['customer_deadline_profiles']++;
                }
            }

            $counts['customer_deadlines'] = CustomerDeadline::query()->whereIn('customer_id', $this->demoCustomerIds($organization))->count();

            return $counts;
        });
    }

    public function purge(Organization $organization): void {
        // Fristen, Fassungen, Leistungen und Verlauf hängen per Kaskade am Kunden.
        Customer::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->whereIn('name', self::CUSTOMERS)
            ->get()
            ->each(static fn (Customer $customer) => $customer->delete());
    }

    private function customer(Organization $organization, string $name, string $city, User $actor): Customer {
        return Customer::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'name' => $name],
            ['company' => $name, 'address_city' => $city, 'country' => 'DE', 'created_by' => $actor->id],
        );
    }

    /** @return list<int> */
    private function demoCustomerIds(Organization $organization): array {
        $ids = Customer::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->whereIn('name', self::CUSTOMERS)
            ->pluck('id')
            ->all();

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $ids));
    }
}
