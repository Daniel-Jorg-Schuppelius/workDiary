<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiCall.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cti;

use Illuminate\Support\Carbon;

/**
 * Providerneutrales, normalisiertes Anruf-Ereignis (Feature 056, MVP-118).
 * Bewusst **nur Metadaten** (Richtung, Nummern, Zeitpunkt, Dauer) — nie
 * Gesprächsinhalte. Der Provider-Adapter erzeugt es; der {@see CtiCallService}
 * arbeitet nur hiermit.
 */
final class CtiCall {
    public const INBOUND = 'inbound';

    public const OUTBOUND = 'outbound';

    public function __construct(
        public readonly string $callId,
        public readonly string $direction,
        public readonly string $fromNumber,
        public readonly string $toNumber,
        public readonly Carbon $occurredAt,
        public readonly int $durationSeconds = 0,
    ) {}

    /** Die externe Gegenstelle je Richtung (bei eingehend der Anrufer). */
    public function counterpartyNumber(): string {
        return $this->direction === self::INBOUND ? $this->fromNumber : $this->toNumber;
    }
}
