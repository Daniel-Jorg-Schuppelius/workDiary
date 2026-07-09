<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasConnectionHealth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Setting;
use Carbon\CarbonImmutable;

/**
 * Einheitlicher Verbindungs-Gesundheitszustand (Feature 067, MVP-178):
 * Standard-Spalten last_error/last_error_at/consecutive_failures/
 * disabled_at für Konnektor-Tabellen. Vorbild: chat_webhooks
 * (Auto-Disable) und todoist_connections (last_error).
 *
 * Konnektoren rufen recordConnectionFailure()/recordConnectionSuccess()
 * in ihren Gateways/Jobs; der ExpiryScanner (MVP-057) meldet gestörte
 * Verbindungen als Betriebsaufgabe und löst sie bei Erholung auf.
 */
trait HasConnectionHealth {
    public function recordConnectionFailure(string $error): void {
        $failures = (int) $this->getAttribute('consecutive_failures') + 1;
        $threshold = (int) Setting::get('integrations.auto_disable_threshold', 10);

        $this->forceFill([
            'last_error' => mb_substr($error, 0, 300),
            'last_error_at' => CarbonImmutable::now(),
            'consecutive_failures' => $failures,
            'disabled_at' => $failures >= $threshold
                ? ($this->getAttribute('disabled_at') ?? CarbonImmutable::now())
                : $this->getAttribute('disabled_at'),
        ])->save();
    }

    public function recordConnectionSuccess(): void {
        if ((int) $this->getAttribute('consecutive_failures') === 0
            && $this->getAttribute('last_error') === null) {
            return; // nichts zurückzusetzen — kein Schreib-Spam
        }

        $this->forceFill([
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ])->save();
    }

    public function isConnectionFailing(): bool {
        return $this->getAttribute('disabled_at') !== null
            || $this->getAttribute('last_error') !== null;
    }
}
