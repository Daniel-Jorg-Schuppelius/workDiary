<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityEventsPending.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling\Guards;

use App\Models\Auth\SecurityEvent;
use App\Scheduling\Contracts\JobGuard;

/** Sicherheitsereignisse (security:evaluate): nur mit Ereignissen im größten Schwellwert-Fenster oder mit Ereignissen jenseits der Aufbewahrung (der Befehl räumt sie ab). */
final class SecurityEventsPending implements JobGuard {
    public function shouldRun(): bool {
        $windows = array_map(
            static fn(array $rule): int => max(1, (int) ($rule['window_minutes'] ?? 1)),
            (array) config('security.events.thresholds', []),
        );
        $since = now()->subMinutes($windows === [] ? 60 : max($windows));
        $retention = now()->subDays(max(1, (int) config('security.events.retention_days', 90)));

        return SecurityEvent::query()->where('occurred_at', '>=', $since)->exists()
            || SecurityEvent::query()->where('occurred_at', '<', $retention)->exists();
    }
}
