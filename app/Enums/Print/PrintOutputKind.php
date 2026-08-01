<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintOutputKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Print;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ausgabeart eines Druckauftrags (MVP-459): Abholung mit Übergabenachweis,
 * Versand über die vorhandene Sendungslogik oder datensparsamer
 * Tresenverkauf für Laufkundschaft.
 */
enum PrintOutputKind: string implements HasLabel {
    use HasOptions;

    case Pickup = 'pickup';
    case Shipping = 'shipping';
    case Counter = 'counter';

    public function label(): string {
        return (string) __('enums.print.output_kind.' . $this->value);
    }
}
