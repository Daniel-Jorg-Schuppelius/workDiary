<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetCompliance;

use App\Enums\Asset\AssetBlockReason;
use App\Enums\AssetCompliance\{AssetComplianceStatus, AssetInspectionResult, AssetInspectionScheduleStatus};
use App\Enums\Notification\NotificationEvent;
use App\Models\{Asset, AssetBlock, Organization, User};
use App\Models\AssetCompliance\{AssetCalibrationCertificate, AssetComplianceAssignment, AssetComplianceProfile, AssetInspectionEvent, AssetInspectionSchedule};
use App\Services\Asset\AssetBlockService;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Prüfmittel- und Prüfpflichtenverwaltung (Feature 075): Katalog-Profile
 * (P1), Prüfpflichten mit Fälligkeit/Toleranz/Nachfrist, Prüfprotokolle als
 * unveränderbare Nachweise (MVP-286/287) und Einsatzsperren über das
 * GEMEINSAME Sperrmodell (D12) — Verleih, Disposition und Einsatzfreigabe
 * lesen denselben Status.
 */
class AssetComplianceService {
    public function __construct(
        private readonly AssetBlockService $blocks,
        private readonly NotificationDispatcher $notifier,
    ) {}

    /**
     * Effektiver Profilkatalog (P1): Org-Profile überschreiben globale
     * Vorlagen mit gleichem Code.
     *
     * @return Collection<int, AssetComplianceProfile>
     */
    public function effectiveProfiles(int $organizationId): Collection {
        $profiles = AssetComplianceProfile::query()
            ->forOrganization($organizationId)
            ->orderBy('code')
            ->get();

        $orgCodes = $profiles->whereNotNull('organization_id')->pluck('code')->all();

        return $profiles
            ->reject(fn (AssetComplianceProfile $p): bool => $p->organization_id === null && in_array($p->code, $orgCodes, true))
            ->values();
    }

    /**
     * Prüfpflicht zuweisen (MVP-284) und Asset-Fälligkeit spiegeln.
     *
     * @param array<string, mixed> $attributes
     */
    public function assign(AssetComplianceProfile $profile, Asset $asset, User $actor, array $attributes = []): AssetComplianceAssignment {
        $lastDone = isset($attributes['last_done_on']) ? \Illuminate\Support\Carbon::parse((string) $attributes['last_done_on']) : null;
        $intervalMonths = (int) ($attributes['interval_months_override'] ?? $profile->interval_months);

        $nextDue = isset($attributes['next_due_on'])
            ? \Illuminate\Support\Carbon::parse((string) $attributes['next_due_on'])
            : ($lastDone?->copy()->addMonthsNoOverflow($intervalMonths) ?? now()->addMonthsNoOverflow($intervalMonths));

        $assignment = AssetComplianceAssignment::query()->create(array_merge($attributes, [
            'organization_id' => $asset->organization_id,
            'asset_compliance_profile_id' => $profile->id,
            'asset_id' => $asset->id,
            'last_done_on' => $lastDone?->toDateString(),
            'next_due_on' => $nextDue->toDateString(),
        ]));

        $asset->audit('assetCompliance.assigned', ['profile' => $profile->code, 'next_due_on' => $nextDue->toDateString()]);
        $this->refreshAssetNextInspection($asset);

        return $assignment;
    }

    /**
     * Prüfung erfassen (MVP-286/287): unveränderbares Ereignis mit
     * Ergebniszeilen (Grenzwert-Snapshot), Messwerten und optionalem
     * Zertifikat; aktualisiert Fälligkeit und wendet die Folgeentscheidung
     * an (MVP-289).
     *
     * @param array<string, mixed> $data
     */
    public function recordInspection(AssetComplianceAssignment $assignment, User $actor, array $data): AssetInspectionEvent {
        return DB::transaction(function () use ($assignment, $actor, $data): AssetInspectionEvent {
            $asset = $assignment->asset()->firstOrFail();
            $profile = $assignment->profile()->firstOrFail();
            $result = $data['result'] instanceof AssetInspectionResult
                ? $data['result']
                : AssetInspectionResult::from((string) $data['result']);

            if ($profile->requires_certificate && $result->isPassed() && empty($data['certificate'])) {
                throw new \InvalidArgumentException((string) __('Dieses Prüfprofil verlangt einen Zertifikatsnachweis.'));
            }

            $performedAt = isset($data['performed_at'])
                ? \Illuminate\Support\Carbon::parse((string) $data['performed_at'])
                : now();

            $validUntil = $result->isPassed()
                ? ($data['valid_until'] ?? $performedAt->copy()->addMonthsNoOverflow($assignment->intervalMonths())->toDateString())
                : null;

            $event = AssetInspectionEvent::query()->create([
                'organization_id' => $assignment->organization_id,
                'asset_inspection_schedule_id' => $data['schedule_id'] ?? null,
                'asset_compliance_assignment_id' => $assignment->id,
                'asset_id' => $asset->id,
                'performed_at' => $performedAt,
                'performed_by_user_id' => $actor->id,
                'external_inspector_name' => $data['external_inspector_name'] ?? null,
                'result' => $result->value,
                'valid_until' => $validUntil,
                'checklist' => $data['checklist'] ?? null,
                'signature_name' => $data['signature_name'] ?? null,
                'signed_at' => ! empty($data['signature_name']) ? now() : null,
                'note' => $data['note'] ?? null,
                'supersedes_id' => $data['supersedes_id'] ?? null,
            ]);

            // Ergebniszeilen: Grenzwerte der Anforderungen als P2-Snapshot.
            $requirements = $profile->requirements()->get()->keyBy('id');
            foreach ((array) ($data['results'] ?? []) as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $requirement = isset($line['requirement_id']) ? $requirements->get((int) $line['requirement_id']) : null;
                $value = isset($line['value']) && $line['value'] !== '' ? (float) $line['value'] : null;
                $min = $requirement?->limit_min !== null ? (float) $requirement->limit_min : null;
                $max = $requirement?->limit_max !== null ? (float) $requirement->limit_max : null;

                $passed = $value === null
                    || (($min === null || $value >= $min) && ($max === null || $value <= $max));

                $event->results()->create([
                    'organization_id' => $assignment->organization_id,
                    'asset_compliance_requirement_id' => $requirement?->id,
                    'label' => (string) ($line['label'] ?? $requirement->label ?? '—'),
                    'value' => $value,
                    'unit' => $line['unit'] ?? $requirement?->unit,
                    'limit_min' => $min,
                    'limit_max' => $max,
                    'passed' => $passed,
                    'note' => $line['note'] ?? null,
                ]);
            }

            foreach ((array) ($data['measurements'] ?? []) as $measurement) {
                if (! is_array($measurement) || ! isset($measurement['value'])) {
                    continue;
                }

                $event->measurements()->create([
                    'organization_id' => $assignment->organization_id,
                    'label' => (string) ($measurement['label'] ?? '—'),
                    'value' => (float) $measurement['value'],
                    'unit' => $measurement['unit'] ?? null,
                    'measured_at' => $measurement['measured_at'] ?? $performedAt,
                ]);
            }

            if (! empty($data['certificate']) && is_array($data['certificate'])) {
                $this->storeCertificate($event, $data['certificate']);
            }

            // Fälligkeit fortschreiben + Termin abschließen.
            if ($result->isPassed()) {
                $assignment->forceFill([
                    'last_done_on' => $performedAt->toDateString(),
                    'next_due_on' => $performedAt->copy()->addMonthsNoOverflow($assignment->intervalMonths())->toDateString(),
                ])->save();
            }

            if (isset($data['schedule_id'])) {
                AssetInspectionSchedule::query()->whereKey((int) $data['schedule_id'])
                    ->first()?->forceFill(['status' => AssetInspectionScheduleStatus::Done->value])->save();
            }

            $this->applyFollowUp($assignment, $asset, $actor, $event, (string) ($data['follow_up'] ?? 'none'), $data['follow_up_note'] ?? null);
            $this->refreshAssetNextInspection($asset);

            $asset->audit('assetCompliance.inspected', [
                'event_id' => $event->id,
                'result' => $result->value,
                'valid_until' => $validUntil,
            ]);

            return $event;
        });
    }

    /**
     * Abgeleiteter Prüfstatus (MVP-288): Einsatz, Disposition und Verleih
     * lesen dieselbe Bewertung — Sperren kommen aus dem D12-Modell.
     */
    public function statusFor(Asset $asset): AssetComplianceStatus {
        $complianceBlock = AssetBlock::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->whereIn('reason', [AssetBlockReason::InspectionOverdue->value, AssetBlockReason::InspectionFailed->value])
            ->exists();

        if ($complianceBlock) {
            return AssetComplianceStatus::Blocked;
        }

        $assignments = AssetComplianceAssignment::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->with('profile')
            ->get();

        if ($assignments->isEmpty()) {
            return AssetComplianceStatus::NotApplicable;
        }

        if ($assignments->contains(fn (AssetComplianceAssignment $a): bool => $a->isOverdue())) {
            return AssetComplianceStatus::Overdue;
        }

        $latest = AssetInspectionEvent::query()
            ->where('asset_id', $asset->id)
            ->orderByDesc('performed_at')
            ->first();

        if ($latest !== null
            && $latest->result === AssetInspectionResult::PassedWithRestrictions
            && ($latest->valid_until === null || ! $latest->valid_until->endOfDay()->isPast())) {
            return AssetComplianceStatus::Restricted;
        }

        if ($assignments->contains(fn (AssetComplianceAssignment $a): bool => $a->isDueSoon())) {
            return AssetComplianceStatus::DueSoon;
        }

        return AssetComplianceStatus::Valid;
    }

    /**
     * Fälligkeits-Scan (MVP-285/288): Warnungen ab Vorwarnzeit, Sperren
     * gemäß blocking_mode (sofort bzw. nach Nachfrist) — idempotent.
     */
    public function scanAssignments(Organization $organization): int {
        $sent = 0;

        $assignments = AssetComplianceAssignment::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->active()
            ->with(['profile', 'asset', 'responsible'])
            ->get();

        foreach ($assignments as $assignment) {
            $asset = $assignment->asset;
            $profile = $assignment->profile;

            if ($asset === null || $profile === null) {
                continue;
            }

            if ($assignment->isDueSoon() || $assignment->isOverdue()) {
                $payload = [
                    'title' => (string) __('Prüfung fällig: :profile (:asset)', ['profile' => $profile->name, 'asset' => $asset->name]),
                    'message' => (string) __('Fällig am :date.', ['date' => $assignment->next_due_on?->format('d.m.Y') ?? '—']),
                    'url' => route('asset-compliance.index'),
                ];
                $sent += $this->notifier->notify(NotificationEvent::AssetInspectionDue, $assignment, $assignment->responsible, $payload, dedup: true);
                $sent += $this->notifier->escalateIfDue(NotificationEvent::AssetInspectionDue, $assignment, $payload);
            }

            $shouldBlock = match ($profile->blocking_mode) {
                \App\Enums\AssetCompliance\AssetComplianceBlockMode::BlockImmediately => $assignment->isOverdue(),
                \App\Enums\AssetCompliance\AssetComplianceBlockMode::BlockAfterGrace => $assignment->isPastGrace(),
                default => false,
            };

            if ($shouldBlock && ! $this->hasActiveBlockFor($assignment)) {
                $this->blocks->block(
                    $asset,
                    AssetBlockReason::InspectionOverdue,
                    null,
                    (string) __('Prüfung ":profile" überfällig seit :date.', ['profile' => $profile->name, 'date' => $assignment->next_due_on?->format('d.m.Y') ?? '—']),
                    $assignment,
                );
            }
        }

        return $sent;
    }

    /**
     * Asset-Spiegel: frühester Prüftermin aller aktiven Pflichten →
     * assets.next_inspection_on (Anzeige + bestehende Fälligkeits-Gates).
     */
    public function refreshAssetNextInspection(Asset $asset): void {
        $earliest = AssetComplianceAssignment::query()
            ->where('asset_id', $asset->id)
            ->active()
            ->whereNotNull('next_due_on')
            ->orderBy('next_due_on')
            ->value('next_due_on');

        $asset->forceFill(['next_inspection_on' => $earliest])->saveQuietly();
    }

    /**
     * Abweichungs-/Maßnahmenpfad (MVP-289): Sperre/Reparatur über das
     * gemeinsame Modell, Streit über Claims, Aussonderung dokumentiert.
     */
    private function applyFollowUp(AssetComplianceAssignment $assignment, Asset $asset, User $actor, AssetInspectionEvent $event, string $followUp, ?string $note): void {
        // Bestandene Prüfung hebt prüfungsbedingte Sperren auf.
        if ($event->result->isPassed() && $followUp === 'none') {
            AssetBlock::query()
                ->where('asset_id', $asset->id)
                ->active()
                ->whereIn('reason', [AssetBlockReason::InspectionOverdue->value, AssetBlockReason::InspectionFailed->value])
                ->get()
                ->each(fn (AssetBlock $block) => $this->blocks->release($block, $actor, (string) __('Prüfung bestanden (Ereignis #:id).', ['id' => $event->id])));

            return;
        }

        switch ($followUp) {
            case 'block':
            case 'repair':
            case 'recalibration':
                $this->blocks->block($asset, AssetBlockReason::InspectionFailed, $actor, $note ?? $event->note, $event);
                break;

            case 'restricted':
                $asset->audit('assetCompliance.restrictedUse', ['event_id' => $event->id, 'note' => $note]);
                break;

            case 'decommission':
                $this->blocks->block($asset, AssetBlockReason::Other, $actor, $note ?? (string) __('Aussonderung nach Prüfung.'), $event);
                $asset->audit('assetCompliance.decommissionRequested', ['event_id' => $event->id]);
                break;

            case 'claim':
                $this->escalateToClaim($asset, $actor, $event, $note);
                break;

            default:
                if (! $event->result->isPassed()) {
                    // Nicht bestanden ohne explizite Maßnahme → Sperre
                    // (keine automatische Freigabe, MVP-Abgrenzung).
                    $this->blocks->block($asset, AssetBlockReason::InspectionFailed, $actor, $note ?? $event->note, $event);
                }
        }
    }

    private function escalateToClaim(Asset $asset, User $actor, AssetInspectionEvent $event, ?string $note): void {
        if (! app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.claims')) {
            return;
        }

        $claim = app(\App\Services\Claims\ClaimCaseService::class)->open(
            Organization::query()->findOrFail($event->organization_id),
            $actor,
            [
                'title' => (string) __('Prüfabweichung: :asset', ['asset' => $asset->name]),
                'description' => trim(($note ?? '') . "\n" . ($event->note ?? '')),
                'asset_id' => $asset->id,
            ],
        );

        \App\Models\Claims\ClaimCaseLink::query()->create([
            'organization_id' => $event->organization_id,
            'claim_case_id' => $claim->id,
            'linkable_type' => $event->getMorphClass(),
            'linkable_id' => $event->id,
            'role' => 'source',
            'note' => (string) __('Aus Prüfprotokoll übergeben'),
            'created_by' => $actor->id,
        ]);

        $asset->audit('assetCompliance.claimOpened', ['claim' => $claim->number, 'event_id' => $event->id]);
    }

    /** @param array<string, mixed> $certificate */
    private function storeCertificate(AssetInspectionEvent $event, array $certificate): AssetCalibrationCertificate {
        return AssetCalibrationCertificate::query()->create([
            'organization_id' => $event->organization_id,
            'asset_inspection_event_id' => $event->id,
            'certificate_no' => (string) ($certificate['certificate_no'] ?? ''),
            'issuer' => (string) ($certificate['issuer'] ?? ''),
            'issued_on' => $certificate['issued_on'] ?? now()->toDateString(),
            'valid_until' => $certificate['valid_until'] ?? null,
            'measurement_range' => $certificate['measurement_range'] ?? null,
            'tolerance' => $certificate['tolerance'] ?? null,
            'document_id' => $certificate['document_id'] ?? null,
            'sha256' => $certificate['sha256'] ?? null,
            'note' => $certificate['note'] ?? null,
        ]);
    }

    private function hasActiveBlockFor(AssetComplianceAssignment $assignment): bool {
        return AssetBlock::query()
            ->where('asset_id', $assignment->asset_id)
            ->active()
            ->where('reason', AssetBlockReason::InspectionOverdue->value)
            ->where('source_type', $assignment->getMorphClass())
            ->where('source_id', $assignment->id)
            ->exists();
    }
}
