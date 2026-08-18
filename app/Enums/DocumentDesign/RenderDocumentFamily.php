<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RenderDocumentFamily.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\DocumentDesign;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Dokumentfamilien des Rendervertrags (Feature 076-Ausbau, Issue #83):
 * Design-Varianten können für eine ganze Familie statt einer einzelnen
 * Dokumentart gelten; die spezifischere Variante gewinnt.
 */
enum RenderDocumentFamily: string implements HasLabel {
    use HasOptions;

    /** Vertrieb/Fakturierung: Angebot, AB, Rechnung, Gutschrift, Pro-forma, Mahnung. */
    case Sales = 'sales';

    /** Einkauf/Logistik: Bestellung, Lieferschein. */
    case Procurement = 'procurement';

    /** Leistung/Nachweis: Bericht, Protokoll, Fallakte, Stundenzettel, Formular, Fertigungsnachweis. */
    case Evidence = 'evidence';

    /** Echte Spezialformate (z. B. Etiketten) mit eingeschränkten Design-Fähigkeiten. */
    case Special = 'special';

    public function label(): string {
        return match ($this) {
            self::Sales => __('Vertrieb/Fakturierung'),
            self::Procurement => __('Einkauf/Logistik'),
            self::Evidence => __('Leistung/Nachweis'),
            self::Special => __('Spezialformat'),
        };
    }
}
