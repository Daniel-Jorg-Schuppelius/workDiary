<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarTokenStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleCalendar\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2Token;
use APIToolkit\Contracts\Interfaces\API\OAuth2TokenStoreInterface;
use App\Models\GoogleCalendarConnection;
use DateTimeImmutable;

/**
 * App-seitige Token-Persistenz für das API-Toolkit (MVP-328, Bauturbo A8):
 * lädt/speichert die OAuth-Tokens der Organisations-Verbindung — verschlüsselt
 * at-rest über die encrypted-Casts des {@see GoogleCalendarConnection}-Modells.
 * Google liefert beim Refresh i. d. R. KEIN neues Refresh-Token — save()
 * behält deshalb das bestehende (Toolkit-`withRefreshToken`-Konvention).
 */
class GoogleCalendarTokenStore implements OAuth2TokenStoreInterface {
    public function __construct(private readonly GoogleCalendarConnection $connection) {}

    public function load(): ?OAuth2Token {
        $accessToken = trim((string) $this->connection->access_token);
        if ($accessToken === '') {
            return null;
        }

        return new OAuth2Token(
            accessToken: $accessToken,
            refreshToken: $this->connection->refresh_token,
            expiresAt: $this->connection->token_expires_at !== null
                ? DateTimeImmutable::createFromInterface($this->connection->token_expires_at)
                : null,
            scope: $this->connection->scopes,
        );
    }

    public function save(OAuth2Token $token): void {
        $this->connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken() ?? $this->connection->refresh_token,
            'token_expires_at' => $token->getExpiresAt(),
            'scopes' => $token->getScope() ?? $this->connection->scopes,
        ])->save();
    }

    public function clear(): void {
        $this->connection->forceFill([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ])->save();
    }
}
