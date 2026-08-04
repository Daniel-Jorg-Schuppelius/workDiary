<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityEventsPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Enums\Security\SecurityEventType;
use App\Models\{SecurityEvent, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security-Dashboard (Feature 096, MVP-445) — HTTP-Sicht: Zugriff nur für
 * Plattform-Admin und Rendern der Ereignisliste. Die IP-Assertions sind
 * Regressionsschutz: Das IpAddress-VO hat bewusst kein __toString, direktes
 * Echo in Blade crasht daher (Prod-Fehler 2026-08-04).
 */
class SecurityEventsPageTest extends TestCase {
    use RefreshDatabase;

    public function test_index_requires_authentication(): void {
        $this->get(route('admin.security-events.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_org_admin(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.security-events.index'))->assertForbidden();
    }

    public function test_index_renders_events_with_ip_for_platform_admin(): void {
        $admin = User::factory()->platformAdmin()->create();

        SecurityEvent::query()->create([
            'event' => SecurityEventType::AuthFailed,
            'ip' => '203.0.113.7',
            'organization_id' => $admin->organization_id,
            'meta' => ['username' => 'jemand'],
            'occurred_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.security-events.index'))
            ->assertOk()
            ->assertSee('auth.failed')
            ->assertSee('203.0.113.7');
    }

    public function test_index_renders_events_without_ip(): void {
        $admin = User::factory()->platformAdmin()->create();

        SecurityEvent::query()->create([
            'event' => SecurityEventType::ApiTokenInvalid,
            'ip' => null,
            'occurred_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.security-events.index'))
            ->assertOk()
            ->assertSee('api.token_invalid');
    }
}
