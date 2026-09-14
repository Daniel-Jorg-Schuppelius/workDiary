<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiRemoteKeys.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Plugins\Support\PluginHttpFactory;
use CommonToolkit\Helper\Data\CryptoHelper;
use ELearningToolkit\Lti\{Keys, LtiException};
use Illuminate\Support\Facades\Cache;
use Jose\Component\Core\JWKSet;

/**
 * Schlüsselmengen fremder Plattformen und Tools (LTI Security Framework 6.3).
 *
 * Kurz zwischengespeichert; wer einen unbekannten `kid` sieht, lädt einmal frisch
 * — so übersteht eine Rotation der Gegenseite den Cache.
 */
final class LearningLtiRemoteKeys {
    private const TTL_SECONDS = 600;

    public function __construct(private readonly PluginHttpFactory $http) {}

    /** @throws LtiException */
    public function keySet(string $jwksUrl, bool $fresh = false): JWKSet {
        $cacheKey = 'learning.lti.jwks.' . CryptoHelper::hash($jwksUrl);

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        /** @var array<string, mixed> $data */
        $data = Cache::remember($cacheKey, self::TTL_SECONDS, function () use ($jwksUrl): array {
            $response = $this->http->coreClient('learning-lti', $jwksUrl)->getResponse($jwksUrl);

            if (! $response->successful() || ! is_array($response->json('keys'))) {
                throw new LtiException(LtiException::UNKNOWN_KEY, 'jwks');
            }

            return (array) $response->json();
        });

        return Keys::keySet($data);
    }
}
