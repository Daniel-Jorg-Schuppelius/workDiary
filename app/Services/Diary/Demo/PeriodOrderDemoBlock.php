<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodOrderDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Demo;

use App\Enums\Diary\{LocationMode, Mode, Status};
use App\Enums\Period\PeriodKind;
use App\Models\Approval\Approval;
use App\Models\CarryForward\CarryForwardNote;
use App\Models\Classification\EntryType;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Services\CarryForward\CarryForwardNotes;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Diary\{FollowUpPeriodService, OrderService};
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;

/**
 * Periodenauftrag mit interner Prüfung und Merker zur Vorführung (Feature
 * 198/201): Eintragsart „Jahresauftrag (Demo)“ mit Jahr und Prüfpflicht, ein
 * Vorjahresauftrag — vorgelegt, freigegeben, abgeschlossen — mit einem Merker
 * fürs nächste Jahr, und der Auftrag des laufenden Jahres als Folgeperiode,
 * vorgelegt und in der Prüf-Warteschlange. Nur erfundene Inhalte.
 */
final class PeriodOrderDemoBlock implements DemoBlock {
    /** Slug der Demo-Eintragsart; Reset räumt nur, was daran hängt. */
    public const string ENTRY_TYPE_SLUG = 'demo_jahresauftrag';

    public function __construct(
        private readonly OrderService $orders,
        private readonly FollowUpPeriodService $followUps,
        private readonly CarryForwardNotes $notes,
    ) {}

    public function supports(DemoSeedContext $context): bool {
        return $context->users->count() >= 2 && $context->mainCustomer !== null;
    }

    public function seed(DemoSeedContext $context): array {
        $worker = $context->users->get(1);
        $reviewer = $context->users->get(0);
        $customer = $context->mainCustomer;
        if (! $worker instanceof User || ! $reviewer instanceof User || $customer === null) {
            return [];
        }
        $organization = $context->organization;
        $type = EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('slug', self::ENTRY_TYPE_SLUG)
            ->first() ?? EntryType::query()->withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'slug' => self::ENTRY_TYPE_SLUG,
                'label' => (string) __('diary.demo.entry_type'),
                'icon' => 'event_repeat',
                'color' => 'info',
                'is_active' => true,
                'requires_customer' => true,
                'allow_priority' => true,
                'default_status' => Status::Planned->value,
                'requires_period' => [PeriodKind::Year->value],
                'requires_review' => true,
            ]);

        $lastYear = PeriodKey::containing(PeriodKind::Year, CarbonImmutable::now())->previous();
        $previous = DiaryEntry::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $worker->id,
            'assigned_user_id' => $worker->id,
            'entry_type_id' => $type->id,
            'customer_id' => $customer->id,
            'title' => (string) __('diary.demo.title', ['year' => $lastYear->label()]),
            'content' => (string) __('diary.demo.content'),
            'status' => Status::Planned->value,
            'period_key' => $lastYear,
            'reviewer_user_id' => $reviewer->id,
            'mode' => Mode::Backlog,
            'location_mode' => LocationMode::Remote,
        ]);

        // Vorjahr: bearbeitet, vorgelegt, freigegeben und abgeschlossen — mit Merker fürs nächste Jahr.
        $previous = $this->orders->accept($previous, $worker);
        $previous = $this->orders->start($previous, $worker);
        $previous = $this->orders->submitForReview($previous, $worker);
        $previous = $this->orders->approveReview($previous, $reviewer, (string) __('diary.demo.approval_note'));
        $previous = $this->orders->complete($previous, $worker, (string) __('diary.demo.summary'));
        $this->notes->create($previous, (string) __('diary.demo.carry_forward'), $worker);

        // Laufendes Jahr: als Folgeperiode angelegt, in Arbeit und zur Prüfung vorgelegt.
        $current = $this->followUps->create($previous, $worker);
        $current = $this->orders->accept($current, $worker);
        $current = $this->orders->start($current, $worker);
        $this->orders->submitForReview($current, $worker);

        return ['period_orders' => 2, 'carry_forward_notes' => 1];
    }

    public function purge(Organization $organization): void {
        $typeIds = EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('slug', self::ENTRY_TYPE_SLUG)
            ->pluck('id');
        foreach ($typeIds as $typeId) {
            $entries = DiaryEntry::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('entry_type_id', $typeId)
                ->get();
            // Merker der Demo-Reihen (Reihenschlüssel kommt vom Auftrag).
            $series = $entries->map(static fn (DiaryEntry $entry): ?string => $entry->carryForwardSeries())->filter()->unique()->values()->all();
            if ($series !== []) {
                CarryForwardNote::query()->withoutGlobalScope(OrganizationScope::class)
                    ->where('organization_id', $organization->id)
                    ->whereIn('series_key', $series)
                    ->delete();
            }
            // Freigabestufen der Prüfung hängen polymorph am Auftrag, ohne Kaskade.
            Approval::query()->withoutGlobalScopes()
                ->where('approvable_type', (new DiaryEntry)->getMorphClass())
                ->whereIn('approvable_id', $entries->modelKeys())
                ->delete();
            $entries->each(static fn (DiaryEntry $entry) => $entry->delete());
        }
        EntryType::query()->withoutGlobalScopes()->whereIn('id', $typeIds)->delete();
    }
}
