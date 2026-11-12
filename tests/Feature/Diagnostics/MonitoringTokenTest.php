<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonitoringTokenTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Diagnostics;

use App\Models\Audit\AuditLog;
use App\Models\Diagnostics\MonitoringToken;
use App\Models\Platform\User;
use App\Services\Diagnostics\MonitoringTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** MVP-1618: Diagnose-JSON für externe Überwachung über einen Token im Header. */
class MonitoringTokenTest extends TestCase {
    use RefreshDatabase;

    public function test_platform_operator_creates_token_shown_once_and_stored_as_fingerprint(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get(route('admin.diagnostics.tokens.create'))->assertOk();
        $response = $this->actingAs($admin)->post(route('admin.diagnostics.tokens.store'), ['name' => 'Überwachung Rechenzentrum'])
            ->assertRedirect(route('admin.diagnostics.index'))
            ->assertSessionHas('monitoring_token_plain');

        $plain = (string) $response->getSession()->get('monitoring_token_plain');
        $token = MonitoringToken::query()->sole();
        $this->assertSame(hash('sha256', $plain), $token->token_hash);
        $this->assertSame(mb_substr($plain, 0, 6), $token->token_hint);
        $this->assertStringNotContainsString($plain, (string) json_encode($token->toArray()));
        $this->assertDatabaseHas('audit_logs', ['event' => 'diagnostics.tokenCreated', 'auditable_id' => $token->id, 'user_id' => $admin->id]);

        // Die Seite nach dem Speichern zeigt den Klartext, jede weitere nur noch die Kennung.
        $this->actingAs($admin)->get(route('admin.diagnostics.index'))->assertOk()->assertSee($plain);
        $this->actingAs($admin)->get(route('admin.diagnostics.index'))
            ->assertOk()
            ->assertSee($token->token_hint)
            ->assertDontSee($plain);
    }

    public function test_regular_user_cannot_create_tokens(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->post(route('admin.diagnostics.tokens.store'), ['name' => 'X'])->assertForbidden();
        $this->assertSame(0, MonitoringToken::query()->count());
    }

    public function test_json_requires_a_valid_bearer_token_and_omits_personal_metrics(): void {
        $admin = User::factory()->platformAdmin()->create();
        ['plain' => $plain, 'token' => $token] = app(MonitoringTokenService::class)->issue('Prometheus', $admin);
        config(['mail.from.address' => 'betrieb@example.test']);

        $this->getJson(route('api.monitoring.diagnostics'))->assertUnauthorized();
        $this->getJson(route('api.monitoring.diagnostics'), ['Authorization' => 'Bearer falsch'])->assertUnauthorized();
        $this->getJson(route('api.monitoring.diagnostics') . '?token=' . $plain)->assertUnauthorized();

        $response = $this->getJson(route('api.monitoring.diagnostics'), ['Authorization' => 'Bearer ' . $plain])
            ->assertOk()
            ->assertJsonStructure(['generated_at', 'overall_status', 'sections' => [['code', 'status', 'metrics', 'messages']]]);

        $sections = collect($response->json('sections'))->keyBy('code');
        $this->assertArrayNotHasKey('from', $sections['mail']['metrics']);
        $this->assertArrayHasKey('driver', $sections['mail']['metrics']);
        $this->assertArrayNotHasKey('licensee', (array) ($sections['license']['metrics'] ?? []));
        $this->assertStringNotContainsString('betrieb@example.test', (string) $response->getContent());

        $this->assertNotNull($token->fresh()?->last_used_at);
        $this->assertSame(1, AuditLog::query()->where('event', 'diagnostics.viewed')->where('auditable_id', $token->id)->count());

        // Polling im Minutentakt schreibt nicht jedes Mal ins Audit.
        $this->getJson(route('api.monitoring.diagnostics'), ['Authorization' => 'Bearer ' . $plain])->assertOk();
        $this->assertSame(1, AuditLog::query()->where('event', 'diagnostics.viewed')->where('auditable_id', $token->id)->count());

        $this->travelTo(Carbon::now()->addMinutes(61));
        $this->getJson(route('api.monitoring.diagnostics'), ['Authorization' => 'Bearer ' . $plain])->assertOk();
        $this->assertSame(2, AuditLog::query()->where('event', 'diagnostics.viewed')->where('auditable_id', $token->id)->count());
    }

    public function test_revoked_token_no_longer_grants_access(): void {
        $admin = User::factory()->platformAdmin()->create();
        ['plain' => $plain, 'token' => $token] = app(MonitoringTokenService::class)->issue('Zabbix', $admin);

        $this->actingAs($admin)->delete(route('admin.diagnostics.tokens.revoke', $token))->assertRedirect();

        $this->assertNotNull($token->fresh()?->revoked_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'diagnostics.tokenRevoked', 'auditable_id' => $token->id]);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->getJson(route('api.monitoring.diagnostics'), ['Authorization' => 'Bearer ' . $plain])->assertUnauthorized();
    }
}
