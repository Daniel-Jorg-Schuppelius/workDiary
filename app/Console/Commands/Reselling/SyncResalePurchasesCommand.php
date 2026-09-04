<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncResalePurchasesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reselling;

use App\Models\Organization;
use App\Services\Reselling\Register\PurchaseAllocator;
use Illuminate\Console\Command;

/**
 * Domain-Buchungsjournal (083) als Einkaufsbelege der Domain-Abos übernehmen
 * (Feature 152, MVP-762). Täglich nach `resale:sync-domains`; idempotent.
 */
class SyncResalePurchasesCommand extends Command {
    protected $signature = 'resale:sync-purchases {--org= : Nur diese Organisation (ID)}';

    protected $description = 'Domain-Buchungen als Einkaufsbelege ins Reselling-Register übernehmen';

    public function handle(PurchaseAllocator $allocator): int {
        $query = Organization::query();
        if ($this->option('org') !== null) {
            $query->whereKey((int) $this->option('org'));
        }
        foreach ($query->orderBy('id')->get() as $organization) {
            $result = $allocator->syncDomainAccounting($organization);
            if ($result['entries'] === 0 && $result['skipped'] === 0) {
                continue;
            }
            $this->info(sprintf('Organisation #%d (%s): %d Einkaufsbelege neu, %d übersprungen.', $organization->id, $organization->name, $result['entries'], $result['skipped']));
        }

        return self::SUCCESS;
    }
}
