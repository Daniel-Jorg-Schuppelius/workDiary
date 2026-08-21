<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeWakeCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CloudIntake\CloudDocumentConnection;
use App\Services\CloudIntake\{CloudIntakeRunner, IntakeWakeSignal};
use Illuminate\Console\Command;

/**
 * Aufweck-Spur des Cloud-Dokumenteingangs (Feature 080).
 *
 * Die Provider-Webhooks (Dropbox, Google Drive, Microsoft Graph) setzen je
 * betroffener Verbindung ein Aufweck-Flag. Bis MVP-613 hat dieses Flag
 * NIEMAND gelesen: `IntakeWakeSignal::consume()` kam ausschließlich in Tests
 * vor, und der reguläre Lauf (`cloud-intake:sync`, alle 15 Minuten) fragt es
 * nicht ab. Die Webhooks haben also nichts beschleunigt.
 *
 * Dieser Lauf schließt die Lücke: Er zieht die gesetzten Flags und lässt
 * genau die betroffenen Verbindungen sofort laufen. Er ersetzt den regulären
 * Lauf nicht — das Flag ist verlusttolerant, und ein verlorener Webhook darf
 * keinen Beleg kosten.
 */
class CloudIntakeWakeCommand extends Command {
    protected $signature = 'cloud-intake:wake {--organization= : Nur diese Organisation (ID)}';

    protected $description = 'Cloud-Dokumenteingang: durch Webhook geweckte Verbindungen sofort abrufen';

    public function handle(CloudIntakeRunner $runner, IntakeWakeSignal $wake): int {
        $connections = CloudDocumentConnection::query()
            ->withoutGlobalScopes()
            ->when($this->option('organization') !== null, fn ($q) => $q->where('organization_id', (int) $this->option('organization')))
            ->orderBy('id')
            ->get();

        $woken = 0;
        $failed = 0;

        foreach ($connections as $connection) {
            // consume() ist bewusst destruktiv: Das Flag gilt einmal. Bleibt
            // es stehen, liefe dieselbe Verbindung im Minutentakt.
            if (! $wake->consume((int) $connection->id)) {
                continue;
            }
            $woken++;

            $result = $runner->run($connection);
            if (in_array($result['status'], ['not_runnable', 'locked'], true)) {
                continue;
            }

            $this->line(sprintf(
                '#%d %s: %s — importiert %d, Inbox %d',
                $connection->id,
                $connection->name,
                $result['status'],
                $result['imported'],
                $result['inbox'],
            ));

            if ($result['status'] !== 'ok') {
                $failed++;
            }
        }

        $this->info(sprintf('Aufgeweckte Verbindungen: %d, davon fehlerhaft: %d', $woken, $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
