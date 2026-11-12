<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NewDeviceAlertOptOutTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Platform\User;
use App\Services\Security\KnownDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Opt-out „Anmeldung von neuem Gerät“ im Profil (Feature 096, MVP-1612): wirkt und wird auditiert. */
final class NewDeviceAlertOptOutTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    private const string FIREFOX = 'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0';

    public function test_profile_switch_turns_the_alert_off_and_on_with_audit(): void {
        $this->setUpOrganization();
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $form = ['name' => $user->name, 'email' => $user->email];

        $this->actingAs($user)->get(route('account.profile.edit'))->assertOk()->assertSee('preferences[security_new_device_alert]', false);

        $this->actingAs($user)->put(route('account.profile.update'), $form + ['preferences' => ['security_new_device_alert' => '0']])->assertRedirect();
        $this->assertFalse($user->fresh()?->preferences[KnownDeviceService::PREFERENCE_KEY]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.newDeviceAlertDisabled']);

        Notification::fake();
        $service = app(KnownDeviceService::class);
        $service->touch($user->fresh(), self::CHROME, '127.0.0.1');
        $service->touch($user->fresh(), self::FIREFOX, '127.0.0.1');
        Notification::assertNothingSent();

        // Speichern anderer Einstellungen ohne Änderung des Schalters schreibt kein weiteres Audit.
        $this->actingAs($user)->put(route('account.profile.update'), $form + ['preferences' => ['security_new_device_alert' => '0', 'mail_signature' => 'Gruß']])->assertRedirect();
        $this->assertSame(1, \App\Models\Audit\AuditLog::query()->withoutGlobalScopes()->where('event', 'user.newDeviceAlertDisabled')->count());

        $this->actingAs($user)->put(route('account.profile.update'), $form + ['preferences' => ['security_new_device_alert' => '1']])->assertRedirect();
        $this->assertTrue($user->fresh()?->preferences[KnownDeviceService::PREFERENCE_KEY]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.newDeviceAlertEnabled']);
    }
}
