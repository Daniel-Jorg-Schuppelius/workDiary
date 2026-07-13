<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlacetelNormalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

use Illuminate\Support\Carbon;

/**
 * Adapter für Placetel-Notify-Webhooks (Feature 056, MVP-343). Placetel
 * sendet je Anruf mehrere Ereignisse (`IncomingCall`/`CallAccepted`/
 * `OutgoingCall`/`HungUp`); protokolliert wird nur das terminale `HungUp`
 * (vollständiger Anruf). Feldmapping des Notify-Formats:
 * `call_id`, `direction` (`in`|`out`), `from`, `to`, `duration` (Sekunden).
 */
class PlacetelNormalizer implements CtiEventNormalizer {
    public function normalize(array $payload): ?CtiCall {
        $event = strtolower((string) ($payload['event'] ?? ''));
        if ($event !== 'hungup') {
            return null; // Zwischenzustände nicht protokollieren
        }

        $callId = (string) ($payload['call_id'] ?? '');
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
