<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrityCheckStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Security;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ergebnis eines Integritätslaufs (Feature 095). `Baseline` markiert die
 * Erzeugung/Erneuerung der Datei-Hash-Baseline in derselben Timeline —
 * die Alarm-Logik vergleicht nur echte Prüfläufe (ok/deviation).
 */
enum IntegrityCheckStatus: string implements HasLabel {
    use HasOptions;

    case Baseline = 'baseline';
    case Ok = 'ok';
    case Deviation = 'deviation';
    case MissingBaseline = 'missing_baseline';
    case Error = 'error';

    public function label(): string {
        return (string) __('enums.security.integrity_check_status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Ok => 'success',
            self::Baseline => 'info',
            self::MissingBaseline => 'warning',
            self::Deviation, self::Error => 'error',
        };
    }
}
