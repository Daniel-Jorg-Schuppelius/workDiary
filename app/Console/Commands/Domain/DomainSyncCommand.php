<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Domain;

use App\Enums\Domain\DomainConnectionStatus;
use App\Models\Domain\DomainProviderConnection;
use App\Services\Domain\{DomainAccountingService, DomainSyncService};
use Illuminate\Console\Command;
use Throwable;

/**
 * Budgetierter Portfolio-/Reseller-/Accounting-Abgleich aller aktiven
 * DomainReselling-Verbindungen (Feature 083, MVP-391). Markiert veraltete
 * Projektionen; Reconciliation-Konflikte werden nicht blind überschrieben.
 */
class DomainSyncCommand extends Command {
    protected $signature = 'domain:sync {--connection= : Nur eine Verbindung (ID)}';

    protected $description = 'Gleicht DomainReselling-Portfolio, Reseller und Accounting ab.';

    public function handle(DomainSyncService $sync, DomainAccountingService $accounting): int {
        $query = DomainProviderConnection::query()->where('status', DomainConnectionStatus::Active->value);
        if (($id = $this->option('connection')) !== null) {
            $query->whereKey($id);
        }

        $processed = 0;
        foreach ($query->cursor() as $connection) {
            if (! $connection->isRunnable()) {
                continue;
            }
            try {
                $sync->syncAll($connection);
                $sync->markStale($connection);
                $accounting->sync($connection);
                $connection->recordConnectionSuccess();
                $processed++;
            } catch (Throwable $e) {
                $connection->recordConnectionFailure(class_basename($e));
                $this->warn(sprintf('Verbindung #%d: %s', $connection->id, class_basename($e)));

                // Vollaudit 2026-07 (H12): Syncausfall an die Admins melden
                // (dedupliziert — ein Dauerausfall spammt nicht je Lauf).
                app(\App\Services\Notification\NotificationDispatcher::class)->notify(
                    \App\Enums\Notification\NotificationEvent::DomainSyncFailed,
                    $connection,
                    null,
                    [
                        'title' => (string) __('notification.message.domain_sync_failed_title', ['name' => (string) $connection->name]),
                        'title_key' => 'notification.message.domain_sync_failed_title',
                        'title_params' => ['name' => (string) $connection->name],
                        'message' => class_basename($e),
                        'url' => route('admin.domain-provider.index'),
                    ],
                    dedup: true,
                );
            }
        }

        $this->info(sprintf('%d Verbindung(en) abgeglichen.', $processed));

        return self::SUCCESS;
    }
}
