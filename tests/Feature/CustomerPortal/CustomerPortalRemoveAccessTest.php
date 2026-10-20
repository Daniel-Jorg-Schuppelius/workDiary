<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalRemoveAccessTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\User\Permission as P;
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Privacy\RetentionProposal;
use App\Services\Retention\RetentionScanService;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1246: Portalzugang entfernen — Löschen nur ohne jede Anmeldung (Login-
 * Audit entscheidet), sonst Anonymisierung nach Deaktivierung; beides mit
 * Passwortbestätigung und Audit am Kunden; entfernte Zugänge verlassen die
 * Liste; die Löschfristen-Prüfung schlägt deaktivierte Konten vor.
 */
class CustomerPortalRemoveAccessTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $manager;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->manager = $this->orgUser();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        foreach ([P::CustomerPortalAccessManage, P::CustomerView] as $p) {
            SpatiePermission::findOrCreate($p->value, 'web');
            $this->manager->givePermissionTo($p->value);
        }
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Entfern-Kunde']);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** @param array<string, mixed> $overrides */
    private function portalUser(array $overrides = []): User {
        return User::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Paula Portal',
            'email' => 'paula@example.test',
            'portal_invited_at' => now(),
            'portal_invite_token_hash' => str_repeat('a', 64),
            'portal_invite_expires_at' => now()->addDays(7),
        ], $overrides));
    }

    private function recordLogin(User $portalUser): void {
        AuditLog::query()->create([
            'user_id' => $portalUser->id,
            'organization_id' => $portalUser->organization_id,
            'event' => 'auth.login',
            'auditable_type' => MorphMap::stableKey(User::class),
            'auditable_id' => $portalUser->id,
            'changes' => null,
        ]);
    }

    public function test_never_signed_in_access_is_deleted_with_audit_on_the_customer(): void {
        $portalUser = $this->portalUser();

        $this->actingAs($this->manager)->withRecentAuthentication()
            ->delete(route('customers.portal-access.destroy', [$this->customer, $portalUser]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull(User::query()->withoutGlobalScopes()->find($portalUser->id));
        $audit = AuditLog::query()->where('event', 'portal.access.deleted')->firstOrFail();
        $changes = (array) $audit->changes;
        $this->assertSame((int) $this->customer->id, (int) $audit->auditable_id);
        $this->assertSame($portalUser->id, (int) ($changes['portal_user_id'] ?? 0));
        $this->assertSame('invited', $changes['state'] ?? null);
    }

    public function test_delete_and_remove_need_a_recent_authentication(): void {
        $portalUser = $this->portalUser();

        $this->actingAs($this->manager)
            ->delete(route('customers.portal-access.destroy', [$this->customer, $portalUser]))
            ->assertRedirect(route('password.confirm'));
        $this->assertNotNull(User::query()->withoutGlobalScopes()->find($portalUser->id));

        $this->actingAs($this->manager)
            ->post(route('customers.portal-access.anonymize', [$this->customer, $portalUser]))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_signed_in_access_cannot_be_deleted_only_removed_after_deactivation(): void {
        $portalUser = $this->portalUser(['portal_invite_token_hash' => null, 'portal_invite_expires_at' => null, 'email_verified_at' => now()]);
        $this->recordLogin($portalUser);

        $this->actingAs($this->manager)->withRecentAuthentication()
            ->delete(route('customers.portal-access.destroy', [$this->customer, $portalUser]))
            ->assertRedirect()
            ->assertSessionHas('error', __('customer_portal.remove.delete_refused'));
        $this->assertNotNull(User::query()->withoutGlobalScopes()->find($portalUser->id));

        // Aktiv: erst deaktivieren.
        $this->actingAs($this->manager)->withRecentAuthentication()
            ->post(route('customers.portal-access.anonymize', [$this->customer, $portalUser]))
            ->assertSessionHas('error', __('customer_portal.remove.anonymize_refused'));
        $this->assertNull($portalUser->refresh()->anonymized_at);

        $portalUser->forceFill(['deactivated_at' => now()])->save();
        $this->actingAs($this->manager)->withRecentAuthentication()
            ->post(route('customers.portal-access.anonymize', [$this->customer, $portalUser]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $portalUser->refresh();
        $this->assertNotNull($portalUser->anonymized_at);
        $this->assertSame("Ehemaliger Portalzugang #{$portalUser->id}", $portalUser->name);
        $this->assertSame("anonymisiert-{$portalUser->id}@example.invalid", $portalUser->email);
        $this->assertNotNull($portalUser->deactivated_at);
        $this->assertSame(1, AuditLog::query()->where('event', 'portal.access.anonymized')->where('auditable_id', $this->customer->id)->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'user.anonymized')->where('auditable_id', $portalUser->id)->count());

        // Die Adresse ist frei: eine neue Einladung an dieselbe Adresse geht durch.
        $this->actingAs($this->manager)
            ->post(route('customers.portal-access.store', $this->customer), ['name' => 'Paula Neu', 'email' => 'paula@example.test'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, User::query()->withoutGlobalScopes()->where('email', 'paula@example.test')->count());

        // Idempotent: ein zweiter Aufruf ändert nichts und schreibt kein zweites Audit.
        $this->actingAs($this->manager)->withRecentAuthentication()
            ->post(route('customers.portal-access.anonymize', [$this->customer, $portalUser]))->assertRedirect();
        $this->assertSame(1, AuditLog::query()->where('event', 'portal.access.anonymized')->count());
    }

    public function test_removed_accesses_leave_the_list_and_show_in_the_summary(): void {
        $removed = $this->portalUser(['name' => 'Rita Entfernt', 'email' => 'rita@example.test', 'portal_invite_token_hash' => null, 'portal_invite_expires_at' => null]);
        $this->recordLogin($removed);
        $removed->forceFill(['deactivated_at' => now()])->save();
        app(\App\Services\CustomerPortal\PortalAccessService::class)->anonymize($removed, $this->manager);
        $active = $this->portalUser(['name' => 'Anna Aktiv', 'email' => 'anna@example.test']);

        $page = $this->actingAs($this->manager)->get(route('customers.show', $this->customer))->assertOk();
        $page->assertSee('Anna Aktiv')
            ->assertDontSee('Rita Entfernt')
            ->assertSee(trans_choice('customer_portal.remove.removed_summary', 1, ['count' => 1]))
            ->assertSee("Ehemaliger Portalzugang #{$removed->id}")
            // Nie angemeldet: Löschen wird angeboten, Entfernen nicht.
            ->assertSee(route('customers.portal-access.destroy', [$this->customer, $active]), false)
            ->assertDontSee(route('customers.portal-access.anonymize', [$this->customer, $active]), false);
    }

    public function test_foreign_customer_and_internal_accounts_answer_404(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $portalUser = $this->portalUser();
        $internal = $this->orgUser();

        $this->actingAs($this->manager)->withRecentAuthentication()
            ->delete(route('customers.portal-access.destroy', [$other, $portalUser]))->assertNotFound();
        $this->actingAs($this->manager)->withRecentAuthentication()
            ->delete(route('customers.portal-access.destroy', [$this->customer, $internal]))->assertNotFound();
        $this->assertNotNull(User::query()->withoutGlobalScopes()->find($portalUser->id));
        $this->assertNotNull(User::query()->withoutGlobalScopes()->find($internal->id));
    }

    public function test_retention_area_proposes_deactivated_portal_accounts_and_purges_by_anonymizing(): void {
        app()->instance('currentOrganization', $this->organization);
        $old = $this->portalUser(['email' => 'alt@example.test', 'portal_invite_token_hash' => null, 'portal_invite_expires_at' => null, 'deactivated_at' => now()->subYears(2)]);
        $recent = $this->portalUser(['email' => 'neu@example.test', 'portal_invite_token_hash' => null, 'portal_invite_expires_at' => null, 'deactivated_at' => now()->subMonth()]);
        $active = $this->portalUser(['email' => 'aktiv@example.test']);

        $service = app(RetentionScanService::class);
        $service->scan($this->organization);
        $proposals = RetentionProposal::query()->where('area', 'portal_accounts')->get();
        $this->assertSame([$old->id], $proposals->map(fn ($p) => (int) $p->subject_id)->all());

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $proposal = RetentionProposal::query()->where('area', 'portal_accounts')->firstOrFail();
        $service->approve($proposal, $admin);
        $this->assertNull($old->fresh()?->anonymized_at, 'approve anonymisiert noch nicht.');
        $service->purge($proposal->refresh(), $admin);

        $this->assertNotNull($old->fresh()?->anonymized_at);
        $this->assertNull($recent->fresh()?->anonymized_at);
        $this->assertNull($active->fresh()?->anonymized_at);
    }
}
