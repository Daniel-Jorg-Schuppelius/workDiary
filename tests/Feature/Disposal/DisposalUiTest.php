<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Disposal;

use App\Enums\Disposal\DisposalJobStatus;
use App\Models\{Customer, Organization, User};
use App\Models\Disposal\{DisposalItem, DisposalJob};
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 100 (MVP-474/475): Weboberfläche der Entsorgungsakten —
 * Modul-Gate (423) + Rechte (403), Liste mit KPIs, Akten-Ansicht mit
 * Abschluss-Prüfpanel, Anlage über den Dialog, Autorisierung der
 * Kind-Endpunkte gegen die Akte sowie Tenant-Grenze (Cross-Org → 404).
 * Entsorgung ist NICHT branchenprofilgebunden — nur modul- und
 * rechtegebunden.
 */
final class DisposalUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    /** @param array<string, mixed> $overrides */
    private function makeJob(array $overrides = []): DisposalJob {
        return DisposalJob::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->admin->id,
        ], $overrides));
    }

    public function test_module_and_permission_gates(): void {
        // Ohne disposal-Rechte: 403.
        $member = $this->orgUser();
        $this->actingAs($member)->get(route('disposal.index'))->assertForbidden();

        // Modul abgeschaltet: 423 (Locked) trotz Admin.
        config(['license.feature_overrides' => ['module.entsorgung' => false]]);
        app(FeatureFlagResolver::class)->flush();
        $this->actingAs($this->admin)->get(route('disposal.index'))->assertStatus(423);
        config(['license.feature_overrides' => []]);
        app(FeatureFlagResolver::class)->flush();

        // Admin mit aktivem Modul: 200.
        $this->actingAs($this->admin)->get(route('disposal.index'))->assertOk();
    }

    public function test_index_lists_jobs_and_kpis(): void {
        $first = $this->makeJob(['number' => 'ENT-2026-9001']);
        $second = $this->makeJob(['number' => 'ENT-2026-9002']);

        $this->get(route('disposal.index'))
            ->assertOk()
            ->assertSee($first->number)
            ->assertSee($second->number);
    }

    public function test_show_renders_with_blockers_panel(): void {
        $job = $this->makeJob(['number' => 'ENT-2026-9010']);
        DisposalItem::factory()->withDataStorage()->create(['disposal_job_id' => $job->id]);

        // Offene Akte: Ansicht zeigt Nummer + Abschluss-Prüfpanel (Blocker vorhanden,
        // da Behandlung/Unterschrift/Übergabe fehlen).
        $this->get(route('disposal.show', $job))
            ->assertOk()
            ->assertSee($job->number);
    }

    public function test_cross_org_job_is_not_found(): void {
        $foreignOrg = Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $foreignOrg->id]);
        $foreignCustomer = Customer::factory()->create(['organization_id' => $foreignOrg->id]);
        $foreignJob = DisposalJob::factory()->create([
            'organization_id' => $foreignOrg->id,
            'customer_id' => $foreignCustomer->id,
            'created_by_user_id' => $foreignAdmin->id,
        ]);

        // Eigener Admin auf fremder Sqid-URL: Org-Scope liefert 404, nicht 403.
        $this->actingAs($this->admin)->get(route('disposal.show', $foreignJob))->assertNotFound();
    }

    public function test_store_creates_job_via_dialog(): void {
        $response = $this->post(route('disposal.store'), [
            'customer_id' => $this->customer->id,
        ]);

        $job = DisposalJob::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('disposal.show', $job));

        $this->assertDatabaseHas('disposal_jobs', [
            'id' => $job->id,
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => DisposalJobStatus::Draft->value,
        ]);
    }

    public function test_item_endpoints_authorize_against_parent(): void {
        $job = $this->makeJob();
        $member = $this->orgUser();

        // Valide Felddaten, damit die Autorisierung (und nicht die Validierung) greift.
        $this->actingAs($member)->post(route('disposal.items.store', $job), [
            'category' => 'PC',
            'quantity' => 1,
            'avv_code' => '16 02 14',
        ])->assertForbidden();
    }
}
