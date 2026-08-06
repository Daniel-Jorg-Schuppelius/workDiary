<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphCalendarImportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Console;

use App\Models\MsgraphConnection;
use App\Plugins\Msgraph\Services\MsgraphCalendarImportService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Zwei-Wege-Kalender-Rückimport (Feature 102, C3): calendarView-Delta aller
 * Verbindungen mit `two_way`-Opt-in → Integrations-Inbox-Fälle (Vorschläge,
 * Konflikte, Lösch-Hinweise). Fehler zählen auf den Verbindungs-Health.
 */
class MsgraphCalendarImportCommand extends Command {
    protected $signature = 'msgraph:calendar-import
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Importiert Änderungen aus dem Microsoft-365-Kalender als Integrations-Inbox-Vorschläge (Zwei-Wege, Opt-in).';

    public function handle(MsgraphCalendarImportService $import): int {
        $orgOption = $this->option('organization');
        $failed = 0;
        $totals = ['proposals' => 0, 'conflicts' => 0, 'deleted' => 0];

        $connections = MsgraphConnection::query()
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
            'Kalender-Rückimport: %d Vorschläge, %d Konflikte, %d Lösch-Hinweise, %d Fehler',
            $totals['proposals'],
            $totals['conflicts'],
            $totals['deleted'],
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
