<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeedRolesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;

/**
 * Gemeinsamer Kern der Rollen-Backfill-Commands (Konsolidierung B11):
 * seedet je Organisation eine isolierte Rolle + Permissions (idempotent).
 * Ableitungen liefern Seeder und Rollennamen; Org-Auswahl läuft über das
 * optionale `organization`-Argument (nicht die --organization-Option des
 * IteratesOrganizations-Skeletts — Signaturen bleiben stabil).
 */
abstract class SeedRolesCommand extends Command {
    /** Seedet Rolle + Permissions für eine Organisation (idempotent). */
    abstract protected function seedOrganization(Organization $organization): void;

    /** Rollenname für die OK-Zeile (z. B. `datenschutz`). */
    abstract protected function roleName(): string;

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
            $this->seedOrganization($org);
            $this->line("  <fg=green>OK</> Org #{$org->id} {$org->name}: Rolle '{$this->roleName()}' + Permissions geseedet.");
        }

        $this->info($orgs->count() . ' Organisation(en) verarbeitet. Rolle ist jetzt unter Admin → Zugriff → Mitglieder zuweisbar.');

        return self::SUCCESS;
    }
}
