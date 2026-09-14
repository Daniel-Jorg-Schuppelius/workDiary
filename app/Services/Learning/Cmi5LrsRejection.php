<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Cmi5LrsRejection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use RuntimeException;

/**
 * Das cmi5-LRS lehnt eine Anfrage ab. 400 für Formfehler nach xAPI, 403 für
 * Verstöße gegen die cmi5-Regeln der Sitzung, 409/412 für Nebenläufigkeit.
 */
final class Cmi5LrsRejection extends RuntimeException {
    public function __construct(
        public readonly int $status,
        public readonly string $reason,
    ) {
        parent::__construct($reason);
    }
}
