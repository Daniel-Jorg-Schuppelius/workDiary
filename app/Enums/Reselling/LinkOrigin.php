<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LinkOrigin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Contracts\HasLabel;

/**
 * Herkunft eines Rechnungsbezugs (Feature 152): vom Vorschlagslauf, vom
 * Nutzer bestätigt oder von Hand gesetzt. Vorschläge werden beim nächsten
 * Lauf neu berechnet; bestätigte und manuelle Bezüge bleiben.
 */
enum LinkOrigin: string implements HasLabel {
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case Manual = 'manual';
    /** Vom Vorschlagslauf als sicher bestätigt (Feature 185, MVP-1406) — entschieden, aber ohne Nutzer; „Zurücknehmen" löst ihn wie einen Vorschlag. */
    case Auto = 'auto';

    public function label(): string {
        return (string) __('resale.link.origin.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Proposed => 'info',
            self::Confirmed => 'success',
            self::Manual => 'primary',
            self::Auto => 'success',
        };
    }

    public function isDecided(): bool {
        return $this !== self::Proposed;
    }
}
