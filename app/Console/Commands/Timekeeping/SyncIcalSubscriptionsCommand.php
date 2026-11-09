<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncIcalSubscriptionsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Timekeeping;

use App\Models\Time\IcalSubscription;
use App\Services\Timekeeping\IcalSubscriptionSync;
use App\Support\Sqid;
use Illuminate\Console\Command;

/**
 * Liest alle aktiven iCal-Abos (MVP-1516); für den Scheduler stündlich.
 */
class SyncIcalSubscriptionsCommand extends Command {
    protected $signature = 'ical:sync
        {--subscription= : Nur dieses Abo (Kennung aus der Abo-Liste)}';

    protected $description = 'Liest abonnierte iCal-Kalender inkrementell in die Zeiterfassung (Löschungen der Quelle als Hinweis).';

    public function handle(IcalSubscriptionSync $sync): int {
        $query = IcalSubscription::query()->withoutGlobalScopes()->where('is_active', true)->orderBy('id');
        $only = (string) $this->option('subscription');
        if ($only !== '') {
            $query->whereKey(Sqid::decodeOrNumeric(IcalSubscription::class, $only) ?? 0);
        }

        $totals = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'adopted' => 0, 'staged' => 0, 'failed' => 0, 'removed' => 0];
        $failedSources = 0;
        foreach ($query->cursor() as $subscription) {
            foreach ($sync->sync($subscription) as $key => $count) {
                $totals[$key] += $count;
            }
            if ($subscription->last_status === \App\Enums\Import\IcalSyncStatus::Failed) {
                $failedSources++;
            }
        }

        $this->info(sprintf(
            'iCal-Abos: %d neu, %d aktualisiert, %d unverändert, %d übernommen, %d in der Inbox, %d fehlerhaft, %d in der Quelle entfernt; %d Quellen nicht lesbar.',
            $totals['created'], $totals['updated'], $totals['unchanged'], $totals['adopted'], $totals['staged'], $totals['failed'], $totals['removed'], $failedSources,
        ));

        return self::SUCCESS;
    }
}
