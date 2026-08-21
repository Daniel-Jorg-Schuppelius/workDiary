<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarImportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\GoogleCalendar\Console;

use App\Models\GoogleCalendarConnection;
use App\Plugins\GoogleCalendar\Services\GoogleCalendarImportService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Kalender-Rückimport Google (Feature 121, MVP-610a): Änderungsliste aller
 * Verbindungen mit `two_way`-Opt-in → Integrations-Inbox-Fälle. Fehler zählen
 * auf die Verbindungs-Gesundheit (Auto-Disable ab Schwellwert).
 */
class GoogleCalendarImportCommand extends Command {
    protected $signature = 'google-calendar:import
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Importiert Änderungen aus dem Google-Kalender als Integrations-Inbox-Vorschläge (Zwei-Wege, Opt-in).';

    public function handle(GoogleCalendarImportService $import): int {
        $orgOption = $this->option('organization');
        $failed = 0;
        $totals = ['proposals' => 0, 'conflicts' => 0, 'deleted' => 0];

        $connections = GoogleCalendarConnection::query()
            ->withoutGlobalScopes()
            ->where('two_way', true)
            ->when(is_numeric($orgOption), fn ($q) => $q->where('organization_id', (int) $orgOption))
            ->get();

        foreach ($connections as $connection) {
            try {
                $result = $import->run($connection);
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
            'Google-Kalender-Rückimport: %d Vorschläge, %d Konflikte, %d Lösch-Hinweise, %d Fehler',
            $totals['proposals'],
            $totals['conflicts'],
            $totals['deleted'],
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
