<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncVouchersJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Jobs;

use App\Models\{Customer, ExternalReference, Supplier};
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUnique, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Voll-Sync der Lexoffice-Belege EINER Organisation — als Fan-out.
 *
 * Statt alle verknüpften Kontakte in EINEM langen Job abzuarbeiten (würde bei
 * vielen Kontakten das Queue-`retry_after` von 90s überschreiten → Doppelläufe),
 * reiht dieser Job pro Owner einen kurzen {@see SyncOwnerVouchersJob} ein. Der
 * Dispatcher selbst ist in Sekunden fertig; die Einzeljobs verteilen sich über
 * die kurzen Worker-Fenster des Cron-Workers (`queue:work --max-time=55`).
 *
 * {@see ShouldBeUnique} verhindert, dass paralleler Klick/Cron denselben
 * Org-Fan-out gleichzeitig doppelt anstößt.
 */
class SyncVouchersJob implements ShouldBeUnique, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Reines Einreihen — unter retry_after (90s) bleiben. */
    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(public readonly int $organizationId) {}

    public function uniqueId(): string {
        return (string) $this->organizationId;
    }

    public function handle(): void {
        $config = LexofficeConfig::resolve($this->organizationId);
        if (! is_string($config['api_key']) || $config['api_key'] === '') {
            return; // Org nicht konfiguriert → nichts zu tun
        }

        $customerMorph = (new Customer)->getMorphClass();
        $supplierMorph = (new Supplier)->getMorphClass();

        ExternalReference::query()
            ->forPlugin($this->organizationId, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->whereIn('referenceable_type', [$customerMorph, $supplierMorph])
            ->select('referenceable_type', 'referenceable_id')
            ->distinct()
            ->cursor()
            ->each(function (ExternalReference $ref) use ($customerMorph): void {
                $kind = $ref->referenceable_type === $customerMorph ? 'customer' : 'supplier';
                SyncOwnerVouchersJob::dispatch($this->organizationId, $kind, (int) $ref->referenceable_id);
            });

        // Zuletzt eingereiht ⇒ läuft hinter den Einzeljobs: der Retainer-
        // Zahlstatus (Feature 098) ist damit direkt nach dem Sync aktuell.
        ReconcileRetainersJob::dispatch($this->organizationId);
    }
}
