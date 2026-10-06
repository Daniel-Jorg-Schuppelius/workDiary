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
use App\Plugins\Lexoffice\Enums\LexofficeMatchPolicy;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Plugins\Lexoffice\Services\LexofficeArticleSync;
use App\Plugins\Support\Console\ChecksPluginSwitch;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class LexofficeSyncArticlesCommand extends Command {
    use ChecksPluginSwitch;
    use IteratesOrganizations;

    protected $signature = 'lexoffice:sync-articles ' . self::ORGANIZATION_OPTION . '
        {--policy= : Override für die Konflikt-Strategie (lexoffice_wins|local_wins|manual_review), sonst die Plugin-Einstellung}';

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
            if (! $this->pluginEnabledFor(LexofficePlugin::ID, (int) $org->id)) {
                continue;
            }

            if (! is_string($config['api_key']) || $config['api_key'] === '') {
                $this->warn("Organisation #{$org->id} ({$org->name}): Lexoffice nicht konfiguriert — übersprungen.");

                continue;
            }
            // Die Konflikt-Strategie der Plugin-Einstellung gilt auch für Artikel (Entscheidung 2026-10-06).
            $policy = LexofficeMatchPolicy::fromSetting((string) ($this->option('policy') ?: $config['match_policy']));

            try {
                Cache::lock(LexofficeConfig::apiLockKey((int) $org->id), 1800)->block(LexofficeConfig::API_LOCK_WAIT_SCHEDULED, function () use ($org, $config, $policy): void {
                    $this->info("Sync Lexoffice-Artikel für Organisation #{$org->id} ({$org->name}) [policy={$policy->value}]...");
                    try {
                        $result = (new LexofficeArticleSync($config['api_key'], $config['base_url'], $config['request_interval']))->withPolicy($policy)->sync($org);
                        $this->line("  created: {$result['created']}, updated: {$result['updated']}, archived: {$result['archived']}, conflicts: {$result['conflicts']}");
                    } catch (\Throwable $e) {
                        $this->error("  Fehler: {$e->getMessage()}");
                    }
                });
            } catch (LockTimeoutException) {
                $this->warn("Organisation #{$org->id} ({$org->name}): anderer Lexoffice-Lauf blockiert seit 10 Minuten — übersprungen.");
            }
        }

        return self::SUCCESS;
    }
}
