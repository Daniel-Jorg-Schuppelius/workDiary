<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionBase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Bemessungsgrundlage eines Sicherheitseinbehalts (Feature 113, MVP-602).
 *
 * Verträge kennen beide Varianten — „5 % der Netto-Auftragssumme" ebenso wie
 * „5 % der Schlussrechnungssumme". Ein fester Default wäre in der Hälfte der
 * Fälle falsch und die Differenz fiele erst bei der Freigabe auf, wenn
 * niemand mehr weiß, wie gerechnet wurde. Deshalb steht die Grundlage am
 * einzelnen Einbehalt.
 */
enum RetentionBase: string implements HasLabel {
    use HasOptions;

    case Net = 'net';
    case Gross = 'gross';

    public function label(): string {
        return (string) __('enums.retention_base.' . $this->value);
    }
}
