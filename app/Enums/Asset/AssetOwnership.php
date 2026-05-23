<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetOwnership.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Asset;

enum AssetOwnership: string {
    case Organization = 'org';
    case Customer = 'customer';
    case External = 'external';

    public function requiresCustomer(): bool {
        return $this === self::Customer;
    }
}
