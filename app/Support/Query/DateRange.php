<?php
/*
 * Created on   : Mon Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DateRange.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Query;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Indexfähige Datumsbereiche (Vollscan 2026-08-23, A8, MVP-722).
 *
 * `whereDate($spalte, …)` erzeugt `DATE(spalte) …` — eine Funktion auf der
 * Spalte. Auf DATETIME-/TIMESTAMP-Spalten fällt damit jeder Index aus;
 * gemessen an 50.000 `time_entries`: Zeitraumsumme 7,4 ms `type=ALL` gegenüber
 * 0,7 ms `type=range` nach der Umstellung.
 *
 * Zwei Fälle, zwei Methoden — die Unterscheidung ist die eigentliche Regel:
 *   - {@see whereDateBetween()} für DATE-Spalten (`booked_on`, `date`,
 *     `due_on`): direkter Bereichsvergleich mit `Y-m-d`, beide Grenzen
 *     einschließlich.
 *   - {@see whereTimestampBetween()} für DATETIME/TIMESTAMP (`start_at`,
 *     `created_at`): halboffenes Intervall `[from 00:00:00, to+1 Tag
 *     00:00:00)` — exakt dieselbe Menge wie `DATE(spalte) BETWEEN from AND to`,
 *     nur ohne Funktion auf der Spalte. Für einseitige Vergleiche liefern
 *     {@see dayStart()}/{@see dayAfter()} dieselben Grenzen.
 *
 * Zeitzonen-Semantik: Die Tagesgrenzen entstehen aus den übergebenen Werten, es
 * wird NICHT umgerechnet — genau wie `whereDate()`, das die gespeicherte
 * (UTC-)Zeit zerlegt. Wer lokale Tagesgrenzen braucht, übergibt sie bereits
 * lokal aufgelöst ({@see \App\Support\Tz::startOfDay()}); eine Umrechnung hier
 * würde bestehende Ergebnismengen verschieben.
 *
 * CommonToolkit hat bewusst keine Entsprechung: dessen `DateHelper` rechnet auf
 * DateTime-Werten, nicht auf Query-Buildern.
 */
final class DateRange {
    /** DATE-Spalte (ohne Zeitanteil), beide Grenzen einschließlich. */
    public static function whereDateBetween(Builder $query, string $column, DateTimeInterface|string $from, DateTimeInterface|string $to): void {
        $query->whereBetween($column, self::days($from, $to));
    }

    /**
     * Dieselbe Regel als Wertepaar — die fließende Form für Ketten:
     * `->whereBetween('date', DateRange::days($from, $to))`.
     *
     * @return array{0: string, 1: string}
     */
    public static function days(DateTimeInterface|string $from, DateTimeInterface|string $to): array {
        return [self::day($from), self::day($to)];
    }

    /** DATETIME-/TIMESTAMP-Spalte: halboffenes Intervall über die Tagesgrenzen. */
    public static function whereTimestampBetween(Builder $query, string $column, DateTimeInterface|string $from, DateTimeInterface|string $to): void {
        $query
            ->where($column, '>=', self::dayStart($from))
            ->where($column, '<', self::dayAfter($to));
    }

    /** Tagesdatum als `Y-m-d` — Vergleichswert für DATE-Spalten. */
    public static function day(DateTimeInterface|string $value): string {
        return self::carbon($value)->format('Y-m-d');
    }

    /** Beginn des Tages als `Y-m-d 00:00:00` — untere Grenze auf DATETIME-Spalten. */
    public static function dayStart(DateTimeInterface|string $value): string {
        return self::carbon($value)->startOfDay()->format('Y-m-d H:i:s');
    }

    /** Beginn des Folgetages — obere, AUSSCHLIESSENDE Grenze auf DATETIME-Spalten. */
    public static function dayAfter(DateTimeInterface|string $value): string {
        return self::carbon($value)->startOfDay()->addDay()->format('Y-m-d H:i:s');
    }

    private static function carbon(DateTimeInterface|string $value): CarbonImmutable {
        return $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value);
    }
}
