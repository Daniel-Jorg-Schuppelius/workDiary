<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Demo;

use App\Enums\Diary\{LocationMode, Mode, Status};
use App\Enums\Period\PeriodKind;
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Services\Customer\Deadlines\CustomerServiceManager;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Diary\OrderService;
use App\Services\ServicePlan\Dto\ServiceKey;
use App\Services\ServicePlan\{ServicePlanCells, ServiceTemplates};
use App\Support\OrganizationContext;
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;

/**
 * Leistungsplan zur Vorführung (Feature 199): zwei erfundene Leistungen
 * („Jahresabschluss (Demo)“ jährlich, „Monatsbetreuung (Demo)“ monatlich) als
 * Vorlagen, Leistungsumfang für bis zu drei der erfundenen Demo-Kunden und
 * Aufträge in verschiedenen Ständen — zur Prüfung, erledigt, in Arbeit —, ein
 * „entfällt“ und „Unterlagen da“. So zeigen Jahres- und Periodensicht,
 * Kacheln und Karte sofort etwas. Legt keine Kunden an; Reset räumt über die
 * Demo-Eintragsarten (Leistungsumfang, „entfällt“, Unterlagen und Vorlagen
 * hängen per Kaskade daran) und deren Aufträge.
 */
final class ServicePlanDemoBlock implements DemoBlock {
    /** Slugs der Demo-Eintragsarten — Reset räumt nur, was daran hängt. */
    public const array ENTRY_TYPES = ['demo_leistung_jahr', 'demo_leistung_monat'];

    public function __construct(
        private readonly ServiceTemplates $templates,
        private readonly CustomerServiceManager $services,
        private readonly ServicePlanCells $cells,
        private readonly OrderService $orders,
    ) {}

    public function supports(DemoSeedContext $context): bool {
        return $context->users->isNotEmpty() && $context->mainCustomer !== null;
    }

    public function seed(DemoSeedContext $context): array {
        $organization = $context->organization;

        return OrganizationContext::run($organization, function () use ($context, $organization): array {
            $worker = $context->users->get(1) ?? $context->users->first();
            $actor = $context->actor;
            if (! $worker instanceof User) {
                return [];
            }
            $annual = $this->entryType($organization, self::ENTRY_TYPES[0], (string) __('service_plan.demo.annual'), PeriodKind::Year, true);
            $monthly = $this->entryType($organization, self::ENTRY_TYPES[1], (string) __('service_plan.demo.monthly'), PeriodKind::Month, false);
            $this->templates->install($organization, [
                ['entry_type' => self::ENTRY_TYPES[0], 'period_kind' => PeriodKind::Year->value, 'mandatory' => true, 'label' => (string) __('service_plan.demo.annual')],
                ['entry_type' => self::ENTRY_TYPES[1], 'period_kind' => PeriodKind::Month->value, 'mandatory' => true, 'label' => (string) __('service_plan.demo.monthly')],
            ], $actor);

            $today = CarbonImmutable::today();
            $lastYear = PeriodKey::of(PeriodKind::Year, $today->year - 1);
            $customers = Customer::query()->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organization->id)
                ->whereNull('archived_at')
                ->orderByRaw('id = ? desc', [(int) $context->mainCustomer?->id])
                ->orderBy('id')
                ->limit(3)
                ->get()
                ->values();
            $first = $customers->get(0);
            $second = $customers->get(1);
            if (! $first instanceof Customer || ! $second instanceof Customer) {
                return [];
            }
            foreach ($customers as $customer) {
                $this->services->add($customer, $annual, null, true, PeriodKind::Year, $lastYear->start(), null, null, $actor);
            }
            // Monatsleistung seit drei Monaten — feste Zahl an Demo-Aufträgen, gleich an welchem Tag geseedet wird.
            $current = PeriodKey::containing(PeriodKind::Month, $today);
            foreach ([$first, $second] as $customer) {
                $this->services->add($customer, $monthly, null, true, PeriodKind::Month, $current->previous(2)->start(), null, null, $actor);
            }

            // Jahressicht: der erste Kunde zur Prüfung, der zweite erledigt, der dritte „entfällt“.
            $review = $this->order($organization, $first, $annual, $lastYear, $worker, Status::Open);
            $review = $this->orders->accept($review, $worker);
            $review = $this->orders->start($review, $worker);
            $this->orders->submitForReview($review, $worker);
            $this->order($organization, $second, $annual, $lastYear, $worker, Status::Done);
            $this->cells->saveNote($second, new ServiceKey((int) $annual->id), $lastYear, $today->subMonths(2)->startOfDay(), (string) __('service_plan.demo.note'), $actor);
            $third = $customers->get(2);
            if ($third instanceof Customer) {
                $this->cells->waive($third, new ServiceKey((int) $annual->id), $lastYear, (string) __('service_plan.demo.waiver'), $actor);
            }

            // Periodensicht: die zwei Vormonate erledigt, der laufende in Arbeit; der zweite Kunde hat noch nichts („fehlt“).
            $this->order($organization, $first, $monthly, $current->previous(2), $worker, Status::Done);
            $this->order($organization, $first, $monthly, $current->previous(), $worker, Status::Done);
            $this->order($organization, $first, $monthly, $current, $worker, Status::InProgress);

            return ['service_templates' => 2, 'customer_services' => $customers->count() + 2, 'service_plan_orders' => 5, 'customer_service_waivers' => $third instanceof Customer ? 1 : 0];
        });
    }

    public function purge(Organization $organization): void {
        $typeIds = EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('slug', self::ENTRY_TYPES)
            ->pluck('id');
        DiaryEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereIn('entry_type_id', $typeIds)
            ->get()
            ->each(static fn (DiaryEntry $entry) => $entry->delete());
        // Leistungsumfang, „entfällt“, Unterlagen und Vorlagen hängen per Kaskade an der Eintragsart.
        EntryType::query()->withoutGlobalScopes()->whereIn('id', $typeIds)->delete();
    }

    private function entryType(Organization $organization, string $slug, string $label, PeriodKind $kind, bool $review): EntryType {
        return EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('slug', $slug)
            ->first() ?? EntryType::query()->withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'slug' => $slug,
                'label' => $label,
                'icon' => $kind === PeriodKind::Year ? 'summarize' : 'event_repeat',
                'color' => 'info',
                'is_active' => true,
                'requires_customer' => true,
                'allow_priority' => true,
                'default_status' => Status::Planned->value,
                'requires_period' => [$kind->value],
                'requires_review' => $review,
            ]);
    }

    private function order(Organization $organization, Customer $customer, EntryType $type, PeriodKey $period, User $worker, Status $status): DiaryEntry {
        $now = CarbonImmutable::now();

        return DiaryEntry::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $worker->id,
            'assigned_user_id' => $worker->id,
            'entry_type_id' => $type->id,
            'customer_id' => $customer->id,
            'title' => $type->label . ' ' . $period->label(),
            'content' => (string) __('service_plan.demo.content'),
            'status' => $status->value,
            'period_key' => $period,
            'requires_review' => (bool) $type->requires_review,
            'mode' => Mode::Backlog,
            'location_mode' => LocationMode::Remote,
            'accepted_at' => $status === Status::Open ? null : $now,
            'started_at' => $status === Status::Open ? null : $now,
            'completed_at' => $status === Status::Done ? $now : null,
        ]);
    }
}
