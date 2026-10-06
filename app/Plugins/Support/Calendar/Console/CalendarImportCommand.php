<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarImportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar\Console;

use App\Plugins\Support\Calendar\RemoteCalendarConnection;
use App\Plugins\Support\Console\ChecksPluginSwitch;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Gemeinsamer Rückimport-Lauf der Kalender-Plugins (Konsolidierungs-Audit
 * 2026-10, k2-05 — die drei Kommandos waren bis auf Plugin, Dienst und
 * Verbindungsmodell gleich): Delta aller Anbindungen mit `two_way`-Opt-in →
 * Integrations-Inbox-Fälle. Fehler zählen auf die Verbindungs-Gesundheit.
 * Signatur und Beschreibung bleiben bei den Plugin-Ableitungen.
 *
 * @template TConnection of Model&RemoteCalendarConnection
 */
abstract class CalendarImportCommand extends Command {
    use ChecksPluginSwitch;

    /** Plugin-ID für die Schalterprüfung. */
    abstract protected function pluginId(): string;

    /** @return class-string<TConnection> */
    abstract protected function connectionModel(): string;

    /**
     * @param  TConnection  $connection
     * @return array{proposals: int, conflicts: int, deleted: int}
     */
    abstract protected function import(RemoteCalendarConnection $connection): array;

    /** Anfang der Ergebniszeile, z. B. „CalDAV-Rückimport“. */
    abstract protected function label(): string;

    public function handle(): int {
        $orgOption = $this->option('organization');
        $failed = 0;
        $totals = ['proposals' => 0, 'conflicts' => 0, 'deleted' => 0];

        $connections = $this->connectionModel()::query()
            ->withoutGlobalScopes()
            ->where('two_way', true)
            ->when(is_numeric($orgOption), fn ($q) => $q->where('organization_id', (int) $orgOption))
            ->get();

        foreach ($connections as $connection) {
            if (! $this->pluginEnabledFor($this->pluginId(), $connection->organizationId())) {
                continue;
            }
            try {
                $result = $this->import($connection);
                foreach ($totals as $key => $value) {
                    $totals[$key] = $value + $result[$key];
                }
                $connection->recordConnectionSuccess();
            } catch (Throwable $e) {
                $failed++;
                $connection->recordConnectionFailure(class_basename($e));
            }
        }

        $this->info(sprintf(
            '%s: %d Vorschläge, %d Konflikte, %d Lösch-Hinweise, %d Fehler',
            $this->label(),
            $totals['proposals'],
            $totals['conflicts'],
            $totals['deleted'],
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
