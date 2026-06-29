<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncOwnerVouchersJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Jobs;

use App\Models\{Customer, Supplier};
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeVoucherSync};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUnique, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Beleg-Sync EINES Kunden/Lieferanten (ein Lexoffice-Kontakt) im Hintergrund.
 *
 * Bewusst klein gehalten: Der Voll-Sync wird von {@see SyncVouchersJob} in viele
 * dieser Einzeljobs aufgeteilt. Jeder ruft nur die Belege EINES Kontakts ab und
 * ist damit in Sekunden fertig — deutlich unter dem Queue-`retry_after` (90s)
 * und dem `queue:work --max-time` (55s) des Cron-Workers. So kann ein Voll-Sync
 * über viele Worker-Durchläufe abgearbeitet werden, ohne dass ein langer Job
 * abgebrochen oder (wegen retry_after) doppelt gestartet wird.
 */
class SyncOwnerVouchersJob implements ShouldBeUnique, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Unter retry_after (90s) halten → kein Doppellauf durch Re-Reservierung. */
    public int $timeout = 60;

    public int $tries = 2;

    /** @param 'customer'|'supplier' $kind */
    public function __construct(
        public readonly int $organizationId,
        public readonly string $kind,
        public readonly int $ownerId,
    ) {}

    public function uniqueId(): string {
        return "{$this->organizationId}:{$this->kind}:{$this->ownerId}";
    }

    public function handle(): void {
        $config = LexofficeConfig::resolve($this->organizationId);
        if (! is_string($config['api_key']) || $config['api_key'] === '') {
            return;
        }

        $owner = $this->kind === 'supplier'
            ? Supplier::query()->find($this->ownerId)
            : Customer::query()->find($this->ownerId);

        if ($owner === null || (int) $owner->organization_id !== $this->organizationId) {
            return;
        }

        (new LexofficeVoucherSync($config['api_key'], $config['base_url']))->syncFor($owner);
    }
}
