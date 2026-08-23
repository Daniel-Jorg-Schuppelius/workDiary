<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringRunStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ergebnis eines Vorlagenlaufs (Feature 125, MVP-675).
 *
 * `expected` ist der Normalzustand einer Belegerwartung: Der Lauf hat den
 * Vorgang eröffnet, das Original fehlt noch. Erst `fulfilled` schließt ihn.
 */
enum RecurringRunStatus: string implements HasLabel {
    use HasOptions;

    /** Belegerwartung eröffnet — Original steht aus. */
    case Expected = 'expected';

    /** Buchungsentwurf erzeugt — Prüfung und Festschreibung stehen aus. */
    case DraftCreated = 'draft_created';

    /** Erwartung durch das Original erfüllt bzw. Entwurf festgeschrieben. */
    case Fulfilled = 'fulfilled';

    /** Lauf konnte nichts erzeugen (fehlende Regel, geschlossene Periode …). */
    case Blocked = 'blocked';

    /** Bewusst übersprungen (pausiert, beendet). */
    case Skipped = 'skipped';

    public function label(): string {
        return (string) __('enums.finance.recurring-run-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Expected => 'warning',
            self::DraftCreated => 'info',
            self::Fulfilled => 'success',
            self::Blocked => 'error',
            self::Skipped => 'ghost',
        };
    }

    public function isOpen(): bool {
        return $this === self::Expected || $this === self::DraftCreated;
    }
}
