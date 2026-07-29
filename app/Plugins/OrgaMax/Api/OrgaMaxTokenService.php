<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxTokenService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Api;

use APIToolkit\Exceptions\{ApiException, UnauthorizedException};
use App\Models\OrgaMaxConnection;
use Illuminate\Support\Carbon;
use Orgamax\API\Endpoints\AuthEndpoint;

/**
 * Bearer-Token-Verwaltung (Feature 077, MVP-306): orgaMAX dokumentiert
 * keinen Refresh-Flow — das JWT-Ablaufdatum (exp-Claim) wird ausgewertet und
 * bei Bedarf über POST /auth/token mit der gespeicherten ownershipId ein
 * neuer Token erzeugt. Fehlschläge blockieren die Verbindung mit
 * „Verbindung erneuern" statt in Endlosschleifen zu laufen.
 */
class OrgaMaxTokenService {
    public function validTokenFor(OrgaMaxConnection $connection): string {
        $token = (string) ($connection->bearer_token ?? '');
        $expires = $connection->token_expires_at;
        $window = (int) config('plugins.orgamax.token_refresh_window', 120);

        if ($token !== '' && ($expires === null || $expires->subSeconds($window)->isFuture())) {
            return $token;
        }

        return $this->refresh($connection);
    }

    public function refresh(OrgaMaxConnection $connection): string {
        [$apiKey, $apiSecret] = $this->credentialsFor($connection);
        $ownershipId = (string) ($connection->ownership_id ?? '');
        if ($apiKey === '' || $apiSecret === '' || $ownershipId === '') {
            $this->block($connection, 'credentials_missing');

            throw new UnauthorizedException('orgaMAX-Zugangsdaten unvollständig — Verbindung erneuern.', 401);
        }

        try {
            // OrgaMaxClientFactory lazy aus dem Container (Test-Naht FakePluginHttp).
            $client = app(OrgaMaxClientFactory::class)->credentials($apiKey, $apiSecret);
            $token = (string) (new AuthEndpoint($client))->token($ownershipId)->getToken();
        } catch (ApiException $e) {
            if (in_array($e->getCode(), [401, 403], true)) {
                $this->block($connection, 'token_refresh_failed');
            }

            throw $e;
        }

        if ($token === '') {
            throw new ApiException('orgaMAX /auth/token lieferte keinen Token.', 502);
        }

        $connection->forceFill([
            'bearer_token' => $token,
            'token_expires_at' => self::expiryFromJwt($token),
        ])->save();

        return $token;
    }

    /**
     * Key/Secret je Betriebsart: privat = je Org verschlüsselt,
     * Marketplace = installationsweites Betreibergeheimnis aus der Umgebung.
     *
     * @return array{0: string, 1: string}
     */
    public function credentialsFor(OrgaMaxConnection $connection): array {
        if ($connection->mode === OrgaMaxConnection::MODE_MARKETPLACE) {
            return [
                (string) config('plugins.orgamax.operator_api_key', ''),
                (string) config('plugins.orgamax.operator_api_secret', ''),
            ];
        }

        return [(string) ($connection->api_key ?? ''), (string) ($connection->api_secret ?? '')];
    }

    /** exp-Claim des JWT (Base64url-Payload) — null bei nicht parsebarem Token. */
    public static function expiryFromJwt(string $token): ?Carbon {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return null;
        }
        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        $exp = is_array($payload) ? ($payload['exp'] ?? null) : null;

        return is_numeric($exp) ? Carbon::createFromTimestamp((int) $exp) : null;
    }

    private function block(OrgaMaxConnection $connection, string $reason): void {
        $connection->forceFill([
            'status' => OrgaMaxConnection::STATUS_BLOCKED,
            'blocked_reason' => $reason,
        ])->save();
    }
}
