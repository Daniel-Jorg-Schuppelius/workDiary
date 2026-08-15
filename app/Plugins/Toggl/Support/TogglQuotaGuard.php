<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglQuotaGuard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl\Support;

use APIToolkit\Exceptions\ApiException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Quota-Wächter des Toggl-Exports: Nach 402/429 (Stunden-Quota erschöpft)
 * pausiert die Zustellung je Organisation bis zum Reset — jeder weitere
 * Push würde nur zusätzliche API-Calls für dieselbe Antwort verbrennen
 * (Retry-Sturm der Outbox). Liegengebliebene Create-Einträge holt der
 * stündliche `toggl:push`-Backfill nach Ablauf der Pause nach.
 */
class TogglQuotaGuard {
    private const CACHE_KEY = 'toggl:quota-pause:';

    /** Fallback, wenn die Antwort keine Reset-Angabe trägt (Toggl: 1h-Fenster). */
    private const DEFAULT_PAUSE_SECONDS = 3600;

    /** Pausiert die Zustellung der Org bis zum Quota-Reset; gibt die Dauer zurück. */
    public static function pauseFromException(int $organizationId, Throwable $exception): int {
        $seconds = self::resetSeconds($exception);
        Cache::put(self::CACHE_KEY . $organizationId, true, $seconds);

        return $seconds;
    }

    public static function isPaused(int $organizationId): bool {
        return Cache::has(self::CACHE_KEY . $organizationId);
    }

    public static function clear(int $organizationId): void {
        Cache::forget(self::CACHE_KEY . $organizationId);
    }

    /**
     * Sekunden bis zum Quota-Reset aus den Antwort-Headern
     * (Toggl: `X-Toggl-Quota-Resets-In`, generisch: `Retry-After`) —
     * leicht gepolstert, damit der erste Folge-Push nicht erneut aufläuft.
     * Ohne Antwort im Fehler (z. B. {@see \App\Plugins\Toggl\Exceptions\TogglApiException})
     * greift das volle Toggl-Stundenfenster.
     */
    private static function resetSeconds(Throwable $exception): int {
        $response = $exception instanceof ApiException ? $exception->getResponse() : null;
        if ($response === null) {
            return self::DEFAULT_PAUSE_SECONDS;
        }

        foreach (['X-Toggl-Quota-Resets-In', 'Retry-After'] as $header) {
            $value = trim($response->getHeaderLine($header));
            if ($value !== '' && ctype_digit($value)) {
                return max(60, min((int) $value + 15, 7200));
            }
        }

        return self::DEFAULT_PAUSE_SECONDS;
    }
}
