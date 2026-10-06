<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TalentPoolReadmissionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Enums\Applications\{JobApplicationInterviewStatus, JobApplicationStatus};
use App\Enums\User\UserRole;
use App\Mail\InterviewOfferMail;
use App\Models\Applications\{JobApplication, JobApplicationInterview, JobInterviewOffer};
use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use App\Services\Applications\{InterviewOfferService, RecruitingService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, vierte Entscheidungsrunde: „Aus dem
 * Talentpool aufnehmen“ ist die einzige Ausnahme von „entschieden ist
 * endgültig“ und verlangt eine gültige Einwilligung; mit der Entscheidung
 * enden geplante Gespräche und offene Terminangebote.
 */
final class TalentPoolReadmissionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const CONSENT_MESSAGE = 'Die Talentpool-Einwilligung fehlt oder ist abgelaufen — die Akte kann nicht wieder aufgenommen werden.';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-10-06 12:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function application(JobApplicationStatus $status, array $attributes = []): JobApplication {
        return JobApplication::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'candidate_name' => 'Kim Beispiel',
            'email' => 'kim@example.test',
            'source' => 'website',
            'status' => $status,
            'received_at' => now()->subMonths(3),
        ]);
    }

    /** Talentpool-Akte mit Einwilligung; die Löschvormerkung liegt auf dem Ablauftag. */
    private function talentPoolFile(string $expiresOn = '2028-04-06'): JobApplication {
        return $this->application(JobApplicationStatus::TalentPool, [
            'consent_talent_pool_at' => '2026-10-06 11:00:00',
            'consent_expires_on' => $expiresOn,
            'retention_until' => $expiresOn,
        ]);
    }

    /** @return array<string, mixed> */
    private function deadlines(JobApplication $application): array {
        $fresh = $application->fresh();

        return [
            'status' => $fresh->status,
            'retention_until' => $fresh->retention_until?->toDateString(),
            'consent_talent_pool_at' => $fresh->consent_talent_pool_at?->toDateTimeString(),
            'consent_expires_on' => $fresh->consent_expires_on?->toDateString(),
        ];
    }

    private function assertNotReadmitted(JobApplication $application, array $before): void {
        $this->assertSame($before, $this->deadlines($application));
        $this->assertSame(0, AuditLog::query()->where('event', 'recruiting.application_readmitted')->count());
    }

    private function offer(JobApplication $application, CarbonImmutable $expiresAt): string {
        app(InterviewOfferService::class)->offer($application, [CarbonImmutable::parse('2026-10-20 10:00:00')], 'onsite', 45, null, $expiresAt, $this->admin);

        /** @var InterviewOfferMail $mail */
        $mail = Mail::queued(InterviewOfferMail::class)->last();

        return $mail->token;
    }

    private function show(JobApplication $application): string {
        return (string) $this->actingAs($this->admin)->get(route('recruiting.applications.show', $application))->assertOk()->getContent();
    }

    // ── Aufnehmen ────────────────────────────────────────────────────────

    public function test_readmission_restarts_the_pipeline_and_clears_the_deadlines(): void {
        $application = $this->talentPoolFile();

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.readmit', $application), ['note' => 'Neue Stelle passt'])
            ->assertRedirect(route('recruiting.applications.show', $application))
            ->assertSessionHas('success', __('Bewerbung aus dem Talentpool aufgenommen.'));

        $fresh = $application->fresh();
        $this->assertSame(JobApplicationStatus::Received, $fresh->status);
        $this->assertNull($fresh->retention_until);
        $this->assertNull($fresh->consent_talent_pool_at);
        $this->assertNull($fresh->consent_expires_on);

        $audit = AuditLog::query()->where('event', 'recruiting.application_readmitted')->sole();
        $this->assertSame('Neue Stelle passt', $audit->changes['note']);
        $this->assertSame($this->admin->id, $audit->changes['by']);
        $this->assertSame('2028-04-06', $audit->changes['consent_expires_on']);
    }

    /** Danach ist die Akte eine laufende Bewerbung: die nächste Entscheidung setzt die Frist neu. */
    public function test_a_readmitted_file_can_be_decided_again(): void {
        $application = $this->talentPoolFile();
        app(RecruitingService::class)->readmit($application, $this->admin);

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.decide', $application), ['decision' => 'rejected'])
            ->assertSessionHas('success');

        $fresh = $application->fresh();
        $this->assertSame(JobApplicationStatus::Rejected, $fresh->status);
        $this->assertSame('2027-04-06', $fresh->retention_until?->toDateString());
    }

    public function test_readmission_is_refused_without_consent(): void {
        $application = $this->application(JobApplicationStatus::TalentPool, ['retention_until' => '2028-04-06']);
        $before = $this->deadlines($application);

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.readmit', $application))
            ->assertSessionHas('error', __(self::CONSENT_MESSAGE));

        $this->assertNotReadmitted($application, $before);
    }

    public function test_readmission_is_refused_with_an_expired_consent(): void {
        $application = $this->talentPoolFile('2026-10-05');
        $before = $this->deadlines($application);

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.readmit', $application))
            ->assertSessionHas('error', __(self::CONSENT_MESSAGE));

        $this->assertNotReadmitted($application, $before);
    }

    /** „Nicht überschritten“: am Ablauftag gilt die Einwilligung noch. */
    public function test_consent_is_valid_through_its_expiry_day(): void {
        $application = $this->talentPoolFile('2026-10-06');

        $this->assertTrue($application->hasValidTalentPoolConsent());
        app(RecruitingService::class)->readmit($application, $this->admin);

        $this->assertSame(JobApplicationStatus::Received, $application->fresh()->status);
    }

    /** @return iterable<string, array{JobApplicationStatus}> */
    public static function outsideTalentPool(): iterable {
        foreach (JobApplicationStatus::cases() as $status) {
            if (! in_array($status, [JobApplicationStatus::TalentPool, JobApplicationStatus::Deleted], true)) {
                yield $status->value => [$status];
            }
        }
    }

    #[DataProvider('outsideTalentPool')]
    public function test_readmission_is_refused_outside_the_talent_pool(JobApplicationStatus $status): void {
        // Auch mit gesetzter Einwilligung zählt allein der Stand der Akte.
        $application = $this->application($status, ['consent_talent_pool_at' => '2026-10-06 11:00:00', 'consent_expires_on' => '2028-04-06']);
        $before = $this->deadlines($application);

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.readmit', $application))
            ->assertSessionHas('error', __('Nur Akten im Talentpool lassen sich wieder aufnehmen.'));

        $this->assertNotReadmitted($application, $before);
    }

    public function test_an_anonymized_file_cannot_be_readmitted(): void {
        $application = $this->talentPoolFile();
        app(RecruitingService::class)->anonymize($application, $this->admin);

        // Die Policy sperrt anonymisierte Akten für jede Entscheidung …
        $hr = $this->userWithRole(UserRole::Personalverwaltung->value);
        $this->actingAs($hr)->post(route('recruiting.applications.readmit', $application))->assertForbidden();

        // … und der Dienst prüft selbst — auch für Admins, die an der Policy vorbeikommen.
        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.readmit', $application))
            ->assertSessionHas('error', __('Die Akte ist bereits anonymisiert.'));
        try {
            app(RecruitingService::class)->readmit($application->fresh(), $this->admin);
            $this->fail('Eine anonymisierte Akte ließ sich aufnehmen.');
        } catch (\RuntimeException $e) {
            $this->assertSame((string) __('Die Akte ist bereits anonymisiert.'), $e->getMessage());
        }

        $this->assertSame(JobApplicationStatus::Deleted, $application->fresh()->status);
        $this->assertSame(0, AuditLog::query()->where('event', 'recruiting.application_readmitted')->count());
    }

    public function test_readmission_requires_the_decide_permission(): void {
        $application = $this->talentPoolFile();
        $before = $this->deadlines($application);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);

        $this->actingAs($lead)->post(route('recruiting.applications.readmit', $application))->assertForbidden();

        $this->assertNotReadmitted($application, $before);
    }

    // ── Entscheidung beendet Gespräche und Terminangebote ────────────────

    public function test_a_decision_cancels_planned_interviews_and_keeps_completed_ones(): void {
        $application = $this->application(JobApplicationStatus::Interviewed);
        $done = $application->interviews()->create([
            'organization_id' => $this->organization->id,
            'scheduled_at' => now()->subDay(),
            'mode' => 'onsite',
            'status' => JobApplicationInterviewStatus::Done,
            'rating' => 4,
            'notes' => 'Überzeugend',
        ]);
        $planned = $application->interviews()->create([
            'organization_id' => $this->organization->id,
            'scheduled_at' => now()->addDays(3),
            'mode' => 'remote',
            'status' => JobApplicationInterviewStatus::Planned,
            'notes' => 'Zweitgespräch',
        ]);

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.decide', $application), ['decision' => 'rejected', 'note' => 'Leider nein'])
            ->assertSessionHas('success');

        $this->assertSame(JobApplicationStatus::Rejected, $application->fresh()->status);
        $this->assertSame(JobApplicationInterviewStatus::Cancelled, $planned->fresh()->status);
        $this->assertSame('Zweitgespräch', $planned->fresh()->notes);
        $this->assertSame(JobApplicationInterviewStatus::Done, $done->fresh()->status);
        $this->assertSame(4, $done->fresh()->rating);

        $audit = AuditLog::query()->where('event', 'recruiting.application_decided')->sole();
        $this->assertSame('rejected', $audit->changes['decision']);
        $this->assertSame(1, $audit->changes['interviews_cancelled']);
        $this->assertSame(0, $audit->changes['offers_expired']);
    }

    public function test_a_decision_expires_open_offers_and_the_public_link_is_gone(): void {
        $application = $this->application(JobApplicationStatus::Screened);
        $token = $this->offer($application, CarbonImmutable::parse('2026-10-13 12:00:00'));
        $open = JobInterviewOffer::query()->sole();
        // Ein längst abgelaufenes Angebot bleibt, wie es war.
        $this->offer($application, CarbonImmutable::parse('2026-10-01 09:00:00'));
        $stale = JobInterviewOffer::query()->whereKeyNot($open->id)->sole();
        $this->get(route('interview-offers.show', $token))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('recruiting.applications.decide', $application), ['decision' => 'withdrawn'])
            ->assertSessionHas('success');
        auth()->logout();

        $this->assertSame('2026-10-06 12:00:00', $open->fresh()->expires_at->toDateTimeString());
        $this->assertFalse($open->fresh()->isOpen());
        $this->assertSame('2026-10-01 09:00:00', $stale->fresh()->expires_at->toDateTimeString());
        $this->get(route('interview-offers.show', $token))->assertNotFound();
        $this->post(route('interview-offers.choose', $token), ['slot' => 0])->assertNotFound();
        $this->assertSame(0, JobApplicationInterview::query()->count());

        $audit = AuditLog::query()->where('event', 'recruiting.application_decided')->sole();
        $this->assertSame(0, $audit->changes['interviews_cancelled']);
        $this->assertSame(1, $audit->changes['offers_expired']);
    }

    // ── Oberfläche ───────────────────────────────────────────────────────

    public function test_the_readmit_button_appears_only_for_a_talent_pool_file_with_valid_consent(): void {
        $valid = $this->talentPoolFile();
        $html = $this->show($valid);
        $this->assertStringContainsString('action="' . route('recruiting.applications.readmit', $valid) . '"', $html);
        $this->assertStringContainsString(e(__('Diese Akte aus dem Talentpool wieder in die Pipeline aufnehmen? Löschvormerkung und Talentpool-Einwilligung werden entfernt.')), $html);
        $this->assertStringNotContainsString(e(__(self::CONSENT_MESSAGE)), $html);

        $expired = $this->talentPoolFile('2026-09-30');
        $html = $this->show($expired);
        $this->assertStringNotContainsString(route('recruiting.applications.readmit', $expired), $html);
        $this->assertStringContainsString(e(__(self::CONSENT_MESSAGE)), $html);

        foreach ([
            $this->application(JobApplicationStatus::Rejected, ['retention_until' => '2027-04-06']),
            $this->application(JobApplicationStatus::Screened),
        ] as $other) {
            $html = $this->show($other);
            $this->assertStringNotContainsString(route('recruiting.applications.readmit', $other), $html);
            $this->assertStringNotContainsString(e(__(self::CONSENT_MESSAGE)), $html);
        }
    }
}
