<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqImportConflictException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use RuntimeException;

/**
 * Ein Reimport würde LV-Positionen mit Ausführungs-/Abrechnungsbezug
 * überschreiben. Der Import bricht ab, ohne Bestehendes still zu ändern.
 */
class BoqImportConflictException extends RuntimeException {
    /** @param list<string> $conflictingRefs */
    public function __construct(public readonly array $conflictingRefs) {
        parent::__construct('Reimport berührt Positionen mit Ausführungsbezug: ' . implode(', ', $conflictingRefs));
    }
}
