<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportReportPermissionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Import;

use App\Enums\User\{Permission, UserRole};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/** Importläufe lesen ohne Importrecht: import.viewReports (lk-06). */
class ImportReportPermissionTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    public function test_import_runs_are_readable_with_import_view_reports(): void {
        $this->setUpOrganization();

        $this->actingAs($this->actorIn($this->organization, [Permission::ImportViewReports]))->get(route('admin.imports.index'))->assertOk();
        $this->actingAs($this->userWithRole(UserRole::Callcenter->value))->get(route('admin.imports.index'))->assertForbidden();
        $this->actingAs($this->orgAdmin())->get(route('admin.imports.index'))->assertOk();
    }
}
