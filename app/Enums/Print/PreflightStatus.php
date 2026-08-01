<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PreflightStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Print;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Preflight-Ergebnis (MVP-459): Fehler blockieren die Freigabe, Warnungen
 * nicht; ein begründeter manueller Override bleibt auditiert möglich.
 */
enum PreflightStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Passed = 'passed';
    case Warnings = 'warnings';
    case Failed = 'failed';
    case Overridden = 'overridden';

    public function label(): string {
        return (string) __('enums.print.preflight_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Passed => 'success',
            self::Warnings, self::Overridden => 'warning',
            self::Failed => 'error',
            self::Pending => 'neutral',
        };
    }

    /** Freigabefähig — fehlerfrei oder begründet übersteuert. */
    public function allowsApproval(): bool {
        return in_array($this, [self::Passed, self::Warnings, self::Overridden], true);
    }
}
