<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZonesFromCustomersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Location;

use App\Models\Platform\Organization;
use App\Services\Location\ZoneFactory;
use App\Support\OrganizationContext;
use Illuminate\Console\Command;

/** Zonen aus Kundenadressen für alle Organisationen (MVP-1181); legt nie doppelt an. */
class ZonesFromCustomersCommand extends Command {
    protected $signature = 'location:zones-from-customers {--organization= : nur diese Organisation (ID)}';

    protected $description = 'Legt für Kunden mit Koordinaten ohne aktive Zone eine Standortzone an.';

    public function handle(ZoneFactory $zones): int {
        $query = Organization::query()->orderBy('id');
        if ($this->option('organization') !== null) {
            $query->whereKey((int) $this->option('organization'));
        }
        $total = 0;
        foreach ($query->get() as $organization) {
            $count = OrganizationContext::run($organization, static fn(): int => $zones->forAllCustomers());
            $total += $count;
            $this->line(sprintf('%s: %d Zone(n)', $organization->name, $count));
        }
        $this->info(sprintf('%d Zone(n) angelegt.', $total));

        return self::SUCCESS;
    }
}
