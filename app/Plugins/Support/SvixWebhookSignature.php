<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SvixWebhookSignature.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

/**
 * Konstantzeitliche Prüfung von Webhook-Signaturen im Svix-Format
 * (Feature 101, Etsy — das Schema ist provider-neutral wiederverwendbar):
 * signiert wird `"{webhook-id}.{webhook-timestamp}.{raw_body}"` per
 * HMAC-SHA256; das Secret trägt das Präfix `whsec_` und ist base64-kodiert;
 * der `webhook-signature`-Header kann MEHRERE space-getrennte
 * `v1,<base64>`-Einträge tragen (Secret-Rotation) — ein Treffer genügt.
 * Leeres/fehlerhaftes Secret lehnt IMMER ab (kein Fail-Open); Fehlsignaturen
 * melden das fail2ban-Signal wie {@see WebhookSignature}.
 */
final class SvixWebhookSignature {
    public static function valid(string $webhookId, string $timestamp, string $rawBody, ?string $secret, string $signatureHeader): bool {
        $key = self::decodeSecret($secret);
        if ($key === null || $webhookId === '' || $timestamp === '' || trim($signatureHeader) === '') {
            self::reportInvalid();

            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $webhookId . '.' . $timestamp . '.' . $rawBody, $key, true));

        foreach (explode(' ', trim($signatureHeader)) as $candidate) {
            $parts = explode(',', trim($candidate), 2);
            if (count($parts) === 2 && $parts[0] === 'v1' && $parts[1] !== '' && hash_equals($expected, $parts[1])) {
                return true;
            }
        }

        self::reportInvalid();

        return false;
    }

    /** `whsec_<base64>` → roher HMAC-Key; null bei leerem/unlesbarem Secret. */
    private static function decodeSecret(?string $secret): ?string {
        $secret = trim((string) ($secret ?? ''));
        if ($secret === '') {
            return null;
        }
        if (str_starts_with($secret, 'whsec_')) {
            $secret = substr($secret, strlen('whsec_'));
        }

        $key = base64_decode($secret, true);

        return ($key === false || $key === '') ? null : $key;
    }

    /** fail2ban-Signal (Feature 096) — identisch zur {@see WebhookSignature}. */
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
}
