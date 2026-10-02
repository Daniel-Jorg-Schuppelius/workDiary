<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : McpOAuthException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mcp\Exceptions;

use RuntimeException;

/**
 * Fehler im OAuth-Ablauf des MCP-Servers (MVP-1065) mit Fehlercode nach
 * RFC 6749. `redirectable`: darf an den Client zurückgeleitet werden — nie,
 * solange Client oder Rücksprungadresse nicht geprüft sind.
 */
final class McpOAuthException extends RuntimeException {
    public function __construct(
        public readonly string $error,
        string $description,
        public readonly int $status = 400,
        public readonly bool $redirectable = false,
    ) {
        parent::__construct($description);
    }
}
