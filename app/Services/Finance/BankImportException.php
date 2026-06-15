<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankImportException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance;

use RuntimeException;

/**
 * Fachlicher Fehler beim Bankimport (Feature 045, „Priorität 3"): doppelte
 * Datei, nicht erkanntes Format, leerer/ungültiger Auszug.
 */
class BankImportException extends RuntimeException {
    /** @param array<string, mixed> $context */
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
