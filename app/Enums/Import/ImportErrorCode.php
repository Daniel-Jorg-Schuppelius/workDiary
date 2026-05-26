<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportErrorCode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Import;

/**
 * Stabile Fehler-Codes für Zeilenfehler im CSV-Import (MVP-049).
 *
 * Werden in `import_run_errors.code` persistiert und sind Teil des
 * öffentlichen Vertrags (i18n-Schlüssel `import.error.<code>`).
 */
enum ImportErrorCode: string {
    case Required = 'required';
    case Format = 'format';
    case Unique = 'unique';
    case FkMissing = 'fkMissing';
    case TooLong = 'tooLong';
    case OutOfRange = 'outOfRange';
    case Persist = 'persist';
    case HeaderMissing = 'headerMissing';
    case HeaderUnknown = 'headerUnknown';
}
