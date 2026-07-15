<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Product;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus eines Produkts (Typ-Ebene, MVP-369): aktiv im Einsatz,
 * auslaufend (keine Neubeschaffung) oder vom Hersteller abgekündigt.
 */
enum ProductStatus: string implements HasLabel {
    use HasOptions;

    case Active = 'active';
    case PhasingOut = 'phasing_out';
    case Discontinued = 'discontinued';

    public function label(): string {
        return (string) __('enums.product.status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::PhasingOut => 'warning',
            self::Discontinued => 'error',
        };
    }
}
