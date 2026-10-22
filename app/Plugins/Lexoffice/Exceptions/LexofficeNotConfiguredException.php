<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeNotConfiguredException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Exceptions;

use RuntimeException;

/** Die Organisation hat keinen Lexoffice-API-Schlüssel hinterlegt. */
class LexofficeNotConfiguredException extends RuntimeException {
    public function __construct() {
        parent::__construct((string) __('lexoffice::finance.error.lexoffice_not_configured'));
    }
}
