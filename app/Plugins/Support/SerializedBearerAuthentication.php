<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerializedBearerAuthentication.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use APIToolkit\API\Authentication\OAuth2\{OAuth2AuthorizationCodeGrant, OAuth2BearerAuthentication, OAuth2Token};
use APIToolkit\Exceptions\{ApiException, UnauthorizedException};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Bearer-Anmeldung mit gesperrtem Token-Refresh je Verbindung: Anbieter mit
 * rotierenden Refresh-Tokens (Atlassian, Asana) entwerten das alte Token beim
 * ersten Einlösen — zwei parallele Läufe dürfen nicht beide erneuern. Unter der
 * Sperre wird die Verbindung neu geladen: ein anderer Lauf kann schon erneuert
 * oder die Verbindung getrennt haben. Das Trennen nimmt dieselbe Sperre
 * ({@see self::lock()}), damit kein verspäteter Refresh Tokens zurückschreibt.
 */
final class SerializedBearerAuthentication extends OAuth2BearerAuthentication {
    private const LOCK_SECONDS = 60;

    private const WAIT_SECONDS = 30;

    public function __construct(private readonly Model $connection, OAuth2AuthorizationCodeGrant $grant) {
        parent::__construct(new ConnectionTokenStore($connection), $grant);
    }

    public static function lock(Model $connection): \Illuminate\Contracts\Cache\Lock {
        return Cache::lock('oauth-refresh:' . $connection->getTable() . ':' . $connection->getKey(), self::LOCK_SECONDS);
    }

    public function refresh(): bool {
        $before = $this->tokenStore->load()?->getAccessToken();

        return (bool) self::lock($this->connection)->block(self::WAIT_SECONDS, function () use ($before): bool {
            $this->connection->refresh();
            $current = $this->tokenStore->load();
            if ($current === null) {
                return false;
            }
            if ($current->getAccessToken() !== $before) {
                return true;
            }

            return parent::refresh();
        });
    }

    protected function freshToken(): OAuth2Token {
        $token = $this->tokenStore->load();
        if ($token !== null && ! $token->isExpired($this->expiryLeeway)) {
            return $token;
        }

        return self::lock($this->connection)->block(self::WAIT_SECONDS, function (): OAuth2Token {
            $this->connection->refresh();
            try {
                return parent::freshToken();
            } catch (\Throwable $e) {
                // Abgelehnter Refresh (invalid_grant): die Anmeldung ist hin; Drosselung und 5xx bleiben Störungen.
                if ($e instanceof UnauthorizedException || ! $e instanceof ApiException || ! in_array($e->getCode(), [400, 401, 403], true)) {
                    throw $e;
                }

                throw new UnauthorizedException('OAuth2 refresh rejected', 401, null, $e);
            }
        });
    }
}
