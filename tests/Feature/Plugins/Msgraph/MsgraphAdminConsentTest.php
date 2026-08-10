<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphAdminConsentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Msgraph;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Tenantweite Freigabe über den v2-Admin-Consent-Endpunkt: 'common' wird auf
 * 'organizations' abgebildet (persönliche Konten können nicht tenantweit
 * einwilligen), die Scope-Sätze aller org-gebundenen Grants werden voll
 * qualifiziert vereinigt, der state ist einmalig sowie org-/nutzergebunden,
 * und der Callback gibt nie error_description weiter (Trace-/Correlation-IDs).
 */
final class MsgraphAdminConsentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        config()->set('plugins.msgraph.client_id', 'test-client');
        config()->set('plugins.msgraph.client_secret', 'test-secret');
    }

    /**
     * Startet den Flow und liefert Redirect-URL + Query-Parameter.
     *
     * @return array{0: string, 1: array<int|string, mixed>}
     */
    private function startFlow(): array {
        $response = $this->actingAs($this->admin)->post(route('admin.msgraph.adminconsent.start'));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return [$location, $query];
    }

    public function test_start_redirects_to_v2_adminconsent_with_union_of_org_scopes(): void {
        [$location, $query] = $this->startFlow();

        // Default-Tenant 'common' → 'organizations' (am Adminconsent-Endpunkt unzulässig).
        $this->assertStringStartsWith('https://login.microsoftonline.com/organizations/v2.0/adminconsent?', $location);
        $this->assertSame('test-client', $query['client_id'] ?? null);
        $this->assertSame(route('admin.msgraph.adminconsent.callback'), $query['redirect_uri'] ?? null);
        $this->assertNotSame('', (string) ($query['state'] ?? ''));

        // Vereinigte Scope-Sätze: Graph-Scopes voll qualifiziert, OIDC-Scopes nackt.
        $scope = (string) ($query['scope'] ?? '');
        foreach (['Calendars.ReadWrite', 'Mail.Send', 'Contacts.ReadWrite', 'Tasks.ReadWrite', 'Files.Read.All', 'Sites.Read.All'] as $expected) {
            $this->assertStringContainsString('https://graph.microsoft.com/' . $expected, $scope);
        }
        $this->assertStringContainsString('offline_access', $scope);
        $this->assertStringNotContainsString('https://graph.microsoft.com/offline_access', $scope);
        // Backup-Scopes sind Instanz-Sache, nicht Teil der Org-Freigabe.
        $this->assertStringNotContainsString('Files.ReadWrite', $scope);
    }

    public function test_start_keeps_tenant_guid(): void {
        config()->set('plugins.msgraph.tenant', 'aaaabbbb-0000-cccc-1111-dddd2222eeee');

        [$location] = $this->startFlow();

        $this->assertStringStartsWith('https://login.microsoftonline.com/aaaabbbb-0000-cccc-1111-dddd2222eeee/v2.0/adminconsent?', $location);
    }

    public function test_start_requires_configuration(): void {
        config()->set('plugins.msgraph.client_id', '');

        $this->actingAs($this->admin)->post(route('admin.msgraph.adminconsent.start'))
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_flow_requires_admin(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('admin.msgraph.adminconsent.start'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.msgraph.adminconsent.callback'))->assertForbidden();
    }

    public function test_callback_success_flashes_and_audits_on_organization(): void {
        [, $query] = $this->startFlow();

        $this->actingAs($this->admin)
            ->get(route('admin.msgraph.adminconsent.callback', [
                'state' => $query['state'],
                'admin_consent' => 'True',
                'tenant' => 'aaaabbbb-0000-cccc-1111-dddd2222eeee',
            ]))
            ->assertRedirect(route('admin.msgraph.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $this->organization->getMorphClass(),
            'auditable_id' => $this->organization->id,
            'event' => 'msgraph.admin_consent_granted',
        ]);
    }

    public function test_callback_error_reports_code_but_never_description(): void {
        [, $query] = $this->startFlow();

        // Microsoft sendet auch im Fehlerfall admin_consent=True.
        $this->actingAs($this->admin)
            ->get(route('admin.msgraph.adminconsent.callback', [
                'state' => $query['state'],
                'admin_consent' => 'True',
                'error' => 'consent_required',
                'error_description' => 'AADSTS65004: denied. Trace ID: 0000aaaa',
            ]))
            ->assertRedirect(route('admin.msgraph.index'))
            ->assertSessionHas('error');

        $flash = (string) session('error');
        $this->assertStringContainsString('consent_required', $flash);
        $this->assertStringNotContainsString('Trace ID', $flash);
    }

    public function test_callback_state_is_single_use_and_forgery_rejected(): void {
        [, $query] = $this->startFlow();

        $this->actingAs($this->admin)
            ->get(route('admin.msgraph.adminconsent.callback', ['state' => $query['state'], 'admin_consent' => 'True']))
            ->assertSessionHas('success');

        // Replay desselben state → abgelehnt, kein zweites Audit.
        $this->actingAs($this->admin)
            ->get(route('admin.msgraph.adminconsent.callback', ['state' => $query['state'], 'admin_consent' => 'True']))
            ->assertSessionHas('error');
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('audit_logs')->where('event', 'msgraph.admin_consent_granted')->count());

        $this->actingAs($this->admin)
            ->get(route('admin.msgraph.adminconsent.callback', ['state' => 'forged', 'admin_consent' => 'True']))
            ->assertSessionHas('error');
    }
}
