<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionScheduleStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetCompliance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines Prüftermins (MVP-285).
 */
enum AssetInspectionScheduleStatus: string implements HasLabel {
    use HasOptions;

    case Planned = 'planned';
    case Announced = 'announced';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Missed = 'missed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Planned => (string) __('enums.asset_compliance.asset_inspection_schedule_status.planned'),
            self::Announced => (string) __('enums.asset_compliance.asset_inspection_schedule_status.announced'),
            self::InProgress => (string) __('enums.asset_compliance.asset_inspection_schedule_status.in_progress'),
            self::Done => (string) __('enums.asset_compliance.asset_inspection_schedule_status.done'),
            self::Missed => (string) __('enums.asset_compliance.asset_inspection_schedule_status.missed'),
            self::Cancelled => (string) __('enums.asset_compliance.asset_inspection_schedule_status.cancelled'),
        };
    }

    public function isOpen(): bool {
        return in_array($this, [self::Planned, self::Announced, self::InProgress], true);
    }
}
