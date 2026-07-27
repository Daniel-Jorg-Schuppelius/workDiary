<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialExportSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Export\Specs;

use App\Enums\Export\ExportEntity;
use App\Models\{Material, Organization};
use Illuminate\Database\Eloquent\Model;

/**
 * Export-Spezifikation für Materialien — Round-Trip zur {@see \App\Services\Import\Specs\MaterialSpec}.
 *
 * Filter:
 * - `status`: active|inactive (Standard: alle)
 * - `q`: Freitextsuche über Bezeichnung / Artikelnummer
 */
class MaterialExportSpec extends AbstractExportSpec {
    public function entity(): ExportEntity {
        return ExportEntity::Materials;
    }

    public function columns(): array {
        return ['sku', 'name', 'unit', 'default_unit_price', 'tax_rate', 'external_provider', 'external_id', 'is_active'];
    }

    public function query(Organization $organization, array $filters): iterable {
        $status = (string) ($filters['status'] ?? '');
        $search = trim((string) ($filters['q'] ?? ''));

        return Material::query()
            ->where('organization_id', $organization->id)
            ->when($status === 'active', fn($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->whereLikeEscaped('name', $search)
                        ->orWhereLikeEscaped('sku', $search);
                });
            })
            ->orderBy('name')
            ->cursor();
    }

    public function toRow(Model $model): array {
        /** @var Material $model */
        return [
            'sku' => $this->str($model->sku),
            'name' => $this->str($model->name),
            'unit' => $this->str($model->unit),
            'default_unit_price' => $this->decimalCell($model->default_unit_price?->getAmount()),
            'tax_rate' => $this->decimalCell($model->tax_rate?->getNumericValue()),
            'external_provider' => $this->str($model->external_provider),
            'external_id' => $this->str($model->external_id),
            'is_active' => $this->boolCell($model->is_active),
        ];
    }
}
