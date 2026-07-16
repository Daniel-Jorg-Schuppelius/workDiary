<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainEventsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Domain;

use App\Enums\Domain\DomainConnectionStatus;
use App\Models\Domain\DomainProviderConnection;
use App\Services\Domain\DomainEventPollingService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ereignis-Polling aller aktiven DomainReselling-Verbindungen (Feature 083,
 * MVP-391). Jedes Ereignis wird dauerhaft gespeichert, BEVOR es über
 * `DeleteEvent` quittiert wird.
 */
class DomainEventsCommand extends Command {
    protected $signature = 'domain:events {--limit=100 : Ereignisse je Verbindung}';

    protected $description = 'Pollt DomainReselling-Ereignisse (Durable Store vor Acknowledge).';

    public function handle(DomainEventPollingService $events): int {
        $connections = DomainProviderConnection::query()
            ->where('status', DomainConnectionStatus::Active->value)
            ->cursor();

        $stored = 0;
        $acked = 0;
        foreach ($connections as $connection) {
            if (! $connection->isRunnable()) {
                continue;
            }
            try {
                $result = $events->poll($connection, (int) $this->option('limit'));
                $stored += $result['stored'];
                $acked += $result['acknowledged'];
            } catch (Throwable $e) {
                $this->warn(sprintf('Verbindung #%d: %s', $connection->id, class_basename($e)));
            }
        }

        $this->info(sprintf('%d neue Ereignisse gespeichert, %d quittiert.', $stored, $acked));

        return self::SUCCESS;
    }
}
