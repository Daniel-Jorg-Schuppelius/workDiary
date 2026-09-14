<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormContentToken.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningEnrollment, LearningScormPackage, LearningUnit};
use Carbon\CarbonImmutable;
use JsonException;

/**
 * Signierter, befristeter Zugang zu den Dateien eines SCORM-Pakets am Inhalts-Host.
 *
 * Der Inhalts-Host bekommt kein Sitzungscookie der Anwendung. Stattdessen steht im
 * Pfad ein Token mit Einschreibung, Einheit, Paket und Ablauf, geschlüsselt über
 * den APP_KEY. Er öffnet nur Kursmaterial, keine Personendaten, und verliert seine
 * Wirkung, sobald das Paket ersetzt wird.
 */
final class ScormContentToken {
    /**
     * @return non-empty-string
     */
    public function issue(LearningEnrollment $enrollment, LearningUnit $unit, LearningScormPackage $package, ?CarbonImmutable $now = null): string {
        $now ??= CarbonImmutable::now();

        $body = self::encode((string) json_encode([
            'e' => $enrollment->id,
            'u' => $unit->id,
            'p' => $package->id,
            'x' => $now->getTimestamp() + max(60, (int) config('learning.scorm.token_ttl', 28800)),
        ]));

        return $body . '.' . self::encode(hash_hmac('sha256', $body, self::key(), true));
    }

    /**
     * @return array{enrollment: int, unit: int, package: int}|null
     */
    public function verify(string $token, ?CarbonImmutable $now = null): ?array {
        $parts = explode('.', $token);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$body, $mac] = $parts;

        if (! hash_equals(self::encode(hash_hmac('sha256', $body, self::key(), true)), $mac)) {
            return null;
        }

        $json = self::decode($body);

        try {
            $data = $json === null ? null : json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data) || ! is_int($data['e'] ?? null) || ! is_int($data['u'] ?? null) || ! is_int($data['p'] ?? null) || ! is_int($data['x'] ?? null)) {
            return null;
        }

        if ($data['x'] < ($now ?? CarbonImmutable::now())->getTimestamp()) {
            return null;
        }

        return ['enrollment' => $data['e'], 'unit' => $data['u'], 'package' => $data['p']];
    }

    private static function key(): string {
        $appKey = (string) config('app.key', '');
        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            $appKey = $decoded === false ? $appKey : $decoded;
        }

        // Eigener Ableitungszweck: ein Token lässt sich nicht als anderes Signat verwenden.
        return hash_hmac('sha256', 'scorm-content-token', $appKey, true);
    }

    private static function encode(string $bytes): string {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): ?string {
        $decoded = base64_decode(strtr($text, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
