<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarrierTokenCache.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

use APIToolkit\API\Authentication\OAuth2\OAuth2Token;
use App\Models\CarrierConnection;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\{Cache, Crypt};
use InvalidArgumentException;

/**
 * Verschlüsselte OAuth2-Token-Ablage der Carrier-Anbindungen (Feature 059,
 * MVP-128): UPS/FedEx vergeben kurzlebige Client-Credentials-Tokens (~4 h
 * bzw. 60 min). Abgelegt wird je Organisation + Carrier + Umgebung
 * (Sandbox/Prod) mit Ablauf-Sicherheitsfenster; verschlüsselt (APP_KEY), da
 * Cache-Stores (file/redis) sonst Klartext-Bearer-Tokens trügen.
 *
 * Die Grant-Logik (Token holen, Ablauf-Leeway, 401 ⇒ verwerfen + genau ein
 * Retry) lebt seit php-api-toolkit v2.3.3 im Toolkit
 * ({@see \APIToolkit\API\Authentication\OAuth2\OAuth2ClientCredentialsAuthentication});
 * diese Klasse ist nur noch der Storage darunter und dockt über den
 * {@see CarrierConnectionTokenStore} an dessen Store-Interface an.
 */
final class CarrierTokenCache {
    /** Sicherheitsfenster: so viele Sekunden vor Ablauf fällt der Eintrag aus dem Cache. */
    private const EXPIRY_LEEWAY_SECONDS = 60;

    /** Liefert den abgelegten Token oder null (nichts gecacht, abgelaufen oder nicht entschlüsselbar). */
    public function get(CarrierConnection $connection): ?OAuth2Token {
        $key = $this->key($connection);

        $cached = Cache::get($key);
        if (! is_string($cached) || $cached === '') {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($cached), true);
        } catch (DecryptException) {
            Cache::forget($key); // z. B. APP_KEY-Rotation → frisch holen

            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            return OAuth2Token::fromArray($data);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** Legt einen frischen Token verschlüsselt ab (TTL bis Ablauf minus Sicherheitsfenster). */
    public function put(CarrierConnection $connection, OAuth2Token $token): void {
        // FedEx liefert `token_type` klein ("bearer"); für byte-gleiche
        // Authorization-Header wie bisher auf "Bearer" normalisieren.
        if ($token->getTokenType() !== 'Bearer' && strcasecmp($token->getTokenType(), 'Bearer') === 0) {
            $token = new OAuth2Token($token->getAccessToken(), $token->getRefreshToken(), $token->getExpiresAt(), $token->getScope(), 'Bearer');
        }

        $expiresAt = $token->getExpiresAt();
        $expiresIn = $expiresAt !== null ? $expiresAt->getTimestamp() - time() : 0;

        Cache::put(
            $this->key($connection),
            Crypt::encryptString((string) json_encode($token->toArray())),
            max(self::EXPIRY_LEEWAY_SECONDS, $expiresIn - self::EXPIRY_LEEWAY_SECONDS),
        );
    }

    /** Verwirft den abgelegten Token (z. B. nach einem 401 des Carriers). */
    public function forget(CarrierConnection $connection): void {
        Cache::forget($this->key($connection));
    }

    /** Cache-Schlüssel je Organisation + Carrier + Umgebung (Org-Isolation!). */
    private function key(CarrierConnection $connection): string {
        return sprintf(
            'shipping:oauth:%s:%d:%s',
            $connection->carrier,
            $connection->organization_id,
            $connection->sandbox ? 'sandbox' : 'production',
        );
    }
}
