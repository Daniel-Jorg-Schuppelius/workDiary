<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconciliationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Contracts\HasLabel;

/**
 * Ergebnis je erwarteter Abrechnungsperiode eines weiterverkauften Abos
 * (Feature 151). Konsolenbericht, deshalb deutsche Klartext-Labels ohne __().
 */
enum ReconciliationStatus: string implements HasLabel {
    /** Passende Rechnungsposition gefunden, Stückpreis ≥ Einkauf. */
    case Covered = 'covered';

    /** Passende Position gefunden, aber Stückpreis unter dem Einkaufspreis. */
    case Underpriced = 'underpriced';

    /** Nur ein Teil der Menge ist berechnet. */
    case Partial = 'partial';

    /** Keine Produktposition, aber ein Beleg im Zeitfenster mit ausreichendem Betrag (schwach). */
    case CoveredByAmount = 'covered_by_amount';

    /** Keine Rechnung im Zeitfenster. */
    case Missing = 'missing';

    /** Firma konnte keinem Lexoffice-Kontakt zugeordnet werden. */
    case Unmapped = 'unmapped';

    public function label(): string {
        return match ($this) {
            self::Covered => 'Gedeckt',
            self::Underpriced => 'Unter Einkauf',
            self::Partial => 'Teilweise',
            self::CoveredByAmount => 'Nur Betrag',
            self::Missing => 'Fehlt',
            self::Unmapped => 'Nicht zugeordnet',
        };
    }

    public function isProblem(): bool {
        return match ($this) {
            self::Covered => false,
            default => true,
        };
    }
}
