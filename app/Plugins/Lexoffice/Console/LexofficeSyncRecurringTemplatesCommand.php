<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeSyncRecurringTemplatesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Plugins\Lexoffice\Services\LexofficeRecurringTemplateSync;
use App\Plugins\Support\Console\ChecksPluginSwitch;
use App\Services\Reselling\Plan\PlanLinkProposer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Serienvorlagen aus Lexoffice spiegeln (Feature 185, MVP-1409) und die
 * Zuordnung Abo ↔ Vorlagenposition vorschlagen. Täglich im Zeitplan, dazu
 * nach dem stündlichen Belegsync.
 */
class LexofficeSyncRecurringTemplatesCommand extends Command {
    use ChecksPluginSwitch;
    use IteratesOrganizations;

    protected $signature = 'lexoffice:sync-recurring-templates ' . self::ORGANIZATION_OPTION;

    protected $description = 'Spiegelt die Lexoffice-Serienvorlagen (nur lesend) als Plan-Quelle des Reselling-Registers.';

    public function handle(PlanLinkProposer $proposer): int {
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }
        foreach ($organizations as $org) {
            if (! $this->pluginEnabledFor(LexofficePlugin::ID, (int) $org->id) || ! app(LexofficeClientFactory::class)->isConfigured($org->id)) {
                continue;
            }
            try {
                Cache::lock(LexofficeConfig::apiLockKey((int) $org->id), 1800)->block(LexofficeConfig::API_LOCK_WAIT_SCHEDULED, function () use ($org, $proposer): void {
                    try {
                        $result = (new LexofficeRecurringTemplateSync($org->id))->sync($org);
                        $this->line("Organisation #{$org->id} ({$org->name}): {$result['read']} Vorlagen gelesen, {$result['created']} neu, {$result['updated']} aktualisiert, {$result['lines']} Positionen, {$result['ended']} beendet, {$result['failed']} Fehler" . ($result['complete'] ? '' : ' — Liste unvollständig, nichts beendet'));
                        $links = $proposer->propose($org);
                        $this->line("  Zuordnungen: {$links['links']} vorgeschlagen, {$links['unmatched']} Vorlagenpositionen ohne Abo");
                    } catch (\Throwable $e) {
                        $this->error("  Fehler: {$e->getMessage()}");
                    }
                });
            } catch (LockTimeoutException) {
                $this->warn("Organisation #{$org->id} ({$org->name}): anderer Lexoffice-Lauf blockiert — übersprungen.");
            }
        }

        return self::SUCCESS;
    }
}
