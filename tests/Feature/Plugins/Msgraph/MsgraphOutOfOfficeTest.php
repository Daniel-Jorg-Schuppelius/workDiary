<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphOutOfOfficeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Msgraph;

use App\Models\Absence\Vacation;
use App\Models\Platform\User;
use App\Plugins\Msgraph\Enums\MsgraphConnectionStatus;
use App\Plugins\Msgraph\Models\MsgraphConnection;
use App\Plugins\Msgraph\Services\MsgraphOutOfOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Abwesenheitsnotiz folgt der Plugin-Einstellung `oof_enabled` (MVP-1042). */
class MsgraphOutOfOfficeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    public function test_out_of_office_follows_the_plugin_setting(): void {
        $this->setUpOrganization();
        $user = User::factory()->create(['organization_id' => $this->organization->id, 'email' => 'urlaub@firma.example']);
        $this->pluginSecret('msgraph', ['client_id' => 'test-client', 'client_secret' => 'test-secret']);
        MsgraphConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-1',
            'status' => MsgraphConnectionStatus::Active,
        ]);
        $vacation = Vacation::query()->create([
            'organization_id' => $this->organization->id, 'user_id' => $user->id,
            'start_date' => '2026-10-05', 'end_date' => '2026-10-09', 'type' => 'vacation', 'status' => 'pending',
        ]);
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/users/*' => FakePluginHttp::response([], 200),
        ]);
        $service = app(MsgraphOutOfOfficeService::class);

        $this->assertFalse($service->applyForVacation($vacation), 'ohne Schalter keine Notiz');
        $fake->assertNothingSent();

        $this->pluginSecret('msgraph', ['oof_enabled' => true]);
        $this->assertTrue($service->applyForVacation($vacation->fresh() ?? $vacation));
        $fake->assertSent(fn ($request): bool => str_contains((string) $request->getUri(), '/mailboxSettings'));
    }
}
