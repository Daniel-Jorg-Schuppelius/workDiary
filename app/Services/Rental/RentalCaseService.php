<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalCaseService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Rental;

use App\Enums\Asset\AssetBlockReason;
use App\Enums\Notification\NotificationEvent;
use App\Enums\Numbering\NumberScope;
use App\Enums\Rental\{RentalCaseStatus, RentalReservationKind, RentalReturnFollowUp};
use App\Models\{Asset, Organization, User};
use App\Models\Notification\NotificationDispatchLog;
use App\Models\Rental\{RentalCase, RentalCaseAsset, RentalHandoverReport, RentalRateCard, RentalReservation, RentalReturnReport};
use App\Services\Asset\AssetBlockService;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lebenszyklus der Verleihakte (Feature 073): Anlage mit Konditionen-Snapshot
 * (D10), Reservierung mit Konfliktprüfung, Übergabe-/Rücknahmeprotokolle,
 * Verlängerung, Tauschgerät, Überfälligkeit und Folgeentscheidungen über das
 * gemeinsame Sperrmodell (D12).
 */
class RentalCaseService {
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly RentalAvailabilityService $availability,
        private readonly AssetBlockService $blocks,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /**
     * @param array<string, mixed> $attributes
     * @param list<int> $assetIds
     */
    public function open(Organization $organization, User $creator, array $attributes, array $assetIds = []): RentalCase {
        return DB::transaction(function () use ($organization, $creator, $attributes, $assetIds): RentalCase {
            $case = RentalCase::query()->create(array_merge($attributes, [
                'organization_id' => $organization->id,
                'number' => $this->numbers->next($organization, NumberScope::Rental),
                'status' => RentalCaseStatus::Draft->value,
                'created_by' => $creator->id,
            ]));

            foreach (array_unique($assetIds) as $assetId) {
                RentalCaseAsset::query()->create([
                    'organization_id' => $organization->id,
                    'rental_case_id' => $case->id,
                    'asset_id' => $assetId,
                    'status' => 'planned',
                ]);
            }

            if ($case->rental_rate_card_id !== null) {
                $this->freezeTerms($case);
            }

            return $case;
        });
    }

    /**
     * D10: angewendete Rate-Card-Version als Snapshot an der Akte einfrieren.
     *
     * @param array<string, mixed>|null $manualOverrides
     */
    public function freezeTerms(RentalCase $case, ?array $manualOverrides = null): RentalCase {
        $card = $case->rateCard()->with('items')->first();

        if ($card instanceof RentalRateCard) {
            $snapshot = $card->toSnapshot();

            if ($manualOverrides !== null && $manualOverrides !== []) {
                $snapshot['overrides'] = $manualOverrides;
            }

            $case->forceFill(['terms_snapshot' => $snapshot])->save();
            $case->audit('rental.termsFrozen', ['rate_card' => $card->name, 'version' => $card->version]);
        }

        return $case;
    }

    /**
     * Draft → Reserved: prüft jede Position gegen Sperren und Doppelbuchung
     * und legt harte Belegungsfenster inklusive Profil-Puffern an.
     */
    public function reserve(RentalCase $case, User $actor): RentalCase {
        $this->assertTransition($case, RentalCaseStatus::Reserved);

        return DB::transaction(function () use ($case, $actor): RentalCase {
            foreach ($case->caseAssets()->with('asset.rentalProfile')->get() as $caseAsset) {
                $asset = $caseAsset->asset;

                if (! $asset instanceof Asset) {
                    continue;
                }

                // Konflikt-/Sperrprüfung unter Sperre gegen parallele Buchung
                Asset::query()->whereKey($asset->id)->lockForUpdate()->first();
                $this->availability->assertAvailable($asset, $case->starts_at, $case->ends_at, $case->id);

                $profile = $asset->rentalProfile;

                RentalReservation::query()->create([
                    'organization_id' => $case->organization_id,
                    'rental_case_id' => $case->id,
                    'asset_id' => $asset->id,
                    'kind' => RentalReservationKind::Hard->value,
                    'status' => 'active',
                    'starts_at' => $case->starts_at,
                    'ends_at' => $case->ends_at,
                    'buffer_before_hours' => $profile->buffer_before_hours ?? 0,
                    'buffer_after_hours' => $profile->buffer_after_hours ?? 0,
                    'created_by' => $actor->id,
                ]);
            }

            $case->forceFill(['status' => RentalCaseStatus::Reserved->value])->save();
            $case->audit('rental.reserved', ['assets' => $case->caseAssets()->count()]);

            return $case;
        });
    }

    /**
     * Übergabeprotokoll je Leihobjekt (MVP-263). Sobald alle Positionen
     * übergeben sind, wechselt die Akte auf HandedOver.
     *
     * @param array<string, mixed> $data
     */
    public function handover(RentalCase $case, Asset $asset, User $actor, array $data = []): RentalHandoverReport {
        if (! in_array($case->status, [RentalCaseStatus::Reserved, RentalCaseStatus::HandedOver], true)) {
            throw new \RuntimeException((string) __('Übergaben sind nur aus reservierten Akten möglich.'));
        }

        return DB::transaction(function () use ($case, $asset, $actor, $data): RentalHandoverReport {
            // Kein stilles Übergeben gesperrter Geräte — auch wenn die
            // Sperre erst nach der Reservierung entstanden ist.
            app(\App\Services\Asset\AssetUsageGuard::class)->ensureUsable($asset, RentalAvailabilityService::USAGE_CONTEXT);

            $caseAsset = $this->caseAssetFor($case, $asset);

            $report = RentalHandoverReport::query()->create(array_merge($data, [
                'organization_id' => $case->organization_id,
                'rental_case_id' => $case->id,
                'asset_id' => $asset->id,
                'reported_at' => $data['reported_at'] ?? now(),
                'reported_by' => $actor->id,
            ]));

            $this->syncReportItems($report, $data);

            $caseAsset->forceFill(['status' => 'handed_over'])->save();
            $asset->audit('rental.handedOver', ['case' => $case->number, 'report_id' => $report->id]);

            $reservation = $case->reservations()->active()
                ->where('asset_id', $asset->id)
                ->where('kind', RentalReservationKind::Hard->value)
                ->first();
            $reservation?->forceFill(['kind' => RentalReservationKind::Rental->value])->save();

            $open = $case->caseAssets()->whereIn('status', ['planned'])->exists();
            if (! $open && $case->status === RentalCaseStatus::Reserved) {
                $case->forceFill(['status' => RentalCaseStatus::HandedOver->value])->save();
                $case->audit('rental.active', []);
            }

            return $report;
        });
    }

    /**
     * Laufzeitverlängerung (MVP-264): auditiert, verlängert Akte und
     * Belegungsfenster nach erneuter Konfliktprüfung.
     */
    public function extend(RentalCase $case, User $actor, Carbon $newEndsAt, ?string $reason = null): RentalCase {
        if (! in_array($case->status, [RentalCaseStatus::Reserved, RentalCaseStatus::HandedOver, RentalCaseStatus::Overdue], true)) {
            throw new \RuntimeException((string) __('Nur laufende Verleihakten können verlängert werden.'));
        }

        if ($newEndsAt <= $case->ends_at) {
            throw new \InvalidArgumentException((string) __('Das neue Ende muss nach dem bisherigen Ende liegen.'));
        }

        return DB::transaction(function () use ($case, $actor, $newEndsAt, $reason): RentalCase {
            foreach ($case->reservations()->active()->get() as $reservation) {
                $asset = $reservation->asset;
                if ($asset !== null) {
                    $conflict = $this->availability->findConflict($asset, $case->ends_at, $newEndsAt, $case->id);
                    if ($conflict !== null) {
                        throw new \App\Exceptions\RentalConflictException(
                            (string) __('Verlängerung kollidiert mit einer Folgebelegung von :asset.', ['asset' => $asset->name]),
                            $conflict,
                        );
                    }
                }

                $reservation->forceFill(['ends_at' => $newEndsAt])->save();
            }

            $previousEnd = $case->ends_at;
            $status = $case->status === RentalCaseStatus::Overdue ? RentalCaseStatus::HandedOver->value : $case->status->value;
            $case->forceFill(['ends_at' => $newEndsAt, 'status' => $status])->save();
            $case->audit('rental.extended', [
                'from' => $previousEnd->toDateTimeString(),
                'to' => $newEndsAt->toDateTimeString(),
                'reason' => $reason,
                'by' => $actor->id,
            ]);

            return $case;
        });
    }

    /**
     * Tauschgerät (MVP-264): alte Position wird als getauscht markiert, das
     * Ersatzgerät übernimmt den Restzeitraum nach Konfliktprüfung.
     */
    public function swapAsset(RentalCase $case, RentalCaseAsset $current, Asset $replacement, User $actor, ?string $note = null): RentalCaseAsset {
        if (! in_array($case->status, [RentalCaseStatus::Reserved, RentalCaseStatus::HandedOver, RentalCaseStatus::Overdue], true)) {
            throw new \RuntimeException((string) __('Tauschgeräte sind nur in laufenden Akten möglich.'));
        }

        return DB::transaction(function () use ($case, $current, $replacement, $actor, $note): RentalCaseAsset {
            $from = now();
            $this->availability->assertAvailable($replacement, $from, $case->ends_at, $case->id);

            $newCaseAsset = RentalCaseAsset::query()->create([
                'organization_id' => $case->organization_id,
                'rental_case_id' => $case->id,
                'asset_id' => $replacement->id,
                'status' => $case->status === RentalCaseStatus::Reserved ? 'planned' : 'handed_over',
                'note' => $note,
            ]);

            $current->forceFill(['status' => 'swapped', 'replaced_by_id' => $newCaseAsset->id])->save();

            // Belegung des Altgeräts endet jetzt; Ersatzgerät übernimmt.
            $case->reservations()->active()->where('asset_id', $current->asset_id)
                ->get()
                ->each(function (RentalReservation $reservation) use ($from): void {
                    $reservation->forceFill(['ends_at' => $from, 'status' => 'completed'])->save();
                });

            $profile = $replacement->rentalProfile;
            RentalReservation::query()->create([
                'organization_id' => $case->organization_id,
                'rental_case_id' => $case->id,
                'asset_id' => $replacement->id,
                'kind' => ($case->status === RentalCaseStatus::Reserved ? RentalReservationKind::Hard : RentalReservationKind::Rental)->value,
                'status' => 'active',
                'starts_at' => $from,
                'ends_at' => $case->ends_at,
                'buffer_before_hours' => $profile->buffer_before_hours ?? 0,
                'buffer_after_hours' => $profile->buffer_after_hours ?? 0,
                'created_by' => $actor->id,
            ]);

            $case->audit('rental.assetSwapped', [
                'old_asset_id' => $current->asset_id,
                'new_asset_id' => $replacement->id,
                'note' => $note,
            ]);

            return $newCaseAsset;
        });
    }

    /**
     * Rücknahmeprüfung (MVP-265) mit Folgeentscheidung: Reinigung erzeugt ein
     * Belegungsfenster, Reparatur/Sperre eine Sperre im gemeinsamen Modell,
     * Reklamation übergibt kontrolliert an Claims (MVP-267).
     *
     * @param array<string, mixed> $data
     */
    public function returnAsset(RentalCase $case, Asset $asset, User $actor, array $data = []): RentalReturnReport {
        if (! in_array($case->status, [RentalCaseStatus::HandedOver, RentalCaseStatus::Overdue], true)) {
            throw new \RuntimeException((string) __('Rücknahmen sind nur aus übergebenen Akten möglich.'));
        }

        return DB::transaction(function () use ($case, $asset, $actor, $data): RentalReturnReport {
            $caseAsset = $this->caseAssetFor($case, $asset);

            $report = RentalReturnReport::query()->create(array_merge($data, [
                'organization_id' => $case->organization_id,
                'rental_case_id' => $case->id,
                'asset_id' => $asset->id,
                'reported_at' => $data['reported_at'] ?? now(),
                'reported_by' => $actor->id,
            ]));

            $this->syncReportItems($report, $data);

            $caseAsset->forceFill(['status' => 'returned'])->save();
            $asset->audit('rental.returned', ['case' => $case->number, 'report_id' => $report->id]);

            $case->reservations()->active()->where('asset_id', $asset->id)
                ->get()
                ->each(fn (RentalReservation $r) => $r->forceFill(['status' => 'completed'])->save());

            $this->applyFollowUp($case, $asset, $actor, $report);

            $open = $case->caseAssets()->whereIn('status', ['planned', 'handed_over'])->exists();
            if (! $open) {
                $case->forceFill([
                    'status' => RentalCaseStatus::Returned->value,
                    'actual_return_at' => $report->reported_at,
                ])->save();
                $case->audit('rental.completed', []);
            }

            return $report;
        });
    }

    public function cancel(RentalCase $case, User $actor, ?string $reason = null): RentalCase {
        $this->assertTransition($case, RentalCaseStatus::Cancelled);

        return DB::transaction(function () use ($case, $actor, $reason): RentalCase {
            $case->reservations()->active()->get()->each(
                fn (RentalReservation $r) => $r->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save(),
            );

            $case->forceFill(['status' => RentalCaseStatus::Cancelled->value])->save();
            $case->audit('rental.cancelled', ['reason' => $reason, 'by' => $actor->id]);

            return $case;
        });
    }

    public function close(RentalCase $case, User $actor): RentalCase {
        $this->assertTransition($case, RentalCaseStatus::Closed);

        $case->forceFill([
            'status' => RentalCaseStatus::Closed->value,
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ])->save();
        $case->audit('rental.closed', []);

        return $case;
    }

    /**
     * Überfälligkeits-Scanner (MVP-264): setzt HandedOver-Akten nach
     * Fristablauf auf Overdue und benachrichtigt idempotent je Akte/Tag.
     */
    public function escalateOverdue(Organization $organization): int {
        $count = 0;

        $cases = RentalCase::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->overdueCandidates()
            ->get();

        foreach ($cases as $case) {
            $case->forceFill(['status' => RentalCaseStatus::Overdue->value])->save();
            $case->audit('rental.overdue', ['ends_at' => $case->ends_at->toDateTimeString()]);
            $count++;
        }

        $overdue = RentalCase::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', RentalCaseStatus::Overdue->value)
            ->get();

        foreach ($overdue as $case) {
            // Betroffene Person = Akten-Verantwortlicher; zusätzlich gehen
            // Rollen-Empfänger (Teamleitung) über die Org-Regel mit.
            $this->notifier->notify(NotificationEvent::RentalReturnOverdue, $case, $case->responsible, [
                'title' => (string) __('Verleih überfällig: :number', ['number' => $case->number]),
                'title_key' => 'Verleih überfällig: :number',
                'title_params' => ['number' => $case->number],
                'message' => (string) __('Rückgabe war bis :date vereinbart.', ['date' => $case->ends_at->format('d.m.Y H:i')]),
                'message_key' => 'Rückgabe war bis :date vereinbart.',
                'message_params' => ['date' => $case->ends_at->toIso8601String()],
                'url' => route('rental.show', $case),
            ], NotificationDispatchLog::STAGE_INITIAL, true);

            $this->notifier->escalateIfDue(NotificationEvent::RentalReturnOverdue, $case, [
                'title' => (string) __('Eskalation: Verleih :number weiterhin überfällig', ['number' => $case->number]),
                'title_key' => 'Eskalation: Verleih :number weiterhin überfällig',
                'title_params' => ['number' => $case->number],
                'url' => route('rental.show', $case),
            ]);
        }

        return $count;
    }

    private function applyFollowUp(RentalCase $case, Asset $asset, User $actor, RentalReturnReport $report): void {
        switch ($report->follow_up) {
            case RentalReturnFollowUp::Cleaning:
                RentalReservation::query()->create([
                    'organization_id' => $case->organization_id,
                    'rental_case_id' => $case->id,
                    'asset_id' => $asset->id,
                    'kind' => RentalReservationKind::Cleaning->value,
                    'status' => 'active',
                    'starts_at' => $report->reported_at,
                    'ends_at' => $report->reported_at->copy()->addHours(max(1, $asset->rentalProfile->buffer_after_hours ?? 4)),
                    'note' => (string) __('Reinigung nach Rücknahme :number', ['number' => $case->number]),
                    'created_by' => $actor->id,
                ]);
                break;

            case RentalReturnFollowUp::Repair:
            case RentalReturnFollowUp::Block:
                $this->blocks->block(
                    $asset,
                    AssetBlockReason::RentalDamage,
                    $actor,
                    $report->follow_up_note ?? $report->damages,
                    $report,
                );
                break;

            case RentalReturnFollowUp::Claim:
                $this->escalateToClaim($case, $asset, $actor, $report);
                break;

            case RentalReturnFollowUp::None:
                break;
        }
    }

    /**
     * Kontrollierte Übergabe an die Reklamation (MVP-267): Schaden wird als
     * Fallakte weiterbearbeitet, das Verleihmodul entscheidet nicht parallel.
     */
    private function escalateToClaim(RentalCase $case, Asset $asset, User $actor, RentalReturnReport $report): void {
        if (! app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.claims')) {
            return;
        }

        $claim = app(\App\Services\Claims\ClaimCaseService::class)->open(
            Organization::query()->findOrFail($case->organization_id),
            $actor,
            [
                'title' => (string) __('Verleihschaden :number: :asset', ['number' => $case->number, 'asset' => $asset->name]),
                'description' => trim(($report->damages ?? '') . "\n" . ($report->follow_up_note ?? '')),
                'customer_id' => $case->customer_id,
                'asset_id' => $asset->id,
            ],
        );

        \App\Models\Claims\ClaimCaseLink::query()->create([
            'organization_id' => $case->organization_id,
            'claim_case_id' => $claim->id,
            'linkable_type' => $case->getMorphClass(),
            'linkable_id' => $case->id,
            'role' => 'source',
            'note' => (string) __('Aus Rücknahmeprüfung übergeben'),
            'created_by' => $actor->id,
        ]);

        $case->audit('rental.claimOpened', ['claim' => $claim->number, 'asset_id' => $asset->id]);
    }

    private function caseAssetFor(RentalCase $case, Asset $asset): RentalCaseAsset {
        $caseAsset = $case->caseAssets()->where('asset_id', $asset->id)->first();

        if ($caseAsset === null) {
            throw new \RuntimeException((string) __('Das Asset gehört nicht zu dieser Verleihakte.'));
        }

        return $caseAsset;
    }

    /**
     * Checklisten-/Zubehörpositionen aus dem Dialog in die Protokolltabellen
     * übernehmen (rental_condition_items / rental_accessory_items).
     *
     * @param array<string, mixed> $data
     */
    private function syncReportItems(RentalHandoverReport|RentalReturnReport $report, array $data): void {
        foreach ((array) ($data['condition_items'] ?? []) as $item) {
            if (! is_array($item) || trim((string) ($item['label'] ?? '')) === '') {
                continue;
            }

            $report->conditionItems()->create([
                'organization_id' => $report->organization_id,
                'label' => (string) $item['label'],
                'state' => in_array($item['state'] ?? 'ok', \App\Models\Rental\RentalConditionItem::STATES, true) ? (string) $item['state'] : 'ok',
                'note' => $item['note'] ?? null,
            ]);
        }

        foreach ((array) ($data['accessory_items'] ?? []) as $item) {
            if (! is_array($item) || trim((string) ($item['label'] ?? '')) === '') {
                continue;
            }

            $report->accessoryItems()->create([
                'organization_id' => $report->organization_id,
                'label' => (string) $item['label'],
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'present' => (bool) ($item['present'] ?? true),
                'note' => $item['note'] ?? null,
            ]);
        }
    }

    private function assertTransition(RentalCase $case, RentalCaseStatus $target): void {
        if (! in_array($target, $case->status->allowedTransitions(), true)) {
            throw new \RuntimeException((string) __('Statuswechsel von :from nach :to ist nicht zulässig.', [
                'from' => $case->status->label(),
                'to' => $target->label(),
            ]));
        }
    }
}
