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

/**
 * Status eines Prüftermins (MVP-285).
 */
enum AssetInspectionScheduleStatus: string {
    case Planned = 'planned';
    case Announced = 'announced';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Missed = 'missed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Planned => (string) __('Geplant'),
            self::Announced => (string) __('Angekündigt'),
            self::InProgress => (string) __('In Durchführung'),
            self::Done => (string) __('Durchgeführt'),
            self::Missed => (string) __('Versäumt'),
            self::Cancelled => (string) __('Storniert'),
        };
    }

    public function isOpen(): bool {
        return in_array($this, [self::Planned, self::Announced, self::InProgress], true);
    }
}
