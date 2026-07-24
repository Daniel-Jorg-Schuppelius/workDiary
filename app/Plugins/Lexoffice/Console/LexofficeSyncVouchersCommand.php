<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeSyncVouchersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeVoucherSync};
use App\Services\Billing\RetainerVoucherReconciler;
use Illuminate\Console\Command;

class LexofficeSyncVouchersCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'lexoffice:sync-vouchers ' . self::ORGANIZATION_OPTION;

    protected $description = 'Synchronisiert Lexoffice-Belege (voucherlist) pro verknüpftem Kontakt in die lokale Tabelle `lexoffice_vouchers`.';

    public function handle(): int {
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            $config = LexofficeConfig::resolve($org->id);
            if (! is_string($config['api_key']) || $config['api_key'] === '') {
                $this->warn("Organisation #{$org->id} ({$org->name}): Lexoffice nicht konfiguriert — übersprungen.");

                continue;
            }
            $this->info("Sync Lexoffice-Belege für Organisation #{$org->id} ({$org->name})...");
            try {
                $result = (new LexofficeVoucherSync($config['api_key'], $config['base_url']))->sync($org);
                $this->line("  Kontakte: {$result['contacts']}, created: {$result['created']}, updated: {$result['updated']}, archived: {$result['archived']}");

                // Feature 098: Retainer-Zahlstatus in den Leistungssaldo spiegeln.
                $retainer = app(RetainerVoucherReconciler::class)->reconcile($org);
                $this->line("  Retainer-Zahlungen: gebucht {$retainer['booked']}, storniert {$retainer['revoked']}");
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
