<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarrierConnectionTokenStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

use APIToolkit\API\Authentication\OAuth2\OAuth2Token;
use APIToolkit\Contracts\Interfaces\API\OAuth2TokenStoreInterface;
use App\Models\CarrierConnection;

/**
 * Bindet den verschlüsselten {@see CarrierTokenCache} an das php-api-toolkit
 * an: {@see \APIToolkit\API\Authentication\OAuth2\OAuth2ClientCredentialsAuthentication}
 * lädt/speichert/verwirft ihre Client-Credentials-Tokens über dieses
 * Store-Interface. Eine Instanz je {@see CarrierConnection} — die
 * Schlüssel-Semantik (Organisation + Carrier + Sandbox/Prod, verschlüsselt
 * at-rest) bleibt vollständig im Cache.
 */
final class CarrierConnectionTokenStore implements OAuth2TokenStoreInterface {
    public function __construct(
        private readonly CarrierTokenCache $cache,
        private readonly CarrierConnection $connection,
    ) {}

    public function load(): ?OAuth2Token {
        return $this->cache->get($this->connection);
    }

    public function save(OAuth2Token $token): void {
        $this->cache->put($this->connection, $token);
    }

    public function clear(): void {
        $this->cache->forget($this->connection);
    }
}
