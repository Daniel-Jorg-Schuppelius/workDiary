<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingSovereignty.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Buchungshoheit einer Organisation (Feature 125, MVP-671) — die dritte
 * Führungsachse neben Faktura- ({@see BillingMode}) und Stammdatenhoheit.
 *
 * Bewusst getrennt: Wer Rechnungen schreibt, führt nicht zwangsläufig das
 * Hauptbuch. Ein einzelner „lokale Buchhaltung an/aus"-Schalter würde beide
 * Fragen vermischen und den heutigen Zustand (Belegprüfung + Übergabe, aber
 * kein eigenes Hauptbuch) gar nicht abbilden können.
 */
enum AccountingSovereignty: string implements HasLabel {
    use HasOptions;

    /** Heutiger Zustand: Belegprüfung, Zahlung, DATEV-/GoBD-Übergabe — kein lokales Hauptbuch. */
    case Preaccounting = 'preaccounting';

    /** WorkDiary führt Buchungen und Perioden. */
    case Local = 'local';

    /** Ein benannter Provider führt; lokale Daten sind Projektion und Übergabenachweis. */
    case External = 'external';

    public function label(): string {
        return (string) __('enums.finance.accounting-sovereignty.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Preaccounting => 'ghost',
            self::Local => 'success',
            self::External => 'info',
        };
    }

    /** Darf für diesen Zeitraum lokal festgeschrieben werden? */
    public function allowsLocalPosting(): bool {
        return $this === self::Local;
    }

    /** Führt ein benanntes Fremdsystem das Hauptbuch? */
    public function isExternal(): bool {
        return $this === self::External;
    }
}
