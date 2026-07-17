<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConnectionTokenStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use APIToolkit\API\Authentication\OAuth2\OAuth2Token;
use APIToolkit\Contracts\Interfaces\API\OAuth2TokenStoreInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * App-seitige OAuth-Token-Persistenz für das API-Toolkit (Konsolidierung B2 —
 * gehoben aus dem Msgraph-Plugin, vormals GraphTokenStore): lädt/speichert die
 * Tokens einer Organisations-Verbindung — verschlüsselt at-rest über die
 * encrypted-Casts des Verbindungs-Modells. Provider liefern beim Refresh oft
 * KEIN neues Refresh-Token — save() behält deshalb das bestehende
 * (Toolkit-`withRefreshToken`-Konvention).
 *
 * Erwartet die Standardspalten `access_token`, `refresh_token`,
 * `token_expires_at` (datetime-Cast) am übergebenen Modell; die Scope-Spalte
 * ist konfigurierbar: `scopes` als String (Default) oder z. B.
 * `granted_scopes` als Array (`$scopeAsArray = true`, space-separiert
 * gemappt).
 */
class ConnectionTokenStore implements OAuth2TokenStoreInterface {
    public function __construct(
        private readonly Model $connection,
        private readonly string $scopeColumn = 'scopes',
        private readonly bool $scopeAsArray = false,
    ) {}

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
            scope: $this->loadScope(),
        );
    }

    public function save(OAuth2Token $token): void {
        $this->connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken() ?? $this->stringAttribute('refresh_token'),
            'token_expires_at' => $token->getExpiresAt(),
            $this->scopeColumn => $this->saveScope($token->getScope()),
        ])->save();
    }

    public function clear(): void {
        $this->connection->forceFill([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ])->save();
    }

    private function loadScope(): ?string {
        if (!$this->scopeAsArray) {
            return $this->stringAttribute($this->scopeColumn);
        }

        $scopes = $this->connection->getAttribute($this->scopeColumn);

        return is_array($scopes) ? implode(' ', $scopes) : null;
    }

    /** @return array<int, string>|string|null */
    private function saveScope(?string $scope): array|string|null {
        if (!$this->scopeAsArray) {
            return $scope ?? $this->stringAttribute($this->scopeColumn);
        }

        return $scope !== null && $scope !== ''
            ? array_values(array_filter(explode(' ', $scope)))
            : $this->connection->getAttribute($this->scopeColumn);
    }

    private function stringAttribute(string $key): ?string {
        $value = $this->connection->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
