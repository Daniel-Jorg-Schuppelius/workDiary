<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetNotUsableException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\AssetBlock;
use RuntimeException;

/**
 * Strukturierter Fehler des gemeinsamen Sperrmodells (D12): Ein gesperrtes,
 * defektes oder ungeprüftes Asset darf nicht still eingeplant, verliehen
 * oder ausgegeben werden.
 */
class AssetNotUsableException extends RuntimeException {
    public function __construct(
        string $message,
        public readonly ?AssetBlock $block = null,
    ) {
        parent::__construct($message);
    }
}
