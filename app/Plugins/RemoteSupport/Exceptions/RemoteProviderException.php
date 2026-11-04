<?php
/*
 * Created on   : Wed Nov 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteProviderException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\RemoteSupport\Exceptions;

use App\Plugins\RemoteSupport\Providers\RemoteSession;
use RuntimeException;

/**
 * Ein Fernwartungs-Anbieter konnte nicht gelesen werden (HTTP-Fehler,
 * Anmeldung abgelehnt, Fehlerantwort der API). Der Importer fängt je
 * Anbieter, damit ein ausgefallener Dienst die anderen nicht stoppt.
 */
final class RemoteProviderException extends RuntimeException {
    /** @var list<RemoteSession> */
    private array $sessions = [];

    public static function for(string $provider, string $message): self {
        return new self(sprintf('[%s] %s', $provider, $message));
    }

    /**
     * Lesen abgebrochen (Seitenlimit), aber die bis dahin gelesenen Sitzungen
     * sind gültig: der Importer bucht sie und meldet den Abbruch.
     *
     * @param  list<RemoteSession>  $sessions
     */
    public static function incomplete(string $provider, string $reason, array $sessions): self {
        $e = self::for($provider, 'Verbindungen nicht vollständig gelesen: ' . $reason);
        $e->sessions = $sessions;

        return $e;
    }

    /** @return list<RemoteSession> */
    public function sessions(): array {
        return $this->sessions;
    }
}
