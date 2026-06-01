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

use App\Models\Organization;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeVoucherSync};
use Illuminate\Console\Command;

class LexofficeSyncVouchersCommand extends Command {
    protected $signature = 'lexoffice:sync-vouchers {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Synchronisiert Lexoffice-Belege (voucherlist) pro verknüpftem Kontakt in die lokale Tabelle `lexoffice_vouchers`.';

    public function handle(): int {
        $orgId = $this->option('organization');
        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        $organizations = $query->get();
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
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
