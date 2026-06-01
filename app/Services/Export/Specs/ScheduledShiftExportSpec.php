<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledShiftExportSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Export\Specs;

use App\Enums\Export\ExportEntity;
use App\Models\{Organization, ScheduledShift};
use Illuminate\Database\Eloquent\Model;

/**
 * Export-Spezifikation für Schichtpläne — Round-Trip zur
 * {@see \App\Services\Import\Specs\ScheduledShiftSpec}.
 *
 * Der Mitarbeiter wird als `user_email` (fachlicher Schlüssel des Imports)
 * ausgegeben, der Schichttyp als Name.
 *
 * Filter:
 * - `from` / `to`: Zeitraum (Y-m-d) über das Schichtdatum
 * - `user_id`: Einschränkung auf einen Mitarbeiter
 */
class ScheduledShiftExportSpec extends AbstractExportSpec {
    public function entity(): ExportEntity {
        return ExportEntity::ScheduledShifts;
    }

    public function columns(): array {
        return ['user_email', 'date', 'shift_type', 'start_time', 'end_time', 'note', 'status'];
    }

    public function query(Organization $organization, array $filters): iterable {
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        $userId = $filters['user_id'] ?? null;

        return ScheduledShift::query()
            ->with(['user:id,email', 'shiftType:id,name'])
            ->where('organization_id', $organization->id)
            ->when($from !== '', fn($q) => $q->whereDate('date', '>=', $from))
            ->when($to !== '', fn($q) => $q->whereDate('date', '<=', $to))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->orderBy('date')
            ->orderBy('user_id')
            ->cursor();
    }

    public function toRow(Model $model): array {
        /** @var ScheduledShift $model */
        return [
            'user_email' => $this->str($model->user?->email),
            'date' => $this->dateCell($model->date),
            'shift_type' => $this->str($model->shiftType?->name),
            'start_time' => $this->str($model->start_time),
            'end_time' => $this->str($model->end_time),
            'note' => $this->str($model->note),
            'status' => $this->str($model->status->value),
        ];
    }
}
