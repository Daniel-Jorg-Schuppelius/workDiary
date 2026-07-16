<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiFindResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

/**
 * Trefferliste des Verbs Finden (MVP-398): Referenz-Keys aus dem
 * übergebenen Korpus, absteigend nach Relevanz.
 */
final class AiFindResult {
    /** @param list<string> $matches Referenz-Keys aus dem Request-Korpus */
    public function __construct(
        public readonly array $matches,
        public readonly AiUsage $usage = new AiUsage(),
    ) {}
}
