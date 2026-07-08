<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Admin;

use App\Models\{ChatWebhook, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 056: der Test-Button im Chat-Admin schickt eine Testnachricht über den
 * bestehenden Zustellweg und meldet Erfolg/Fehler zurück.
 */
final class ChatAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function webhook(bool $active = true): ChatWebhook {
        return ChatWebhook::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Teams',
            'kind' => ChatWebhook::KIND_TEAMS,
            'webhook_url' => 'https://hooks.teams.example/x',
            'active' => $active,
        ]);
    }

    public function test_test_action_sends_a_message_and_reports_success(): void {
        Http::fake(['*' => Http::response('1', 200)]);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $webhook = $this->webhook();

        $this->actingAs($admin)
            ->post(route('admin.chat.test'), ['webhook' => $webhook->sqid])
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.teams.example/x');
    }

    public function test_index_shows_test_button_for_active_webhook(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->webhook();

        $this->actingAs($admin)
            ->get(route('admin.chat.index'))
            ->assertOk()
            ->assertSee(__('chat.action.test'));
    }

    public function test_test_action_reports_failure_on_error_response(): void {
        Http::fake(['*' => Http::response('boom', 500)]);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $webhook = $this->webhook();

        $this->actingAs($admin)
            ->post(route('admin.chat.test'), ['webhook' => $webhook->sqid])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
