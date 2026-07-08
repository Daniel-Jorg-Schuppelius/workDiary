<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GenericNormalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Neutraler CTI-Adapter (Feature 056, MVP-118) für Provider ohne eigenen
 * Adapter: erwartet das schlanke WorkDiary-Format
 * `{call_id, direction: inbound|outbound, from, to, occurred_at?, duration_seconds?}`.
 */
class GenericNormalizer implements CtiEventNormalizer {
    public function normalize(array $payload): ?CtiCall {
        $callId = (string) ($payload['call_id'] ?? '');
        $direction = (string) ($payload['direction'] ?? '');
        if ($callId === '' || ! in_array($direction, [CtiCall::INBOUND, CtiCall::OUTBOUND], true)) {
            return null;
        }

        return new CtiCall(
            callId: $callId,
            direction: $direction,
            fromNumber: (string) ($payload['from'] ?? ''),
            toNumber: (string) ($payload['to'] ?? ''),
            occurredAt: $this->parseDate($payload['occurred_at'] ?? null),
            durationSeconds: (int) ($payload['duration_seconds'] ?? 0),
        );
    }

    private function parseDate(mixed $value): Carbon {
        if (! is_string($value) || $value === '') {
            return Carbon::now();
        }
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return Carbon::now();
        }
    }
}
