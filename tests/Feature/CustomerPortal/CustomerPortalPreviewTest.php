<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalPreviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Admin\SupportImpersonationController;
use App\Models\Audit\AuditLog;
use App\Models\Auth\SupportAccessGrant;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Services\CustomerPortal\PortalPreview;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, DB, Hash};
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Vorschau als Kunde (Phase 153, MVP-1229–1231): eine Sicht, keine Anmeldung.
 */
class CustomerPortalPreviewTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private User $admin;

    private Customer $customer;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Vorschau-Kunde',
        ]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create([
                'organization_id' => $this->organization->id,
                'name' => 'Erika Portal',
                'email' => 'erika@example.test',
                'password' => Hash::make('secret-pass'),
            ]);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** Customer::factory() verstellt den Spatie-Team-Kontext — vor jedem Recht zurück auf die Test-Organisation. */
    private function grant(User $user, P ...$permissions): User {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        foreach ($permissions as $permission) {
            SpatiePermission::findOrCreate($permission->value, 'web');
            $user->givePermissionTo($permission->value);
        }
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $user;
    }

    private function start(?User $actor = null, ?User $portalUser = null) {
        return $this->actingAs($actor ?? $this->admin)
            ->post(route('customers.portal-preview.start', [$this->customer, $portalUser ?? $this->portalUser]));
    }

    private function customerPage(): string {
        return route('customers.show', ['customer' => $this->customer, '#' => 'portal-access']);
    }

    private function lastEndReason(): ?string {
        $entry = AuditLog::query()->where('event', 'customer.portal_preview.ended')->latest('id')->first();

        return $entry?->changes['reason'] ?? null;
    }

    public function test_preview_requires_its_own_permission(): void {
        $viewer = $this->grant($this->orgUser(), P::CustomerView, P::CustomerPortalAccessManage);

        $this->start($viewer)->assertForbidden();
        $this->assertNull(session(PortalPreview::SESSION_KEY));

        $this->grant($viewer, P::CustomerPortalPreview);
        $this->start($viewer)->assertRedirect(route('customer.dashboard'));
    }

    public function test_foreign_customer_and_foreign_account_are_404(): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($otherCustomer);
        $otherAccount = User::factory()->kunde((int) $otherCustomer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id, 'email' => 'fremd@example.test']);

        // Konto eines anderen Kunden derselben Organisation.
        $this->start(null, $otherAccount)->assertNotFound();

        // Kunde einer fremden Organisation ist über das Binding unsichtbar.
        $foreignOrg = Organization::factory()->create();
        $foreignCustomer = Customer::factory()->create(['organization_id' => $foreignOrg->id]);
        $this->actingAs($this->admin)
            ->post(route('customers.portal-preview.start', [$foreignCustomer, $this->portalUser]))
            ->assertNotFound();
    }

    public function test_preview_renders_portal_as_account_without_logging_the_account_in(): void {
        $accountAudits = AuditLog::query()
            ->where('auditable_type', MorphMap::stableKey(User::class))
            ->where('auditable_id', $this->portalUser->id)
            ->count();

        $this->start()->assertRedirect(route('customer.dashboard'));

        $this->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Erika Portal')
            ->assertSee(__('customer_portal.preview.stop'));

        // Keine Anmeldung: kein Sitzungsschlüssel des customer-Guards, kein Audit am Konto.
        $this->assertFalse(session()->has(Auth::guard('customer')->getName()));
        $this->assertSame($accountAudits, AuditLog::query()
            ->where('auditable_type', MorphMap::stableKey(User::class))
            ->where('auditable_id', $this->portalUser->id)
            ->count());

        $started = AuditLog::query()->where('event', 'customer.portal_preview.started')->firstOrFail();
        $this->assertSame($this->admin->id, (int) $started->user_id);
        $this->assertSame($this->customer->id, (int) $started->auditable_id);
        $this->assertSame($this->portalUser->id, (int) $started->changes['portal_user_id']);
    }

    public function test_session_row_stays_with_the_employee(): void {
        config(['session.driver' => 'database']);

        $this->start();
        $this->get(route('customer.dashboard'))->assertOk();

        // Der Sitzungsspeicher (database) schreibt die Zeile mit der ID des Standard-Guards —
        // nach dem Portal-Request muss das die Mitarbeiterin sein, nie das Portalkonto.
        $owners = DB::table('sessions')->pluck('user_id')->map(static fn ($id) => (int) $id)->unique()->values()->all();
        $this->assertSame([$this->admin->id], $owners);
        $this->assertSame('web', Auth::getDefaultDriver());
    }

    public function test_writes_and_credential_pages_are_blocked(): void {
        $this->start();

        $this->post(route('customer.queries.store'), ['subject' => 'x'])->assertForbidden();
        $this->post(route('customer.tickets.store'), [])->assertForbidden();
        $this->get(route('customer.2fa.show'))->assertForbidden();
        $this->get(route('customer.password.change'))->assertForbidden();
        $this->post(route('customer.profile.email.request'), ['email' => 'neu@example.test'])->assertForbidden();

        // Lesen bleibt möglich, auch das Profil.
        $this->get(route('customer.profile.show'))->assertOk()->assertDontSee(route('customer.password.change'));
        $this->assertNotNull(session(PortalPreview::SESSION_KEY));
    }

    public function test_portal_logout_ends_preview_but_keeps_internal_session(): void {
        $this->start();

        $this->post(route('customer.logout'))->assertRedirect($this->customerPage());
        $this->assertNull(session(PortalPreview::SESSION_KEY));
        $this->assertSame('manual', $this->lastEndReason());

        // Interne Sitzung lebt, das Portal verlangt wieder eine Anmeldung.
        $this->get(route('customers.show', $this->customer))->assertOk();
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
    }

    public function test_stop_route_ends_preview_with_audit(): void {
        $this->start();
        $this->get(route('customer.dashboard'))->assertOk();

        $this->post(route('customer.preview.stop'))->assertRedirect($this->customerPage());

        $ended = AuditLog::query()->where('event', 'customer.portal_preview.ended')->firstOrFail();
        $this->assertSame('manual', $ended->changes['reason']);
        $this->assertSame($this->admin->id, (int) $ended->user_id);
        $this->assertArrayHasKey('duration_seconds', $ended->changes);
        $this->assertFalse(session()->has('password_hash_customer'));
    }

    public function test_expiry_ends_preview(): void {
        $this->start();

        $this->travel(PortalPreview::TTL_MINUTES + 1)->minutes();

        $this->get(route('customer.dashboard'))->assertRedirect($this->customerPage());
        $this->assertSame('expired', $this->lastEndReason());
        $this->assertNull(session(PortalPreview::SESSION_KEY));
    }

    public function test_permission_loss_ends_preview(): void {
        $manager = $this->grant($this->orgUser(), P::CustomerView, P::CustomerPortalAccessManage, P::CustomerPortalPreview);
        $this->start($manager)->assertRedirect(route('customer.dashboard'));
        $this->get(route('customer.dashboard'))->assertOk();

        $manager->revokePermissionTo(P::CustomerPortalPreview->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->get(route('customer.dashboard'))->assertRedirect($this->customerPage());
        $this->assertSame('permission_lost', $this->lastEndReason());
    }

    public function test_password_change_of_the_account_ends_preview(): void {
        $this->start();
        $this->get(route('customer.dashboard'))->assertOk();

        $this->portalUser->forceFill(['password' => Hash::make('ganz-neu-9876')])->save();

        $this->get(route('customer.dashboard'))->assertRedirect($this->customerPage());
        $this->assertSame('account_changed', $this->lastEndReason());
    }

    public function test_deactivation_of_the_account_ends_preview(): void {
        $this->start();

        $this->portalUser->forceFill(['deactivated_at' => now()])->save();

        $this->get(route('customer.dashboard'))->assertRedirect($this->customerPage());
        $this->assertSame('account_deactivated', $this->lastEndReason());
    }

    public function test_deactivated_account_cannot_be_previewed(): void {
        $this->portalUser->forceFill(['deactivated_at' => now()])->save();

        $this->start()->assertForbidden();
    }

    public function test_support_session_blocks_preview(): void {
        $target = $this->grant($this->orgUser(), P::CustomerView, P::CustomerPortalPreview);
        SupportAccessGrant::query()->create([
            'organization_id' => $this->organization->id,
            'granted_by_user_id' => $this->admin->id,
            'scope' => SupportAccessGrant::SCOPE_FULL,
            'purpose' => 'Ticket #153',
            'expires_at' => now()->addDay(),
        ]);
        $this->actingAs($this->admin)->post(route('admin.support.impersonate.start', $target))->assertRedirect();
        $this->assertTrue(session()->has(SupportImpersonationController::SESSION_KEY));

        $this->post(route('customers.portal-preview.start', [$this->customer, $this->portalUser]))->assertForbidden();
        $this->assertNull(session(PortalPreview::SESSION_KEY));
    }

    public function test_two_factor_requirement_of_the_organization_does_not_block_preview(): void {
        $this->start();
        $this->organization->forceFill(['two_factor_required' => true])->save();

        $this->get(route('customer.dashboard'))->assertOk();
    }

    public function test_every_portal_index_page_renders_in_preview(): void {
        $this->start();

        $routes = [
            'customer.dashboard', 'customer.search', 'customer.help.index', 'customer.diary.index',
            'customer.time-entries.index', 'customer.invoices.index', 'customer.appointments.index',
            'customer.open-issues.index', 'customer.known-errors.index', 'customer.tickets.index',
            'customer.catalog.index', 'customer.claims.index', 'customer.returns.create',
            'customer.rentals.index', 'customer.rentals.requests.index', 'customer.assets.index',
            'customer.queries.index', 'customer.queries.create', 'customer.intakes.index',
            'customer.intakes.create', 'customer.intakes.upload', 'customer.documents.index',
            'customer.profile.show', 'customer.subscriptions.index', 'customer.agreements.index',
            'customer.billing.index',
        ];

        foreach ($routes as $name) {
            $status = $this->get(route($name))->getStatusCode();
            // 404 = Modul oder Abrechnungskonto für diesen Kunden nicht vorhanden; nie Fehler, nie Umleitung.
            $this->assertContains($status, [200, 404], "Route {$name} antwortete {$status}.");
        }

        $this->assertNotNull(session(PortalPreview::SESSION_KEY));
    }

    public function test_customer_file_shows_running_preview_and_preview_actions(): void {
        $this->actingAs($this->admin)->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee(route('customers.portal-preview.start', [$this->customer, $this->portalUser]));

        $this->start();

        $this->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee(route('customer.preview.stop'))
            ->assertSee('Erika Portal');
    }
}
