<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Applications;

use App\Enums\User\UserRole;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\{Customer, Project, User};
use App\Services\Applications\TenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 068, MVP-184–187: Ausschreibungsakte — Go-/No-go, Pflicht-
 * Unterlagen vor Einreichung, versioniertes Einreichungspaket mit Hash,
 * Entscheidung mit Verlustgrund und kontrollierte Projekt-Überführung;
 * Rechte- und Mandantentrennung.
 */
final class TenderLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_full_lifecycle_from_capture_to_project_transfer(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        // Anlage über die UI (Teamleitung darf führen).
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $this->actingAs($lead)->post(route('tenders.store'), [
            'title' => 'Rahmenvertrag Wartung',
            'kind' => 'framework',
            'customer_id' => $customer->sqid,
            'submission_deadline' => now()->addDays(10)->toDateString(),
            'estimated_value' => '50000',
            'probability' => 60,
        ])->assertRedirect();

        $opportunity = ApplicationOpportunity::query()->firstOrFail();
        $this->assertSame('captured', $opportunity->status);

        // Pflicht-Anforderung offen → Einreichung blockiert (auch nach Go).
        $this->actingAs($lead)->post(route('tenders.requirements.store', $opportunity), [
            'label' => 'Referenzliste', 'kind' => 'proof',
        ])->assertRedirect();

        $service = app(TenderService::class);
        $service->decideGo($opportunity->refresh(), 'go', null, $admin);
        try {
            $service->submit($opportunity->refresh(), 'portal', null, $admin);
            $this->fail('Einreichung trotz offener Pflicht-Unterlage.');
        } catch (\RuntimeException) {
        }

        // Anforderung erledigen → Einreichung erzeugt Snapshot mit Hash.
        $requirement = $opportunity->requirements()->firstOrFail();
        $this->actingAs($lead)->put(route('tenders.requirements.update', [$opportunity, $requirement]), [
            'status' => 'done',
        ])->assertRedirect();

        $submission = $service->submit($opportunity->refresh(), 'portal', 'Erstabgabe', $admin);
        $this->assertSame(1, $submission->version);
        $this->assertSame(hash('sha256', (string) json_encode($submission->snapshot)), $submission->sha256);
        $this->assertSame('submitted', $opportunity->fresh()->status);

        // Zuschlag + Überführung in ein NEUES Projekt.
        $service->decide($opportunity->refresh(), 'won', null, $admin);
        $project = $service->transferToProject($opportunity->refresh(), null, $admin);
        $this->assertInstanceOf(Project::class, $project);
        $this->assertSame((int) $project->id, (int) $opportunity->fresh()->project_id);
    }

    public function test_loss_requires_reason_and_no_go_closes_file(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $opportunity = ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Testakte',
            'kind' => 'tender',
            'created_by' => $admin->id,
        ]);

        $service = app(TenderService::class);
        try {
            $service->decide($opportunity, 'lost', null, $admin);
            $this->fail('Verlust ohne Grund akzeptiert.');
        } catch (\RuntimeException) {
        }

        $service->decideGo($opportunity->refresh(), 'no_go', 'Kapazität fehlt', $admin);
        $fresh = $opportunity->fresh();
        $this->assertSame('withdrawn', $fresh->status);
        $this->assertSame('Kapazität fehlt', $fresh->loss_reason);
    }

    public function test_access_is_permission_and_tenant_scoped(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $opportunity = ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Interna',
            'kind' => 'tender',
            'created_by' => $admin->id,
        ]);

        // Normale Rolle (User) sieht Ausschreibungen NICHT.
        $plain = $this->userWithRole(UserRole::User->value);
        $this->actingAs($plain)->get(route('tenders.index'))->assertForbidden();
        $this->actingAs($plain)->get(route('tenders.show', $opportunity))->assertForbidden();

        // Buchhaltung darf lesen, aber nicht entscheiden.
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);
        $this->actingAs($accounting)->get(route('tenders.show', $opportunity))->assertOk();
        $this->actingAs($accounting)->post(route('tenders.go', $opportunity), ['decision' => 'go'])->assertForbidden();

        // Fremde Organisation: 404 durch Tenant-Scope.
        $otherOrg = \App\Models\Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        app()->instance('currentOrganization', $otherOrg);
        $this->actingAs($foreignAdmin)->get(route('tenders.show', $opportunity))->assertNotFound();
    }
}
