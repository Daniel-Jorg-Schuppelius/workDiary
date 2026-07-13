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

use App\Models\CarrierConnection;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\{Cache, Crypt};

/**
 * OAuth2-Access-Token-Cache der Carrier-Anbindungen (Feature 059, MVP-128):
 * UPS/FedEx vergeben kurzlebige Client-Credentials-Tokens (~4 h bzw. 60 min).
 * Der Cache hält sie je Organisation + Carrier + Umgebung (Sandbox/Prod) mit
 * Ablauf-Sicherheitsfenster; abgelegt wird verschlüsselt (APP_KEY), da
 * Cache-Stores (file/redis) sonst Klartext-Bearer-Tokens trügen.
 *
 * Hinweis Toolkit-first: das `php-api-toolkit` bietet derzeit nur einen
 * Authorization-Code-Grant — der Client-Credentials-Grant ist ein
 * Erweiterungskandidat (Klasse C, wie beim JTL-Cloud-Gateway dokumentiert);
 * bis zum Toolkit-Release lebt der Austausch bewusst in den Carrier-Plugins.
 */
final class CarrierTokenCache {
    /** Sicherheitsfenster: so viele Sekunden vor Ablauf gilt der Token als abgelaufen. */
    private const EXPIRY_LEEWAY_SECONDS = 60;

    /**
     * Liefert den gecachten Access-Token oder holt über `$fetch` einen neuen.
     *
     * @param  Closure(): array{access_token: string, expires_in: int}  $fetch
     */
    public function remember(CarrierConnection $connection, Closure $fetch): string {
        $key = $this->key($connection);

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            try {
                return Crypt::decryptString($cached);
            } catch (DecryptException) {
                Cache::forget($key); // z. B. APP_KEY-Rotation → frisch holen
            }
        }

        ['access_token' => $token, 'expires_in' => $expiresIn] = $fetch();

        Cache::put($key, Crypt::encryptString($token), max(self::EXPIRY_LEEWAY_SECONDS, $expiresIn - self::EXPIRY_LEEWAY_SECONDS));

        return $token;
    }

    /** Verwirft den gecachten Token (z. B. nach einem 401 des Carriers). */
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
