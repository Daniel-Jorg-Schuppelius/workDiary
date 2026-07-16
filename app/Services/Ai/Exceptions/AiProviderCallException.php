<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiProviderCallException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

/**
 * Transport-/Providerfehler eines Adapter-Aufrufs (MVP-398): einziger
 * Fehlerkanal der Adapter nach außen. Die Meldung ist redigiert
 * (Status + Kontext, nie Prompt-Inhalte oder Schlüssel) und landet
 * gekürzt im Health-Tracking der Verbindung.
 */
class AiProviderCallException extends AiException {
    public static function transport(string $provider, string $detail): self {
        return new self(sprintf('%s: %s', $provider, $detail));
    }
}
