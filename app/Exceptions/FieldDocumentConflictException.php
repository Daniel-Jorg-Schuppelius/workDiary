<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldDocumentConflictException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Speichern je Feld (Feature 203, MVP-1650) trifft auf einen anderen Stand:
 * Die gesendete Version ist veraltet (`stale`) oder der Träger nimmt keine
 * Zwischenstände mehr an (`closed`, etwa abgesendet). Trägt die aktuelle
 * Version, damit der Browser einen Hinweis zeigen kann (HTTP 409) — nie
 * stilles Überschreiben.
 */
class FieldDocumentConflictException extends RuntimeException {
    public const string STALE = 'stale';

    public const string CLOSED = 'closed';

    public function __construct(public readonly string $reason, public readonly int $currentVersion) {
        parent::__construct((string) __($reason === self::CLOSED ? 'fields.autosave.closed' : 'fields.autosave.conflict'));
    }

    /** @return array{message: string, reason: string, version: int} */
    public function toArray(): array {
        return ['message' => $this->getMessage(), 'reason' => $this->reason, 'version' => $this->currentVersion];
    }
}
