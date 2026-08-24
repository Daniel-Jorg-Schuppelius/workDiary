<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetriesTransientFailures.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Jobs\Concerns;

use App\Plugins\PluginErrorRecorder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Retry-/Timeout-/Fehlerbasis für Wake-/Ingest-/Publish-Jobs (Vollscan
 * 2026-08-23, J7): Zehn Jobs liefen ohne tries/backoff/timeout/failed() — mit
 * der cron.sh-Worker-Variante (queue:work --tries Default 1) landete jeder
 * transiente Fehler (Timeout, 503) sofort endgültig in failed_jobs, und
 * niemand erfuhr davon.
 *
 * Drei Versuche mit wachsendem Abstand, 5 Minuten Laufzeitbudget je Versuch
 * (unter dem retry_after der DB-Queue, 630 s). Nach dem letzten Versuch wird
 * der Fehler protokolliert und — bei Plugin-Jobs mit `pluginId`/`pluginErrorId()`
 * — in die Plugin-Fehler-Inbox geschrieben, damit er im Aufgabencenter sichtbar ist.
 */
trait RetriesTransientFailures {
    public int $tries = 3;

    public int $timeout = 300;

    /** @return list<int> Sekunden zwischen den Versuchen */
    public function backoff(): array {
        return [30, 120, 600];
    }

    /** Sicherheitsnetz der Queue nach Aufbrauchen aller Versuche. */
    public function failed(?Throwable $e): void {
        $context = ['job' => static::class];
        if (property_exists($this, 'organizationId')) {
            $context['organization_id'] = $this->organizationId;
        }

        Log::error('Queue-Job endgültig gescheitert: ' . static::class, $context + ['error' => $e?->getMessage()]);

        $pluginId = $this->pluginErrorId();
        if ($pluginId !== null && $e !== null) {
            try {
                app(PluginErrorRecorder::class)->record(
                    $pluginId,
                    'runtime',
                    $e,
                    $context,
                    property_exists($this, 'organizationId') ? (int) $this->organizationId : null,
                );
            } catch (Throwable $recorderFailure) {
                Log::warning('Plugin-Fehler konnte nicht protokolliert werden.', ['error' => $recorderFailure->getMessage()]);
            }
        }
    }

    /** Plugin-ID für die Fehler-Inbox; Kern-Jobs liefern null. */
    protected function pluginErrorId(): ?string {
        if (property_exists($this, 'pluginId') && is_string($this->pluginId)) {
            return $this->pluginId;
        }

        return null;
    }
}
