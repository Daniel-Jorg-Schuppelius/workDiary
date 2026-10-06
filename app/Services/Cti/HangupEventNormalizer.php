<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HangupEventNormalizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

use Illuminate\Support\Carbon;

/**
 * Anbieter, die je Anruf mehrere Ereignisse senden: protokolliert wird nur
 * das terminale Ereignis (vollständiger Anruf). Die Ableitung nennt dessen
 * Namen und das Feld der Anruf-ID; Richtung, Nummern und Dauer heißen gleich.
 */
abstract class HangupEventNormalizer implements CtiEventNormalizer {
    /** Name des terminalen Ereignisses, kleingeschrieben. */
    abstract protected function terminalEvent(): string;

    abstract protected function callIdKey(): string;

    public function normalize(array $payload): ?CtiCall {
        if (strtolower((string) ($payload['event'] ?? '')) !== $this->terminalEvent()) {
            return null; // Zwischenzustände nicht protokollieren
        }

        $callId = (string) ($payload[$this->callIdKey()] ?? '');
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
