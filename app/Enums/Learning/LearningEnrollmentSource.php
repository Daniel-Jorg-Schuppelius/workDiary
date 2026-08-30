<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningEnrollmentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Herkunft einer Einschreibung (Feature 149). `Requirement` stammt aus der
 * Pflichtmatrix (Feature 145) und darf deshalb nicht frei gelöscht werden —
 * das Soll bliebe sonst unerfüllt zurück.
 */
enum LearningEnrollmentSource: string implements HasLabel {
    use HasOptions;

    case Requirement = 'requirement';
    case Manual = 'manual';
    case Self = 'self';
    case Booking = 'booking';
    case Rule = 'rule';
    // Aus einem Lernpfad (MVP-745): Reihenfolge mit Fristen, kein zweites Soll.
    case Path = 'path';

    public function label(): string {
        return (string) __('enums.learning.enrollment-source.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Requirement => 'error',
            self::Manual => 'info',
            self::Self => 'success',
            self::Booking => 'warning',
            self::Rule => 'ghost',
            self::Path => 'neutral',
        };
    }
}
