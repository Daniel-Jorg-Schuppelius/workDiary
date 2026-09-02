<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DateRangeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Support;

use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor;
use Illuminate\Database\SQLiteConnection;
use PHPUnit\Framework\TestCase;

/**
 * MVP-722 (Vollscan 2026-08-23, A8): Der Helfer muss dieselbe Menge treffen
 * wie `whereDate()`, aber ohne Funktion auf der Spalte — sonst wäre die
 * Umstellung eine stille Ergebnisänderung.
 */
class DateRangeTest extends TestCase {
    private function builder(): Builder {
        $connection = new SQLiteConnection(new \PDO('sqlite::memory:'));

        return new Builder($connection, new MySqlGrammar($connection), new MySqlProcessor());
    }

    public function test_date_column_range_compares_the_column_directly(): void {
        $query = $this->builder()->from('accounting_entries');
        DateRange::whereDateBetween($query, 'booked_on', '2026-01-01', '2026-12-31');

        $this->assertStringNotContainsString('date(', strtolower($query->toSql()));
        $this->assertStringContainsString('`booked_on` between ? and ?', $query->toSql());
        // Obergrenze als Tagesende: hält den Grenztag auch dann in der Menge,
        // wenn der date-Cast auf SQLite `Y-m-d 00:00:00` gespeichert hat.
        $this->assertSame(['2026-01-01', '2026-12-31 23:59:59'], $query->getBindings());
    }

    /**
     * Halboffenes Intervall: `DATE(start_at) <= '2026-01-31'` ist dieselbe
     * Menge wie `start_at < '2026-02-01 00:00:00'` — die letzte Sekunde des
     * Tages darf nicht herausfallen.
     */
    public function test_timestamp_column_uses_a_half_open_interval(): void {
        $query = $this->builder()->from('diary_entries');
        DateRange::whereTimestampBetween($query, 'start_at', '2026-01-01', '2026-01-31');

        $this->assertStringNotContainsString('date(', strtolower($query->toSql()));
        $this->assertSame(['2026-01-01 00:00:00', '2026-02-01 00:00:00'], $query->getBindings());
    }

    public function test_boundaries_accept_strings_and_date_objects(): void {
        $this->assertSame('2026-03-07', DateRange::day(CarbonImmutable::parse('2026-03-07 23:59:59')));
        $this->assertSame('2026-03-07 00:00:00', DateRange::dayStart('2026-03-07 18:15:00'));
        $this->assertSame('2026-03-08 00:00:00', DateRange::dayAfter('2026-03-07 18:15:00'));
        $this->assertSame(['2026-03-01', '2026-03-31 23:59:59'], DateRange::days('2026-03-01', CarbonImmutable::parse('2026-03-31')));
        $this->assertSame('2026-03-31 23:59:59', DateRange::dayEnd(CarbonImmutable::parse('2026-03-31 08:30:00')));
    }

    /** Monats-/Jahreswechsel: die obere Grenze rutscht korrekt weiter. */
    public function test_day_after_crosses_month_and_year_boundaries(): void {
        $this->assertSame('2026-02-01 00:00:00', DateRange::dayAfter('2026-01-31'));
        $this->assertSame('2027-01-01 00:00:00', DateRange::dayAfter('2026-12-31'));
        $this->assertSame('2028-02-29 00:00:00', DateRange::dayAfter('2028-02-28'));
    }
}
