<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zuordnungsstatus eines Bankumsatzes im Prüfbereich (Feature 045,
 * „Priorität 3"). Der Import erzeugt Umsätze immer als `unmatched` (bzw.
 * `duplicate` bei org-weiter Fingerprint-Dublette); erst die Bestätigung
 * setzt `matched`.
 *
 *   unmatched    = noch keine Zuordnung (ggf. mit Vorschlägen)
 *   suggested    = Vorschläge vorhanden (rein darstellungsbezogen, optional)
 *   matched      = bestätigt zugeordnet (Allocations vorhanden)
 *   ignored      = bewusst beiseitegelegt (z. B. Bankgebühr, interner Umsatz)
 *   unassignable = geprüft und als nicht zuordenbar markiert
 *   duplicate    = bereits bekannter Umsatz (Fingerprint-Treffer über Auszüge)
 */
enum MatchStatus: string implements HasLabel {
    use HasOptions;

    case Unmatched = 'unmatched';
    case Suggested = 'suggested';
    case Matched = 'matched';
    case Ignored = 'ignored';
    case Unassignable = 'unassignable';
    case Duplicate = 'duplicate';

    public function label(): string {
        return (string) __('enums.finance.match-status.' . $this->value);
    }

    /** Offen = bedarf noch einer Entscheidung (für Kennzahlen/Filter). */
    public function isOpen(): bool {
        return $this === self::Unmatched || $this === self::Suggested;
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Unmatched => 'warning',
            self::Suggested => 'info',
            self::Matched => 'success',
            self::Ignored => 'neutral',
            self::Unassignable => 'neutral',
            self::Duplicate => 'error',
        };
    }
}
