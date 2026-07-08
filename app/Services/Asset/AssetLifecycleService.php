<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetLifecycleService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use App\Enums\Asset\{AssetOwnership, AssetStatus};
use App\Models\{Asset, AssetOwnershipChange, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Leitet den Lebenszyklus-Status eines Assets für die Objektakte (Feature 027)
 * aus den vorhandenen Stammdaten ab — KEINE eigene Statusmaschine. Quelle bleibt
 * {@see AssetStatus} + decommissioned_on/warranty_until.
 *
 * Phasen:
 *  - inOperation   : aktiv im Einsatz (active/inMaintenance/inRepair/…)
 *  - retired       : ersetzt, aber noch nicht endgültig stillgelegt (replaced)
 *  - decommissioned: endgültig außer Betrieb (decommissioned/lost oder
 *                    decommissioned_on gesetzt)
 */
class AssetLifecycleService {
    public const PHASE_IN_OPERATION = 'inOperation';

    public const PHASE_RETIRED = 'retired';

    public const PHASE_DECOMMISSIONED = 'decommissioned';

    /**
     * Verzeichnet einen Eigentümerwechsel append-only (Feature 027 → Rang 49)
     * und aktualisiert das Asset (Eigentümerschaft + optional Kunde) atomar.
     * Ohne echte Änderung passiert nichts (keine Leerzeilen in der Historie).
     * Bei Nicht-Kunden-Eigentümerschaft wird die Kundenbindung entfernt.
     */
    public function changeOwnership(
        Asset $asset,
        User $actor,
        AssetOwnership $toOwnership,
        ?int $toCustomerId = null,
        ?string $note = null,
    ): ?AssetOwnershipChange {
        $fromOwnership = $asset->owned_by;
        $fromCustomerId = $asset->customer_id !== null ? (int) $asset->customer_id : null;

        if ($toOwnership !== AssetOwnership::Customer) {
            $toCustomerId = null;
        }

        if ($fromOwnership === $toOwnership && $fromCustomerId === $toCustomerId) {
            return null;
        }

        return DB::transaction(function () use ($asset, $actor, $fromOwnership, $toOwnership, $fromCustomerId, $toCustomerId, $note): AssetOwnershipChange {
            $change = AssetOwnershipChange::query()->create([
                'organization_id' => $asset->organization_id,
                'asset_id' => $asset->id,
                'from_ownership' => $fromOwnership->value,
                'to_ownership' => $toOwnership->value,
                'from_customer_id' => $fromCustomerId,
                'to_customer_id' => $toCustomerId,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'changed_by_user_id' => $actor->id,
                'changed_at' => Carbon::now(),
            ]);

            $asset->forceFill([
                'owned_by' => $toOwnership->value,
                'customer_id' => $toCustomerId,
            ])->save();

            // Sichtbar in der Objektakte-Timeline (asset.audit); der generische
            // `updated`-Eintrag des Speicherns wird dort ohnehin ausgeblendet.
            $asset->audit('asset.ownership_changed', [
                'from' => $fromOwnership->value,
                'to' => $toOwnership->value,
                'from_customer_id' => $fromCustomerId,
                'to_customer_id' => $toCustomerId,
            ]);

            return $change;
        });
    }

    public function phase(Asset $asset): string {
        if ($asset->status === AssetStatus::Decommissioned
            || $asset->status === AssetStatus::Lost
            || $asset->decommissioned_on !== null) {
            return self::PHASE_DECOMMISSIONED;
        }

        if ($asset->status === AssetStatus::Replaced) {
            return self::PHASE_RETIRED;
        }

        return self::PHASE_IN_OPERATION;
    }

    public function phaseLabel(Asset $asset): string {
        return match ($this->phase($asset)) {
            self::PHASE_DECOMMISSIONED => __('asset.lifecycle.decommissioned'),
            self::PHASE_RETIRED => __('asset.lifecycle.retired'),
            default => __('asset.lifecycle.in_operation'),
        };
    }

    public function phaseTone(Asset $asset): string {
        return match ($this->phase($asset)) {
            self::PHASE_DECOMMISSIONED => 'error',
            self::PHASE_RETIRED => 'warning',
            default => 'success',
        };
    }

    public function warrantyExpired(Asset $asset, ?Carbon $reference = null): bool {
        if ($asset->warranty_until === null) {
            return false;
        }

        return ($reference ?? Carbon::today())->greaterThan($asset->warranty_until);
    }

    /**
     * Kompaktes Lebenszyklus-Dossier-Summary für Kopfbereich und Druckansicht.
     *
     * @return array{phase: string, phase_label: string, phase_tone: string, commissioned_on: ?Carbon, decommissioned_on: ?Carbon, warranty_until: ?Carbon, warranty_expired: bool, age_days: ?int, in_service_days: ?int}
     */
    public function summary(Asset $asset, ?Carbon $reference = null): array {
        $ref = $reference ?? Carbon::today();
        $end = $asset->decommissioned_on ?? $ref;
        $inServiceDays = $asset->commissioned_on !== null
            ? max(0, (int) $asset->commissioned_on->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()))
            : null;

        return [
            'phase' => $this->phase($asset),
            'phase_label' => $this->phaseLabel($asset),
            'phase_tone' => $this->phaseTone($asset),
            'commissioned_on' => $asset->commissioned_on,
            'decommissioned_on' => $asset->decommissioned_on,
            'warranty_until' => $asset->warranty_until,
            'warranty_expired' => $this->warrantyExpired($asset, $ref),
            'age_days' => $inServiceDays,
            'in_service_days' => $inServiceDays,
        ];
    }
}
