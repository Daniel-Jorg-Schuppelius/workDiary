<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SuitabilityMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Enums\Applications\JobRequisitionStatus;
use App\Enums\User\UserRole;
use App\Models\Applications\{JobApplication, JobApplicationRating, JobRequisition};
use App\Models\Learning\{Competency, CompetencyRequirement};
use App\Models\Platform\User;
use App\Services\Applications\RecruitingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-924: Eignungsmatrix — Soll der Stelle, Einschätzung je Bewerbung, Matrix. */
final class SuitabilityMatrixTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $hr;

    private JobRequisition $requisition;

    private Competency $safety;

    private Competency $electrics;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->hr = $this->userWithRole(UserRole::Personalverwaltung->value);
        $this->requisition = JobRequisition::query()->create(['organization_id' => $this->organization->id, 'title' => 'Servicetechniker:in', 'status' => JobRequisitionStatus::Open]);
        $this->safety = Competency::query()->create(['organization_id' => $this->organization->id, 'code' => 'psa', 'name' => 'PSA gegen Absturz', 'max_level' => 4, 'is_active' => true]);
        $this->electrics = Competency::query()->create(['organization_id' => $this->organization->id, 'code' => 'efk', 'name' => 'Elektrofachkraft', 'max_level' => 4, 'is_active' => true]);
    }

    private function apply(string $name): JobApplication {
        return app(RecruitingService::class)->intake(['job_requisition_id' => $this->requisition->id, 'candidate_name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test', 'source' => 'website'], $this->hr)['application'];
    }

    public function test_requirements_ratings_and_matrix(): void {
        $store = route('recruiting.requisitions.suitability.requirements.store', $this->requisition);
        $this->actingAs($this->hr)->post($store, ['competency_id' => $this->safety->sqid, 'required_level' => 3])->assertSessionHas('success');
        $this->actingAs($this->hr)->post($store, ['competency_id' => $this->electrics->sqid, 'required_level' => 9])->assertSessionHas('success');
        $this->assertSame(4, (int) CompetencyRequirement::query()->where('competency_id', $this->electrics->id)->value('required_level'));

        $kim = $this->apply('Kim Neu');
        $alex = $this->apply('Alex Alt');
        $this->actingAs($this->hr)->get(route('recruiting.applications.show', $kim))->assertOk()->assertSee(__('recruiting.suitability.rating_title'));
        $rate = fn (JobApplication $a, Competency $c, int $level) => $this->actingAs($this->hr)->post(route('recruiting.applications.ratings.store', $a), ['competency_id' => $c->sqid, 'level' => $level, 'note' => 'Gespräch'])->assertSessionHas('success');
        $rate($kim, $this->safety, 3);
        $rate($kim, $this->electrics, 4);
        $rate($alex, $this->safety, 1);
        $rate($alex, $this->safety, 2);

        $this->assertSame(3, JobApplicationRating::query()->count());
        $html = (string) $this->actingAs($this->hr)->get(route('recruiting.requisitions.suitability', $this->requisition))->assertOk()->getContent();
        // Kim erfüllt alles (100 %), Alex 2 von 7 Punkten (28 %), Kim steht oben.
        $this->assertStringContainsString('100 %', $html);
        $this->assertStringContainsString('28 %', $html);
        $this->assertLessThan(strpos($html, 'Alex Alt'), strpos($html, 'Kim Neu'));
    }

    public function test_ratings_are_part_of_the_access_request_and_anonymization(): void {
        $kim = $this->apply('Kim Neu');
        $this->actingAs($this->hr)->post(route('recruiting.applications.ratings.store', $kim), ['competency_id' => $this->safety->sqid, 'level' => 2, 'note' => 'sehr sicher'])->assertSessionHas('success');

        $export = $this->actingAs($this->hr)->get(route('recruiting.applications.export', $kim))->assertOk()->json();
        $this->assertSame('PSA gegen Absturz', $export['competency_ratings'][0]['competency']);

        $this->actingAs($this->hr)->post(route('recruiting.applications.anonymize', $kim))->assertRedirect();
        $this->assertNull(JobApplicationRating::query()->sole()->note);
    }

    public function test_other_roles_cannot_rate(): void {
        $kim = $this->apply('Kim Neu');
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $this->actingAs($lead)->post(route('recruiting.applications.ratings.store', $kim), ['competency_id' => $this->safety->sqid, 'level' => 2])->assertForbidden();
        $this->actingAs($lead)->get(route('recruiting.requisitions.suitability', $this->requisition))->assertForbidden();
    }
}
