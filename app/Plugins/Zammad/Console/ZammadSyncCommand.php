<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Zammad\Console;

use App\Models\{Organization, ZammadConnection};
use App\Plugins\Zammad\Contracts\ZammadGatewayFactory;
use App\Plugins\Zammad\Services\ZammadTicketImporter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Polling-Import als verlässliche Quelle (Feature 060, MVP-129): läuft im
 * Scheduler und manuell aus der Admin-UI. Idempotent über ExternalReference —
 * verpasste Webhooks werden hier aufgeholt, ein Abbruch einer Anbindung lässt
 * die übrigen unberührt. Der Aufholpunkt (`last_polled_at`) wird je Anbindung
 * fortgeschrieben.
 */
class ZammadSyncCommand extends Command {
    protected $signature = 'zammad:sync
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Importiert Zammad-Tickets je Organisation als Aufgaben (idempotent, Queue→Projekt).';

    public function handle(ZammadGatewayFactory $factory, ZammadTicketImporter $importer): int {
        $orgId = $this->option('organization');
        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        foreach ($query->get() as $org) {
            // Org-Kontext für nachgelagerte (scoped) Operationen binden.
            app()->instance('currentOrganization', $org);

            $connections = ZammadConnection::query()->withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->get();

            foreach ($connections as $connection) {
                if (! $connection->isActive()) {
                    continue;
                }

                try {
                    $result = $importer->import($connection, $factory->for($connection));
                    $this->info(sprintf(
                        'Organisation #%d (%s) / %s: created %d, skipped %d',
                        $org->id, $org->name, $connection->name, $result['created'], $result['skipped'],
                    ));
                } catch (Throwable $e) {
                    $this->error(sprintf('Organisation #%d / %s: Abbruch — %s', $org->id, $connection->name, $e->getMessage()));
                }
            }
        }

        return self::SUCCESS;
    }
}
