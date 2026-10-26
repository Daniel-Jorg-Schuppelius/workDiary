<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainRefreshDetailsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Domain;

use App\Enums\Domain\DomainConnectionStatus;
use App\Models\Domain\{DomainProjection, DomainProviderConnection};
use App\Services\Domain\DomainSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Detailabgleich der Domains mit Budget (Feature 185, MVP-1407): der
 * Listen-Sync liefert weder Registrierungsdatum noch Verlängerungspreis —
 * beides kommt nur aus `StatusDomain` je Domain. Je Lauf die N Projektionen
 * mit fehlenden Detailfeldern, danach die mit dem ältesten Stand; Fehler je
 * Domain werden gezählt, nicht hochgereicht. Täglich vor `resale:sync-domains`.
 */
class DomainRefreshDetailsCommand extends Command {
    protected $signature = 'domain:refresh-details {--limit=40 : Domains je Lauf} {--connection= : Nur eine Verbindung (ID)}';

    protected $description = 'Registrierungsdatum, Preis und Kontakt je Domain über StatusDomain nachladen (Budget je Lauf)';

    public function handle(DomainSyncService $sync): int {
        $limit = max(1, (int) $this->option('limit'));
        $connections = DomainProviderConnection::query()->where('status', DomainConnectionStatus::Active->value);
        if (($id = $this->option('connection')) !== null) {
            $connections->whereKey($id);
        }
        $connectionIds = [];
        foreach ($connections->get() as $connection) {
            if ($connection->isRunnable()) {
                $connectionIds[] = $connection->id;
            }
        }
        if ($connectionIds === []) {
            $this->line('Keine laufende Domain-Verbindung.');

            return self::SUCCESS;
        }

        // Fehlende Detailfelder zuerst, dann der älteste Stand.
        $projections = DomainProjection::query()->withoutGlobalScopes()
            ->whereIn('connection_id', $connectionIds)
            ->orderByRaw('CASE WHEN registration_at IS NULL OR renewal_price IS NULL THEN 0 ELSE 1 END')
            ->orderBy('synced_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $refreshed = 0;
        $failed = 0;
        foreach ($projections as $projection) {
            try {
                $sync->refreshDomain($projection);
                $refreshed++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('domain:refresh-details: Detailabgleich fehlgeschlagen', ['domain' => $projection->external_domain, 'exception' => $e::class, 'message' => $e->getMessage()]);
                $this->warn(sprintf('%s: %s', $projection->external_domain, class_basename($e)));
            }
        }
        $this->info(sprintf('%d Domains nachgeladen, %d fehlgeschlagen (Budget %d).', $refreshed, $failed, $limit));

        return self::SUCCESS;
    }
}
