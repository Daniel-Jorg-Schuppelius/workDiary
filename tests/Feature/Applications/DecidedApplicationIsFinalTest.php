<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecidedApplicationIsFinalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Enums\Applications\{JobApplicationInterviewStatus, JobApplicationStatus};
use App\Models\Applications\{JobApplication, JobApplicationInterview, JobInterviewOffer};
use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use App\Services\Applications\RecruitingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Entscheidung des Inhabers vom 2026-10-05: „Entschieden ist endgültig“.
 * Gespräch planen, Gespräch abschließen, Terminangebot und Entscheiden gehen
 * nur in der Pipeline. Vorher fiel eine abgelehnte Akte über einen direkten
 * POST in die Pipeline zurück, behielt ihre Löschvormerkung und wurde später
 * trotzdem gelöscht. Einzige Ausnahme ist die Aufnahme aus dem Talentpool
 * ({@see TalentPoolReadmissionTest}) — für alle anderen Entscheidungen bleibt
 * sie gesperrt.
 */
final class DecidedApplicationIsFinalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const RETENTION = '2027-04-05';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-10-05 12:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @return iterable<string, array{JobApplicationStatus}> */
    public static function decided(): iterable {
        yield 'zugesagt' => [JobApplicationStatus::Accepted];
        yield 'abgelehnt' => [JobApplicationStatus::Rejected];
        yield 'zurückgezogen' => [JobApplicationStatus::Withdrawn];
        yield 'Talentpool' => [JobApplicationStatus::TalentPool];
    }

    /** @return iterable<string, array{JobApplicationStatus}> */
    public static function pipeline(): iterable {
        foreach (JobApplicationStatus::pipeline() as $status) {
            yield $status->value => [$status];
        }
    }

    private function application(JobApplicationStatus $status): JobApplication {
        return JobApplication::query()->create([
            'organization_id' => $this->organization->id,
            'candidate_name' => 'Kim Beispiel',
            'email' => 'kim@example.test',
            'source' => 'website',
            'status' => $status,
            'received_at' => now(),
            'retention_until' => $status->inPipeline() ? null : self::RETENTION,
        ]);
    }

    private function plannedInterview(JobApplication $application): JobApplicationInterview {
        return $application->interviews()->create([
            'organization_id' => $this->organization->id,
            'scheduled_at' => now()->addDay(),
            'mode' => 'onsite',
            'status' => JobApplicationInterviewStatus::Planned,
        ]);
    }

    private function assertUntouched(JobApplicationStatus $status, JobApplication $application): void {
        $fresh = $application->fresh();
        $this->assertSame($status, $fresh->status);
        $this->assertSame(self::RETENTION, $fresh->retention_until?->toDateString());
    }

    private function decidedMessage(): string {
        return (string) __('Die Akte ist bereits entschieden.');
    }

    // ── Gespräch planen ──────────────────────────────────────────────────

    #[DataProvider('decided')]
    public function test_planning_an_interview_is_rejected_on_a_decided_file(JobApplicationStatus $status): void {
        $application = $this->application($status);

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.interviews.store', $application), ['scheduled_at' => '2026-10-08T10:00', 'mode' => 'onsite'])
            ->assertSessionHas('error', $this->decidedMessage());

        $this->assertUntouched($status, $application);
        $this->assertSame(0, $application->interviews()->count());
    }

    #[DataProvider('pipeline')]
    public function test_planning_an_interview_still_works_in_the_pipeline(JobApplicationStatus $status): void {
        $application = $this->application($status);

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.interviews.store', $application), ['scheduled_at' => '2026-10-08T10:00', 'mode' => 'phone', 'notes' => 'Erstgespräch'])
            ->assertSessionHas('success');

        $this->assertSame(JobApplicationStatus::InterviewPlanned, $application->fresh()->status);
        $interview = $application->interviews()->sole();
        $this->assertSame(JobApplicationInterviewStatus::Planned, $interview->status);
        $this->assertSame('phone', $interview->mode);
        $this->assertSame('Erstgespräch', $interview->notes);
    }

    // ── Gespräch abschließen ─────────────────────────────────────────────

    #[DataProvider('decided')]
    public function test_completing_an_interview_is_rejected_on_a_decided_file(JobApplicationStatus $status): void {
        $application = $this->application($status);
        $interview = $this->plannedInterview($application);

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.interviews.complete', [$application, $interview]), ['rating' => 4])
            ->assertSessionHas('error', $this->decidedMessage());

        $this->assertUntouched($status, $application);
        $this->assertSame(JobApplicationInterviewStatus::Planned, $interview->fresh()->status);
        $this->assertNull($interview->fresh()->rating);
    }

    public function test_completing_an_interview_still_works_in_the_pipeline(): void {
        $application = $this->application(JobApplicationStatus::InterviewPlanned);
        $interview = $this->plannedInterview($application);

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.interviews.complete', [$application, $interview]), ['rating' => 4, 'notes' => 'Überzeugend'])
            ->assertSessionHas('success');

        $this->assertSame(JobApplicationStatus::Interviewed, $application->fresh()->status);
        $this->assertSame(JobApplicationInterviewStatus::Done, $interview->fresh()->status);
        $this->assertSame(4, $interview->fresh()->rating);
    }

    // ── Entscheiden ──────────────────────────────────────────────────────

    #[DataProvider('decided')]
    public function test_a_decision_cannot_be_revised(JobApplicationStatus $status): void {
        $application = $this->application($status);

        foreach (['offer', 'accepted', 'rejected', 'withdrawn', 'talent_pool'] as $decision) {
            $this->actingAs($this->admin)
                ->post(route('recruiting.applications.decide', $application), ['decision' => $decision, 'talent_pool_consent' => '1'])
                ->assertSessionHas('error', $this->decidedMessage());
        }

        $this->assertUntouched($status, $application);
        $this->assertNull($application->fresh()->consent_talent_pool_at);
        $this->assertSame(0, AuditLog::query()->where('event', 'recruiting.application_decided')->count());
    }

    /** Der Dienst prüft selbst — nicht nur der Controller. */
    public function test_the_service_rejects_a_second_decision(): void {
        $application = $this->application(JobApplicationStatus::Rejected);

        try {
            app(RecruitingService::class)->decide($application, 'accepted', null, $this->admin);
            $this->fail('Eine abgelehnte Akte ließ sich zusagen.');
        } catch (\RuntimeException $e) {
            $this->assertSame($this->decidedMessage(), $e->getMessage());
        }

        $this->assertUntouched(JobApplicationStatus::Rejected, $application);
    }

    public function test_decisions_work_as_before_within_the_pipeline(): void {
        $application = $this->application(JobApplicationStatus::Interviewed);

        // „Angebot“ bleibt ein Pipeline-Stand: danach lässt sich weiter entscheiden.
        $this->actingAs($this->admin)->post(route('recruiting.applications.decide', $application), ['decision' => 'offer'])->assertSessionHas('success');
        $this->assertSame(JobApplicationStatus::Offer, $application->fresh()->status);

        $this->actingAs($this->admin)->post(route('recruiting.applications.decide', $application), ['decision' => 'rejected'])->assertSessionHas('success');
        $fresh = $application->fresh();
        $this->assertSame(JobApplicationStatus::Rejected, $fresh->status);
        $this->assertSame('2027-04-05', $fresh->retention_until?->toDateString());
    }

    // ── Aufnehmen aus dem Talentpool ─────────────────────────────────────

    /** @return iterable<string, array{JobApplicationStatus}> */
    public static function finallyDecided(): iterable {
        yield 'zugesagt' => [JobApplicationStatus::Accepted];
        yield 'abgelehnt' => [JobApplicationStatus::Rejected];
        yield 'zurückgezogen' => [JobApplicationStatus::Withdrawn];
    }

    #[DataProvider('finallyDecided')]
    public function test_readmission_is_refused_for_every_other_decision(JobApplicationStatus $status): void {
        $application = $this->application($status);
        $message = (string) __('Nur Akten im Talentpool lassen sich wieder aufnehmen.');

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.readmit', $application))
            ->assertSessionHas('error', $message);

        try {
            app(RecruitingService::class)->readmit($application->fresh(), $this->admin);
            $this->fail('Eine entschiedene Akte ließ sich aus einem anderen Stand als dem Talentpool aufnehmen.');
        } catch (\RuntimeException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        $this->assertUntouched($status, $application);
        $this->assertSame(0, AuditLog::query()->where('event', 'recruiting.application_readmitted')->count());
    }

    // ── Terminangebot ────────────────────────────────────────────────────

    #[DataProvider('decided')]
    public function test_an_interview_offer_is_rejected_on_a_decided_file(JobApplicationStatus $status): void {
        $application = $this->application($status);

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.interview-offers.store', $application), [
                'slots' => ['2026-10-08T10:00'], 'mode' => 'onsite', 'duration_minutes' => 45, 'valid_days' => 7,
            ])
            ->assertSessionHas('error', $this->decidedMessage());

        $this->assertUntouched($status, $application);
        $this->assertSame(0, JobInterviewOffer::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_an_interview_offer_still_works_in_the_pipeline(): void {
        $application = $this->application(JobApplicationStatus::Screened);

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.interview-offers.store', $application), [
                'slots' => ['2026-10-08T10:00'], 'mode' => 'onsite', 'duration_minutes' => 45, 'valid_days' => 7,
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, JobInterviewOffer::query()->count());
    }

    // ── Oberfläche ───────────────────────────────────────────────────────

    #[DataProvider('decided')]
    public function test_a_decided_file_offers_no_interview_or_decision_forms(JobApplicationStatus $status): void {
        $application = $this->application($status);
        $interview = $this->plannedInterview($application);

        $html = (string) $this->actingAs($this->admin)->get(route('recruiting.applications.show', $application))->assertOk()->getContent();

        foreach ([
            route('recruiting.applications.interviews.store', $application),
            route('recruiting.applications.interview-offers.store', $application),
            route('recruiting.applications.interviews.complete', [$application, $interview]),
            route('recruiting.applications.decide', $application),
            route('recruiting.applications.status', $application),
        ] as $action) {
            $this->assertStringNotContainsString('action="' . $action . '"', $html);
        }
    }

    public function test_a_file_in_the_pipeline_offers_the_forms(): void {
        $application = $this->application(JobApplicationStatus::Screened);
        $interview = $this->plannedInterview($application);

        $html = (string) $this->actingAs($this->admin)->get(route('recruiting.applications.show', $application))->assertOk()->getContent();

        foreach ([
            route('recruiting.applications.interviews.store', $application),
            route('recruiting.applications.interview-offers.store', $application),
            route('recruiting.applications.interviews.complete', [$application, $interview]),
            route('recruiting.applications.decide', $application),
        ] as $action) {
            $this->assertStringContainsString('action="' . $action . '"', $html);
        }
    }
}
