<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceFindingStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Compliance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Bearbeitungsstand eines persistierten Compliance-Verstoßes (Feature 006,
 * Welle D):
 *  - open        : erkannt, noch nicht gesichtet;
 *  - acknowledged: gesichtet/quittiert (Begründung optional);
 *  - accepted    : bewusst akzeptiert MIT Pflicht-Begründung;
 *  - resolved    : tritt nicht mehr auf (automatisch durch den Scan gesetzt) —
 *                  NICHT gelöscht (revisionssicher).
 *
 * Manuell quittierbar sind nur `open`/`acknowledged`; `resolved` (auto) und
 * `accepted` (finale Entscheidung) sind terminal.
 */
enum ComplianceFindingStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Accepted = 'accepted';

    public function label(): string {
        return (string) __('enums.compliance.finding-status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Open => 'error',
            self::Acknowledged => 'info',
            self::Accepted => 'warning',
            self::Resolved => 'success',
        };
    }

    /** Darf dieser Befund noch quittiert/akzeptiert werden? */
    public function isAcknowledgeable(): bool {
        return in_array($this, [self::Open, self::Acknowledged], true);
    }

    /** Offen im Sinne der Auswertung (weder behoben noch akzeptiert)? */
    public function isOutstanding(): bool {
        return in_array($this, [self::Open, self::Acknowledged], true);
    }
}
