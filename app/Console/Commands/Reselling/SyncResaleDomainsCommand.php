<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncResaleDomainsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reselling;

use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Organization;
use App\Models\Reselling\ResaleSubscription;
use App\Services\Reselling\Register\DomainSubscriptionSync;
use Illuminate\Console\Command;

/**
 * Domain-Projektionen (Feature 083) als Domain-Abos ins Reselling-Register
 * spiegeln (Feature 152, MVP-763). Täglich nach `domain:sync`; idempotent.
 */
class SyncResaleDomainsCommand extends Command {
    protected $signature = 'resale:sync-domains {--org= : Nur diese Organisation (ID)}';

    protected $description = 'Domains aus der Domainverwaltung als Abos ins Reselling-Register übernehmen';

    public function handle(DomainSubscriptionSync $sync): int {
        $query = Organization::query();
        if ($this->option('org') !== null) {
            $query->whereKey((int) $this->option('org'));
        }
        foreach ($query->orderBy('id')->get() as $organization) {
            $result = $sync->sync($organization);
            if ($result['domains'] === 0 && $result['ended'] === 0) {
                // Ohne Projektionen beendet der Sync nichts (Review 2026-09-10, B6) — nur melden, wenn es Domain-Abos gibt.
                if ($result['skipped_gone'] && ResaleSubscription::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->where('provider', SubscriptionProvider::DomainReselling->value)->exists()) {
                    $this->warn(sprintf('Organisation #%d (%s): keine Domain-Projektionen — Domain-Abos bleiben unverändert.', $organization->id, $organization->name));
                }

                continue;
            }
            $this->info(sprintf('Organisation #%d (%s): %d Domains, %d neu, %d geändert, %d unverändert, %d beendet.', $organization->id, $organization->name, $result['domains'], $result['created'], $result['updated'], $result['unchanged'], $result['ended']));
        }

        return self::SUCCESS;
    }
}
