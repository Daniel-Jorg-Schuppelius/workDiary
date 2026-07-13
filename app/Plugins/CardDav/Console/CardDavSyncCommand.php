<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CardDav\Console;

use App\Models\Organization;
use App\Plugins\CardDav\Services\CardDavContactImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Liest CardDAV-Kontakte je Organisation und speist sie als
 * Zuordnungsvorschläge in die Integrations-Inbox ein (Bauturbo A9, MVP-329).
 * Läuft im Scheduler und manuell aus der Admin-UI. Idempotent über UID+ETag —
 * wiederholte Läufe erzeugen keine Duplikate.
 */
class CardDavSyncCommand extends Command {
    protected $signature = 'carddav:sync
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Liest CardDAV-Kontakte und speist sie als Zuordnungsvorschläge in die Integrations-Inbox ein.';

    public function handle(CardDavContactImporter $importer): int {
        $orgId = $this->option('organization');
        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        foreach ($query->get() as $org) {
            // Org-Kontext für nachgelagerte (scoped) Operationen binden.
            app()->instance('currentOrganization', $org);

            try {
                $r = $importer->sync($org);
                $this->info(sprintf(
                    'Organisation #%d (%s): connections %d, changed %d, linked %d, staged %d, skipped %d, deleted %d, failed %d',
                    $org->id, $org->name, $r['connections'], $r['changed'], $r['linked'], $r['staged'], $r['skipped'], $r['deleted'], $r['failed'],
                ));
            } catch (Throwable $e) {
                $this->error(sprintf('Organisation #%d (%s): Abbruch — %s', $org->id, $org->name, $e->getMessage()));
            }
        }

        return self::SUCCESS;
    }
}
