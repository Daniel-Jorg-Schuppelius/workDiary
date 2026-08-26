<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Asset\{AssetClass, AssetHealth, AssetOwnership, AssetStatus};
use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\{Asset, Organization};
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\Concerns\{ResolvesImportReferences, ValidatesImportDates};
use Throwable;

/**
 * Assets aus einem Vorsystem (MVP-707, Vollscan H20). Idempotenzschlüssel
 * (Organisation, `asset_no`) — vorhandene Assets werden aktualisiert (leere
 * CSV-Felder überschreiben nichts). Kunde über Kundennummer (org-gescopt),
 * Enum-Felder (Klasse, Status, Eigentum, Zustand) gegen die Enums geprüft,
 * Datumsfelder über das gemeinsame ParsesMixedDate-Muster.
 */
class AssetSpec extends AbstractEntitySpec {
    use ResolvesImportReferences;
    use ValidatesImportDates;

    /** @var list<string> */
    private const DATE_COLUMNS = ['commissioned_on', 'decommissioned_on', 'warranty_until', 'next_maintenance_on', 'next_inspection_on', 'acquired_on'];

    public function entity(): ImportEntity {
        return ImportEntity::Assets;
    }

    public function columns(): array {
        return [
            'asset_no',
            'name',
            'asset_class',
            'category_code',
            'manufacturer',
            'model',
            'serial_no',
            'inventory_no',
            'customer_number',
            'owned_by',
            'status',
            'health',
            'location_text',
            'commissioned_on',
            'decommissioned_on',
            'warranty_until',
            'next_maintenance_on',
            'next_inspection_on',
            'acquisition_cost',
            'acquired_on',
            'notes',
        ];
    }

    public function requiredColumns(): array {
        return ['asset_no', 'name'];
    }

    public function headerAliases(): array {
        return [
            'assetnummer' => 'asset_no',
            'asset-nummer' => 'asset_no',
            'nummer' => 'asset_no',
            'bezeichnung' => 'name',
            'klasse' => 'asset_class',
            'typ' => 'asset_class',
            'kategorie' => 'category_code',
            'hersteller' => 'manufacturer',
            'modell' => 'model',
            'seriennummer' => 'serial_no',
            'inventarnummer' => 'inventory_no',
            'kundennummer' => 'customer_number',
            'eigentum' => 'owned_by',
            'eigentümer' => 'owned_by',
            'zustand' => 'health',
            'standort' => 'location_text',
            'inbetriebnahme' => 'commissioned_on',
            'außerbetriebnahme' => 'decommissioned_on',
            'garantie bis' => 'warranty_until',
            'garantie' => 'warranty_until',
            'nächste wartung' => 'next_maintenance_on',
            'nächste prüfung' => 'next_inspection_on',
            'anschaffungskosten' => 'acquisition_cost',
            'anschaffungsdatum' => 'acquired_on',
            'notiz' => 'notes',
            'bemerkung' => 'notes',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'asset_class', 'status', 'owned_by', 'health' => $this->enumValue($this->trimmedString($raw)),
                'acquisition_cost' => $this->decimal($this->trimmedString($raw)),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];

        if (($row['asset_no'] ?? null) === null) {
            $issues[] = $this->requiredIssue('asset_no');
        } elseif (mb_strlen((string) $row['asset_no']) > 64) {
            $issues[] = $this->tooLongIssue('asset_no', 64);
        }
        if (($row['name'] ?? null) === null) {
            $issues[] = $this->requiredIssue('name');
        } elseif (mb_strlen((string) $row['name']) > 255) {
            $issues[] = $this->tooLongIssue('name', 255);
        }

        $this->validateEnum($issues, $row, 'asset_class', AssetClass::class);
        $this->validateEnum($issues, $row, 'status', AssetStatus::class);
        $this->validateEnum($issues, $row, 'owned_by', AssetOwnership::class);
        $this->validateEnum($issues, $row, 'health', AssetHealth::class);

        $customerNumber = $row['customer_number'] ?? null;
        if ($customerNumber !== null && $this->customerByNumber($organization, (string) $customerNumber) === null) {
            $issues[] = $this->fkIssue('customer_number', 'customer', (string) $customerNumber);
        }
        // Kundeneigentum ohne Kunde ist inkonsistent (Regel aus AssetService).
        $ownership = AssetOwnership::tryFrom((string) ($row['owned_by'] ?? ''));
        if ($ownership !== null && $ownership->requiresCustomer() && $customerNumber === null) {
            $issues[] = $this->requiredIssue('customer_number');
        }

        foreach (self::DATE_COLUMNS as $field) {
            $this->validateDateField($issues, $row, $field);
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            $customer = $this->customerByNumber($organization, $row['customer_number'] ?? null);
            if (($row['customer_number'] ?? null) !== null && $customer === null) {
                return [ImportOutcome::Failed, $this->fkIssue('customer_number', 'customer', (string) $row['customer_number'])];
            }

            $payload = array_filter($row, static fn($v): bool => $v !== null);
            unset($payload['customer_number']);
            foreach (self::DATE_COLUMNS as $field) {
                if (isset($payload[$field])) {
                    $payload[$field] = $this->dateString($payload[$field]);
                }
            }
            $payload['organization_id'] = $organization->id;
            if ($customer !== null) {
                $payload['customer_id'] = $customer->id;
            }

            $existing = $this->assetByNumber($organization, (string) $payload['asset_no']);
            if ($existing !== null) {
                $existing->fill($payload)->save();

                return [ImportOutcome::Updated, null];
            }

            $payload['asset_class'] ??= AssetClass::Device->value;
            $payload['status'] ??= AssetStatus::Active->value;
            $payload['owned_by'] ??= AssetOwnership::Organization->value;
            $payload['health'] ??= AssetHealth::Ok->value;

            $asset = Asset::query()->create($payload);
            $asset->audit('asset.created', ['asset_no' => $asset->asset_no, 'source' => 'csv-import']);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage())];
        }
    }

    /**
     * Enum-Rohwerte: technischer Wert oder — case-insensitiv — Label; unbekannte
     * Werte bleiben stehen, damit {@see validateRow()} sie meldet. `org` und
     * `customer` (AssetOwnership) sind bereits kanonisch.
     */
    private function enumValue(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        $lower = mb_strtolower($value);

        // Enum-Werte mit Binnenmajuskel (inMaintenance, loanOut …) tolerant abbilden.
        foreach ([AssetClass::class, AssetStatus::class, AssetOwnership::class, AssetHealth::class] as $enum) {
            foreach ($enum::cases() as $case) {
                if (mb_strtolower($case->value) === $lower) {
                    return $case->value;
                }
            }
        }

        return $value;
    }

    /**
     * @param  list<ValidationIssue>  $issues
     * @param  array<string, mixed>  $row
     * @param  class-string<AssetClass|AssetStatus|AssetOwnership|AssetHealth>  $enum
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
