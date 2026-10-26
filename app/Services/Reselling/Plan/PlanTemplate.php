<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use Carbon\CarbonImmutable;

/**
 * Serienvorlage eines Buchhaltungsprogramms (Feature 185, MVP-1409): der
 * Plan, was wiederkehrend berechnet werden soll — Empfänger, Rhythmus,
 * nächste Ausführung, Positionen. Status und Intervall tragen die Begriffe
 * der Lexoffice-API (`ACTIVE`/`PAUSED`/`ENDED`, `WEEKLY` … `ANNUALLY`);
 * andere Quellen bilden darauf ab.
 */
final readonly class PlanTemplate {
    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_PAUSED = 'PAUSED';

    public const STATUS_ENDED = 'ENDED';

    /** Monate je Ausführung; Wochenrhythmen tragen 0 (keine Lizenzableitung). */
    public const INTERVAL_MONTHS = ['WEEKLY' => 0, 'BIWEEKLY' => 0, 'MONTHLY' => 1, 'QUARTERLY' => 3, 'BIANNUALLY' => 6, 'ANNUALLY' => 12];

    /**
     * @param  list<PlanLine>  $lines
     */
    public function __construct(
        public string $sourceKey,
        /** Schlüssel der Vorlage über alle Quellen: `<quelle>:<externe id>`. */
        public string $key,
        public string $title,
        public ?int $recipientCustomerId,
        public ?string $recipientName,
        public string $status,
        public string $interval,
        public ?CarbonImmutable $startsOn,
        public ?CarbonImmutable $endsOn,
        public ?CarbonImmutable $nextExecutionOn,
        public ?CarbonImmutable $lastExecutionOn,
        public bool $finalize,
        public bool $lastFailed,
        public ?string $lastError,
        public array $lines,
        public ?string $permalink,
    ) {}

    public static function keyOf(string $sourceKey, string $externalId): string {
        return $sourceKey . ':' . $externalId;
    }

    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function intervalMonths(): int {
        return self::INTERVAL_MONTHS[$this->interval] ?? 0;
    }

    public function line(int $position): ?PlanLine {
        foreach ($this->lines as $line) {
            if ($line->position === $position) {
                return $line;
            }
        }

        return null;
    }

    /** @return list<PlanLine> */
    public function licenceLines(): array {
        return array_values(array_filter($this->lines, static fn(PlanLine $line): bool => $line->articleIsLicence));
    }

    public function label(): string {
        return $this->title !== '' ? $this->title : $this->key;
    }
}
