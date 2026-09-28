<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountTakeoverResponseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\Auth\TwoFactorType;
use App\Enums\Security\SecurityEventType;
use App\Models\Audit\AuditLog;
use App\Models\Auth\{SecurityEvent, TwoFactorCredential, UserKnownDevice};
use App\Models\Platform\User;
use App\Notifications\PasswordResetLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash, Notification};
use Tests\TestCase;

/** MVP-1008: Reaktion auf eine bestätigte Kontoübernahme. */
final class AccountTakeoverResponseTest extends TestCase {
    use RefreshDatabase;

    private function victim(int $organizationId): User {
        $user = User::factory()->user()->create(['organization_id' => $organizationId, 'password' => Hash::make('altes-Geheimnis-1'), 'is_new_system' => true]);
        $user->createToken('Angreifer-Token');
        foreach ([TwoFactorType::Webauthn, TwoFactorType::Totp] as $i => $type) {
            TwoFactorCredential::query()->create(['user_id' => $user->id, 'type' => $type, 'label' => $type->value, 'credential_id' => 'cred-' . $i, 'confirmed_at' => now()]);
        }
        UserKnownDevice::query()->create(['user_id' => $user->id, 'fingerprint' => str_repeat('a', 64), 'label' => 'Fremdes Gerät', 'last_seen_at' => now()]);

        return $user;
    }

    public function test_organization_admin_secures_a_member_account(): void {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $victim = $this->victim((int) $admin->organization_id);

        $this->actingAs($admin)->get(route('admin.sessions.index'))->assertOk()->assertSee(__('security.account_secure.action'));
        $this->actingAs($admin)->post(route('admin.sessions.user.secure', ['userSqid' => $victim->sqid]))
            ->assertRedirect()->assertSessionHas('success');

        $victim->refresh();
        $this->assertFalse(Hash::check('altes-Geheimnis-1', $victim->password));
        $this->assertTrue($victim->must_change_password);
        $this->assertSame(0, $victim->tokens()->count());
        $this->assertSame([TwoFactorType::Totp], $victim->twoFactorCredentials()->get()->pluck('type')->all());
        $this->assertSame(0, UserKnownDevice::query()->where('user_id', $victim->id)->count());
        $this->assertTrue(DB::table('password_reset_tokens')->where('email', $victim->email)->exists());
        Notification::assertSentTo($victim, PasswordResetLink::class);
        $event = SecurityEvent::query()->where('user_id', $victim->id)->sole();
        $this->assertSame(SecurityEventType::AccountSecured, $event->event);
        $this->assertSame('organization_admin', $event->meta['source'] ?? null);
        $this->assertSame('1', $event->meta['passkeys'] ?? null);
        $this->assertTrue(AuditLog::query()->where('event', 'user.account_secured')->where('auditable_id', $victim->id)->where('user_id', $admin->id)->exists());

        $this->actingAs($admin)->post(route('admin.sessions.user.secure', ['userSqid' => $admin->sqid]))->assertSessionHasErrors('user');
        $stranger = User::factory()->user()->create();
        $this->actingAs($admin)->post(route('admin.sessions.user.secure', ['userSqid' => $stranger->sqid]))->assertNotFound();
    }

    public function test_platform_admin_confirms_a_takeover_from_a_security_event(): void {
        Notification::fake();
        $platform = User::factory()->platformAdmin()->create();
        $victim = $this->victim((int) User::factory()->admin()->create()->organization_id);
        $event = SecurityEvent::query()->create([
            'event' => SecurityEventType::ImpossibleTravel, 'ip' => '203.0.113.9', 'user_id' => $victim->id,
            'organization_id' => $victim->organization_id, 'meta' => ['km' => '900'], 'occurred_at' => now(),
        ]);

        $this->actingAs($platform)->get(route('admin.security-events.index'))->assertOk()->assertSee(__('security.account_secure.action_event'));
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.security-events.secure-account', $event))->assertForbidden();
        $this->actingAs($platform)->post(route('admin.security-events.secure-account', $event))->assertRedirect()->assertSessionHas('success');

        $this->assertSame(0, $victim->tokens()->count());
        Notification::assertSentTo($victim, PasswordResetLink::class);
        $secured = SecurityEvent::query()->where('user_id', $victim->id)->where('event', SecurityEventType::AccountSecured->value)->sole();
        $this->assertSame((string) $event->id, $secured->meta['event_id'] ?? null);
    }

    public function test_users_secure_their_own_account_and_portal_users_are_refused(): void {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $victim = $this->victim((int) $admin->organization_id);

        $this->actingAs($victim)->get(route('account.2fa.show'))->assertOk()->assertSee(__('security.account_secure.self_heading'));
        $this->actingAs($victim)->post(route('account.secure'))->assertRedirect(route('login'))->assertSessionHas('status', __('security.account_secure.flash.self'));
        $this->assertGuest();
        $this->assertTrue($victim->refresh()->must_change_password);
        Notification::assertSentTo($victim, PasswordResetLink::class);

        $portal = User::factory()->user()->create(['organization_id' => $admin->organization_id, 'customer_id' => \App\Models\Customer\Customer::factory()->create(['organization_id' => $admin->organization_id])->id]);
        $this->actingAs($admin)->post(route('admin.sessions.user.secure', ['userSqid' => $portal->sqid]))->assertSessionHasErrors('user');
        Notification::assertNotSentTo($portal, PasswordResetLink::class);
    }
}
