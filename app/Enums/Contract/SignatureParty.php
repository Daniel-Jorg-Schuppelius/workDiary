<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SignatureParty.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Contract;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Unterzeichnende Partei einer Kundenvereinbarung (Feature 157): je eine
 * Person auf Kunden- und Organisationsseite; mehrere Unterzeichner je
 * Partei sind Folgeausbau.
 */
enum SignatureParty: string implements HasLabel {
    use HasOptions;

    case Customer = 'customer';
    case Organization = 'organization';

    public function label(): string {
        return (string) __('contract-signing.party.' . $this->value);
    }
}
