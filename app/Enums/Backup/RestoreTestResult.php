<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RestoreTestResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Backup;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ergebnis eines protokollierten Restore-Tests (Feature 017):
 *   passed  = vollständige Wiederherstellung verifiziert
 *   partial = wiederhergestellt, aber mit Auflagen/Abweichungen
 *   failed  = Wiederherstellung fehlgeschlagen
 *
 * Nur `passed` zählt als „erfolgreicher Restore-Test" für die
 * Überfälligkeits-Berechnung auf der Backup-Statusseite.
 */
enum RestoreTestResult: string implements HasLabel {
    use HasOptions;

    case Passed = 'passed';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('enums.backup.restore-test-result.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Passed => 'success',
            self::Partial => 'warning',
            self::Failed => 'error',
        };
    }

    /** Gilt der Test als erfolgreich (zählt gegen die Überfälligkeit)? */
    public function isSuccessful(): bool {
        return $this === self::Passed;
    }
}
