<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementInsightsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\Learning\LearningCourseStatus;
use App\Models\Customer\Customer;
use App\Models\Learning\{Competency, CompetencyRequirement, LearningCourse};
use App\Models\Platform\User;
use App\Models\ServiceTicket\ServiceTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-926: wiederkehrende Probleme und Schulungsbedarf. */
final class ManagementInsightsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-09-28 09:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_recurring_tickets_are_reported_above_the_threshold(): void {
        $often = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Oft GmbH']);
        $rare = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Selten KG']);
        foreach ([[$often, 3], [$rare, 2]] as [$customer, $count]) {
            ServiceTicket::factory()->count($count)->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'asset_id' => null, 'reported_at' => now()->subDays(10)]);
        }
        ServiceTicket::factory()->count(3)->create(['organization_id' => $this->organization->id, 'customer_id' => $rare->id, 'asset_id' => null, 'reported_at' => now()->subDays(200)]);

        $this->actingAs($this->admin)->get(route('reports.management'))->assertOk()
            ->assertSee(__('reporting.warning.recurring_tickets.title', ['name' => 'Oft GmbH']))
            ->assertDontSee(__('reporting.warning.recurring_tickets.title', ['name' => 'Selten KG']));
    }

    public function test_training_needs_list_gaps_and_matching_courses(): void {
        $competency = Competency::query()->create(['organization_id' => $this->organization->id, 'code' => 'psa', 'name' => 'PSA gegen Absturz', 'max_level' => 4, 'is_active' => true]);
        CompetencyRequirement::query()->create(['organization_id' => $this->organization->id, 'competency_id' => $competency->id, 'subject_kind' => 'role', 'subject_key' => 'user', 'required_level' => 2, 'is_active' => true]);
        User::factory()->user()->count(2)->create(['organization_id' => $this->organization->id]);
        LearningCourse::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Höhensicherung kompakt', 'status' => LearningCourseStatus::Released->value, 'competency_id' => $competency->id, 'competency_level' => 2]);

        $this->actingAs($this->admin)->get(route('reports.management'))->assertOk()
            ->assertSee('PSA gegen Absturz')
            ->assertSee('Höhensicherung kompakt');
    }

    public function test_only_report_viewers_may_open_it(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->get(route('reports.management'))->assertForbidden();
    }
}
