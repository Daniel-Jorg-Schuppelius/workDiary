<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerLexofficePushButtonTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{PluginSetting, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Der „Lexoffice: alle pushen"-Button in der Kundenliste erscheint nur, wenn
 * Lexoffice in der aktiven Organisation aktiviert ist — sonst lief der Klick in
 * „Lexoffice-Plugin ist nicht aktiviert".
 */
final class CustomerLexofficePushButtonTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_push_all_hidden_when_lexoffice_disabled(): void {
        $this->actingAs($this->admin)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee(route('customers.lexoffice.push-all'), false);
    }

    public function test_push_all_shown_when_lexoffice_enabled(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'test-key'],
        ]);

        $this->actingAs($this->admin)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee(route('customers.lexoffice.push-all'), false);
    }
}
