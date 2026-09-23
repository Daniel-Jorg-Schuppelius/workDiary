<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEligibilityReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use Carbon\CarbonImmutable;

/**
 * Ergebnis der Zulassungsprüfung (MVP-846): erfüllt/fehlend je Voraussetzung,
 * Zählzeitraum, Stichtag und verwendete Nachweise. Der Bericht rechnet in
 * Minuten; Stunden und Einheiten entstehen erst bei der Anzeige.
 */
final class ClubEligibilityReport {
    /**
     * @param  list<array{key: string, met: bool, required: string, actual: string}>  $items
     * @param  list<int>  $usedRecordIds
     */
    public function __construct(
        public readonly bool $met,
        public readonly array $items,
        public readonly CarbonImmutable $examDay,
        public readonly ?CarbonImmutable $countingFrom,
        public readonly int $minutes,
        public readonly int $sessions,
        public readonly ?int $requiredMinutes,
        public readonly ?int $requiredSessions,
        public readonly ?int $unitMinutes,
        public readonly array $usedRecordIds,
    ) {}

    /** @return list<array{key: string, met: bool, required: string, actual: string}> */
    public function missing(): array {
        return array_values(array_filter($this->items, static fn(array $item): bool => ! $item['met']));
    }

    public function remainingMinutes(): int {
        return max(0, ($this->requiredMinutes ?? 0) - $this->minutes);
    }

    /** „18 Stunden 45 Minuten von 20 Stunden; noch 1 Stunde 15 Minuten“ — Rundung erst hier. */
    public function progressText(): ?string {
        if ($this->requiredMinutes === null) {
            return null;
        }
        $text = (string) __('club.grading.progress.of', ['actual' => self::hoursMinutes($this->minutes), 'required' => self::hoursMinutes($this->requiredMinutes)]);
        $remaining = $this->remainingMinutes();

        return $remaining > 0
            ? $text . '; ' . (string) __('club.grading.progress.remaining', ['remaining' => self::hoursMinutes($remaining)])
            : $text . '; ' . (string) __('club.grading.progress.reached');
    }

    /** Anzeige in Unterrichtseinheiten, wenn die Regelversion eine feste Einheit kennt; Restminuten bleiben sichtbar. */
    public function unitsText(): ?string {
        if ($this->unitMinutes === null || $this->unitMinutes <= 0) {
            return null;
        }
        $units = intdiv($this->minutes, $this->unitMinutes);
        $rest = $this->minutes % $this->unitMinutes;

        return (string) trans_choice('club.grading.units', $units, ['count' => $units, 'unit' => $this->unitMinutes])
            . ($rest > 0 ? ' ' . (string) __('club.grading.progress.rest_minutes', ['minutes' => $rest]) : '');
    }

    public static function hoursMinutes(int $minutes): string {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        $parts = [];
        if ($hours > 0 || $rest === 0) {
            $parts[] = (string) trans_choice('club.grading.hours', $hours, ['count' => $hours]);
        }
        if ($rest > 0) {
            $parts[] = (string) trans_choice('club.grading.minutes', $rest, ['count' => $rest]);
        }

        return implode(' ', $parts);
    }
}
