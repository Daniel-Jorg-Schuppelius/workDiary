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

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Status eines Prüftermins (MVP-285).
 */
enum AssetInspectionScheduleStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

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

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Planned => [self::Announced, self::InProgress, self::Done, self::Missed, self::Cancelled],
            // Ein stornierter Prüfauftrag gibt seine Termine wieder frei.
            self::Announced => [self::Planned, self::InProgress, self::Done, self::Missed, self::Cancelled],
            self::InProgress => [self::Planned, self::Done, self::Missed, self::Cancelled],
            // Eine versäumte Prüfung lässt sich nachholen oder absagen.
            self::Missed => [self::Done, self::Cancelled],
            self::Done, self::Cancelled => [],
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Done => 'success',
            self::Missed => 'error',
            self::Cancelled => 'ghost',
            default => 'info',
        };
    }

    public function isOpen(): bool {
        return in_array($this, [self::Planned, self::Announced, self::InProgress], true);
    }
}
