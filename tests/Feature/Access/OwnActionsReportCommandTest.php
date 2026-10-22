<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OwnActionsReportCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Enums\User\UserRole;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Nach der Rechte-Umstellung (lk-06): wer verliert eigene Vorgänge? Der Bericht nennt es, ändert aber nichts. */
final class OwnActionsReportCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_lists_accounts_without_own_action_rights_but_not_standard_roles(): void {
        $this->setUpOrganization();
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Ulla Standard']);
        User::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Rolf Ohnerolle']);
        $trainer = User::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Tina Schulung']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $trainer->syncRoles([Role::findOrCreate(UserRole::TrainingManager->value, 'web')]);

        $this->artisan('permissions:own-actions-report', ['--organization' => $this->organization->id])
            ->expectsOutputToContain('Rolf Ohnerolle')
            ->expectsOutputToContain('Tina Schulung')
            ->doesntExpectOutputToContain('Ulla Standard')
            ->assertSuccessful();
    }
}
