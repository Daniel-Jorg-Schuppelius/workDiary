<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhistleblowingCryptoService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Whistleblowing;

use App\Support\Crypto\EnvelopeCrypto;

/**
 * Envelope-Krypto des Hinweisgebermoduls (Abschnitt 10/25): per-Fall-DEK,
 * gewrappt mit dem aus WHISTLEBLOWING_KEY abgeleiteten KEK (getrennt von
 * APP_KEY und vom Datenschutz-Schluessel). Crypto-Shredding durch Vernichten
 * von `dek_wrapped`.
 */
class WhistleblowingCryptoService extends EnvelopeCrypto {
    public function __construct() {
        parent::__construct('whistleblowing.key', 'WHISTLEBLOWING_KEY');
    }
}
