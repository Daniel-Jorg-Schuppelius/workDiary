<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LogbookRules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Travel;

use App\Models\{TravelLog, Vehicle};

/**
 * Fachregeln des steuerlichen Fahrtenbuchs (Feature 137, GoBD „zeitnah,
 * lückenlos, unveränderlich"): Pflicht-km-Stände, Plausibilität Tacho ↔
 * Distanz (±5 %) und lückenlose km-Kette je Fahrzeug. Nur im Logbook-Modus
 * blockierend; Erstattungs-Fahrten liefern höchstens eine Lückenwarnung.
 */
final class LogbookRules {
    /** Zulässige Abweichung Tacho-Differenz ↔ erfasste Distanz. */
    public const PLAUSIBILITY_TOLERANCE = 0.05;

    /**
     * Feldbezogene Verstöße für den Logbook-Modus; leer = zulässig.
     *
     * @param  array<string, mixed>  $attributes  bereits mit Defaults/Bestand gemischt
     * @return array<string, string>
     */
    public function violations(array $attributes, Vehicle $vehicle, ?TravelLog $existing = null): array {
        if (! $vehicle->logbook_mode) {
            return [];
        }

        $errors = [];
        $start = $this->intOrNull($attributes['odometer_start_km'] ?? null);
        $end = $this->intOrNull($attributes['odometer_end_km'] ?? null);

        if ($start === null) {
            $errors['odometer_start_km'] = (string) __('Im Fahrtenbuch-Modus ist der Tachostand bei Fahrtbeginn Pflicht.');
        }
        if ($end === null) {
            $errors['odometer_end_km'] = (string) __('Im Fahrtenbuch-Modus ist der Tachostand bei Fahrtende Pflicht.');
        }
        if ($start === null || $end === null) {
            return $errors;
        }
        if ($end < $start) {
            $errors['odometer_end_km'] = (string) __('Der Tachostand bei Fahrtende muss mindestens dem Stand bei Fahrtbeginn entsprechen.');

            return $errors;
        }

        $driven = $end - $start;
        $distance = (float) ($attributes['distance_km'] ?? 0);
        if (! empty($attributes['round_trip'])) {
            $distance *= 2; // Distanz ist einfache Strecke; Tacho zählt beide Richtungen
        }
        if (abs($driven - $distance) > $driven * self::PLAUSIBILITY_TOLERANCE) {
            $errors['distance_km'] = (string) __('Distanz (:distance km) und Tacho-Differenz (:driven km) weichen um mehr als :tolerance % ab.', [
                'distance' => number_format($distance, 2, ',', '.'),
                'driven' => $driven,
                'tolerance' => (int) (self::PLAUSIBILITY_TOLERANCE * 100),
            ]);
        }

        $corrected = $attributes['corrects_travel_log_id'] ?? $existing?->corrects_travel_log_id;
        $expectedStart = $this->lastOdometerEnd($vehicle, $existing, $corrected !== null ? (int) $corrected : null);
        if ($expectedStart !== null && $expectedStart !== $start) {
            $errors['odometer_start_km'] = (string) __('Lücke in der km-Kette: Die letzte Fahrt endete bei :expected km, diese beginnt bei :start km.', [
                'expected' => $expectedStart,
                'start' => $start,
            ]);
        }

        return $errors;
    }

    /**
     * End-km der letzten wirksamen Fahrt des Fahrzeugs (ohne stornierte
     * Originale, ohne die gerade bearbeitete Fahrt) — Anker der km-Kette.
     */
    public function lastOdometerEnd(Vehicle $vehicle, ?TravelLog $exclude = null, ?int $excludeCorrectedId = null): ?int {
        $excludeCorrectedId ??= $exclude?->corrects_travel_log_id;
        $value = TravelLog::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereNotNull('odometer_end_km')
            ->effective()
            ->when($exclude !== null, fn ($q) => $q->whereKeyNot($exclude?->getKey()))
            ->when($excludeCorrectedId !== null, fn ($q) => $q->whereKeyNot($excludeCorrectedId))
            ->orderByDesc('date')
            ->orderByDesc('odometer_end_km')
            ->orderByDesc('id')
            ->value('odometer_end_km');

        return $value === null ? null : (int) $value;
    }

    private function intOrNull(mixed $value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
