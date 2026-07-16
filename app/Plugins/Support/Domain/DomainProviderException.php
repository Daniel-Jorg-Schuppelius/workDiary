<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProviderException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Domain;

use RuntimeException;

/**
 * Transport-/Protokollfehler des DomainReselling-Adapters (Feature 083):
 * HTTP-Fehler, fehlender `EOF`-Marker oder leere Antwort. Trägt nur
 * Befehlsname + Provider-Code, NIE Zugangsdaten oder Payload.
 *
 * `incomplete` = fehlendes `EOF`/Timeout: der Ausgang ist unklar, die
 * aufrufende Schicht MUSS reconcilen statt blind zu wiederholen.
 */
class DomainProviderException extends RuntimeException {
    public function __construct(
        string $message,
        public readonly string $command = '',
        public readonly ?int $providerCode = null,
        public readonly bool $incomplete = false,
    ) {
        parent::__construct($message);
    }

    public static function incomplete(string $command): self {
        return new self(
            sprintf('DomainReselling-Befehl "%s" ohne vollständiges EOF — Ausgang unklar.', $command),
            $command,
            null,
            true,
        );
    }
}
