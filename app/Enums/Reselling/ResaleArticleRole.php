<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleArticleRole.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Reselling;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Einstufung eines Lexoffice-Artikels für das Reselling-Register (Feature 152):
 * Abo-Produkt oder nie Abo-Position. Ohne Einstufung entscheidet die
 * Produkterkennung über den Artikelnamen.
 */
enum ResaleArticleRole: string implements HasLabel {
    use HasOptions;

    case License = 'license';
    case Excluded = 'excluded';

    public function label(): string {
        return (string) __('resale.products.role.' . $this->value);
    }

    public function tone(): string {
        return $this === self::License ? 'success' : 'neutral';
    }
}
