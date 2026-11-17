<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTemplates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Period\PeriodKind;
use App\Models\Classification\EntryType;
use App\Models\Customer\{Customer, CustomerService};
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Models\ServicePlan\ServiceTemplate;
use App\Services\Classification\ClassificationResolver;
use App\Services\Customer\Deadlines\CustomerServiceManager;
use App\Support\Period\PeriodKey;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Leistungsvorlagen (Feature 199, MVP-1674): aus dem Branchenprofil
 * installieren (ergänzt nur Fehlendes, Bestand bleibt) und beim Anlegen eines
 * Kunden als Leistungsumfang übernehmen (nur fehlende Leistungen).
 */
final class ServiceTemplates {
    public function __construct(
        private readonly ServicePlanCatalog $catalog,
        private readonly CustomerServiceManager $services,
        private readonly ClassificationResolver $classifications,
    ) {}

    /**
     * Profilzeilen `service_catalog` installieren: Leistung = Eintragsart (Slug)
     * + Produktgruppe (Code), Periodenart, Vorgabe Pflicht/Wahl, Bezeichnung
     * und Übersetzungen. Fehlt die Eintragsart oder Produktgruppe, oder gibt
     * es die Vorlage schon, zählt die Zeile als übersprungen.
     *
     * @param  array<int|string, mixed>  $rows
     * @return array{created: int, skipped: int}
     */
    public function install(Organization $organization, array $rows, ?User $actor): array {
        $created = 0;
        $skipped = 0;
        $sort = (int) ServiceTemplate::query()->withoutGlobalScope(OrganizationScope::class)->where('organization_id', $organization->id)->max('sort');
        foreach ($rows as $row) {
            $slug = is_array($row) && is_string($row['entry_type'] ?? null) ? $row['entry_type'] : '';
            $kind = is_array($row) && is_string($row['period_kind'] ?? null) ? PeriodKind::tryFrom($row['period_kind']) : null;
            $label = is_array($row) && is_string($row['label'] ?? null) ? trim($row['label']) : '';
            $group = is_array($row) && is_string($row['product_group'] ?? null) && $row['product_group'] !== '' ? $row['product_group'] : null;
            $type = $slug !== '' ? EntryType::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('slug', $slug)->first() : null;
            if (! $type instanceof EntryType || $kind === null || $label === ''
                || ($group !== null && $this->classifications->resolveCode((int) $organization->id, ClassificationDomain::ProductGroup, $group) === null)) {
                $skipped++;

                continue;
            }
            $exists = ServiceTemplate::query()->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organization->id)
                ->where('entry_type_id', $type->id)
                ->when($group === null, static fn ($q) => $q->whereNull('product_group_code'), static fn ($q) => $q->where('product_group_code', $group))
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }
            $sort += 10;
            ServiceTemplate::query()->create([
                'organization_id' => $organization->id,
                'entry_type_id' => $type->id,
                'product_group_code' => $group,
                'period_kind' => $kind,
                'is_mandatory' => (bool) ($row['mandatory'] ?? true),
                'label' => mb_substr($label, 0, 150),
                'label_i18n' => ServiceTemplate::cleanTranslations($row['label_i18n'] ?? null),
                'sort' => $sort,
                'created_by' => $actor?->id,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Vorlagen mit aktiver Eintragsart — Angebot im Dialog „Kunde anlegen“.
     *
     * @return Collection<int, ServiceTemplate>
     */
    public function offered(int $organizationId): Collection {
        return $this->catalog->templates($organizationId)
            ->filter(static fn (ServiceTemplate $template): bool => (bool) ($template->entryType->is_active ?? false))
            ->values();
    }

    /**
     * Gewählte Vorlagen als Leistungsumfang des Kunden übernehmen, ab Beginn
     * der laufenden Periode der Vorlage; vorhandene Leistungen bleiben.
     *
     * @param  list<string>  $templateSqids
     * @return int Anzahl neu angelegter Leistungen
     */
    public function applyToCustomer(Customer $customer, array $templateSqids, User $actor, ?CarbonImmutable $today = null): int {
        $ids = array_values(array_filter(array_map(static fn (string $sqid): ?int => Sqid::decode(ServiceTemplate::class, $sqid), $templateSqids)));
        if ($ids === []) {
            return 0;
        }
        $today ??= CarbonImmutable::today();
        $added = 0;
        $templates = ServiceTemplate::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $customer->organization_id)
            ->whereIn('id', $ids)
            ->with('entryType')
            ->get();
        $existing = $this->services->forCustomer($customer, $today);
        foreach ($templates as $template) {
            $key = $template->serviceKey();
            $present = $existing->contains(static fn (CustomerService $service): bool => (int) $service->entry_type_id === $key->entryTypeId && $service->product_group_code === $key->productGroup);
            if ($present || ! $template->entryType instanceof EntryType) {
                continue;
            }
            $from = PeriodKey::containing($template->period_kind, $today)->start();
            $this->services->add($customer, $template->entryType, $key->productGroup, $template->is_mandatory, $template->period_kind, $from, null, null, $actor);
            $added++;
        }

        return $added;
    }
}
