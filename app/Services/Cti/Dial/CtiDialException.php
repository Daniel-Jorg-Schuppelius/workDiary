<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiDialException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti\Dial;

use RuntimeException;

/** Die Telefonanlage hat den Anrufauftrag abgelehnt (Click-to-Dial, W4.5). */
class CtiDialException extends RuntimeException {}
