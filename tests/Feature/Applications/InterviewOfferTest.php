<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InterviewOfferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Applications;

use App\Enums\User\UserRole;
use App\Mail\{InterviewConfirmedMail, InterviewOfferMail};
use App\Models\Applications\{JobApplication, JobApplicationInterview, JobInterviewOffer, JobRequisition};
use App\Models\Platform\User;
use App\Services\Applications\RecruitingService;
use App\Services\Event\IcsFeedService;
use App\Support\Tz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-925: Terminwahl durch den Bewerber per Link, Gespräch mit Bestätigung und ICS. */
final class InterviewOfferTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $hr;

    private JobApplication $application;

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-09-28 09:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->hr = $this->userWithRole(UserRole::Personalverwaltung->value);
        $requisition = JobRequisition::query()->create(['organization_id' => $this->organization->id, 'title' => 'Servicetechniker:in', 'status' => 'open']);
        $this->application = app(RecruitingService::class)->intake(['job_requisition_id' => $requisition->id, 'candidate_name' => 'Kim Neu', 'email' => 'kim.neu@example.test', 'source' => 'website'], $this->hr)['application'];
    }

    private function offer(): string {
        $this->actingAs($this->hr)->post(route('recruiting.applications.interview-offers.store', $this->application), [
            'slots' => ['2026-10-01T10:00', '2026-10-02T14:30', '2026-09-01T10:00'],
            'mode' => 'onsite', 'duration_minutes' => 45, 'valid_days' => 7,
        ])->assertSessionHas('success');
        $token = null;
        Mail::assertQueued(InterviewOfferMail::class, function (InterviewOfferMail $m) use (&$token): bool {
            $token = $m->token;

            return $m->hasTo('kim.neu@example.test');
        });
        auth()->logout();

        return (string) $token;
    }

    public function test_applicant_chooses_a_slot_and_gets_a_confirmation(): void {
        $token = $this->offer();
        $offer = JobInterviewOffer::query()->sole();
        // Vergangener Termin fällt weg; Ortszeit → UTC.
        $this->assertCount(2, $offer->slots);
        $this->assertSame(Tz::parse('2026-10-01 10:00')->utc()->toIso8601String(), $offer->slots[0]);
        $this->assertNotSame($token, $offer->token_hash);

        (new InterviewOfferMail($offer->id, $token))->assertSeeInText(route('interview-offers.show', $token))->assertSeeInText('Kim Neu');
        $this->get(route('interview-offers.show', $token))->assertOk()->assertSee('Servicetechniker:in');
        $this->post(route('interview-offers.choose', $token), ['slot' => 1])->assertOk()->assertSee(__('recruiting.offer.confirmed_title'));

        $interview = JobApplicationInterview::query()->sole();
        $this->assertSame($offer->slots[1], $interview->scheduled_at->toIso8601String());
        $this->assertSame('interview_planned', $this->application->fresh()->status);
        Mail::assertQueued(InterviewConfirmedMail::class, fn (InterviewConfirmedMail $m): bool => $m->hasTo('kim.neu@example.test'));
        $this->assertStringContainsString('BEGIN:VCALENDAR', app(IcsFeedService::class)->documentForInterview($interview, 45));
        $confirmed = new InterviewConfirmedMail($interview->id, 45);
        $confirmed->assertSeeInText('Kim Neu');
        $this->assertCount(1, $confirmed->attachments());

        // Der Link ist verbraucht.
        $this->get(route('interview-offers.show', $token))->assertNotFound();
        $this->post(route('interview-offers.choose', $token), ['slot' => 0])->assertNotFound();
    }

    public function test_unknown_or_expired_links_are_not_found(): void {
        $token = $this->offer();
        $this->get(route('interview-offers.show', 'falsch'))->assertNotFound();
        $this->travelTo('2026-10-10 09:00:00');
        $this->get(route('interview-offers.show', $token))->assertNotFound();
    }

    public function test_only_recruiting_may_offer(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $this->actingAs($lead)->post(route('recruiting.applications.interview-offers.store', $this->application), ['slots' => ['2026-10-01T10:00'], 'mode' => 'onsite', 'duration_minutes' => 45, 'valid_days' => 7])->assertForbidden();
    }
}
