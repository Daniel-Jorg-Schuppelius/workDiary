<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalEmailChangeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Mail\{PortalEmailChangeConfirmMail, PortalEmailChangedNoticeMail};
use App\Models\{Customer, User};
use App\Services\CustomerPortal\{PortalAccessService, PortalEmailChangeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Mail, URL};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-712 (Vollscan G7): E-Mail-Selbständerung im Portal — Bestätigung an
 * die NEUE Adresse (signiert, 24 h), Info an die alte, neutrale Antwort bei
 * Kollision, kein Wechsel bei deaktiviertem Zugang.
 */
final class PortalEmailChangeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id, 'email' => 'alt@example.test']);
    }

    private function requestChange(string $email): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.profile.email.request'), ['email' => $email]);
    }

    public function test_profile_page_shows_current_email(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.profile.show'))
            ->assertOk()
            ->assertSee('alt@example.test');
    }

    public function test_request_stores_pending_and_mails_confirmation_to_new_address_only(): void {
        $this->requestChange('Neu@Example.test')->assertRedirect(route('customer.profile.show'));

        $user = $this->portalUser->fresh();
        $this->assertSame('alt@example.test', $user->email);
        $this->assertSame('neu@example.test', $user->portal_pending_email);
        $this->assertNotNull($user->portal_pending_email_requested_at);

        Mail::assertSent(PortalEmailChangeConfirmMail::class, fn (PortalEmailChangeConfirmMail $m): bool => $m->hasTo('neu@example.test') && ! $m->hasTo('alt@example.test'));
        Mail::assertNotSent(PortalEmailChangedNoticeMail::class);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $user->id, 'event' => 'portal.profile.email_change_requested']);
    }

    public function test_confirmation_link_switches_email_and_informs_old_address(): void {
        $this->requestChange('neu@example.test');
        $url = app(PortalEmailChangeService::class)->confirmUrl($this->portalUser->fresh(), 'neu@example.test');

        // Klick von einem anderen Gerät: ohne Portal-Session → Login-Seite.
        Auth::guard('customer')->logout();
        $this->flushSession();
        $this->get($url)->assertRedirect(route('customer.login'));

        $user = $this->portalUser->fresh();
        $this->assertSame('neu@example.test', $user->email);
        $this->assertNull($user->portal_pending_email);
        $this->assertNotNull($user->email_verified_at);
        Mail::assertSent(PortalEmailChangedNoticeMail::class, fn (PortalEmailChangedNoticeMail $m): bool => $m->hasTo('alt@example.test'));
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $user->id, 'event' => 'portal.profile.email_changed']);

        // Einmalig: derselbe Link wirkt nicht erneut.
        $this->get($url)->assertNotFound();
    }

    public function test_expired_signature_is_rejected_and_nothing_changes(): void {
        $this->requestChange('neu@example.test');
        $hash = app(PortalEmailChangeService::class)->hashFor('neu@example.test');
        $expired = URL::temporarySignedRoute('customer.profile.email.confirm', Carbon::now()->subMinute(), [
            'user' => $this->portalUser->getRouteKey(),
            'hash' => $hash,
        ]);

        $this->get($expired)->assertForbidden();
        $this->assertSame('alt@example.test', $this->portalUser->fresh()->email);
        Mail::assertNotSent(PortalEmailChangedNoticeMail::class);
    }

    public function test_pending_older_than_24_hours_is_not_confirmable(): void {
        $this->requestChange('neu@example.test');
        $this->portalUser->fresh()->forceFill(['portal_pending_email_requested_at' => Carbon::now()->subHours(25)])->save();
        $url = app(PortalEmailChangeService::class)->confirmUrl($this->portalUser->fresh(), 'neu@example.test');

        $this->get($url)->assertNotFound();
        $this->assertSame('alt@example.test', $this->portalUser->fresh()->email);
        $this->assertNull($this->portalUser->fresh()->portal_pending_email);
    }

    public function test_wrong_hash_or_tampered_signature_is_rejected(): void {
        $this->requestChange('neu@example.test');
        $wrongHash = app(PortalEmailChangeService::class)->confirmUrl($this->portalUser->fresh(), 'andere@example.test');
        $this->get($wrongHash)->assertNotFound();

        $valid = app(PortalEmailChangeService::class)->confirmUrl($this->portalUser->fresh(), 'neu@example.test');
        $this->get($valid . 'x')->assertForbidden();

        $this->assertSame('alt@example.test', $this->portalUser->fresh()->email);
    }

    public function test_collision_answers_neutrally_without_pending_or_mail(): void {
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'besetzt@example.test']);
        $neutral = (string) __('Sofern die Adresse verwendbar ist, haben wir einen Bestätigungslink an die neue E-Mail-Adresse gesendet. Die Änderung wird erst nach dem Klick wirksam.');

        // Identische Meldung für belegte und freie Adresse — keine Konten-Enumeration.
        $this->requestChange('besetzt@example.test')
            ->assertRedirect(route('customer.profile.show'))
            ->assertSessionHas('status', $neutral);
        $this->assertNull($this->portalUser->fresh()->portal_pending_email);
        Mail::assertNotSent(PortalEmailChangeConfirmMail::class);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $this->portalUser->id, 'event' => 'portal.profile.email_change_blocked']);

        $this->requestChange('frei@example.test')
            ->assertRedirect(route('customer.profile.show'))
            ->assertSessionHas('status', $neutral);
        $this->assertSame('frei@example.test', $this->portalUser->fresh()->portal_pending_email);
    }

    public function test_collision_at_confirmation_time_is_rejected(): void {
        $this->requestChange('neu@example.test');
        $url = app(PortalEmailChangeService::class)->confirmUrl($this->portalUser->fresh(), 'neu@example.test');
        // Race: Adresse wird zwischen Anfrage und Klick anderweitig vergeben.
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'neu@example.test']);

        $this->get($url)->assertNotFound();
        $this->assertSame('alt@example.test', $this->portalUser->fresh()->email);
    }

    public function test_deactivated_access_cannot_change_email(): void {
        $this->requestChange('neu@example.test');
        $url = app(PortalEmailChangeService::class)->confirmUrl($this->portalUser->fresh(), 'neu@example.test');

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        app(PortalAccessService::class)->deactivate($this->portalUser->fresh(), $admin);

        // Offener Link wirkt nicht mehr …
        $this->get($url)->assertNotFound();
        $this->assertSame('alt@example.test', $this->portalUser->fresh()->email);

        // … und eine neue Anfrage ist gesperrt.
        $this->actingAs($this->portalUser->fresh(), 'customer')
            ->post(route('customer.profile.email.request'), ['email' => 'nochmal@example.test'])
            ->assertForbidden();
        Mail::assertNotSent(PortalEmailChangeConfirmMail::class, fn (PortalEmailChangeConfirmMail $m): bool => $m->hasTo('nochmal@example.test'));
    }

    public function test_internal_user_cannot_be_targeted_by_confirmation_route(): void {
        $internal = User::factory()->user()->create(['organization_id' => $this->organization->id, 'portal_pending_email' => 'x@example.test', 'portal_pending_email_requested_at' => Carbon::now()]);
        $url = URL::temporarySignedRoute('customer.profile.email.confirm', Carbon::now()->addHour(), [
            'user' => $internal->getRouteKey(),
            'hash' => app(PortalEmailChangeService::class)->hashFor('x@example.test'),
        ]);

        $this->get($url)->assertNotFound();
        $this->assertNotSame('x@example.test', $internal->fresh()->email);
    }
}
