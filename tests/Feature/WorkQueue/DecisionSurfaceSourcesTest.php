<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecisionSurfaceSourcesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\WorkQueue;

use App\Enums\Approval\ApprovalDecision;
use App\Enums\Club\ClubGroupMembershipStatus;
use App\Enums\Customer\IntakeStatus;
use App\Enums\Hr\{HrDocumentCategory, PersonnelFileSubmissionStatus};
use App\Enums\Learning\LearningBookingStatus;
use App\Enums\Rental\RentalRequestStatus;
use App\Enums\Shift\{DutyPlanStatus, ShiftExchangeStatus};
use App\Enums\Supplier\SupplierQuestionnaireStatus;
use App\Enums\TimeApproval\{DayClosureStatus, DayCorrectionStatus, TimeCorrectionStatus};
use App\Enums\User\UserRole;
use App\Models\Approval\ApprovalStep;
use App\Models\Club\{ClubGroup, ClubGroupMembership, ClubMember};
use App\Models\Customer\{Customer, CustomerIntake};
use App\Models\Diary\{DayClosure, DayCorrectionRequest};
use App\Models\Hr\PersonnelFileSubmission;
use App\Models\Learning\{LearningBooking, LearningCourse};
use App\Models\Platform\{Organization, User};
use App\Models\Rental\RentalRequest;
use App\Models\Schedule\{DutyPlan, ScheduledShift, ShiftExchange};
use App\Models\Supplier\{Supplier, SupplierQuestionnaire, SupplierQuestionnaireRequest};
use App\Models\Time\{OvertimeRequest, TimeCorrectionRequest};
use App\Services\Fields\FieldSchema;
use App\Services\Hr\PersonnelFilePermissions;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Org\OrganizationRoles;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\WorkQueue;
use App\Support\Sqid;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Entscheidungsflächen im Arbeitsvorrat (MVP-1525): je Quelle sieht die
 * zuständige Person den Vorgang, die antragstellende Person und fremde
 * Organisationen nicht.
 */
class DecisionSurfaceSourcesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const array SOURCES = [
        'overtime_request',
        'time_correction',
        'day_correction',
        'duty_plan',
        'shift_exchange',
        'personnel_file_submission',
        'customer_intake',
        'rental_request',
        'learning_booking',
        'supplier_questionnaire',
        'club_membership',
    ];

    private Organization $foreign;

    private User $stranger;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 10:00:00');
        $this->setUpOrganization();
        $this->foreign = Organization::factory()->create();
        $this->stranger = User::factory()->user()->create(['organization_id' => $this->foreign->id, 'name' => 'Fremde Person']);
    }

    /** @param array<string, mixed> $attributes */
    private function worker(array $attributes = []): User {
        return $this->userWithRole(UserRole::User->value, attributes: $attributes);
    }

    /** @param array<string, mixed> $attributes */
    private function lead(array $attributes = []): User {
        return $this->userWithRole(UserRole::Teamleitung->value, attributes: $attributes);
    }

    /** @return list<WorkItem> */
    private function items(User $viewer, string $source): array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $queue = app(WorkQueue::class);
        $queue->forget($viewer, $this->organization);

        return array_values(array_filter(
            $queue->itemsFor($viewer, $this->organization),
            static fn (WorkItem $item): bool => $item->source === $source,
        ));
    }

    /** @return list<string> */
    private function titles(User $viewer, string $source): array {
        return array_map(static fn (WorkItem $item): string => $item->title, $this->items($viewer, $source));
    }

    public function test_overtime_requests_reach_deciders_once_per_person(): void {
        $lead = $this->lead();
        $employee = $this->worker(['name' => 'Bernd Beispiel']);
        $request = OvertimeRequest::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $employee->id, 'scope_date' => '2026-11-05', 'minutes' => 90]);
        OvertimeRequest::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $lead->id, 'scope_date' => '2026-11-05']);
        OvertimeRequest::factory()->create(['organization_id' => $this->foreign->id, 'user_id' => $this->stranger->id, 'scope_date' => '2026-11-05']);

        $items = $this->items($lead, 'overtime_request');
        $this->assertSame(['Überstunden von Bernd Beispiel'], array_map(static fn (WorkItem $i): string => $i->title, $items));
        $this->assertStringContainsString('90 Min.', (string) $items[0]->detail);
        $this->assertSame(route('admin.overtime.index', ['status' => 'submitted']), $items[0]->url);
        $this->assertSame([], $this->items($employee, 'overtime_request'), 'Antragsteller entscheiden nicht.');
        $this->actingAs($lead)->get(route('work-queue.index'))
            ->assertOk()->assertSee('Überstunden von Bernd Beispiel')->assertSee('Überstundenanträge');

        ApprovalStep::query()->create([
            'organization_id' => $this->organization->id,
            'approvable_type' => $request->getMorphClass(),
            'approvable_id' => $request->id,
            'stage' => 1,
            'decision' => ApprovalDecision::Approved->value,
            'decided_by' => $lead->id,
        ]);
        $this->assertSame([], $this->items($lead, 'overtime_request'), 'Die zweite Stufe gibt eine andere Person.');
    }

    public function test_time_corrections_follow_the_team_scope_and_wait_for_application(): void {
        $lead = $this->lead(['name' => 'Lena Leitung']);
        $member = $this->worker(['name' => 'Carla Kollegin']);
        $outsider = $this->worker(['name' => 'Otto Außen']);
        $this->sameTeam($lead, $member);
        $correction = fn (User $user, TimeCorrectionStatus $status, ?Organization $organization = null): TimeCorrectionRequest => TimeCorrectionRequest::query()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'user_id' => $user->id,
            'requested_by_user_id' => $user->id,
            'scope_date' => '2026-11-03',
            'status' => $status->value,
            'reason' => 'Stempelung am Morgen vergessen.',
        ]);
        $submitted = $correction($member, TimeCorrectionStatus::Submitted);
        $correction($outsider, TimeCorrectionStatus::Submitted);
        $correction($lead, TimeCorrectionStatus::Submitted);
        $correction($this->stranger, TimeCorrectionStatus::Submitted, $this->foreign);

        $items = $this->items($lead, 'time_correction');
        $this->assertSame(['Zeitkorrektur von Carla Kollegin'], array_map(static fn (WorkItem $i): string => $i->title, $items), 'Team-Recht: nur das eigene Team, nie der eigene Antrag.');
        $this->assertSame(route('admin.corrections.show', $submitted), $items[0]->url);
        $this->assertSame([], $this->items($member, 'time_correction'));

        $correction($outsider, TimeCorrectionStatus::Approved);
        $admin = $this->orgAdmin();
        $this->assertEqualsCanonicalizing(
            ['Zeitkorrektur von Carla Kollegin', 'Zeitkorrektur von Otto Außen', 'Zeitkorrektur von Otto Außen anwenden', 'Zeitkorrektur von Lena Leitung'],
            $this->titles($admin, 'time_correction'),
        );
        $this->assertNotContains('Zeitkorrektur von Otto Außen anwenden', $this->titles($lead, 'time_correction'), 'Anwenden darf nur, wer das Systemrecht hat.');
    }

    public function test_day_close_corrections_reach_the_team_lead(): void {
        $lead = $this->lead();
        $member = $this->worker(['name' => 'Dora Tag']);
        $this->sameTeam($lead, $member);
        $request = function (User $user, ?Organization $organization = null): DayCorrectionRequest {
            $organizationId = ($organization ?? $this->organization)->id;
            $closure = DayClosure::factory()->create(['organization_id' => $organizationId, 'user_id' => $user->id, 'day' => '2026-11-04', 'status' => DayClosureStatus::Correction->value]);

            return DayCorrectionRequest::factory()->create(['organization_id' => $organizationId, 'day_closure_id' => $closure->id, 'requested_by_user_id' => $user->id, 'status' => DayCorrectionStatus::Pending->value]);
        };
        $request($member);
        $request($lead);
        $request($this->stranger, $this->foreign);

        $items = $this->items($lead, 'day_correction');
        $this->assertSame(['Tagesabschluss von Dora Tag korrigieren'], array_map(static fn (WorkItem $i): string => $i->title, $items));
        $this->assertSame(route('day-close.show', ['date' => '2026-11-04', 'user' => Sqid::encode(User::class, $member->id)]), $items[0]->url);
        $this->assertSame([], $this->items($member, 'day_correction'));
    }

    public function test_duty_plans_wait_for_an_admin_who_did_not_submit_them(): void {
        $admin = $this->orgAdmin();
        $lead = $this->lead();
        $plan = fn (string $title, DutyPlanStatus $status, ?User $by, ?Organization $organization = null): DutyPlan => DutyPlan::factory()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'title' => $title,
            'status' => $status,
            'submitted_by' => $by?->id,
            'from_date' => '2026-12-01',
            'to_date' => '2026-12-31',
        ]);
        $plan('Dezember Station A', DutyPlanStatus::Submitted, $lead);
        $plan('Eigener Plan', DutyPlanStatus::Submitted, $admin);
        $plan('Entwurf', DutyPlanStatus::Draft, $lead);
        $plan('Fremder Plan', DutyPlanStatus::Submitted, $this->stranger, $this->foreign);

        $items = $this->items($admin, 'duty_plan');
        $this->assertSame(['Dienstplan „Dezember Station A“'], array_map(static fn (WorkItem $i): string => $i->title, $items));
        $this->assertSame('2026-12-01', $items[0]->dueAt?->toDateString());
        $this->assertSame([], $this->items($lead, 'duty_plan'), 'Genehmigen dürfen nur Admins.');
    }

    public function test_shift_exchange_waits_for_the_colleague_first_and_then_for_planning(): void {
        $planner = $this->lead(['name' => 'Paula Planung']);
        $requester = $this->worker(['name' => 'Anton Abgabe']);
        $colleague = $this->worker(['name' => 'Berta Bereit']);
        $shift = fn (User $user, ?Organization $organization = null): ScheduledShift => ScheduledShift::factory()->create(['organization_id' => ($organization ?? $this->organization)->id, 'user_id' => $user->id, 'date' => '2026-11-12']);
        $exchange = ShiftExchange::factory()->create(['organization_id' => $this->organization->id, 'scheduled_shift_id' => $shift($requester)->id, 'requested_by_user_id' => $requester->id, 'target_user_id' => $colleague->id]);
        ShiftExchange::factory()->create(['organization_id' => $this->organization->id, 'scheduled_shift_id' => $shift($requester)->id, 'requested_by_user_id' => $requester->id, 'target_user_id' => null]);
        ShiftExchange::factory()->create(['organization_id' => $this->foreign->id, 'scheduled_shift_id' => $shift($this->stranger, $this->foreign)->id, 'requested_by_user_id' => $this->stranger->id, 'target_user_id' => $this->stranger->id, 'status' => ShiftExchangeStatus::Accepted]);

        $accept = $this->items($colleague, 'shift_exchange');
        $this->assertSame(['Tauschanfrage von Anton Abgabe'], array_map(static fn (WorkItem $i): string => $i->title, $accept), 'Offene Abgaben ohne Zielperson sind kein Vorgang.');
        $this->assertTrue($accept[0]->personal);
        $this->assertSame('2026-11-12', $accept[0]->dueAt?->toDateString());
        $this->assertSame([], $this->items($planner, 'shift_exchange'), 'Vor der Annahme ist die Planung nicht am Zug.');
        $this->assertSame([], $this->items($requester, 'shift_exchange'));

        $exchange->update(['status' => ShiftExchangeStatus::Accepted]);

        $approve = $this->items($planner, 'shift_exchange');
        $this->assertSame(['Schichttausch von Anton Abgabe an Berta Bereit'], array_map(static fn (WorkItem $i): string => $i->title, $approve));
        $this->assertFalse($approve[0]->personal);
        $this->assertSame([], $this->items($colleague, 'shift_exchange'), 'Wer am Tausch beteiligt ist, gibt ihn nicht frei.');
        $this->assertSame([], $this->items($requester, 'shift_exchange'));
    }

    public function test_personnel_file_submissions_reach_only_the_file_circle(): void {
        $hr = $this->worker();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $hr->assignRole(OrganizationRoles::named(PersonnelFilePermissions::ROLE_PERSONALAKTE, (int) $this->organization->id));
        $member = $this->worker(['name' => 'Erika Beispiel']);
        $submission = fn (User $user, string $title, ?Organization $organization = null): PersonnelFileSubmission => PersonnelFileSubmission::query()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'user_id' => $user->id,
            'title' => $title,
            'hr_category' => HrDocumentCategory::Other->value,
            'disk' => 'local',
            'original_name' => 'nachweis.pdf',
            'size' => 1024,
            'status' => PersonnelFileSubmissionStatus::Submitted->value,
        ]);
        $submission($member, 'Ersthelfer-Bescheinigung');
        $submission($hr, 'Eigene Bescheinigung');
        $submission($this->stranger, 'Fremde Bescheinigung', $this->foreign);

        $this->assertSame(['Einreichung von Erika Beispiel: Ersthelfer-Bescheinigung'], $this->titles($hr, 'personnel_file_submission'));
        $this->assertSame([], $this->items($member, 'personnel_file_submission'));
        $this->assertSame([], $this->items($this->orgAdmin(), 'personnel_file_submission'), 'Die Personalakte kennt keinen Admin-Durchgriff.');
    }

    public function test_customer_intakes_reach_all_handlers_until_someone_takes_them(): void {
        $handler = $this->lead();
        $colleague = $this->lead();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Muster Druck GmbH']);
        $intake = fn (string $number, IntakeStatus $status, ?User $assignee = null, ?User $submitter = null): CustomerIntake => CustomerIntake::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => $number,
            'subject' => 'Anfrage ' . $number,
            'status' => $status,
            'assigned_user_id' => $assignee?->id,
            'submitted_by_user_id' => $submitter?->id,
            'desired_date' => $number === 'KE-2026-0001' ? '2026-11-20' : null,
        ]);
        $intake('KE-2026-0001', IntakeStatus::Submitted);
        $intake('KE-2026-0002', IntakeStatus::InProgress, $colleague);
        $intake('KE-2026-0003', IntakeStatus::InProgress, $handler);
        $intake('KE-2026-0004', IntakeStatus::AwaitingCustomer, $handler);
        $intake('KE-2026-0005', IntakeStatus::Submitted, submitter: $handler);
        $foreignCustomer = Customer::factory()->create(['organization_id' => $this->foreign->id]);
        CustomerIntake::factory()->create(['organization_id' => $this->foreign->id, 'customer_id' => $foreignCustomer->id, 'status' => IntakeStatus::Submitted]);

        $items = $this->items($handler, 'customer_intake');
        $this->assertSame(['Kundeneingang KE-2026-0001: Anfrage KE-2026-0001', 'Kundeneingang KE-2026-0003: Anfrage KE-2026-0003'], array_map(static fn (WorkItem $i): string => $i->title, $items));
        $this->assertSame([false, true], array_map(static fn (WorkItem $i): bool => $i->personal, $items));
        $this->assertSame('Muster Druck GmbH', $items[0]->detail);
        $this->assertSame([], $this->items($this->worker(), 'customer_intake'));
    }

    public function test_rental_requests_wait_for_rental_managers(): void {
        $manager = $this->lead();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beispiel Bau KG']);
        $request = fn (RentalRequestStatus $status, ?Organization $organization = null, ?Customer $for = null): RentalRequest => RentalRequest::query()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'customer_id' => ($for ?? $customer)->id,
            'starts_at' => '2026-11-16 08:00:00',
            'ends_at' => '2026-11-18 17:00:00',
            'status' => $status->value,
        ]);
        $request(RentalRequestStatus::Requested);
        $request(RentalRequestStatus::AwaitingPayment);
        $request(RentalRequestStatus::Requested, $this->foreign, Customer::factory()->create(['organization_id' => $this->foreign->id]));

        $items = $this->items($manager, 'rental_request');
        $this->assertSame(['Verleihanfrage von Beispiel Bau KG'], array_map(static fn (WorkItem $i): string => $i->title, $items));
        $this->assertSame('2026-11-16', $items[0]->dueAt?->toDateString());
        $this->assertSame([], $this->items($this->worker(), 'rental_request'));
    }

    public function test_learning_bookings_wait_for_learning_managers(): void {
        $manager = $this->lead();
        $learner = $this->worker(['name' => 'Fritz Lernend']);
        $course = LearningCourse::factory()->create(['organization_id' => $this->organization->id, 'title' => 'Erste Hilfe am Arbeitsplatz']);
        $booking = fn (User $user, LearningBookingStatus $status, ?LearningCourse $in = null): LearningBooking => LearningBooking::query()->create([
            'organization_id' => ($in ?? $course)->organization_id,
            'learning_course_id' => ($in ?? $course)->id,
            'user_id' => $user->id,
            'status' => $status->value,
            'seats' => 1,
            'requested_at' => now(),
        ]);
        $booking($learner, LearningBookingStatus::Requested);
        $booking($manager, LearningBookingStatus::Requested);
        $booking($learner, LearningBookingStatus::Confirmed);
        $booking($this->stranger, LearningBookingStatus::Requested, LearningCourse::factory()->create(['organization_id' => $this->foreign->id]));

        $items = $this->items($manager, 'learning_booking');
        $this->assertSame(['Kursbuchung von Fritz Lernend'], array_map(static fn (WorkItem $i): string => $i->title, $items));
        $this->assertSame('Erste Hilfe am Arbeitsplatz', $items[0]->detail);
        $this->assertSame([], $this->items($learner, 'learning_booking'));
    }

    public function test_answered_supplier_questionnaires_wait_for_review(): void {
        $buyer = $this->userWithRole(UserRole::Buchhaltung->value);
        $request = function (SupplierQuestionnaireStatus $status, Organization $organization, string $supplierName): SupplierQuestionnaireRequest {
            $supplier = Supplier::factory()->create(['organization_id' => $organization->id, 'name' => $supplierName]);
            $questionnaire = SupplierQuestionnaire::query()->create(['organization_id' => $organization->id, 'name' => 'ESG-Basis', 'schema' => FieldSchema::fromArray([]), 'validity_months' => 12, 'is_active' => true]);

            return SupplierQuestionnaireRequest::query()->create([
                'organization_id' => $organization->id,
                'supplier_questionnaire_id' => $questionnaire->id,
                'supplier_id' => $supplier->id,
                'status' => $status->value,
                'token_hash' => hash('sha256', $supplierName),
                'recipient_email' => 'einkauf@lieferant.test',
                'schema_snapshot' => FieldSchema::fromArray([]),
                'expires_at' => now()->addDays(30),
                'submitted_at' => $status === SupplierQuestionnaireStatus::Submitted ? now() : null,
            ]);
        };
        $request(SupplierQuestionnaireStatus::Submitted, $this->organization, 'Stahl Beispiel AG');
        $request(SupplierQuestionnaireStatus::Sent, $this->organization, 'Holz Beispiel GmbH');
        $request(SupplierQuestionnaireStatus::Submitted, $this->foreign, 'Fremd Beispiel AG');

        $items = $this->items($buyer, 'supplier_questionnaire');
        $this->assertSame(['Selbstauskunft von Stahl Beispiel AG'], array_map(static fn (WorkItem $i): string => $i->title, $items));
        $this->assertStringStartsWith('ESG-Basis', (string) $items[0]->detail);
        $this->assertSame([], $this->items($this->worker(), 'supplier_questionnaire'));
    }

    public function test_club_group_requests_reach_the_group_lead_and_club_administration(): void {
        $leader = $this->lead(['name' => 'Gert Gruppe']);
        $admin = $this->orgAdmin();
        $youth = ClubGroup::factory()->create(['organization_id' => $this->organization->id, 'name' => 'U12', 'leader_user_id' => $leader->id]);
        $seniors = ClubGroup::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Senioren']);
        $request = function (ClubGroup $group, string $first, string $last, ?User $account = null, ClubGroupMembershipStatus $status = ClubGroupMembershipStatus::Requested): void {
            $member = ClubMember::factory()->create(['organization_id' => $group->organization_id, 'first_name' => $first, 'last_name' => $last, 'user_id' => $account?->id]);
            ClubGroupMembership::query()->create([
                'organization_id' => $group->organization_id,
                'club_group_id' => $group->id,
                'club_member_id' => $member->id,
                'status' => $status->value,
                'valid_from' => '2026-11-15',
            ]);
        };
        $request($youth, 'Mia', 'Muster');
        $request($seniors, 'Tom', 'Test');
        $request($youth, 'Gert', 'Gruppe', $leader);
        $request($youth, 'Lea', 'Aktiv', status: ClubGroupMembershipStatus::Active);
        $request(ClubGroup::factory()->create(['organization_id' => $this->foreign->id]), 'Fritz', 'Fremd');

        $mine = $this->items($leader, 'club_membership');
        $this->assertSame(['Aufnahme von Mia Muster in „U12“'], array_map(static fn (WorkItem $i): string => $i->title, $mine), 'Gruppenleitung: nur eigene Gruppen, nie das eigene Mitgliedskonto.');
        $this->assertTrue($mine[0]->personal);
        $this->assertSame(route('club.groups.show', $youth), $mine[0]->url);
        $this->assertEqualsCanonicalizing(
            ['Aufnahme von Mia Muster in „U12“', 'Aufnahme von Tom Test in „Senioren“', 'Aufnahme von Gert Gruppe in „U12“'],
            $this->titles($admin, 'club_membership'),
        );
        $this->assertSame([], $this->items($this->worker(), 'club_membership'));
    }

    public function test_feature_module_sources_disappear_when_the_module_is_off(): void {
        $admin = $this->orgAdmin();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->assertSame([], array_diff(['duty_plan', 'shift_exchange', 'rental_request', 'learning_booking', 'club_membership'], array_keys(app(WorkQueue::class)->sourcesFor($admin))));

        $this->organization->forceFill(['plan' => Organization::PLAN_FREE])->save();
        app()->instance('currentOrganization', $this->organization->fresh());
        app(FeatureFlagResolver::class)->flush();

        $this->assertSame([], array_intersect(['duty_plan', 'shift_exchange', 'rental_request', 'learning_booking', 'club_membership'], array_keys(app(WorkQueue::class)->sourcesFor($admin))));
    }

    public function test_the_collector_runs_every_new_source_without_failure(): void {
        Exceptions::fake();
        $admin = $this->orgAdmin();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin->assignRole(OrganizationRoles::named(PersonnelFilePermissions::ROLE_PERSONALAKTE, (int) $this->organization->id));
        $admin->unsetRelation('roles');

        $sources = app(WorkQueue::class)->sourcesFor($admin);
        foreach (self::SOURCES as $key) {
            $this->assertArrayHasKey($key, $sources);
            $this->assertStringNotContainsString('work-queue.', $sources[$key]->label(), $key . ' braucht eine Bezeichnung.');
        }

        app(WorkQueue::class)->itemsFor($admin, $this->organization, $sources);

        Exceptions::assertNothingReported();
    }
}
