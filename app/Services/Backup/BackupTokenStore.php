<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTokenStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup;

use APIToolkit\API\Authentication\OAuth2\OAuth2Token;
use APIToolkit\Contracts\Interfaces\API\OAuth2TokenStoreInterface;
use App\Models\Backup\BackupTargetConnection;
use DateTimeImmutable;

/**
 * Provider-neutrale Token-Persistenz der Backupziel-Verbindungen fürs
 * api-toolkit (Feature 017 Phase 32, MVP-363): verschlüsselt at-rest über
 * die encrypted-Casts von {@see BackupTargetConnection}. Bewusst getrennt
 * vom Dokumentimport ({@see \App\Services\CloudIntake\CloudIntakeTokenStore})
 * — eigene Verbindungen, eigene Scopes (Produktgrenze). Provider liefern
 * beim Refresh oft kein neues Refresh-Token — save() behält das bestehende.
 */
class BackupTokenStore implements OAuth2TokenStoreInterface {
    public function __construct(private readonly BackupTargetConnection $connection) {}

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
            scope: is_array($this->connection->granted_scopes)
                ? implode(' ', $this->connection->granted_scopes)
                : null,
        );
    }

    public function save(OAuth2Token $token): void {
        $scope = $token->getScope();

        $this->connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'refresh_token' => $token->getRefreshToken() ?? $this->connection->refresh_token,
            'token_expires_at' => $token->getExpiresAt(),
            'granted_scopes' => $scope !== null && $scope !== ''
                ? array_values(array_filter(explode(' ', $scope)))
                : $this->connection->granted_scopes,
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
