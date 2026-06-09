<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeedRoles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Whistleblowing;

use App\Models\Organization;
use App\Services\Whistleblowing\WhistleblowingPermissions;
use Illuminate\Console\Command;

/**
 * Backfill: legt die Rolle `meldestelle` + WB-Permissions fuer bestehende
 * Organisationen an. Neue Orgs erhalten sie automatisch ueber den
 * OrganizationObserver; dieser Befehl deckt Orgs ab, die vor der Modul-
 * Einfuehrung existierten. Idempotent – mehrfach ausfuehrbar.
 */
class SeedRoles extends Command {
    protected $signature = 'whistleblowing:seed-roles {organization? : Optionale Org-ID; ohne Angabe alle}';

    protected $description = 'Legt die Meldestellen-Rolle + Permissions fuer bestehende Organisationen an (Backfill).';

    public function handle(): int {
        $id = $this->argument('organization');

        $orgs = $id !== null
            ? Organization::query()->where('id', (int) $id)->get()
            : Organization::query()->get();

        if ($orgs->isEmpty()) {
            $this->error($id !== null ? "Organisation #{$id} nicht gefunden." : 'Keine Organisationen vorhanden.');

            return self::FAILURE;
        }

        foreach ($orgs as $org) {
            WhistleblowingPermissions::seedOrganization($org);
            $this->line("  <fg=green>OK</> Org #{$org->id} {$org->name}: Rolle '"
                . WhistleblowingPermissions::ROLE_MELDESTELLE . "' + Permissions geseedet.");
        }

        $this->info($orgs->count() . ' Organisation(en) verarbeitet. Rolle ist jetzt unter Admin → Zugriff → Mitglieder zuweisbar.');

        return self::SUCCESS;
    }
}
