<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Gitlab;

use App\Models\{PluginSetting, Task};
use App\Plugins\Gitlab\GitlabPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 060, MVP-129 (Bauturbo A6): GitLab-Webhook-Endpunkt. Autorisierung
 * ausschließlich über den statischen X-Gitlab-Token-Header (Konstantzeit-
 * Vergleich); ein gültiger Anstoß löst einen idempotenten Import aus,
 * ungültige/unbekannte Anfragen werden ohne Verarbeitung abgewiesen.
 */
final class GitlabWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const TOKEN = 'whtok-gitlab';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        FakePluginHttp::fake([
            'https://gitlab.com/api/v4/projects/4242/issues*' => FakePluginHttp::response([
                ['id' => 990001, 'iid' => 1, 'project_id' => 4242, 'title' => 'Drucker', 'state' => 'opened', 'updated_at' => '2026-07-12T10:00:00Z'],
            ]),
        ]);
    }

    private function configure(?string $token = self::TOKEN, bool $enabled = true): PluginSetting {
        $settings = [
            'api_token' => 'glpat-token',
            'project_id' => '4242',
            'allow_private_network' => true,
        ];
        if ($token !== null) {
            $settings['webhook_token'] = $token;
        }

        return PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => GitlabPlugin::ID,
            'enabled' => $enabled,
            'settings' => $settings,
        ]);
    }

    /** @return TestResponse<\Illuminate\Http\Response> */
    private function sendWebhook(int $settingId, ?string $token): TestResponse {
        return $this->call('POST', "/api/webhooks/gitlab/{$settingId}", [], [], [], [
            'HTTP_X-Gitlab-Token' => $token ?? '',
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['object_kind' => 'issue']));
    }

    public function test_valid_token_triggers_import(): void {
        $row = $this->configure();

        $this->sendWebhook((int) $row->id, self::TOKEN)
            ->assertOk()
            ->assertJson(['status' => 'ok', 'created' => 1]);

        $this->assertSame(1, Task::query()->count());
    }

    public function test_invalid_token_is_rejected(): void {
        $row = $this->configure();

        $this->sendWebhook((int) $row->id, 'wrong-token')->assertForbidden();
        $this->assertSame(0, Task::query()->count());
    }

    public function test_unknown_setting_is_ignored(): void {
        $this->sendWebhook(999999, self::TOKEN)->assertNotFound();
    }

    public function test_connection_without_token_is_ignored(): void {
        $row = $this->configure(token: null);

        $this->sendWebhook((int) $row->id, self::TOKEN)->assertNotFound();
        $this->assertSame(0, Task::query()->count());
    }
}
