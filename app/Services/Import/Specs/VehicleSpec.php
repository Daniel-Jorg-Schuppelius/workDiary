<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Enums\Vehicle\{VehicleOwnership, VehiclePropulsion, VehicleType};
use App\Models\{Organization, Vehicle};
use App\Services\Import\{ImportOutcome, ValidationIssue};
use Throwable;

/**
 * CSV-Spezifikation für den Fahrzeug-Import (Feature 020 / MVP-049).
 *
 * Fachlicher Schlüssel zur Idempotenz: (organization_id, license_plate). Ein
 * bereits vorhandenes Fahrzeug mit demselben Kennzeichen wird aktualisiert,
 * sonst neu angelegt. Felder ohne Wert in der CSV überschreiben keine
 * bestehenden Daten. Enum-Felder (Typ, Antrieb, Eigentum) werden auf ihre
 * kanonischen Werte abgebildet und gegen die jeweilige Enum validiert.
 */
class VehicleSpec extends AbstractEntitySpec {
    public function entity(): ImportEntity {
        return ImportEntity::Vehicles;
    }

    public function columns(): array {
        return [
            'license_plate',
            'label',
            'vehicle_type',
            'propulsion',
            'ownership',
            'odometer_km',
            'tank_capacity_liters',
            'battery_capacity_kwh',
            'wltp_consumption',
            'default_rate_per_km',
            'notes',
        ];
    }

    public function requiredColumns(): array {
        return ['license_plate'];
    }

    public function headerAliases(): array {
        return [
            'kennzeichen' => 'license_plate',
            'nummernschild' => 'license_plate',
            'kfz-kennzeichen' => 'license_plate',
            'bezeichnung' => 'label',
            'name' => 'label',
            'fahrzeug' => 'label',
            'typ' => 'vehicle_type',
            'fahrzeugtyp' => 'vehicle_type',
            'art' => 'vehicle_type',
            'antrieb' => 'propulsion',
            'kraftstoff' => 'propulsion',
            'treibstoff' => 'propulsion',
            'eigentum' => 'ownership',
            'besitz' => 'ownership',
            'eigentumsart' => 'ownership',
            'kilometerstand' => 'odometer_km',
            'km-stand' => 'odometer_km',
            'tankvolumen' => 'tank_capacity_liters',
            'tankinhalt' => 'tank_capacity_liters',
            'akkukapazität' => 'battery_capacity_kwh',
            'batteriekapazität' => 'battery_capacity_kwh',
            'verbrauch' => 'wltp_consumption',
            'kilometersatz' => 'default_rate_per_km',
            'km-satz' => 'default_rate_per_km',
            'notiz' => 'notes',
            'bemerkung' => 'notes',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'license_plate' => $this->upperOrNull($this->trimmedString($raw)),
                'vehicle_type', 'propulsion', 'ownership' => $this->enumValue($this->trimmedString($raw)),
                'odometer_km' => ($v = $this->decimal($this->trimmedString($raw))) !== null ? (int) round((float) $v) : null,
                'tank_capacity_liters', 'battery_capacity_kwh', 'wltp_consumption', 'default_rate_per_km' => $this->decimal($this->trimmedString($raw)),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];

        if (($row['license_plate'] ?? null) === null) {
            $issues[] = $this->requiredIssue('license_plate');
        } elseif (mb_strlen((string) $row['license_plate']) > 32) {
            $issues[] = $this->tooLongIssue('license_plate', 32);
        }

        if (($row['label'] ?? null) !== null && mb_strlen((string) $row['label']) > 255) {
            $issues[] = $this->tooLongIssue('label', 255);
        }

        $this->validateEnum($issues, $row, 'vehicle_type', VehicleType::class);
        $this->validateEnum($issues, $row, 'propulsion', VehiclePropulsion::class);
        $this->validateEnum($issues, $row, 'ownership', VehicleOwnership::class);

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            $payload = array_filter($row, static fn($v): bool => $v !== null);
            $payload['organization_id'] = $organization->id;
            $payload['vehicle_type'] ??= VehicleType::Car->value;
            $payload['propulsion'] ??= VehiclePropulsion::Diesel->value;
            $payload['ownership'] ??= VehicleOwnership::Owned->value;

            $existing = Vehicle::query()
                ->where('organization_id', $organization->id)
                ->where('license_plate', $payload['license_plate'])
                ->first();

            if ($existing !== null) {
                $existing->fill($payload)->save();

                return [ImportOutcome::Updated, null];
            }

            Vehicle::create($payload);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [
                ImportOutcome::Failed,
                new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage()),
            ];
        }
    }

    /**
     * Bildet einen Roh-Wert auf den kanonischen Enum-Backing-Wert ab. Akzeptiert
     * sowohl den technischen Wert (z. B. "diesel") als auch — case-insensitiv —
     * die übersetzten Labels (z. B. "Diesel", "Elektro"). Unbekannte Werte werden
     * unverändert (kleingeschrieben) zurückgegeben, damit {@see validateRow()}
     * sie als Formatfehler meldet.
     */
    private function enumValue(?string $value): ?string {
        if ($value === null) {
            return null;
        }

        return mb_strtolower($value);
    }

    /**
     * @param  list<ValidationIssue>  $issues
     * @param  array<string, mixed>  $row
     * @param  class-string<VehicleType|VehiclePropulsion|VehicleOwnership>  $enum
     */
    private function validateEnum(array &$issues, array $row, string $field, string $enum): void {
        $value = $row[$field] ?? null;
        if ($value === null) {
            return;
        }
        if ($enum::tryFrom((string) $value) === null) {
            $issues[] = $this->formatIssue($field, (string) __('import.error.format.enum'));
        }
    }
}
