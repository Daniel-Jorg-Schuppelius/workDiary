<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Kategorie eines Softwareprodukts im organisationsbezogenen
 * Softwareinventar (Feature 044, Ebene 1).
 */
enum SoftwareCategory: string implements HasLabel {
    use HasOptions;

    case Os = 'os';
    case Application = 'application';
    case Service = 'service';
    case Library = 'library';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.isms.software-category.' . $this->value);
    }
}
