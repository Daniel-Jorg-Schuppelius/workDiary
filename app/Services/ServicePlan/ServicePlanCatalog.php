<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanCatalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Period\PeriodKind;
use App\Models\Classification\EntryType;
use App\Models\Customer\CustomerService;
use App\Models\Scopes\OrganizationScope;
use App\Models\ServicePlan\ServiceTemplate;
use App\Services\Classification\ClassificationResolver;
use App\Services\ServicePlan\Dto\{ServiceKey, ServicePlanColumn};
use Illuminate\Database\Eloquent\Collection;

/**
 * Bezeichnungen und Auswahl der Leistungen (Feature 199): eine Leistung heißt
 * wie ihre Vorlage (MVP-1674), sonst „Eintragsart · Produktgruppe“. Liefert
 * die Leistungen, die eine Organisation im Leistungsumfang oder als Vorlage
 * führt — für Spaltenauswahl, Import und Kundendialog.
 */
final class ServicePlanCatalog {
    /** @var array<int, array{templates: array<string, ServiceTemplate>, types: array<int, EntryType>, groups: array<string, string>}> */
    private array $memo = [];

    public function __construct(private readonly ClassificationResolver $classifications) {}

    /**
     * Vorlagen der Organisation in Anzeigereihenfolge.
     *
     * @return Collection<int, ServiceTemplate>
     */
    public function templates(int $organizationId): Collection {
        return ServiceTemplate::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->with('entryType:id,slug,label,is_active')
            ->orderBy('sort')
            ->orderBy('label')
            ->get();
    }

    /** Vorlage genau dieser Leistung (Eintragsart + Produktgruppe), sonst null. */
    public function template(int $organizationId, ServiceKey $service): ?ServiceTemplate {
        return $this->lookup($organizationId)['templates'][$service->key()] ?? null;
    }

    public function label(int $organizationId, ServiceKey $service): string {
        $template = $this->template($organizationId, $service);
        if ($template !== null) {
            return $template->displayLabel();
        }
        $lookup = $this->lookup($organizationId);
        $type = $lookup['types'][$service->entryTypeId] ?? null;
        $label = $type !== null ? (string) $type->label : (string) __('service_plan.unknown_service');
        if ($service->productGroup !== null) {
            $label .= ' · ' . ($lookup['groups'][$service->productGroup] ?? $service->productGroup);
        }

        return $label;
    }

    public function column(int $organizationId, ServiceKey $service, PeriodKind $kind): ServicePlanColumn {
        $template = $this->template($organizationId, $service);

        return new ServicePlanColumn($service, $this->label($organizationId, $service), $kind, $template !== null ? $template->sort : PHP_INT_MAX);
    }

    /** Slug der Eintragsart (Regelsätze ordnen Fristen über ihn zu); null, wenn unbekannt. */
    public function entryTypeSlug(int $organizationId, int $entryTypeId): ?string {
        $slug = $this->lookup($organizationId)['types'][$entryTypeId]->slug ?? null;

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * Leistungen der Organisation aus Vorlagen und Leistungsumfang, je mit der
     * üblichen Periodenart (Vorlage, sonst die zuletzt erfasste).
     *
     * @return list<ServicePlanColumn>
     */
    public function services(int $organizationId): array {
        $columns = [];
        foreach ($this->lookup($organizationId)['templates'] as $template) {
            $columns[$template->serviceKey()->key()] = $this->column($organizationId, $template->serviceKey(), $template->period_kind);
        }
        $rows = CustomerService::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->orderByDesc('id')
            ->get(['entry_type_id', 'product_group_code', 'period_kind']);
        foreach ($rows as $row) {
            $key = new ServiceKey((int) $row->entry_type_id, $row->product_group_code);
            $columns[$key->key()] ??= $this->column($organizationId, $key, $row->period_kind);
        }

        return self::sorted(array_values($columns));
    }

    /**
     * @param  list<ServicePlanColumn>  $columns
     * @return list<ServicePlanColumn>
     */
    public static function sorted(array $columns): array {
        usort($columns, static fn (ServicePlanColumn $a, ServicePlanColumn $b): int => [$a->sort, mb_strtolower($a->label)] <=> [$b->sort, mb_strtolower($b->label)]);

        return $columns;
    }

    /**
     * Vorlagen, Eintragsarten und Produktgruppen je Organisation, gemerkt für die Lebensdauer des Dienstes.
     *
     * @return array{templates: array<string, ServiceTemplate>, types: array<int, EntryType>, groups: array<string, string>} */
    private function lookup(int $organizationId): array {
        if (isset($this->memo[$organizationId])) {
            return $this->memo[$organizationId];
        }
        $templates = [];
        foreach ($this->templates($organizationId) as $template) {
            $templates[$template->serviceKey()->key()] ??= $template;
        }
        $types = [];
        foreach (EntryType::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->get(['id', 'slug', 'label']) as $type) {
            $types[(int) $type->id] = $type;
        }
        $groups = [];
        foreach ($this->classifications->list($organizationId, ClassificationDomain::ProductGroup) as $group) {
            $groups[(string) $group->code] = $group->displayLabel();
        }

        return $this->memo[$organizationId] = ['templates' => $templates, 'types' => $types, 'groups' => $groups];
    }
}
