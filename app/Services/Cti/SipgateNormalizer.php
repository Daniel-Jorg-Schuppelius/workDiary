<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SipgateNormalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

use Illuminate\Support\Carbon;

/**
 * Adapter für sipgate.io-Webhooks (Feature 056, MVP-118, Referenzprovider).
 * sipgate sendet mehrere Ereignisse je Anruf (newCall/answer/hangup);
 * protokolliert wird nur das terminale `hangup` (vollständiger Anruf).
 */
class SipgateNormalizer implements CtiEventNormalizer {
    public function normalize(array $payload): ?CtiCall {
        $event = strtolower((string) ($payload['event'] ?? ''));
        if ($event !== 'hangup') {
            return null; // Zwischenzustände nicht protokollieren
        }

        $callId = (string) ($payload['callId'] ?? '');
        if ($callId === '') {
            return null;
        }

        $direction = strtolower((string) ($payload['direction'] ?? '')) === 'out'
            ? CtiCall::OUTBOUND
            : CtiCall::INBOUND;

        return new CtiCall(
            callId: $callId,
            direction: $direction,
            fromNumber: (string) ($payload['from'] ?? ''),
            toNumber: (string) ($payload['to'] ?? ''),
            occurredAt: Carbon::now(),
            durationSeconds: (int) ($payload['duration'] ?? 0),
        );
    }
}
