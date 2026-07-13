<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GraphTokenStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Msgraph;

use APIToolkit\API\Authentication\OAuth2\OAuth2Token;
use APIToolkit\Contracts\Interfaces\API\OAuth2TokenStoreInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * App-seitige OAuth-Token-Persistenz für das API-Toolkit (MVP-330, Bauturbo
 * A10 — gehoben aus dem Msgraph-Plugin, A8/MVP-328, da Kalender- UND
 * SharePoint-Verbindung dieselbe Logik brauchen): lädt/speichert die Tokens
 * einer Organisations-Verbindung — verschlüsselt at-rest über die
 * encrypted-Casts des Verbindungs-Modells. Refresh-Ergebnisse (401 ⇒ Refresh
 * ⇒ ein Retry im ClientAbstract) landen über save() sofort wieder
 * verschlüsselt in der Verbindung.
 *
 * Erwartet die Standardspalten `access_token`, `refresh_token`,
 * `token_expires_at` (datetime-Cast) und `scopes` am übergebenen Modell
 * (`msgraph_connections`, `sharepoint_connections`, …).
 */
class GraphTokenStore implements OAuth2TokenStoreInterface {
    public function __construct(private readonly Model $connection) {}

    public function load(): ?OAuth2Token {
        $accessToken = trim($this->stringAttribute('access_token') ?? '');
        if ($accessToken === '') {
            return null;
        }

        $expiresAt = $this->connection->getAttribute('token_expires_at');

        return new OAuth2Token(
            accessToken: $accessToken,
            refreshToken: $this->stringAttribute('refresh_token'),
            expiresAt: $expiresAt instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($expiresAt)
                : null,
            scope: $this->stringAttribute('scopes'),
        );
    }

    public function save(OAuth2Token $token): void {
        $this->connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken() ?? $this->stringAttribute('refresh_token'),
            'token_expires_at' => $token->getExpiresAt(),
            'scopes' => $token->getScope() ?? $this->stringAttribute('scopes'),
        ])->save();
    }

    public function clear(): void {
        $this->connection->forceFill([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ])->save();
    }

    private function stringAttribute(string $key): ?string {
        $value = $this->connection->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
