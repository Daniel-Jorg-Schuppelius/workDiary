<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobRetry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Wiederholung eines fehlgeschlagenen Registry-Jobs (Feature 067, MVP-1619).
 * Der Wiederholungslauf ist ein zweites Scheduler-Ereignis desselben Befehls
 * — kein Queue-Job, damit Laufnachweis, Überlappungssperre und Wartungsfenster
 * gleich bleiben. Der Zustand liegt im Cache (nur Skalare): ein verlorener
 * Eintrag kostet höchstens die Wiederholung, nie den regulären Lauf.
 */
final class JobRetry {
    private const string PREFIX = 'scheduler.retry:';

    /** Regulärer Lauf fehlgeschlagen: erster Versuch nach der Wartezeit. */
    public static function afterFailure(string $jobKey, int $maxAttempts, int $delayMinutes): void {
        if ($maxAttempts < 1) {
            return;
        }
        self::plan($jobKey, 1, $delayMinutes);
    }

    /** Wiederholung fehlgeschlagen: nächster Versuch oder aufgeben. */
    public static function afterRetryFailure(string $jobKey, int $maxAttempts, int $delayMinutes): void {
        $attempt = self::attempt($jobKey) + 1;
        if ($attempt > $maxAttempts) {
            self::clear($jobKey);

            return;
        }
        self::plan($jobKey, $attempt, $delayMinutes);
    }

    public static function isDue(string $jobKey): bool {
        $state = Cache::get(self::PREFIX . $jobKey);
        $due = is_array($state) ? ($state['due_at'] ?? null) : null;

        return is_int($due) && $due <= CarbonImmutable::now()->getTimestamp();
    }

    /** Beim Start der Wiederholung: nicht im nächsten Takt erneut fällig. */
    public static function claim(string $jobKey): void {
        Cache::put(self::PREFIX . $jobKey, ['attempt' => self::attempt($jobKey), 'due_at' => null], now()->addDay());
    }

    public static function clear(string $jobKey): void {
        Cache::forget(self::PREFIX . $jobKey);
    }

    public static function attempt(string $jobKey): int {
        $state = Cache::get(self::PREFIX . $jobKey);

        return is_array($state) && is_int($state['attempt'] ?? null) ? $state['attempt'] : 0;
    }

    private static function plan(string $jobKey, int $attempt, int $delayMinutes): void {
        Cache::put(self::PREFIX . $jobKey, [
            'attempt' => $attempt,
            'due_at' => CarbonImmutable::now()->addMinutes(max(1, $delayMinutes))->getTimestamp(),
        ], now()->addDay());
    }
}
