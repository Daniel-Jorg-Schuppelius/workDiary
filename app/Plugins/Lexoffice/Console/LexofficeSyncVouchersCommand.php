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
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeInvoiceService, LexofficeVoucherSync};
use App\Services\Billing\RetainerVoucherReconciler;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

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

            // **`enabled` je Organisation prüfen** (Sicherheitsscan
            // 2026-08-23, S-28). Ohne diese Zeile lief der stündliche Sync
            // über ALLE Organisationen — und wenn der Betreiber einen
            // LEXOFFICE_API_KEY in der .env hat, greift der ENV-Fallback:
            // Kontakte, Artikel und Belege des Betreiberkontos landeten in
            // jedem Mandanten.
            if ($config['enabled'] !== true) {
                continue;
            }

            if (! is_string($config['api_key']) || $config['api_key'] === '') {
                $this->warn("Organisation #{$org->id} ({$org->name}): Lexoffice nicht konfiguriert — übersprungen.");

                continue;
            }
            $lock = Cache::lock(LexofficeConfig::apiLockKey($org->id), 1800);
            try {
                $lock->block(600);
            } catch (LockTimeoutException) {
                $this->warn("Organisation #{$org->id} ({$org->name}): anderer Lexoffice-Lauf blockiert seit 10 Minuten — übersprungen.");

                continue;
            }
            $this->info("Sync Lexoffice-Belege für Organisation #{$org->id} ({$org->name})...");
            try {
                $result = (new LexofficeVoucherSync($config['api_key'], $config['base_url']))->sync($org);
                $this->line("  Kontakte: {$result['contacts']}, created: {$result['created']}, updated: {$result['updated']}, archived: {$result['archived']}");

                // Feature 098: Retainer-Zahlstatus in den Leistungssaldo spiegeln.
                // Org-Kontext binden und das Service-Singleton verwerfen — der
                // Netto-Nachschlag am Beleg löst seinen API-Key sonst über die
                // zuletzt gebundene Organisation auf.
                $retainer = $this->withOrganizationContext($org, function () use ($org): array {
                    app()->forgetInstance(LexofficeInvoiceService::class);

                    return app(RetainerVoucherReconciler::class)->reconcile($org);
                });
                $this->line("  Retainer: gebucht {$retainer['booked']}, storniert {$retainer['revoked']}, neu verknüpft {$retainer['linked']}");
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            } finally {
                $lock->release();
            }
        }

        return self::SUCCESS;
    }
}
