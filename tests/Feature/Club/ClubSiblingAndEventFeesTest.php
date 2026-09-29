<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubSiblingAndEventFeesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubEventVisibility, ClubExamCandidateStatus, ClubFeePositionKind, ClubParticipationStatus};
use App\Models\Club\{ClubEventParticipation, ClubExamCandidate, ClubFeeAccount, ClubFeeTariff, ClubGroup, ClubMember};
use App\Models\Platform\User;
use App\Services\Club\{ClubEventService, ClubExamService, ClubFeeCalculator, ClubFeeService, ClubGradingService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1016/1017: Geschwisterstaffel sowie Lehrgangs- und Prüfungsgebühren im Beitragslauf. */
final class ClubSiblingAndEventFeesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function fees(): ClubFeeService {
        return app(ClubFeeService::class);
    }

    private function tariff(): ClubFeeTariff {
        $tariff = $this->fees()->createTariff($this->organization, ['name' => 'Einzel']);
        $this->fees()->saveRate($tariff, ['valid_from' => '2026-01-01', 'interval' => 'monthly', 'amount' => '20,00', 'anchor_month' => 1, 'due_days' => 14, 'proration' => 'full']);

        return $tariff->refresh();
    }

    private function account(): ClubFeeAccount {
        return $this->fees()->createAccount($this->organization, ['name' => 'Familie Muster', 'email' => 'muster@example.test'], $this->admin);
    }

    /** @return array<string, string> */
    private function amounts(int $year, int $month): array {
        return app(ClubFeeCalculator::class)->calculateMonth($this->organization, $year, $month)['positions']
            ->mapWithKeys(fn ($p): array => [$p->sourceKey => $p->amount->getAmount()])->all();
    }

    public function test_siblings_get_the_configured_staffel(): void {
        $tariff = $this->tariff();
        $account = $this->account();
        $keys = [];
        foreach (['2012-03-01', '2016-07-01', '2014-05-01', '1980-01-01'] as $birth) {
            $member = ClubMember::factory()->create(['joined_on' => '2026-01-01', 'birth_date' => $birth]);
            $assignment = $this->fees()->assign($account, $member, $tariff, ['valid_from' => '2026-01-01']);
            $keys[$birth] = 'assignment:' . $assignment->id . ':2026-01-01';
        }
        $this->assertSame(['20.00'], array_values(array_unique($this->amounts(2026, 1))));

        $this->actingAs($this->admin)->put(route('club.settings.update'), ['siblings' => ['second_percent' => '25', 'further_percent' => '50', 'max_age' => '18']])
            ->assertSessionHasNoErrors();
        $this->organization->refresh();

        $amounts = $this->amounts(2026, 1);
        $this->assertSame('20.00', $amounts[$keys['2012-03-01']], 'Ältestes Kind zahlt voll.');
        $this->assertSame('15.00', $amounts[$keys['2014-05-01']]);
        $this->assertSame('10.00', $amounts[$keys['2016-07-01']]);
        $this->assertSame('20.00', $amounts[$keys['1980-01-01']], 'Erwachsene zählen nicht zur Staffel.');
    }

    public function test_course_and_exam_fees_follow_registration_and_admission(): void {
        $account = $this->account();
        $tariff = $this->tariff();
        $member = ClubMember::factory()->aged(20)->create(['joined_on' => '2026-01-01']);
        $this->fees()->assign($account, $member, $tariff, ['valid_from' => '2026-01-01']);
        $group = ClubGroup::factory()->create(['name' => 'Judo', 'discipline' => 'Judo']);
        $start = CarbonImmutable::parse('2026-05-12 10:00', 'Europe/Berlin');
        $times = ['started_at' => $start->utc()->format('Y-m-d H:i:s'), 'ended_at' => $start->addHours(3)->utc()->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Berlin',
            'visibility' => ClubEventVisibility::Groups->value, 'club_group_ids' => [$group->id]];

        $course = app(ClubEventService::class)->create($this->organization, $this->admin, ['title' => 'Kampfrichterlehrgang', 'kind' => 'course', 'fee_amount' => '40.00'] + $times);
        $registered = ClubEventParticipation::query()->create(['organization_id' => $this->organization->id, 'event_id' => $course->id, 'club_member_id' => $member->id, 'status' => ClubParticipationStatus::Registered->value, 'source' => 'admin', 'registered_at' => now()]);

        $grading = app(ClubGradingService::class);
        $grading->setEnabled($this->organization, true);
        $system = $grading->createSystem($this->organization, ['name' => 'Judo-Ordnung', 'discipline' => 'Judo']);
        $grade = $grading->createGrade($system, ['name' => 'Gelb', 'rank' => 1]);
        $grading->activateVersion($grading->createVersion($system), $this->admin);
        $offer = app(ClubExamService::class)->createOffer($this->organization, $this->admin, ['title' => 'Gürtelprüfung', 'club_grading_system_id' => $system->id,
            'target_grade_ids' => [$grade->id], 'examiner_user_ids' => [], 'fee_amount' => '25.00'] + $times);
        $admitted = ClubExamCandidate::query()->create(['organization_id' => $this->organization->id, 'club_exam_offer_id' => $offer->id, 'club_member_id' => $member->id, 'target_grade_id' => $grade->id, 'status' => ClubExamCandidateStatus::Admitted->value]);
        $other = ClubMember::factory()->aged(20)->create(['joined_on' => '2026-01-01']);
        $this->fees()->assign($account, $other, $tariff, ['valid_from' => '2026-01-01']);
        ClubExamCandidate::query()->create(['organization_id' => $this->organization->id, 'club_exam_offer_id' => $offer->id, 'club_member_id' => $other->id, 'target_grade_id' => $grade->id, 'status' => ClubExamCandidateStatus::Requested->value]);

        $positions = app(ClubFeeCalculator::class)->calculateMonth($this->organization, 2026, 5)['positions'];
        $course = $positions->firstWhere('sourceKey', 'course:' . $registered->id);
        $exam = $positions->firstWhere('sourceKey', 'exam:' . $admitted->id);
        $this->assertSame(ClubFeePositionKind::Course, $course?->kind);
        $this->assertSame('40.00', $course->amount->getAmount());
        $this->assertSame(ClubFeePositionKind::Exam, $exam?->kind);
        $this->assertSame('25.00', $exam->amount->getAmount());
        $this->assertCount(1, $positions->where('kind', ClubFeePositionKind::Exam), 'Nur zugelassene Kandidaten zahlen.');
    }
}
