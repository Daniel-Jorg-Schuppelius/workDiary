<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuthStateHandshake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use APIToolkit\API\Authentication\OAuth2\OAuth2AuthorizationCodeGrant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Gemeinsamer OAuth-State-Handshake der Plugin-Verbindungsflows
 * (Konsolidierung C7): kurzlebiger Einmal-state, org- und nutzergebunden,
 * PKCE-Verifier wandert mit dem state durch den Cache. `redeem()` löst via
 * pull() einmalig ein (Replay-Schutz). Cache-Keys bleiben je Plugin stabil
 * (Prefix aus dem Aufrufer).
 */
final class OAuthStateHandshake {
    private const STATE_TTL_SECONDS = 600;

    public function __construct(private readonly string $cachePrefix) {}

    /**
     * Startet den Handshake: state erzeugen, Payload cachen.
     *
     * @param  array<string, mixed>  $extra  zusätzliche Payload-Felder (z. B. connection_id)
     * @return array{state: string, verifier: string|null}
     */
    public function start(int $organizationId, int $userId, bool $withPkce = true, array $extra = []): array {
        $state = Str::random(40);
        $verifier = $withPkce ? OAuth2AuthorizationCodeGrant::generatePkceVerifier() : null;

        $payload = [
            'organization_id' => $organizationId,
            'user_id' => $userId,
        ] + $extra;
        if ($verifier !== null) {
            $payload['pkce_verifier'] = $verifier;
        }

        Cache::put($this->key($state), $payload, self::STATE_TTL_SECONDS);

        return ['state' => $state, 'verifier' => $verifier];
    }

    /**
     * Löst den state einmalig ein; null = unbekannt, abgelaufen oder an
     * andere Organisation/Sitzung gebunden.
     *
     * @return array<string, mixed>|null
     */
    public function redeem(string $state, int $organizationId, int $userId): ?array {
        $payload = $state !== '' ? Cache::pull($this->key($state)) : null;
        if (! is_array($payload)
            || (int) ($payload['organization_id'] ?? 0) !== $organizationId
            || (int) ($payload['user_id'] ?? 0) !== $userId) {
            return null;
        }

        return $payload;
    }

    /**
     * PKCE-Verifier aus dem eingelösten Payload ('' → null).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function verifierFrom(array $payload): ?string {
        return (string) ($payload['pkce_verifier'] ?? '') ?: null;
    }

    private function key(string $state): string {
        return $this->cachePrefix . ':' . $state;
    }
}
