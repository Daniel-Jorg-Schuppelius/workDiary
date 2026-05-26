<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\{Material, Organization};
use App\Services\Import\{ImportOutcome, ValidationIssue};
use Throwable;

/**
 * CSV-Spezifikation für den Material-Import (MVP-049).
 *
 * Idempotenz-Schlüssel: (organization_id, sku). Felder ohne Wert in der
 * CSV überschreiben keine bestehenden Daten.
 */
class MaterialSpec extends AbstractEntitySpec {
    public function entity(): ImportEntity {
        return ImportEntity::Materials;
    }

    public function columns(): array {
        return ['sku', 'name', 'unit', 'default_unit_price', 'tax_rate', 'external_provider', 'external_id', 'is_active'];
    }

    public function requiredColumns(): array {
        return ['name', 'unit'];
    }

    public function headerAliases(): array {
        return [
            'artikelnummer' => 'sku',
            'artikel' => 'name',
            'bezeichnung' => 'name',
            'einheit' => 'unit',
            'preis' => 'default_unit_price',
            'einzelpreis' => 'default_unit_price',
            'steuersatz' => 'tax_rate',
            'mwst' => 'tax_rate',
            'aktiv' => 'is_active',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'is_active' => $raw === null || $raw === '' ? null : $this->boolish($raw),
                'default_unit_price', 'tax_rate' => $this->decimal($this->trimmedString($raw)),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];
        if (($row['name'] ?? null) === null) {
            $issues[] = $this->requiredIssue('name');
        }
        if (($row['unit'] ?? null) === null) {
            $issues[] = $this->requiredIssue('unit');
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            $payload = array_filter($row, static fn($v): bool => $v !== null);
            $payload['organization_id'] = $organization->id;
            $payload['is_active'] ??= true;

            $existing = null;
            $sku = $payload['sku'] ?? null;
            if ($sku !== null && $sku !== '') {
                $existing = Material::query()
                    ->where('organization_id', $organization->id)
                    ->where('sku', $sku)
                    ->first();
            }

            if ($existing !== null) {
                $existing->fill($payload)->save();

                return [ImportOutcome::Updated, null];
            }

            Material::create($payload);

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [
                ImportOutcome::Failed,
                new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage()),
            ];
        }
    }
}
