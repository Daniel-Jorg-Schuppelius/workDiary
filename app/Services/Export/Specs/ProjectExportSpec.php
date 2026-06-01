<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectExportSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Export\Specs;

use App\Enums\Export\ExportEntity;
use App\Models\{Organization, Project};
use Illuminate\Database\Eloquent\Model;

/**
 * Export-Spezifikation für Projekte — Round-Trip zur {@see \App\Services\Import\Specs\ProjectSpec}.
 *
 * Die Kundenzuordnung wird als `customer_number` (Kundennummer) ausgegeben,
 * passend zum fachlichen Schlüssel des Imports.
 *
 * Filter:
 * - `status`: active|archived (Standard: alle)
 * - `q`: Freitextsuche über Name / Nummer
 */
class ProjectExportSpec extends AbstractExportSpec {
    public function entity(): ExportEntity {
        return ExportEntity::Projects;
    }

    public function columns(): array {
        return [
            'name',
            'number',
            'customer_number',
            'description',
            'color',
            'status',
            'starts_on',
            'ends_on',
            'hourly_rate',
            'internal_rate',
            'budget',
            'time_budget',
            'billable',
        ];
    }

    public function query(Organization $organization, array $filters): iterable {
        $status = (string) ($filters['status'] ?? '');
        $search = trim((string) ($filters['q'] ?? ''));

        return Project::query()
            ->with('customer:id,number')
            ->where('organization_id', $organization->id)
            ->when($status === 'active', fn($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn($q) => $q->whereNotNull('archived_at'))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('number', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->cursor();
    }

    public function toRow(Model $model): array {
        /** @var Project $model */
        return [
            'name' => $this->str($model->name),
            'number' => $this->str($model->number),
            'customer_number' => $this->str($model->customer?->number),
            'description' => $this->str($model->description),
            'color' => $this->str($model->color),
            'status' => $this->str($model->status),
            'starts_on' => $this->dateCell($model->starts_on),
            'ends_on' => $this->dateCell($model->ends_on),
            'hourly_rate' => $this->decimalCell($model->hourly_rate),
            'internal_rate' => $this->decimalCell($model->internal_rate),
            'budget' => $this->decimalCell($model->budget),
            'time_budget' => $this->str($model->time_budget),
            'billable' => $this->boolCell($model->billable),
        ];
    }
}
