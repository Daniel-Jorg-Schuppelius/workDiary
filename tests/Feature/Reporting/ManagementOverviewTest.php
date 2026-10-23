<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementOverviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\Sales\{LeadSource, LeadStatus};
use App\Enums\User\{Permission, UserRole};
use App\Mail\ManagementDigestMail;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\Lead;
use App\Models\Survey\{Survey, SurveyAnswer, SurveyQuestion, SurveyResponse};
use App\Services\Reporting\ManagementOverviewService;
use App\Services\Sales\LeadReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Leitungssicht und geplanter Leitungsbericht (Feature 184, MVP-1379–1381). */
class ManagementOverviewTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function lead(string $company): Lead {
        return Lead::query()->create([
            'organization_id' => $this->organization->id, 'company' => $company, 'contact_first_name' => 'Max', 'contact_last_name' => 'Muster',
            'source' => LeadSource::Web->value, 'status' => LeadStatus::New->value, 'created_by' => $this->admin->id,
        ]);
    }

    /** @return array<string, list<array{label: string, value: string}>> */
    private function overview(User $viewer): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $now = CarbonImmutable::now();
        $groups = app(ManagementOverviewService::class)->build($viewer, $this->organization, $now->startOfMonth(), $now);

        return array_map(static fn (array $kpis): array => array_map(static fn ($k): array => ['label' => $k->label, 'value' => $k->value], $kpis), $groups);
    }

    public function test_kpis_match_their_report_builders(): void {
        $this->lead('Zephyr A');
        $this->lead('Zephyr B');
        $survey = Survey::factory()->create(['organization_id' => $this->organization->id]);
        $question = SurveyQuestion::factory()->create(['organization_id' => $this->organization->id, 'survey_id' => $survey->id]);
        foreach ([10, 10, 5] as $score) {
            $response = SurveyResponse::query()->create(['organization_id' => $this->organization->id, 'survey_id' => $survey->id, 'context_kind' => 'manual']);
            SurveyAnswer::query()->create(['organization_id' => $this->organization->id, 'survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'value_int' => $score]);
        }
        $now = CarbonImmutable::now();

        $groups = $this->overview($this->admin);

        $leads = app(LeadReport::class)->build((int) $this->organization->id, $now->startOfMonth(), $now)['totals']['total'];
        $this->assertSame([['label' => __('reporting.management.kpi.new_leads'), 'value' => (string) $leads]], $groups['sales'] ?? null);
        // (2 Promotoren − 1 Detraktor) / 3 = 33.
        $this->assertSame('33', $groups['quality'][0]['value'] ?? null);
        $this->assertArrayHasKey('operations', $groups);
    }

    public function test_kpis_follow_the_right_of_their_report(): void {
        $this->lead('Zephyr A');
        $reporter = $this->orgUser();
        $reporter->givePermissionTo(Permission::ReportView->value);
        $reporter->unsetRelation('permissions');

        $groups = $this->overview($reporter);

        $this->assertArrayHasKey('operations', $groups);
        $this->assertArrayNotHasKey('sales', $groups);
        $this->assertArrayNotHasKey('finance', $groups);
    }

    public function test_page_shows_kpis_and_offers_the_pdf(): void {
        $this->lead('Zephyr A');

        $this->actingAs($this->admin)->get(route('reports.management'))
            ->assertOk()
            ->assertSee(__('reporting.management.group.sales'))
            ->assertSee(route('reports.management.pdf'), false);
        $this->actingAs($this->admin)->get(route('reports.management.pdf'))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->orgUser())->get(route('reports.management'))->assertForbidden();
    }

    public function test_digest_reaches_only_entitled_recipients_of_each_organization(): void {
        Mail::fake();
        $this->lead('Zephyr A');
        $management = $this->userWithRole(UserRole::Geschaeftsfuehrung->value);
        $worker = $this->orgUser();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->artisan('reports:management-digest', ['--organization' => $this->organization->id])->assertSuccessful();

        Mail::assertSent(ManagementDigestMail::class, fn (ManagementDigestMail $mail): bool => $mail->hasTo($this->admin->email));
        Mail::assertSent(ManagementDigestMail::class, fn (ManagementDigestMail $mail): bool => $mail->hasTo($management->email));
        Mail::assertNotSent(ManagementDigestMail::class, fn (ManagementDigestMail $mail): bool => $mail->hasTo($worker->email));
        Mail::assertNotSent(ManagementDigestMail::class, fn (ManagementDigestMail $mail): bool => $mail->hasTo($foreignAdmin->email));
        Mail::assertSent(ManagementDigestMail::class, fn (ManagementDigestMail $mail): bool => count($mail->attachments()) === 1);
    }
}
