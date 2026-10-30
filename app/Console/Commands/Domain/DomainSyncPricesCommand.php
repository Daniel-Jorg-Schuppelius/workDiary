<?php
/*
 * Created on   : Fri Oct 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainSyncPricesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Domain;

use App\Enums\Domain\DomainConnectionStatus;
use App\Models\Domain\DomainProviderConnection;
use App\Services\Domain\DomainPriceSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Preisstaffeln des Domainanbieters in den Preiskatalog (Feature 185): täglich nach
 * `domain:sync` und vor `resale:sync-domains`, damit die Domain-Abos ihr Soll aus
 * den Benutzerklassen des Portals bekommen.
 */
class DomainSyncPricesCommand extends Command {
    protected $signature = 'domain:sync-prices {--connection= : Nur eine Verbindung (ID)}';

    protected $description = 'Preisstaffeln des Domainanbieters (Einkauf und Benutzerklassen je Zone) in den Preiskatalog spiegeln';

    public function handle(DomainPriceSyncService $sync): int {
        $query = DomainProviderConnection::query()->where('status', DomainConnectionStatus::Active->value)->orderBy('id');
        if (($id = $this->option('connection')) !== null) {
            $query->whereKey($id);
        }
        $connections = $query->get()->filter(static fn (DomainProviderConnection $connection): bool => $connection->isRunnable());
        if ($connections->isEmpty()) {
            $this->line('Keine laufende Domain-Verbindung.');

            return self::SUCCESS;
        }
        $failed = false;
        foreach ($connections as $connection) {
            try {
                $result = $sync->sync($connection);
                $this->info(sprintf(
                    'Verbindung #%d %s: %d Zonen, Klassen %s — %d Zeilen neu, %d geändert, %d unverändert.',
                    $connection->id,
                    (string) $connection->name,
                    $result['zones'],
                    $result['classes'] === [] ? '—' : implode(', ', $result['classes']),
                    $result['created'],
                    $result['updated'],
                    $result['unchanged'],
                ));
            } catch (Throwable $e) {
                $failed = true;
                Log::warning('domain:sync-prices: Preisabgleich fehlgeschlagen', ['connection' => $connection->id, 'exception' => $e::class, 'message' => $e->getMessage()]);
                $this->warn(sprintf('Verbindung #%d: %s — %s', $connection->id, class_basename($e), $e->getMessage()));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
