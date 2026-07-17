<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetCompliance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Abgeleiteter Prüfstatus eines Assets (MVP-288): Einsatz-, Dispositions-
 * und Verleihprüfung lesen dieselbe Bewertung.
 */
enum AssetComplianceStatus: string implements HasLabel {
    use HasOptions;

    case Valid = 'valid';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';
    case Restricted = 'restricted';
    case Blocked = 'blocked';
    case NotApplicable = 'not_applicable';

    public function label(): string {
        return match ($this) {
            self::Valid => (string) __('Gültig geprüft'),
            self::DueSoon => (string) __('Prüfung bald fällig'),
            self::Overdue => (string) __('Prüfung überfällig'),
            self::Restricted => (string) __('Eingeschränkt freigegeben'),
            self::Blocked => (string) __('Gesperrt'),
            self::NotApplicable => (string) __('Keine Prüfpflicht'),
        };
    }
}
