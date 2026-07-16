<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UnknownAiCapabilityException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

/**
 * Capability-Schlüssel ist nicht in der Registry (config/ai.php)
 * registriert — Programmierfehler, kein Laufzeitzustand.
 */
class UnknownAiCapabilityException extends AiException {
    public static function forKey(string $key): self {
        return new self(sprintf('KI-Capability "%s" ist nicht registriert (config/ai.php).', $key));
    }
}
