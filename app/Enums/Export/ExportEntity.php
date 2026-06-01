<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportEntity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Export;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Vom Datentransfer-Bereich unterstützte Export-Entitäten.
 *
 * Die ersten vier Entitäten spiegeln exakt die Import-Entitäten
 * ({@see \App\Enums\Import\ImportEntity}) und ermöglichen einen
 * verlustfreien Round-Trip (Export → Bearbeiten → Re-Import).
 */
enum ExportEntity: string implements HasLabel {
    use HasOptions;

    case Customers = 'customers';
    case Projects = 'projects';
    case Users = 'users';
    case Materials = 'materials';
    case ScheduledShifts = 'scheduled_shifts';
    case Tours = 'tours';

    public function label(): string {
        return (string) __('export.entity.' . $this->value);
    }

    public function permission(): string {
        return match ($this) {
            self::Customers => 'customer.export',
            self::Projects => 'project.export',
            self::Users => 'user.export',
            self::Materials => 'material.export',
            self::ScheduledShifts => 'schedule.export',
            self::Tours => 'tour.export',
        };
    }
}
