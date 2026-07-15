<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Models\CloudIntake\CloudDocumentConnection;
use App\Services\CloudIntake\CloudIntakeRunner;
use Illuminate\Console\Command;

/**
 * Delta-Lauf des Cloud-Dokumenteingangs (Feature 080, MVP-359): verarbeitet
 * alle lauffähigen Verbindungen (aktiv + mindestens eine aktive Route) mit
 * dem budgetierten {@see CloudIntakeRunner}. Webhook-Aufwecksignale ziehen
 * keine Sonderlogik — sie sorgen nur dafür, dass dieser Lauf zeitnah
 * angestoßen wird; das Lease im Runner verhindert Parallel-Läufe.
 */
class CloudIntakeSyncCommand extends Command {
    protected $signature = 'cloud-intake:sync {--organization= : Nur diese Organisation (ID)} {--connection= : Nur diese Verbindung (ID)}';

    protected $description = 'Cloud-Dokumenteingang: Delta-Läufe aller lauffähigen Verbindungen ausführen';

    public function handle(CloudIntakeRunner $runner): int {
        $connections = CloudDocumentConnection::query()
            ->withoutGlobalScopes()
            ->when($this->option('organization') !== null, fn ($q) => $q->where('organization_id', (int) $this->option('organization')))
            ->when($this->option('connection') !== null, fn ($q) => $q->where('id', (int) $this->option('connection')))
            ->orderBy('id')
            ->get();

        $failed = 0;
        foreach ($connections as $connection) {
            $result = $runner->run($connection);

            if (in_array($result['status'], ['not_runnable', 'locked'], true)) {
                continue;
            }

            $this->line(sprintf(
                '#%d %s: %s — Seiten %d, importiert %d, Inbox %d, Dubletten %d, abgelehnt %d, Tombstones %d',
                $connection->id,
                $connection->name,
                $result['status'],
                $result['pages'],
                $result['imported'],
                $result['inbox'],
                $result['duplicates'],
                $result['rejected'],
                $result['tombstones'],
            ));

            if ($result['status'] !== 'ok') {
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
