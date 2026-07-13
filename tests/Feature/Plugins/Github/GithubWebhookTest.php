<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Github;

use App\Models\{PluginSetting, Task};
use App\Plugins\Github\GithubPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 060, MVP-129 (Bauturbo A6): GitHub-Webhook-Endpunkt. Autorisierung
 * ausschließlich über die HMAC-SHA256-Signatur (X-Hub-Signature-256) des
 * Raw-Bodys; ein gültiger Anstoß löst einen idempotenten Import aus,
 * ungültige/unbekannte Anfragen werden ohne Verarbeitung abgewiesen.
 */
final class GithubWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const SECRET = 'whsec-github';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        FakePluginHttp::fake([
            'https://api.github.com/repos/acme/support/issues*' => FakePluginHttp::response([
                ['id' => 900001, 'number' => 1, 'title' => 'Drucker', 'state' => 'open', 'updated_at' => '2026-07-12T10:00:00Z'],
            ]),
        ]);
    }

    private function configure(?string $secret = self::SECRET, bool $enabled = true): PluginSetting {
        $settings = ['api_token' => 'ghp-token', 'repo_owner' => 'acme', 'repo_name' => 'support'];
        if ($secret !== null) {
            $settings['webhook_secret'] = $secret;
        }

        return PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => GithubPlugin::ID,
            'enabled' => $enabled,
            'settings' => $settings,
        ]);
    }

    /** @return TestResponse<\Illuminate\Http\Response> */
    private function sendWebhook(int $settingId, string $body, ?string $signature): TestResponse {
        return $this->call('POST', "/api/webhooks/github/{$settingId}", [], [], [], [
            'HTTP_X-Hub-Signature-256' => $signature ?? '',
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_valid_signature_triggers_import(): void {
        $row = $this->configure();
        $body = (string) json_encode(['action' => 'opened', 'issue' => ['number' => 1]]);
        $signature = 'sha256=' . hash_hmac('sha256', $body, self::SECRET);

        $this->sendWebhook((int) $row->id, $body, $signature)
            ->assertOk()
            ->assertJson(['status' => 'ok', 'created' => 1]);

        $this->assertSame(1, Task::query()->count());
    }

    public function test_invalid_signature_is_rejected(): void {
        $row = $this->configure();
        $body = (string) json_encode(['action' => 'opened']);

        $this->sendWebhook((int) $row->id, $body, 'sha256=deadbeef')->assertForbidden();
        $this->assertSame(0, Task::query()->count());
    }

    public function test_unknown_setting_is_ignored(): void {
        $body = (string) json_encode(['action' => 'opened']);
        $signature = 'sha256=' . hash_hmac('sha256', $body, self::SECRET);

        $this->sendWebhook(999999, $body, $signature)->assertNotFound();
    }

    public function test_connection_without_secret_is_ignored(): void {
        $row = $this->configure(secret: null);
        $body = (string) json_encode(['action' => 'opened']);
        $signature = 'sha256=' . hash_hmac('sha256', $body, self::SECRET);

        $this->sendWebhook((int) $row->id, $body, $signature)->assertNotFound();
        $this->assertSame(0, Task::query()->count());
    }
}
