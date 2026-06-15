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

use App\Enums\Asset\AssetStatus;
use App\Models\Asset;
use Illuminate\Support\Carbon;

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
