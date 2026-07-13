<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeTimeExportTenantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, Organization, PluginSetting, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tenant-Regressionstest für den Lexoffice-Zeiten-Export (Bauturbo A17,
 * MVP-335). Quelle: ../WorkDiary-Architecture/security/tenant-audit-2026.md:278
 * ("Lexoffice Time-Export — offen (Plugin, separate Tests)"); laut Audit
 * gehört der Test in die Plugin-Suite, nicht nach tests/Feature/Tenant/.
 *
 * `POST /customers/{customer}/lexoffice/time-export` darf für einen Kunden
 * einer fremden Organisation nie ausführbar sein: Sqid-Binding +
 * OrganizationScope lösen den fremden Kunden nicht auf (404), es geht kein
 * einziger HTTP-Call Richtung Lexoffice raus — egal ob das Plugin in der
 * Angreifer- oder in der Ziel-Organisation aktiviert ist.
 */
class LexofficeTimeExportTenantTest extends TestCase {
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private Customer $customerB;

    protected function setUp(): void {
        parent::setUp();

        $this->orgA = Organization::factory()->create(['slug' => 'lex-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'lex-b']);

        $this->adminA = User::factory()->admin()->create(['organization_id' => $this->orgA->id]);

        $this->customerB = $this->withOrg($this->orgB, fn (): Customer => Customer::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Geheimkunde Org B',
        ]));
    }

    public function test_time_export_for_cross_org_customer_is_not_found_and_sends_nothing(): void {
        // Plugin in der Organisation des Angreifers aktiv — trotzdem darf der
        // fremde Kunde nicht adressierbar sein.
        $this->enablePluginFor($this->orgA);
        Http::fake();

        $this->actingAs($this->adminA)
            ->post(route('customers.lexoffice.time-export', $this->customerB), [
                'from' => now()->subMonth()->toDateString(),
                'to' => now()->toDateString(),
            ])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_time_export_is_blocked_even_when_plugin_active_in_target_org(): void {
        // Plugin (nur) in der Ziel-Org B aktiv — der Org-A-Admin darf den
        // Export für den Org-B-Kunden dennoch nicht auslösen.
        $this->enablePluginFor($this->orgB);
        Http::fake();

        $this->actingAs($this->adminA)
            ->post(route('customers.lexoffice.time-export', $this->customerB), [
                'from' => now()->subMonth()->toDateString(),
                'to' => now()->toDateString(),
            ])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    private function enablePluginFor(Organization $organization): void {
        PluginSetting::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'test-key'],
        ]);
    }

    /**
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withOrg(Organization $org, \Closure $callback): mixed {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $org);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}
