<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodKey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Period;

use App\Enums\Period\PeriodKind;
use App\Support\Locales;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Stringable;

/**
 * Periodenschlüssel (Feature 198 `period_key`, Feature 201 Merker): eine
 * Kalenderperiode als Text in der Schreibweise der Buchhaltung, ergänzt um das
 * Halbjahr.
 *
 * | Art      | Schlüssel | Ordnungswert |
 * | -------- | --------- | ------------ |
 * | Jahr     | `2026`    | 1            |
 * | Halbjahr | `2026-H1` | 1–2          |
 * | Quartal  | `2026-Q3` | 1–4          |
 * | Monat    | `2026-03` | 1–12         |
 *
 * Vergleiche laufen über die Tagesgrenzen, nie über den Text: `'2026' < '2026-03'`
 * stimmt zufällig, `'2026-H1' < '2026-Q1'` sagt nichts. Innerhalb einer Art ist
 * {@see compareTo()} die zeitliche Reihenfolge; über Arten hinweg ordnet sie
 * nach Beginn, dann nach Ende (das Jahr 2026 vor seinem März). Für „gilt ab
 * dieser Periode“ zählt {@see endsBefore()}: eine Periode, die vor dem Beginn
 * der anderen endet, liegt ganz davor — so trifft ein Merker „ab 2026“ auch ein
 * Vorkommen `2026-03`, wenn eine Reihe die Art wechselt.
 *
 * Grenzen sind Kalendertage ohne Zeitzonenbezug (`start()` und `end()` jeweils
 * 00:00 Uhr des ersten bzw. letzten Tages).
 */
final readonly class PeriodKey implements Stringable {
    private const string PATTERN = '/^(\d{4})(?:-(?:H([1-2])|Q([1-4])|(\d{2})))?$/';

    private function __construct(
        public PeriodKind $kind,
        public int $year,
        public int $ordinal,
    ) {}

    /** Periode aus Art, Jahr und Ordnungswert (Halbjahr 1–2, Quartal 1–4, Monat 1–12; Jahr: 1). */
    public static function of(PeriodKind $kind, int $year, int $ordinal = 1): self {
        if ($year < 1000 || $year > 9999) {
            throw new InvalidArgumentException("Periodenjahr außerhalb 1000–9999: {$year}");
        }
        if ($ordinal < 1 || $ordinal > $kind->perYear()) {
            throw new InvalidArgumentException("Ordnungswert {$ordinal} passt nicht zur Periodenart {$kind->value}");
        }

        return new self($kind, $year, $ordinal);
    }

    /** Schlüssel lesen; Groß-/Kleinschreibung und Leerraum werden geglättet, Ungültiges wirft. */
    public static function parse(string $key): self {
        $parsed = self::tryParse($key);
        if ($parsed === null) {
            throw new InvalidArgumentException("Ungültiger Periodenschlüssel: {$key}");
        }

        return $parsed;
    }

    /** Schlüssel lesen; null bei leerem oder ungültigem Wert. */
    public static function tryParse(?string $key): ?self {
        $key = strtoupper(trim((string) $key));
        if ($key === '' || preg_match(self::PATTERN, $key, $match) !== 1) {
            return null;
        }
        $year = (int) $match[1];
        [$kind, $ordinal] = match (true) {
            ($match[2] ?? '') !== '' => [PeriodKind::HalfYear, (int) $match[2]],
            ($match[3] ?? '') !== '' => [PeriodKind::Quarter, (int) $match[3]],
            ($match[4] ?? '') !== '' => [PeriodKind::Month, (int) $match[4]],
            default => [PeriodKind::Year, 1],
        };
        if ($ordinal < 1 || $ordinal > $kind->perYear()) {
            return null;
        }

        return new self($kind, $year, $ordinal);
    }

    /** Die Periode der Art, in die ein Datum fällt. */
    public static function containing(PeriodKind $kind, DateTimeInterface $date): self {
        $at = CarbonImmutable::instance($date);

        return self::of($kind, $at->year, intdiv($at->month - 1, $kind->months()) + 1);
    }

    public function key(): string {
        return match ($this->kind) {
            PeriodKind::Year => sprintf('%04d', $this->year),
            PeriodKind::HalfYear => sprintf('%04d-H%d', $this->year, $this->ordinal),
            PeriodKind::Quarter => sprintf('%04d-Q%d', $this->year, $this->ordinal),
            PeriodKind::Month => sprintf('%04d-%02d', $this->year, $this->ordinal),
        };
    }

    public function __toString(): string {
        return $this->key();
    }

    /** Erster Tag der Periode, 00:00 Uhr. */
    public function start(): CarbonImmutable {
        return CarbonImmutable::create($this->year, ($this->ordinal - 1) * $this->kind->months() + 1, 1, 0, 0, 0) ?? throw new InvalidArgumentException('Periodenbeginn nicht darstellbar: ' . $this->key());
    }

    /** Letzter Tag der Periode, 00:00 Uhr. */
    public function end(): CarbonImmutable {
        return $this->start()->addMonthsNoOverflow($this->kind->months())->subDay();
    }

    /** Die Periode gleicher Art $steps Schritte später (negativ: früher). */
    public function next(int $steps = 1): self {
        $perYear = $this->kind->perYear();
        $index = $this->year * $perYear + ($this->ordinal - 1) + $steps;

        return self::of($this->kind, intdiv($index, $perYear), $index % $perYear + 1);
    }

    public function previous(int $steps = 1): self {
        return $this->next(-$steps);
    }

    public function equals(self $other): bool {
        return $this->kind === $other->kind && $this->year === $other->year && $this->ordinal === $other->ordinal;
    }

    /** Zeitliche Ordnung: nach Beginn, bei gleichem Beginn nach Ende (−1, 0, 1). */
    public function compareTo(self $other): int {
        return [$this->start()->getTimestamp(), $this->end()->getTimestamp()]
            <=> [$other->start()->getTimestamp(), $other->end()->getTimestamp()];
    }

    /** Endet diese Periode vor dem Beginn der anderen (ganz davor, keine Überschneidung)? */
    public function endsBefore(self $other): bool {
        return $this->end()->lessThan($other->start());
    }

    public function contains(DateTimeInterface $date): bool {
        $day = CarbonImmutable::instance($date)->startOfDay();

        return $day->greaterThanOrEqualTo($this->start()) && $day->lessThanOrEqualTo($this->end());
    }

    /** Lesbare Bezeichnung in der aktiven Sprache, z. B. „3. Quartal 2026“ oder „März 2026“. */
    public function label(): string {
        if ($this->kind === PeriodKind::Month) {
            $start = $this->start();
            $localized = $start->locale(Locales::carbon(app()->getLocale()));

            return (string) __('period.label.month', [
                'month' => ($localized instanceof CarbonImmutable ? $localized : $start)->translatedFormat('F'),
                'year' => $this->year,
            ]);
        }

        return (string) __('period.label.' . $this->kind->value, ['ordinal' => $this->ordinal, 'year' => $this->year]);
    }
}
