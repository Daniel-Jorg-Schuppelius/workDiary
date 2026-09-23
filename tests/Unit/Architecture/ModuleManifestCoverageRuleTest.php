<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleManifestCoverageRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use App\Modules\{ManifestChecker, ModuleRegistry};
use Tests\TestCase;

/**
 * Architektur-Gate „Modul-Manifeste" (MVP-861): jede Tabelle, jeder
 * Domänenordner, jede Rechtegruppe und jeder Lizenzcode gehört genau einem
 * Manifest; Routenmuster treffen Routen; Abhängigkeiten sind zyklenfrei.
 * Dieselbe Regelmenge wie `php artisan modules:check`.
 */
class ModuleManifestCoverageRuleTest extends TestCase {
    public function test_every_table_folder_permission_group_and_license_belongs_to_exactly_one_manifest(): void {
        $violations = (new ManifestChecker($this->app->make(ModuleRegistry::class), base_path()))->check();

        $this->assertSame([], $violations, "Manifeste unvollständig (php artisan modules:check):\n" . implode("\n", $violations));
    }
}
