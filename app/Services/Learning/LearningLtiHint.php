<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiHint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use JsonException;

/**
 * Signierte, kurzlebige Hinweise im LTI-Ablauf (Feature 149): `login_hint`,
 * `lti_message_hint` und das `data` einer Deep-Linking-Anfrage.
 *
 * Das Tool reicht sie nur weiter. Zweck und Ablauf stehen im signierten Inhalt —
 * ein Login-Hinweis taugt deshalb nie als Deep-Linking-Zustand.
 */
final class LearningLtiHint {
    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_MESSAGE = 'message';

    public const PURPOSE_DEEP_LINKING = 'deep_linking';

    /**
     * @param  array<string, int|string>  $claims
     * @return non-empty-string
     */
    public function issue(string $purpose, array $claims, int $ttlSeconds = 600, ?CarbonImmutable $now = null): string {
        $now ??= CarbonImmutable::now();
        $body = CryptoHelper::base64UrlEncode(JsonHelper::encode([
            'p' => $purpose,
            'x' => $now->getTimestamp() + max(1, $ttlSeconds),
            'c' => $claims,
        ]));

        return $body . '.' . CryptoHelper::base64UrlEncode(hash_hmac('sha256', $body, self::key(), true));
    }

    /** @return array<mixed>|null die Angaben, oder `null` bei falscher Signatur, fremdem Zweck oder Ablauf */
    public function verify(string $purpose, string $hint, ?CarbonImmutable $now = null): ?array {
        $parts = explode('.', $hint);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$body, $mac] = $parts;

        if (! hash_equals(CryptoHelper::base64UrlEncode(hash_hmac('sha256', $body, self::key(), true)), $mac)) {
            return null;
        }

        $json = CryptoHelper::base64UrlDecode($body);

        try {
            $data = $json === false ? null : json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data) || ($data['p'] ?? null) !== $purpose || ! is_int($data['x'] ?? null) || ! is_array($data['c'] ?? null)) {
            return null;
        }

        if ($data['x'] < ($now ?? CarbonImmutable::now())->getTimestamp()) {
            return null;
        }

        return $data['c'];
    }

    private static function key(): string {
        $appKey = (string) config('app.key', '');

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            $appKey = $decoded === false ? $appKey : $decoded;
        }

        // Eigener Ableitungszweck: kein anderes Signat der Anwendung passt hierher.
        return hash_hmac('sha256', 'learning-lti-hint', $appKey, true);
    }
}
