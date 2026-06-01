<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourExportSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Export\Specs;

use App\Enums\Export\ExportEntity;
use App\Models\{Organization, Tour};
use Illuminate\Database\Eloquent\Model;

/**
 * Export-Spezifikation für Touren.
 *
 * Filter:
 * - `from` / `to`: Zeitraum (Y-m-d) über das Tourdatum
 * - `user_id`: Einschränkung auf einen Fahrer
 * - `status`: Tourstatus
 */
class TourExportSpec extends AbstractExportSpec {
    public function entity(): ExportEntity {
        return ExportEntity::Tours;
    }

    public function columns(): array {
        return [
            'tour_date',
            'name',
            'driver_email',
            'vehicle',
            'start_address',
            'end_address',
            'planned_distance_km',
            'planned_duration_minutes',
            'status',
            'notes',
        ];
    }

    public function query(Organization $organization, array $filters): iterable {
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        $userId = $filters['user_id'] ?? null;
        $status = trim((string) ($filters['status'] ?? ''));

        return Tour::query()
            ->with(['user:id,email', 'vehicle:id,license_plate,label'])
            ->where('organization_id', $organization->id)
            ->when($from !== '', fn($q) => $q->whereDate('tour_date', '>=', $from))
            ->when($to !== '', fn($q) => $q->whereDate('tour_date', '<=', $to))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($status !== '', fn($q) => $q->where('status', $status))
            ->orderBy('tour_date')
            ->cursor();
    }

    public function toRow(Model $model): array {
        /** @var Tour $model */
        $vehicle = $model->vehicle;
        $vehicleLabel = $vehicle === null
            ? ''
            : trim($this->str($vehicle->license_plate) . ' ' . $this->str($vehicle->label));

        return [
            'tour_date' => $this->dateCell($model->tour_date),
            'name' => $this->str($model->name),
            'driver_email' => $this->str($model->user?->email),
            'vehicle' => $vehicleLabel,
            'start_address' => $this->str($model->start_address),
            'end_address' => $this->str($model->end_address),
            'planned_distance_km' => $this->decimalCell($model->planned_distance_km),
            'planned_duration_minutes' => $this->str($model->planned_duration_minutes),
            'status' => $this->str($model->status->value),
            'notes' => $this->str($model->notes),
        ];
    }
}
