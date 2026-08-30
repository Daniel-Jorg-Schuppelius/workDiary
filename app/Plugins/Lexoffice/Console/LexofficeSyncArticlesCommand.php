<?php
/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeSyncArticlesCommand.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace App\Plugins\Lexoffice\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Plugins\Lexoffice\{LexofficeArticleSync, LexofficeConfig};
use Illuminate\Console\Command;

class LexofficeSyncArticlesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'lexoffice:sync-articles ' . self::ORGANIZATION_OPTION;

    protected $description = 'Synchronisiert Lexoffice-Artikel (Services/Produkte) in die lokale Tabelle `lexoffice_articles`.';

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
            $this->info("Sync Lexoffice-Artikel für Organisation #{$org->id} ({$org->name})...");
            try {
                $result = (new LexofficeArticleSync($config['api_key'], $config['base_url']))->sync($org);
                $this->line("  created: {$result['created']}, updated: {$result['updated']}, archived: {$result['archived']}, conflicts: {$result['conflicts']}");
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
