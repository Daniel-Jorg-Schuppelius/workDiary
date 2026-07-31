<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxCall.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Fritzbox\Sources;

use Carbon\CarbonImmutable;

/**
 * Ein Anruf aus der FRITZ!Box-Anrufliste. Die Box liefert nur Start + Dauer
 * (minutengenau); `endedAt` ist daraus abgeleitet. `e164` ist null bei
 * unterdrückter/unparsbarer Rufnummer.
 */
final class FritzboxCall {
    public const TYPE_INCOMING = 1;

    public const TYPE_MISSED = 2;

    public const TYPE_REJECTED = 3;

    public const TYPE_OUTGOING = 4;

    public const DIR_IN = 'in';

    public const DIR_OUT = 'out';

    public function __construct(
        public readonly int $type,
        public readonly string $direction,
        public readonly CarbonImmutable $startedAt,
        public readonly CarbonImmutable $endedAt,
        public readonly int $durationMinutes,
        public readonly string $numberRaw,
        public readonly ?string $e164,
        public readonly ?string $name,
        public readonly ?string $ownLine,
    ) {}

    /**
     * Deterministischer Idempotenz-Schlüssel: Reimporte derselben oder
     * überlappender Monatslisten erzeugen denselben Key.
     */
    public function callKey(): string {
        return 'call:' . sha1(implode('|', [
            $this->startedAt->format('YmdHis'),
            $this->e164 ?? $this->numberRaw,
            (string) $this->type,
            (string) $this->durationMinutes,
            (string) $this->ownLine,
        ]));
    }

    /** Verpasst/erfolglos (Typ 2 bzw. Dauer 0) — wird nie importiert. */
    public function isMissed(): bool {
        return $this->type === self::TYPE_MISSED || $this->durationMinutes <= 0;
    }
}
