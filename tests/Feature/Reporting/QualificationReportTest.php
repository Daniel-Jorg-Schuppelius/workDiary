<?php
/*
 * Created on   : Thu Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\Hr\Qualification;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class QualificationReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_route_renders(): void {
        $this->actingAs($this->admin)
            ->get(route('reports.qualifications'))
            ->assertOk();
    }

    public function test_csv_export(): void {
        $response = $this->actingAs($this->admin)
            ->get(route('reports.qualifications', ['export' => 'csv']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('#report:qualifications', (string) $response->getContent());
        $this->assertStringContainsString('Mitarbeiter', (string) $response->getContent());
    }

    public function test_inactive_qualifications_are_left_out(): void {
        $active = Qualification::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ersthelfer', 'is_active' => true]);
        $retired = Qualification::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Altschein', 'is_active' => false]);
        $worker = $this->orgUser();
        $worker->qualifications()->attach($active->id, ['valid_until' => '2099-12-31']);
        $worker->qualifications()->attach($retired->id, ['valid_until' => '2000-01-31']);
        $retiredOnly = $this->orgUser();
        $retiredOnly->qualifications()->attach($retired->id);

        $response = $this->actingAs($this->admin)->get(route('reports.qualifications'))->assertOk();

        $this->assertSame([(int) $active->id], $response->viewData('qualifications')->pluck('id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame([(int) $worker->id], $response->viewData('users')->pluck('id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(0, $response->viewData('totals')['expired']);
        $this->assertSame(1, $response->viewData('totals')['assignments']);
    }

    public function test_requires_authentication(): void {
        $this->get(route('reports.qualifications'))->assertRedirect(route('login'));
    }
}
