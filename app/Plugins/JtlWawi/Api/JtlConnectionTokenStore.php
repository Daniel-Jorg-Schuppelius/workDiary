<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlConnectionTokenStore.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Api;

use APIToolkit\API\Authentication\OAuth2\OAuth2Token;
use APIToolkit\Contracts\Interfaces\API\OAuth2TokenStoreInterface;
use App\Models\JtlConnection;
use DateTimeImmutable;

/**
 * Bindet den an der {@see JtlConnection} verschlüsselt persistierten
 * Cloud-Token (access_token/token_expires_at, Casts `encrypted`) an das
 * php-api-toolkit an (Vollaudit 2026-07, N32 — Muster
 * {@see \App\Services\Shipping\CarrierConnectionTokenStore}). Die
 * {@see \APIToolkit\API\Authentication\OAuth2\OAuth2ClientCredentialsAuthentication}
 * lädt/speichert/verwirft ihre Tokens ausschließlich über dieses Interface.
 */
final class JtlConnectionTokenStore implements OAuth2TokenStoreInterface {
    public function __construct(private readonly JtlConnection $connection) {}

    public function load(): ?OAuth2Token {
        $access = trim((string) $this->connection->access_token);
        if ($access === '') {
            return null;
        }

        $expiresAt = $this->connection->token_expires_at !== null
            ? DateTimeImmutable::createFromInterface($this->connection->token_expires_at)
            : null;

        return new OAuth2Token($access, expiresAt: $expiresAt);
    }

    public function save(OAuth2Token $token): void {
        $this->connection->forceFill([
            'access_token' => $token->getAccessToken(),
            'token_expires_at' => $token->getExpiresAt(),
        ])->save();
    }

    public function clear(): void {
        $this->connection->forceFill([
            'access_token' => null,
            'token_expires_at' => null,
        ])->save();
    }
}
