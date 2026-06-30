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
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeContactSync, LexofficeMatchPolicy, LexofficeNumberAuthority};
use Illuminate\Console\Command;

class LexofficeSyncContactsCommand extends Command {
    protected $signature = 'lexoffice:sync-contacts
        {--organization= : ID einer einzelnen Organisation, sonst alle}
        {--policy= : Override für die Match-Policy (lexoffice_wins|local_wins|manual_review)}
        {--only=both : Welche Rollen synchronisiert werden (both|customers|suppliers)}
        {--create-missing : Lokale Kunden/Lieferanten für Remote-Kontakte ohne Match neu anlegen}
        {--stage-unmatched : Remote-Kontakte ohne Match nicht verwerfen, sondern als Unmatched-Eintrag in die Zuordnungs-Inbox stellen (zum Sichten/Mergen vor dem Pull). Wirkt nur ohne --create-missing.}';

    protected $description = 'Pull-Sync der Lexoffice-Kontakte: matcht remote Kontakte rollen-bewusst auf lokale Kunden (customer) bzw. Lieferanten (vendor) und führt je nach Policy Updates oder Konflikt-Einträge durch.';

    public function handle(LexofficeContactSync $sync, LexofficeNumberAuthority $numberAuthority): int {
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
            $stageUnmatched = (bool) $this->option('stage-unmatched');
            // Staging hat Vorrang: ist es aktiv, neutralisiert es das in der
            // Plugin-Config gesetzte create_missing_local (fehlende Kontakte
            // werden zum Mergen in die Inbox gestellt statt blind angelegt).
            // Explizites CLI --create-missing bleibt davon unberührt.
            $createMissing = (bool) $this->option('create-missing') || ($config['create_missing_local'] && ! $stageUnmatched);
            $only = (string) ($this->option('only') ?: 'both');
            if (! in_array($only, ['both', 'customers', 'suppliers'], true)) {
                $only = 'both';
            }

            // Nummernkreis-Hoheit gemäß Plugin-Einstellung an die Org übertragen.
            $numberAuthority->apply($org, (bool) $config['number_authority']);

            $this->info("Sync Lexoffice-Kontakte für Organisation #{$org->id} ({$org->name}) [policy={$policy->value}, only={$only}]...");
            try {
                $result = $sync->sync($org, $policy, $config['api_key'], $config['base_url'], $createMissing, $only, $stageUnmatched);
                $this->line("  Kunden    — matched: {$result['matched']}, linked: {$result['linked']}, created: {$result['created']}, conflicts: {$result['conflicts']}, updated: {$result['updated']}, unmatched: {$result['unmatched']}");
                $this->line("  Lieferanten — matched: {$result['supplier_matched']}, linked: {$result['supplier_linked']}, created: {$result['supplier_created']}, conflicts: {$result['supplier_conflicts']}, updated: {$result['supplier_updated']}, unmatched: {$result['supplier_unmatched']}");
                $this->line("  ambiguous (übersprungen): {$result['ambiguous']}");
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
