<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginHoursTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Services\Auth\LoginPolicy\LoginHours;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Feature 205 (MVP-1654): Zeitfenster je Wochentag — Grenzen halboffen,
 * Fenster über Mitternacht, „bis 00:00“ = Tagesende, nächste Öffnung,
 * Sommerzeitwechsel.
 */
final class LoginHoursTest extends TestCase {
    private const string TZ = 'Europe/Berlin';

    /** @return array<int, array{from: string, until: string}> */
    private static function weekdays(string $from, string $until): array {
        $hours = [];
        for ($day = 1; $day <= 5; $day++) {
            $hours[$day] = ['from' => $from, 'until' => $until];
        }

        return $hours;
    }

    private static function at(string $local): CarbonImmutable {
        return CarbonImmutable::parse($local, self::TZ);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function officeHours(): iterable {
        // Montag, 16.11.2026.
        yield 'vor Beginn' => ['2026-11-16 06:59', false];
        yield 'Beginn zählt' => ['2026-11-16 07:00', true];
        yield 'letzte Minute' => ['2026-11-16 21:59', true];
        yield 'Ende zählt nicht' => ['2026-11-16 22:00', false];
        yield 'Samstag geschlossen' => ['2026-11-21 10:00', false];
    }

    #[DataProvider('officeHours')]
    public function test_fenster_ist_halboffen(string $local, bool $open): void {
        $this->assertSame($open, LoginHours::isOpen(self::weekdays('07:00', '22:00'), self::at($local)));
    }

    public function test_fenster_ueber_mitternacht_reicht_in_den_folgetag(): void {
        $hours = [1 => ['from' => '22:00', 'until' => '06:00']];

        $this->assertTrue(LoginHours::isOpen($hours, self::at('2026-11-16 23:30')));
        $this->assertTrue(LoginHours::isOpen($hours, self::at('2026-11-17 05:59')));
        $this->assertFalse(LoginHours::isOpen($hours, self::at('2026-11-17 06:00')));
        $this->assertFalse(LoginHours::isOpen($hours, self::at('2026-11-16 05:00')));

        // Sonntag auf Montag: Vortag von Montag ist Sonntag (7).
        $sunday = [7 => ['from' => '20:00', 'until' => '02:00']];
        $this->assertTrue(LoginHours::isOpen($sunday, self::at('2026-11-16 01:00')));
    }

    public function test_bis_mitternacht_und_tagesende(): void {
        $this->assertTrue(LoginHours::isOpen([1 => ['from' => '08:00', 'until' => '00:00']], self::at('2026-11-16 23:59')));
        $this->assertFalse(LoginHours::isOpen([1 => ['from' => '08:00', 'until' => '00:00']], self::at('2026-11-17 00:00')));
        $this->assertTrue(LoginHours::isOpen([1 => ['from' => '00:00', 'until' => '24:00']], self::at('2026-11-16 23:59')));
    }

    public function test_naechste_oeffnung(): void {
        $hours = self::weekdays('07:00', '22:00');

        $this->assertSame('2026-11-17 07:00', LoginHours::nextOpening($hours, self::at('2026-11-16 22:00'))?->format('Y-m-d H:i'));
        $this->assertSame('2026-11-16 07:00', LoginHours::nextOpening($hours, self::at('2026-11-16 05:00'))?->format('Y-m-d H:i'));
        $this->assertSame('2026-11-23 07:00', LoginHours::nextOpening($hours, self::at('2026-11-21 12:00'))?->format('Y-m-d H:i'));
        $this->assertNull(LoginHours::nextOpening([], self::at('2026-11-21 12:00')));
    }

    public function test_sommerzeitwechsel_rechnet_in_ortszeit(): void {
        // 29.03.2026: Uhren springen um 02:00 auf 03:00 (Sonntag).
        $hours = [7 => ['from' => '08:00', 'until' => '12:00']];
        $this->assertTrue(LoginHours::isOpen($hours, self::at('2026-03-29 08:30')));
        $this->assertSame('2026-03-29 08:00:00+02:00', LoginHours::nextOpening($hours, self::at('2026-03-29 03:30'))?->format('Y-m-d H:i:sP'));
    }

    public function test_normalize_verwirft_leere_und_ungueltige_tage(): void {
        $this->assertSame(
            [1 => ['from' => '07:00', 'until' => '18:00'], 6 => ['from' => '22:00', 'until' => '06:00']],
            LoginHours::normalize([
                1 => ['from' => '07:00', 'until' => '18:00'],
                2 => ['from' => '', 'until' => ''],
                3 => ['from' => '09:00', 'until' => '09:00'],
                4 => ['from' => '24:00', 'until' => '10:00'],
                '6' => ['from' => '22:00', 'until' => '06:00'],
                8 => ['from' => '07:00', 'until' => '08:00'],
            ]),
        );
        $this->assertTrue(LoginHours::isValidTime('24:00'));
        $this->assertFalse(LoginHours::isValidTime('24:01'));
        $this->assertFalse(LoginHours::isValidTime('7:00'));
    }
}
