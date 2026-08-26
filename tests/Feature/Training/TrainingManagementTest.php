<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingManagementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Training;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\Training\{TrainingAssignmentState, TrainingRequirementSubject};
use App\Enums\User\UserRole;
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\{Organization, Team, User};
use App\Models\Safety\SafetyInstruction;
use App\Models\Training\{TrainingAssignment, TrainingCourse, TrainingCourseVersion, TrainingRequirement};
use App\Services\Safety\SafetyInstructionService;
use App\Services\Training\{TrainingAssignmentService, TrainingCatalogService};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Trainingsmanagement (Feature 145, MVP-727): Katalog inkl. Kursversionen,
 * Pflichtmatrix → Soll-Einträge, Nachweis über das Arbeitsschutz-Register
 * (132) statt eigener Nachweis-Tabelle, Fristen-Scan mit Dedup, Auswertung
 * inkl. Export sowie Tenancy und Rechte.
 */
class TrainingManagementTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function lead(): User {
        return User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    private function field(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    private function catalog(): TrainingCatalogService {
        return app(TrainingCatalogService::class);
    }

    private function assignments(): TrainingAssignmentService {
        return app(TrainingAssignmentService::class);
    }

    /** @param array<string, mixed> $attributes */
    private function course(array $attributes = []): TrainingCourse {
        return $this->catalog()->createCourse($this->organization, null, array_merge([
            'title' => 'Brandschutzhelfer',
            'validity_months' => 12,
            'lead_days' => 30,
        ], $attributes));
    }

    // ── Rechte ───────────────────────────────────────────────────────────

    public function test_lead_sees_all_three_training_pages(): void {
        $lead = $this->lead();

        $this->actingAs($lead)->get(route('training.courses.index'))->assertOk()->assertViewIs('training.courses.index');
        $this->actingAs($lead)->get(route('training.requirements.index'))->assertOk()->assertViewIs('training.requirements.index');
        $this->actingAs($lead)->get(route('training.assignments.index'))->assertOk()->assertViewIs('training.assignments.index');
    }

    public function test_field_staff_cannot_view_or_manage_training(): void {
        $field = $this->field();

        $this->actingAs($field)->get(route('training.courses.index'))->assertForbidden();
        $this->actingAs($field)->get(route('training.requirements.index'))->assertForbidden();
        $this->actingAs($field)->post(route('training.courses.store'), ['title' => 'Test'])->assertForbidden();
        $this->actingAs($field)->post(route('training.requirements.sync'))->assertForbidden();
    }

    public function test_hr_role_also_manages_training(): void {
        $hr = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($hr)->get(route('training.courses.index'))->assertOk();
        $this->assertTrue($hr->can('create', TrainingCourse::class));
    }

    // ── Katalog ──────────────────────────────────────────────────────────

    public function test_course_store_via_http_creates_first_version(): void {
        $lead = $this->lead();

        $this->actingAs($lead)
            ->post(route('training.courses.store'), [
                'title' => 'Ladungssicherung',
                'provider_kind' => 'external',
                'provider_name' => 'TÜV',
                'validity_months' => 24,
                'lead_days' => 45,
                'legal_basis' => 'VDI 2700',
                'is_mandatory' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $course = TrainingCourse::query()->firstOrFail();
        $this->assertSame('Ladungssicherung', $course->title);
        $this->assertSame('ladungssicherung', $course->code);
        $this->assertSame(24, $course->validity_months);
        $this->assertSame(45, $course->lead_days);
        $this->assertTrue($course->is_mandatory);
        $this->assertSame(1, $course->versions()->count());
        $this->assertTrue($course->currentVersion()?->is_current);
    }

    public function test_course_detail_and_dialogs_render(): void {
        $lead = $this->lead();
        $course = $this->course();
        $this->catalog()->addVersion($course, ['label' => '2026-01']);

        $this->actingAs($lead)
            ->get(route('training.courses.show', $course))
            ->assertOk()
            ->assertViewIs('training.courses.show')
            ->assertSee('v2 · 2026-01')
            ->assertSee($course->code);

        $this->actingAs($lead)->get(route('training.courses.create'))->assertOk()->assertViewIs('training.courses._form_dialog');
        $this->actingAs($lead)->get(route('training.courses.edit', $course))->assertOk();
        $this->actingAs($lead)->get(route('training.courses.versions.create', $course))->assertOk()->assertViewIs('training.courses._version_dialog');
        $this->actingAs($lead)->get(route('training.requirements.create'))->assertOk()->assertViewIs('training.requirements._form_dialog');
        $this->actingAs($lead)->get(route('training.assignments.create'))->assertOk()->assertViewIs('training.assignments._form_dialog');
    }

    public function test_course_code_stays_unique_per_organization(): void {
        $first = $this->course(['title' => 'Erste Hilfe']);
        $second = $this->course(['title' => 'Erste Hilfe']);

        $this->assertSame('erste-hilfe', $first->code);
        $this->assertSame('erste-hilfe-2', $second->code);
    }

    public function test_new_version_becomes_current_and_last_version_is_protected(): void {
        $course = $this->course();
        $v2 = $this->catalog()->addVersion($course, ['label' => '2026-01']);

        $this->assertSame(2, $v2->version);
        $this->assertTrue($v2->is_current);
        $this->assertFalse($course->versions()->where('version', 1)->firstOrFail()->is_current);
        $this->assertSame('v2 · 2026-01', $v2->displayLabel());

        $this->catalog()->deleteVersion($v2);
        $this->assertSame(1, $course->versions()->count());
        $this->assertTrue($course->versions()->firstOrFail()->refresh()->is_current);

        $this->expectException(ValidationException::class);
        $this->catalog()->deleteVersion($course->versions()->firstOrFail());
    }

    public function test_lead_time_change_moves_the_notify_window_of_open_assignments(): void {
        $course = $this->course(['lead_days' => 30]);
        $person = $this->field();
        $assignment = $this->assignments()->assignManually($this->organization, $person, $course, Carbon::today()->addDays(100)->toDateString());

        $this->assertSame(Carbon::today()->addDays(70)->toDateString(), $assignment->notify_from?->toDateString());

        $this->catalog()->updateCourse($course, ['lead_days' => 10]);

        $this->assertSame(Carbon::today()->addDays(90)->toDateString(), $assignment->refresh()->notify_from?->toDateString());
    }

    public function test_course_with_proof_cannot_be_deleted(): void {
        $lead = $this->lead();
        $person = $this->field();
        $course = $this->course();
        $this->assignments()->assignManually($this->organization, $person, $course);
        $this->instructionFor($course, $lead, [$person->id]);

        $this->expectException(ValidationException::class);
        $this->catalog()->deleteCourse($course);
    }

    // ── Pflichtmatrix → Soll ─────────────────────────────────────────────

    public function test_requirement_for_a_role_creates_assignments_per_employee(): void {
        $course = $this->course(['lead_days' => 20]);
        $field = $this->field();
        $this->lead();

        TrainingRequirement::query()->create([
            'organization_id' => $this->organization->id,
            'training_course_id' => $course->id,
            'subject_kind' => TrainingRequirementSubject::Role->value,
            'subject_key' => UserRole::Aussendienst->value,
            'first_due_days' => 60,
            'is_active' => true,
            'source' => 'manual',
        ]);

        $result = $this->assignments()->syncOrganization($this->organization);

        $this->assertSame(1, $result['created']);
        $assignment = TrainingAssignment::query()->firstOrFail();
        $this->assertSame($field->id, $assignment->user_id);
        $this->assertSame(Carbon::today()->addDays(60)->toDateString(), $assignment->due_at?->toDateString());
        $this->assertSame(Carbon::today()->addDays(40)->toDateString(), $assignment->notify_from?->toDateString());
        $this->assertSame('requirement', $assignment->source);

        // Idempotent: ein zweiter Lauf legt nichts doppelt an.
        $this->assertSame(0, $this->assignments()->syncOrganization($this->organization)['created']);
        $this->assertSame(1, TrainingAssignment::query()->count());
    }

    public function test_requirement_for_a_team_resolves_team_members(): void {
        $course = $this->course();
        $member = $this->orgUser();
        $outsider = $this->orgUser();
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach($member->id);

        TrainingRequirement::query()->create([
            'organization_id' => $this->organization->id,
            'training_course_id' => $course->id,
            'subject_kind' => TrainingRequirementSubject::Team->value,
            'subject_key' => (string) $team->id,
            'first_due_days' => 10,
            'is_active' => true,
            'source' => 'manual',
        ]);

        $this->assignments()->syncOrganization($this->organization);

        $this->assertSame(1, TrainingAssignment::query()->count());
        $this->assertSame($member->id, TrainingAssignment::query()->firstOrFail()->user_id);
        $this->assertSame(0, TrainingAssignment::query()->where('user_id', $outsider->id)->count());
    }

    public function test_dropping_a_requirement_removes_only_unproven_assignments(): void {
        $lead = $this->lead();
        $course = $this->course();
        $withProof = $this->field();
        $withoutProof = $this->field();

        $requirement = TrainingRequirement::query()->create([
            'organization_id' => $this->organization->id,
            'training_course_id' => $course->id,
            'subject_kind' => TrainingRequirementSubject::Role->value,
            'subject_key' => UserRole::Aussendienst->value,
            'first_due_days' => 30,
            'is_active' => true,
            'source' => 'manual',
        ]);
        $this->assignments()->syncOrganization($this->organization);
        $this->assertSame(2, TrainingAssignment::query()->count());

        $this->instructionFor($course, $lead, [$withProof->id]);
        $requirement->update(['is_active' => false]);
        $this->assignments()->syncOrganization($this->organization);

        $this->assertSame(1, TrainingAssignment::query()->count());
        $this->assertSame($withProof->id, TrainingAssignment::query()->firstOrFail()->user_id);
        $this->assertSame(0, TrainingAssignment::query()->where('user_id', $withoutProof->id)->count());
    }

    public function test_requirement_store_via_http_syncs_assignments(): void {
        $lead = $this->lead();
        $field = $this->field();
        $course = $this->course();

        $this->actingAs($lead)
            ->post(route('training.requirements.store'), [
                'training_course_id' => Sqid::encode(TrainingCourse::class, $course->id),
                'subject_kind' => TrainingRequirementSubject::Role->value,
                'subject_role' => UserRole::Aussendienst->value,
                'first_due_days' => 15,
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('training_requirements', [
            'organization_id' => $this->organization->id,
            'training_course_id' => $course->id,
            'subject_kind' => TrainingRequirementSubject::Role->value,
            'subject_key' => UserRole::Aussendienst->value,
        ]);
        $this->assertSame(1, TrainingAssignment::query()->where('user_id', $field->id)->count());
    }

    // ── Nachweis über das Arbeitsschutz-Register ─────────────────────────

    public function test_instruction_participation_fulfils_the_assignment_and_schedules_the_repeat(): void {
        $lead = $this->lead();
        $person = $this->field();
        $course = $this->course(['validity_months' => 12, 'lead_days' => 30]);
        $assignment = $this->assignments()->assignManually($this->organization, $person, $course, Carbon::today()->addDays(10)->toDateString());
        $version = $this->catalog()->addVersion($course, ['label' => '2026-01']);

        $heldOn = Carbon::today()->subDays(3);
        $instruction = $this->instructionFor($course, $lead, [$person->id], $heldOn->toDateString(), $version);

        $assignment->refresh();
        $this->assertSame($heldOn->toDateString(), $assignment->fulfilled_at?->toDateString());
        $this->assertSame($instruction->id, $assignment->fulfilled_instruction_id);
        $this->assertSame(2, $assignment->fulfilled_course_version);
        $this->assertNotNull($assignment->fulfilled_participant_id);
        $this->assertSame($heldOn->copy()->addMonthsNoOverflow(12)->toDateString(), $assignment->due_at?->toDateString());
        $this->assertSame($heldOn->copy()->addMonthsNoOverflow(12)->subDays(30)->toDateString(), $assignment->notify_from?->toDateString());
        $this->assertSame(TrainingAssignmentState::Fulfilled, $assignment->state());

        // Kein zweiter Nachweis-Speicher: die Teilnahme bleibt die einzige Quelle.
        $this->assertSame(1, $instruction->participants()->count());
    }

    public function test_instruction_without_course_reference_changes_nothing(): void {
        $lead = $this->lead();
        $person = $this->field();
        $course = $this->course();
        $assignment = $this->assignments()->assignManually($this->organization, $person, $course);

        app(SafetyInstructionService::class)->create($this->organization, $lead, [
            'topic' => 'Ohne Kursbezug',
            'held_on' => Carbon::today()->toDateString(),
        ], [$person->id]);

        $this->assertNull($assignment->refresh()->fulfilled_at);
    }

    public function test_removing_the_participant_reopens_the_assignment(): void {
        $lead = $this->lead();
        $person = $this->field();
        $course = $this->course();
        $assignment = $this->assignments()->assignManually($this->organization, $person, $course);
        $instruction = $this->instructionFor($course, $lead, [$person->id]);
        $this->assertNotNull($assignment->refresh()->fulfilled_at);

        app(SafetyInstructionService::class)->update($instruction, [], []);

        $assignment->refresh();
        $this->assertNull($assignment->fulfilled_at);
        $this->assertNull($assignment->fulfilled_instruction_id);
        $this->assertSame(Carbon::today()->toDateString(), $assignment->due_at?->toDateString());
    }

    public function test_attendance_without_a_plan_entry_starts_the_repeat_cycle(): void {
        $lead = $this->lead();
        $person = $this->field();
        $course = $this->course(['validity_months' => 6]);

        $this->instructionFor($course, $lead, [$person->id], Carbon::today()->toDateString());

        $assignment = TrainingAssignment::query()->where('user_id', $person->id)->firstOrFail();
        $this->assertSame('manual', $assignment->source);
        $this->assertSame(Carbon::today()->addMonthsNoOverflow(6)->toDateString(), $assignment->due_at?->toDateString());
    }

    // ── Fristen-Scan ─────────────────────────────────────────────────────

    public function test_deadline_scan_notifies_once_and_escalates_to_the_lead(): void {
        NotificationRule::factory()->forEvent(NotificationEvent::TrainingDue)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => true,
            'recipient_roles' => [UserRole::Teamleitung->value],
        ]);
        $lead = $this->lead();
        $person = $this->field();
        $course = $this->course(['lead_days' => 30]);
        $this->assignments()->assignManually($this->organization, $person, $course, Carbon::today()->addDays(5)->toDateString());

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, NotificationDispatchLog::query()->withoutGlobalScopes()
            ->where('event', NotificationEvent::TrainingDue->value)->count());
        $this->assertSame(1, $person->notifications()->count());
        $this->assertSame(1, $lead->notifications()->count());
    }

    public function test_deadline_scan_ignores_entries_outside_the_lead_window_and_syncs_the_matrix(): void {
        NotificationRule::factory()->forEvent(NotificationEvent::TrainingDue)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => true,
            'recipient_roles' => [],
        ]);
        $person = $this->field();
        $course = $this->course(['lead_days' => 10]);
        // Weit in der Zukunft — außerhalb des Vorlaufs, also keine Meldung.
        $this->assignments()->assignManually($this->organization, $person, $course, Carbon::today()->addDays(200)->toDateString());
        // Zusätzliche Pflichtzuordnung ohne vorherigen Abgleich: der Scan zieht sie nach.
        $second = $this->course(['title' => 'Erste Hilfe', 'lead_days' => 30]);
        TrainingRequirement::query()->create([
            'organization_id' => $this->organization->id,
            'training_course_id' => $second->id,
            'subject_kind' => TrainingRequirementSubject::Role->value,
            'subject_key' => UserRole::Aussendienst->value,
            'first_due_days' => 5,
            'is_active' => true,
            'source' => 'manual',
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(2, TrainingAssignment::query()->count());
        // Nur der nachgezogene Soll-Eintrag liegt im Vorlauf (5 Tage < 30 Tage Fenster).
        $this->assertSame(1, NotificationDispatchLog::query()->withoutGlobalScopes()
            ->where('event', NotificationEvent::TrainingDue->value)->count());
    }

    // ── Auswertung ───────────────────────────────────────────────────────

    public function test_report_shows_the_compliance_rate_and_exports_csv(): void {
        $lead = $this->lead();
        $person = $this->field();
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach($person->id);
        $course = $this->course();

        $this->assignments()->assignManually($this->organization, $person, $course, Carbon::today()->subDays(10)->toDateString());
        $other = $this->field();
        $secondCourse = $this->course(['title' => 'Erste Hilfe']);
        $this->assignments()->assignManually($this->organization, $other, $secondCourse, Carbon::today()->addDays(100)->toDateString());

        $this->actingAs($lead)
            ->get(route('reports.training'))
            ->assertOk()
            ->assertViewIs('reports.training')
            ->assertViewHas('report', function (array $report): bool {
                return $report['totals']['assignments'] === 2
                    && $report['totals']['overdue'] === 1
                    && $report['totals']['rate'] === 50.0;
            });

        $response = $this->actingAs($lead)->get(route('reports.training', ['export' => 'csv']));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $body = (string) $response->getContent();
        $this->assertStringContainsString('erfuellungsgrad_prozent', $body);
        $this->assertStringContainsString($team->name, $body);
    }

    public function test_report_exports_pdf(): void {
        $lead = $this->lead();
        $person = $this->field();
        $course = $this->course();
        $this->assignments()->assignManually($this->organization, $person, $course, Carbon::today()->addDays(5)->toDateString());

        $response = $this->actingAs($lead)->get(route('reports.training', ['export' => 'pdf']));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_report_is_forbidden_without_training_rights(): void {
        $this->actingAs($this->field())->get(route('reports.training'))->assertForbidden();
    }

    // ── Branchenprofil-Vorschläge ────────────────────────────────────────

    public function test_branch_profile_suggestions_seed_catalogue_and_matrix_idempotently(): void {
        $profile = [
            'code' => 'test-profil',
            'version' => 1,
            'training_suggestions' => [
                [
                    'code' => 'psaga',
                    'title' => 'PSA gegen Absturz',
                    'legal_basis' => 'DGUV R 112-198',
                    'validity_months' => 12,
                    'roles' => ['aussendienst', 'teamleitung'],
                ],
            ],
        ];

        $installer = app(\App\Services\Classification\BranchProfileInstaller::class);
        $first = $installer->installProfile($this->organization, $profile, null, false, 'test-profil');

        $this->assertSame(1, $first['created']['training_courses']);
        $this->assertSame(2, $first['created']['training_requirements']);
        $course = TrainingCourse::query()->where('code', 'psaga')->firstOrFail();
        $this->assertSame('profile', $course->source);
        $this->assertSame(1, $course->versions()->count());

        $second = $installer->installProfile($this->organization, $profile, null, false, 'test-profil');
        $this->assertSame(0, $second['created']['training_courses']);
        $this->assertSame(1, $second['skipped']['training_courses']);
        $this->assertSame(2, $second['skipped']['training_requirements']);
        $this->assertSame(1, TrainingCourse::query()->where('code', 'psaga')->count());
        $this->assertSame(2, TrainingRequirement::query()->where('training_course_id', $course->id)->count());
    }

    // ── Tenancy ──────────────────────────────────────────────────────────

    public function test_foreign_organization_records_are_invisible(): void {
        $lead = $this->lead();
        $foreignOrg = Organization::factory()->create();
        $foreignCourse = TrainingCourse::factory()->create(['organization_id' => $foreignOrg->id]);
        TrainingCourseVersion::factory()->create([
            'organization_id' => $foreignOrg->id,
            'training_course_id' => $foreignCourse->id,
        ]);

        $this->actingAs($lead)->get(route('training.courses.show', $foreignCourse))->assertNotFound();
        $this->assertSame(0, TrainingCourse::query()->count());

        // Org-fremder Kurs lässt sich auch nicht per Zuordnung unterschieben.
        $this->actingAs($lead)
            ->post(route('training.requirements.store'), [
                'training_course_id' => Sqid::encode(TrainingCourse::class, $foreignCourse->id),
                'subject_kind' => TrainingRequirementSubject::Role->value,
                'subject_role' => UserRole::Aussendienst->value,
                'first_due_days' => 30,
            ])
            ->assertSessionHasErrors('training_course_id');
    }

    public function test_sync_never_crosses_organizations(): void {
        $foreignOrg = Organization::factory()->create();
        $foreignUser = User::factory()->aussendienst()->create(['organization_id' => $foreignOrg->id]);
        $course = $this->course();
        $own = $this->field();

        TrainingRequirement::query()->create([
            'organization_id' => $this->organization->id,
            'training_course_id' => $course->id,
            'subject_kind' => TrainingRequirementSubject::Role->value,
            'subject_key' => UserRole::Aussendienst->value,
            'first_due_days' => 30,
            'is_active' => true,
            'source' => 'manual',
        ]);
        $this->assignments()->syncOrganization($this->organization);

        $this->assertSame(1, TrainingAssignment::query()->withoutGlobalScopes()->count());
        $this->assertSame($own->id, TrainingAssignment::query()->withoutGlobalScopes()->firstOrFail()->user_id);
        $this->assertSame(0, TrainingAssignment::query()->withoutGlobalScopes()->where('user_id', $foreignUser->id)->count());
    }

    // ── Hilfsmittel ──────────────────────────────────────────────────────

    /** @param list<int> $participantIds */
    private function instructionFor(TrainingCourse $course, User $lead, array $participantIds, ?string $heldOn = null, ?TrainingCourseVersion $version = null): SafetyInstruction {
        return app(SafetyInstructionService::class)->create($this->organization, $lead, [
            'topic' => $course->title,
            'held_on' => $heldOn ?? Carbon::today()->toDateString(),
            'training_course_id' => $course->id,
            'training_course_version_id' => $version?->id ?? $course->currentVersion()?->id,
        ], $participantIds);
    }
}
