<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IpBanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\Security\SecurityEventType;
use App\Models\Auth\SecurityIpBan;
use App\Models\Platform\User;
use App\Services\Security\{IpBanService, SecurityEventLogger};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** MVP-450: temporäre IP-Sperre nach Fehlversuchen (Standard aus). */
final class IpBanTest extends TestCase {
    use RefreshDatabase;

    private const IP = '203.0.113.7';

    protected function setUp(): void {
        parent::setUp();
        config()->set('security.ip_ban.enabled', true);
        config()->set('security.ip_ban.threshold', 3);
        Route::middleware('web')->get('/_test/ban-probe', static fn () => 'ok');
    }

    private function failAttempts(int $times, SecurityEventType $type = SecurityEventType::AuthFailed, string $ip = self::IP): void {
        for ($i = 0; $i < $times; $i++) {
            app(SecurityEventLogger::class)->log($type, ['ip' => $ip, 'user' => 'x@example.com']);
        }
    }

    public function test_threshold_bans_the_address_with_escalating_steps(): void {
        $this->failAttempts(2);
        $this->assertSame(0, SecurityIpBan::query()->count());
        $this->failAttempts(1);

        $ban = SecurityIpBan::query()->sole();
        $this->assertSame(1, $ban->level);
        $this->assertEqualsWithDelta(15, now()->diffInMinutes($ban->banned_until), 1);
        $this->assertNotNull(app(IpBanService::class)->bannedUntil(self::IP));
        $this->assertDatabaseHas('security_events', ['event' => SecurityEventType::IpBanned->value]);

        $this->failAttempts(3);
        $this->assertEqualsWithDelta(60, now()->diffInMinutes(SecurityIpBan::query()->latest('id')->firstOrFail()->banned_until), 1);
    }

    public function test_banned_guest_gets_429_but_signed_in_users_pass(): void {
        $this->failAttempts(3);

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])->get('/_test/ban-probe')
            ->assertStatus(429)->assertHeader('Retry-After');
        $user = User::factory()->user()->create();
        $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => self::IP])->get('/_test/ban-probe')->assertOk();
    }

    public function test_private_networks_allowlist_and_whistleblower_attempts_never_ban(): void {
        $this->failAttempts(5, ip: '10.0.0.5');
        config()->set('security.ip_ban.allowlist', ['198.51.100.0/24']);
        $this->failAttempts(5, ip: '198.51.100.20');
        $this->failAttempts(5, SecurityEventType::WbLoginFailed);

        $this->assertSame(0, SecurityIpBan::query()->count());
    }

    public function test_disabled_by_default_and_release_is_audited(): void {
        config()->set('security.ip_ban.enabled', false);
        $this->failAttempts(5);
        $this->assertSame(0, SecurityIpBan::query()->count());

        config()->set('security.ip_ban.enabled', true);
        $this->failAttempts(3);
        $ban = SecurityIpBan::query()->sole();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get(route('admin.security-events.index'))->assertOk()->assertSeeText(self::IP);
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.security-events.ip-bans.release', $ban))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.security-events.ip-bans.release', $ban))->assertSessionHas('success');

        $this->assertNotNull($ban->fresh()->released_at);
        $this->assertNull(app(IpBanService::class)->bannedUntil(self::IP));
        $this->assertTrue($admin->auditLogs()->where('event', 'security.ipBanReleased')->exists());
    }
}
