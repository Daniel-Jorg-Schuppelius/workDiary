<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookSignature.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

/**
 * Konstantzeitliche Webhook-Signaturprüfung (Konsolidierung B6) — deckt die
 * vier Schemata der Plugin-Webhooks ab: präfixierte Hex-HMACs (GitHub
 * `sha256=…`, Zammad `sha1=…`), rohe Hex-HMACs (Dropbox), base64-HMACs
 * (Todoist) und statische Token-Vergleiche (GitLab/Google Drive/Msgraph).
 * Leeres oder fehlendes Secret lehnt IMMER ab (kein Fail-Open).
 */
final class WebhookSignature {
    /**
     * Prüft eine HMAC-Signatur des Raw-Bodys. `$encoding`: 'hex' (Default)
     * oder 'base64'; `$prefix` (z. B. 'sha256=') muss im gelieferten Wert
     * enthalten sein.
     */
    public static function hmacValid(string $payload, ?string $secret, string $provided, string $algo, string $prefix = '', string $encoding = 'hex'): bool {
        $valid = self::hmacDigestValid($payload, $secret, $provided, $algo, $prefix, $encoding);

        if (! $valid) {
            // fail2ban-Signal (Feature 096, MVP-443) — hmacValid ist überall
            // ein harter Reject (anders als tokenValid, das auch als Matcher
            // dient und deshalb still bleibt).
            self::reportInvalid();
        }

        return $valid;
    }

    private static function hmacDigestValid(string $payload, ?string $secret, string $provided, string $algo, string $prefix, string $encoding): bool {
        if ($secret === null || $secret === '') {
            return false;
        }
        if ($prefix !== '' && ! str_starts_with($provided, $prefix)) {
            return false;
        }

        $digest = $encoding === 'base64'
            ? base64_encode(hash_hmac($algo, $payload, $secret, true))
            : hash_hmac($algo, $payload, $secret);

        return hash_equals($prefix . $digest, $provided);
    }

    private static function reportInvalid(): void {
        try {
            app(\App\Services\Security\SecurityEventLogger::class)->log(
                \App\Enums\Security\SecurityEventType::WebhookSignatureInvalid,
                ['path' => request()->path()],
            );
        } catch (\Throwable) {
            // Signaturprüfung darf nie am Logging scheitern.
        }
    }

    /** Konstantzeit-Vergleich statischer Tokens; leer/fehlend ⇒ false. */
    public static function tokenValid(?string $expected, ?string $provided): bool {
        if ($expected === null || $expected === '' || $provided === null || $provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }
}
