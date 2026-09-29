<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PushRetainersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Billing;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Services\Billing\RetainerRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Feature 098 (Retainer-Modus): erzeugt+übergibt die Vormonats-Monatspauschale
 * aller aktiven Retainer-Agreements an das Buchhaltungsprogramm des Kunden.
 * Org-scoped, weil Zugangsdaten je Organisation gelten; idempotent über
 * retainer_invoice_id. Ohne eingerichtetes Programm zählt der Monat als
 * übersprungen (MVP-1027: der Kanal entscheidet, nicht der Befehl).
 */
class PushRetainersCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'customer-billing:push-retainers ' . self::ORGANIZATION_OPTION;

    protected $description = 'Erzeugt die Monatspauschale (Retainer-Modus, Feature 098) und übergibt sie an das Buchhaltungsprogramm';

    public function handle(): int {
        $lock = Cache::lock('customer-billing:push-retainers', 900);
        if (! $lock->get()) {
            $this->warn('Läuft bereits (Lease aktiv) — Abbruch.');

            return self::SUCCESS;
        }

        try {
            $this->forEachOrganization(function (Organization $org): void {
                $result = app(RetainerRunner::class)->runDueForOrganization($org);
                $this->line("Organisation #{$org->id} ({$org->name}): erstellt {$result['created']}, übersprungen {$result['skipped']}, fehlgeschlagen {$result['failed']}");
            });
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
