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
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Plugins\Lexoffice\Services\{LexofficeInvoiceService, LexofficeVoucherSync};
use App\Plugins\Lexoffice\Services\Retainer\LexofficeRetainerVouchers;
use App\Plugins\Support\Console\ChecksPluginSwitch;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class LexofficeSyncVouchersCommand extends Command {
    use ChecksPluginSwitch;
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
            if (! $this->pluginEnabledFor(LexofficePlugin::ID, (int) $org->id)) {
                continue;
            }

            if (! is_string($config['api_key']) || $config['api_key'] === '') {
                $this->warn("Organisation #{$org->id} ({$org->name}): Lexoffice nicht konfiguriert — übersprungen.");

                continue;
            }
            try {
                Cache::lock(LexofficeConfig::apiLockKey((int) $org->id), 1800)->block(LexofficeConfig::API_LOCK_WAIT_SCHEDULED, function () use ($org, $config): void {
                    $this->info("Sync Lexoffice-Belege für Organisation #{$org->id} ({$org->name})...");
                    try {
                        $result = (new LexofficeVoucherSync($config['api_key'], $config['base_url'], $config['request_interval']))->sync($org);
                        $this->line("  Kontakte: {$result['contacts']}, created: {$result['created']}, updated: {$result['updated']}, archived: {$result['archived']}, Positionen: {$result['lines']}");
                        if (isset($result['lines_error'])) {
                            // Positions-Sync (Feature 152) ist nur gemeldet — der Belegsync steht.
                            $this->warn("  Positionen: {$result['lines_error']}");
                        }
                    } catch (\Throwable $e) {
                        $this->error("  Fehler: {$e->getMessage()}");
                    }

                    // Feature 098: Retainer-Zahlstatus in den Leistungssaldo spiegeln —
                    // unabhängig vom Belegsync (Review 2026-09-10, C8: ein Fehler dort
                    // ließ den Abgleich entfallen). Org-Kontext binden und das Service-
                    // Singleton verwerfen — der Netto-Nachschlag am Beleg löst seinen
                    // API-Key sonst über die zuletzt gebundene Organisation auf.
                    try {
                        $retainer = $this->withOrganizationContext($org, function () use ($org): array {
                            app()->forgetInstance(LexofficeInvoiceService::class);

                            return app(LexofficeRetainerVouchers::class)->reconcile($org);
                        });
                        $this->line("  Retainer: gebucht {$retainer['booked']}, storniert {$retainer['revoked']}, neu verknüpft {$retainer['linked']}");
                    } catch (\Throwable $e) {
                        $this->error("  Retainer-Abgleich: {$e->getMessage()}");
                    }
                });
            } catch (LockTimeoutException) {
                $this->warn("Organisation #{$org->id} ({$org->name}): anderer Lexoffice-Lauf blockiert seit 10 Minuten — übersprungen.");
            }
        }

        return self::SUCCESS;
    }
}
