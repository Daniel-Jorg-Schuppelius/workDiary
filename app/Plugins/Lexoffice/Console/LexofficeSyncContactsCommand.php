<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeSyncContactsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Console;

use App\Models\Organization;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeContactSync, LexofficeMatchPolicy};
use Illuminate\Console\Command;

class LexofficeSyncContactsCommand extends Command {
    protected $signature = 'lexoffice:sync-contacts
        {--organization= : ID einer einzelnen Organisation, sonst alle}
        {--policy= : Override für die Match-Policy (lexoffice_wins|local_wins|manual_review)}
        {--create-missing : Lokale Kunden für Remote-Kontakte ohne Match neu anlegen}';

    protected $description = 'Pull-Sync der Lexoffice-Kontakte: matcht remote Kontakte auf lokale Kunden und führt je nach Policy Updates oder Konflikt-Einträge durch.';

    public function handle(LexofficeContactSync $sync): int {
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

            $policyValue = (string) ($this->option('policy') ?: $config['match_policy']);
            $policy = LexofficeMatchPolicy::fromSetting($policyValue);
            $createMissing = (bool) $this->option('create-missing') || $config['create_missing_local'];

            $this->info("Sync Lexoffice-Kontakte für Organisation #{$org->id} ({$org->name}) [policy={$policy->value}]...");
            try {
                $result = $sync->sync($org, $policy, $config['api_key'], $config['base_url'], $createMissing);
                $this->line("  matched: {$result['matched']}, linked: {$result['linked']}, created: {$result['created']}, conflicts: {$result['conflicts']}, updated: {$result['updated']}");
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
