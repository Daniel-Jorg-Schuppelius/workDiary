<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleInspectionOverdueException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\{AssetBlock, Vehicle};

/**
 * Feature 138 (MVP-703): Reservierung eines Fahrzeugs, dessen Asset wegen
 * überfälliger/nicht bestandener Pflichtprüfung (HU/AU/UVV/SP) gesperrt ist.
 * Spezialisierung des D12-Fehlers — Aufrufer, die AssetNotUsableException
 * behandeln, sehen weiterhin denselben Typ; die Ausnahmefreigabe bleibt der
 * Notfallweg.
 */
class VehicleInspectionOverdueException extends AssetNotUsableException {
    public function __construct(public readonly Vehicle $vehicle, ?AssetBlock $block = null) {
        parent::__construct((string) __('Fahrzeug :vehicle: Pflichtprüfung überfällig (:reason) — Reservierung gesperrt. Prüfung dokumentieren oder eine befristete Ausnahmefreigabe erteilen.', [
            'vehicle' => $vehicle->displayName(),
            'reason' => $block?->reason->label() ?? (string) __('Prüfung überfällig'),
        ]), $block);
    }
}
