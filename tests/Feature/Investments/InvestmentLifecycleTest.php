<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Investments;

use App\Enums\User\UserRole;
use App\Models\Investments\InvestmentCase;
use App\Models\{Organization, User};
use App\Services\Investments\InvestmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 069, MVP-200–207: Investitionsakte — Schwellenwert-Freigabekette
 * (Vier-Augen ab Grenze, Selbstfreigabe-Sperre), eingefrorener
 * Budget-Snapshot, Sperre gegen stille Erhöhung (Nachtrag über genehmigte
 * Abweichung), Ist-Wert-Projektion und Nachbewertung; Rechte-/Tenant-Schutz.
 */
final class InvestmentLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $second;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->second = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function userWithRole(string $role): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);
        $orgRole = Role::query()->where('name', $role)->where('team_id', $this->organization->id)->firstOrFail();
        $user->syncRoles([$orgRole]);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user;
    }

    private function makeCase(): InvestmentCase {
        return InvestmentCase::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Ersatz Servicefahrzeug',
            'category' => 'machine',
            'status' => 'comparison',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_budget_below_threshold_needs_single_approval_and_freezes_snapshot(): void {
        $service = app(InvestmentService::class);
        $case = $this->makeCase();

        $request = $service->submitBudget($case, ['amount' => '5000.00'], $this->admin);
        $this->assertSame(1, $request->approvals()->count(), 'Unter der Schwelle genügt eine Stufe.');
        $this->assertSame('in_approval', $case->fresh()->status);

        // Selbstfreigabe-Sperre: Antragsteller darf nicht freigeben.
        try {
            $service->approveBudget($request, $this->admin);
            $this->fail('Selbstfreigabe wurde akzeptiert.');
        } catch (\RuntimeException) {
        }

        $result = $service->approveBudget($request, $this->second);
        $this->assertSame('approved_all', $result);
        $fresh = $request->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame('5000.00', (string) data_get($fresh->snapshot, 'amount'));
        $this->assertSame('approved', $case->fresh()->status);
    }

    public function test_budget_at_threshold_requires_four_eyes_chain(): void {
        $service = app(InvestmentService::class);
        $case = $this->makeCase();

        $request = $service->submitBudget($case, ['amount' => '25000.00'], $this->admin);
        $this->assertSame(2, $request->approvals()->count(), 'Ab der Schwelle: Vier-Augen (2 Stufen).');

        $this->assertSame('pending', $service->approveBudget($request, $this->second));
        $this->assertSame('approved_all', $service->approveBudget($request->fresh(), $this->second));
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_supplement_requires_approved_budget_deviation_and_supersedes(): void {
        $service = app(InvestmentService::class);
        $case = $this->makeCase();
        $request = $service->submitBudget($case, ['amount' => '5000.00'], $this->admin);
        $service->approveBudget($request, $this->second);

        // Abweichung melden (durch Antragsteller) + entscheiden (zweite Person).
        $deviation = $case->deviations()->create([
            'organization_id' => $this->organization->id,
            'kind' => 'budget',
            'description' => 'Lieferant erhöht Preis',
            'amount_delta' => '1500.00',
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        // Nachtrag ohne genehmigte Abweichung → gesperrt (keine stille Erhöhung).
        try {
            $service->supplementBudget($case->refresh(), $deviation, ['amount' => '6500.00'], $this->admin);
            $this->fail('Nachtrag ohne genehmigte Abweichung akzeptiert.');
        } catch (\RuntimeException) {
        }

        // Selbstentscheidung der Abweichung ist gesperrt.
        try {
            $service->decideDeviation($deviation, 'approved', null, $this->admin);
            $this->fail('Selbstfreigabe der Abweichung akzeptiert.');
        } catch (\RuntimeException) {
        }
        $service->decideDeviation($deviation, 'approved', 'ok', $this->second);

        $supplement = $service->supplementBudget($case->refresh(), $deviation->refresh(), ['amount' => '6500.00'], $this->admin);
        $this->assertSame(2, $supplement->version);
        $this->assertSame('superseded', $request->fresh()->status, 'Alter genehmigter Stand bleibt als superseded erhalten.');
        $service->approveBudget($supplement, $this->second);
        $this->assertSame('6500.00', (string) $case->refresh()->approvedBudget()?->amount);
    }

    public function test_projection_sums_actuals_and_linked_assets(): void {
        $service = app(InvestmentService::class);
        $case = $this->makeCase();
        $request = $service->submitBudget($case, ['amount' => '9000.00'], $this->admin);
        $service->approveBudget($request, $this->second);

        $case->actuals()->create([
            'organization_id' => $this->organization->id,
            'source' => 'manual',
            'amount' => '2500.00',
            'occurred_on' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);
        $asset = \App\Models\Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'acquisition_cost' => '4000.00',
        ]);
        $case->links()->create([
            'organization_id' => $this->organization->id,
            'linkable_type' => $asset->getMorphClass(),
            'linkable_id' => $asset->id,
            'created_by' => $this->admin->id,
        ]);

        $projection = $service->projection($case->refresh());
        $this->assertSame(9000.0, $projection['approved']);
        $this->assertSame(6500.0, $projection['actual']);
        $this->assertSame(2500.0, $projection['remaining']);
    }

    public function test_ui_flow_review_and_access_control(): void {
        // Anlage über die UI (Buchhaltung darf führen + freigeben).
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);
        $this->actingAs($accounting)->post(route('investments.store'), [
            'title' => 'Serverschrank',
            'category' => 'it',
            'urgency' => 'medium',
        ])->assertRedirect();
        $case = InvestmentCase::query()->firstOrFail();

        // Normale Rolle sieht nichts.
        $plain = $this->userWithRole(UserRole::User->value);
        $this->actingAs($plain)->get(route('investments.index'))->assertForbidden();
        $this->actingAs($plain)->get(route('investments.show', $case))->assertForbidden();

        // Nachbewertung erst nach Abschluss; Statuswechsel in Umsetzung
        // verlangt genehmigtes Budget.
        $this->actingAs($accounting)->post(route('investments.status', $case), ['status' => 'in_progress'])
            ->assertSessionHas('error');

        $service = app(InvestmentService::class);
        $request = $service->submitBudget($case->refresh(), ['amount' => '900.00'], $accounting);
        $service->approveBudget($request, $this->second);
        $this->actingAs($accounting)->post(route('investments.status', $case), ['status' => 'in_progress'])->assertSessionHas('status');
        $this->actingAs($accounting)->post(route('investments.status', $case), ['status' => 'completed'])->assertSessionHas('status');

        $this->actingAs($accounting)->post(route('investments.review.store', $case), [
            'benefit_result' => 'Ausfallzeiten halbiert.',
        ])->assertSessionHas('status');
        $this->assertSame('post_review', $case->fresh()->status);

        // Fremde Org: 404.
        $otherOrg = Organization::factory()->create();
        $foreign = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        app()->instance('currentOrganization', $otherOrg);
        $this->actingAs($foreign)->get(route('investments.show', $case))->assertNotFound();
    }

    public function test_report_renders_and_exports(): void {
        $service = app(InvestmentService::class);
        $case = $this->makeCase();
        $request = $service->submitBudget($case, ['amount' => '5000.00'], $this->admin);
        $service->approveBudget($request, $this->second);

        $this->actingAs($this->admin)->get(route('investments.report'))
            ->assertOk()
            ->assertSee('Ersatz Servicefahrzeug');
        $csv = $this->actingAs($this->admin)->get(route('investments.report', ['export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('Ersatz Servicefahrzeug', (string) $csv->getContent());
    }
}
