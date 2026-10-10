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
            self::Valid => (string) __('enums.asset_compliance.asset_compliance_status.valid'),
            self::DueSoon => (string) __('enums.asset_compliance.asset_compliance_status.due_soon'),
            self::Overdue => (string) __('enums.asset_compliance.asset_compliance_status.overdue'),
            self::Restricted => (string) __('enums.asset_compliance.asset_compliance_status.restricted'),
            self::Blocked => (string) __('enums.asset_compliance.asset_compliance_status.blocked'),
            self::NotApplicable => (string) __('enums.asset_compliance.asset_compliance_status.not_applicable'),
        };
    }

    /** Ampelfarbe (Feature 138, Reservierungsdialog seit MVP-994). */
    public function tone(): string {
        return match ($this) {
            self::Valid => 'success',
            self::DueSoon, self::Restricted => 'warning',
            self::Overdue, self::Blocked => 'error',
            self::NotApplicable => 'ghost',
        };
    }

    /** Überfällig oder gesperrt — sperrt mit der Einstellung auch neue Fahrten (MVP-994). */
    public function blocksTrips(): bool {
        return $this === self::Overdue || $this === self::Blocked;
    }
}
