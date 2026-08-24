<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StarfaceNormalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

/**
 * Adapter für STARFACE-Call-Events (Feature 056, MVP-343). STARFACE meldet
 * Zustandswechsel je Anruf (`RINGING`/`CONNECTED`/`HANGUP`); protokolliert
 * wird nur der terminale Zustand (`HANGUP`/`ENDED`). Feldmapping:
 * `callId` (Fallback `id`), `direction` (`INBOUND`|`OUTBOUND`),
 * `callerNumber`/`calledNumber`, `durationInSeconds` (Fallback `duration`),
 * optional `timestamp` (ISO 8601).
 */
class StarfaceNormalizer extends AbstractCtiNormalizer {
    public function normalize(array $payload): ?CtiCall {
        $state = strtoupper((string) ($payload['callState'] ?? $payload['state'] ?? ''));
        if (! in_array($state, ['HANGUP', 'ENDED'], true)) {
            return null; // Zwischenzustände nicht protokollieren
        }

        $callId = (string) ($payload['callId'] ?? $payload['id'] ?? '');
        if ($callId === '') {
            return null;
        }

        $direction = strtoupper((string) ($payload['direction'] ?? '')) === 'OUTBOUND'
            ? CtiCall::OUTBOUND
            : CtiCall::INBOUND;

        return new CtiCall(
            callId: $callId,
            direction: $direction,
            fromNumber: (string) ($payload['callerNumber'] ?? $payload['from'] ?? ''),
            toNumber: (string) ($payload['calledNumber'] ?? $payload['to'] ?? ''),
            occurredAt: $this->parseDate($payload['timestamp'] ?? null),
            durationSeconds: (int) ($payload['durationInSeconds'] ?? $payload['duration'] ?? 0),
        );
    }

}
