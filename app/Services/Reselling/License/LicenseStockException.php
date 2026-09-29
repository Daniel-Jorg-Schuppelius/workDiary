<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseStockException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\License;

use RuntimeException;

/**
 * Fachlich abgewiesene Bestandsaktion (MVP-1024). `field` nennt das
 * Formularfeld, an dem die Meldung erscheint; Schlüsselwerte stehen nie darin.
 */
class LicenseStockException extends RuntimeException {
    /** @param  array<string, string|int>  $replace */
    public function __construct(string $messageKey, public readonly string $field = 'license', array $replace = []) {
        parent::__construct((string) __('resale.license.error.' . $messageKey, $replace));
    }
}
