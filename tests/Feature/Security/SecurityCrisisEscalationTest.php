<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityCrisisEscalationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\Security\SecurityEventType;
use App\Models\Crisis\CrisisCase;
use App\Models\{SecurityEvent, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Notification};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-449 — Massenangriff-Eskalation: eine Schwellwert-Regel mit
 * `crisis => true` erzeugt einen echten Krisenfall (Feature 070) statt einer
 * normalen Admin-Notification; die Entwarnung setzt denselben Fall auf
 * `all_clear` statt einen zweiten zu eröffnen.
 */
class SecurityCrisisEscalationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Notification::fake();
        Cache::flush();

        // Nur die Massenangriff-Regel aktiv halten, damit der Test nicht an
        // den normalen Schwellwerten hängt.
        config()->set('security.events.thresholds', [
            ['key' => 'auth_failed_mass', 'event' => 'auth.failed', 'scope' => 'global', 'window_minutes' => 10, 'limit' => 5, 'crisis' => true],
        ]);
    }

    private function failedLogins(int $count): void {
        for ($i = 0; $i < $count; $i++) {
            SecurityEvent::query()->create([
                'event' => SecurityEventType::AuthFailed->value,
                'ip' => '203.0.113.' . ($i % 200),
                'meta' => [],
                'occurred_at' => now(),
            ]);
        }
    }

    public function test_mass_attack_opens_crisis_case_and_all_clear_closes_it(): void {
        User::factory()->create(['is_platform_admin' => true, 'organization_id' => $this->organization->id]);
        $this->failedLogins(6);

        $this->artisan('security:evaluate')->assertSuccessful();

        $case = CrisisCase::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame('security', $case->category);
        $this->assertSame('critical', $case->severity);
        $this->assertSame('security:auth_failed_mass', $case->trigger_source);
        $this->assertSame('activated', $case->status);

        // Zweiter Lauf mit weiter aktiver Sperre: kein zweiter Fall.
        $this->artisan('security:evaluate')->assertSuccessful();
        $this->assertSame(1, CrisisCase::query()->withoutGlobalScopes()->count());

        // Rate normalisiert (Ereignisse aus dem Fenster geschoben) → Entwarnung.
        SecurityEvent::query()->update(['occurred_at' => now()->subHours(2)]);
        $this->artisan('security:evaluate')->assertSuccessful();

        $case->refresh();
        $this->assertSame('all_clear', $case->status);
        $this->assertNotNull($case->all_clear_at);
        $this->assertSame(1, CrisisCase::query()->withoutGlobalScopes()->count());
    }

    public function test_below_threshold_creates_no_crisis(): void {
        User::factory()->create(['is_platform_admin' => true, 'organization_id' => $this->organization->id]);
        $this->failedLogins(2);

        $this->artisan('security:evaluate')->assertSuccessful();

        $this->assertSame(0, CrisisCase::query()->withoutGlobalScopes()->count());
    }

    public function test_normal_rules_keep_using_notifications(): void {
        config()->set('security.events.thresholds', [
            ['key' => 'auth_failed_global', 'event' => 'auth.failed', 'scope' => 'global', 'window_minutes' => 10, 'limit' => 5],
        ]);
        User::factory()->create(['is_platform_admin' => true, 'organization_id' => $this->organization->id]);
        $this->failedLogins(6);

        $this->artisan('security:evaluate')->assertSuccessful();

        $this->assertSame(0, CrisisCase::query()->withoutGlobalScopes()->count());
        Notification::assertSentTimes(\App\Notifications\GenericEventNotification::class, 1);
    }
}
