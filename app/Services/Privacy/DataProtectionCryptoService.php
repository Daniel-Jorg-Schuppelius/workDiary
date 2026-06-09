<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataProtectionCryptoService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Support\Crypto\EnvelopeCrypto;

/**
 * Envelope-Krypto des Datenschutzmoduls: per-Fall-DEK, gewrappt mit dem aus
 * DATAPROTECTION_KEY abgeleiteten KEK (getrennt von APP_KEY und vom
 * Hinweisgeber-Schluessel). Crypto-Shredding durch Vernichten von `dek_wrapped`.
 */
class DataProtectionCryptoService extends EnvelopeCrypto {
    public function __construct() {
        parent::__construct('dataprotection.key', 'DATAPROTECTION_KEY');
    }
}
