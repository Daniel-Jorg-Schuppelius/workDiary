<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyEngineTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Models\{Customer, User};
use App\Models\Survey\{Survey, SurveyInvitation, SurveyResponse};
use App\Services\Survey\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Umfrage-Engine (Feature 090, MVP-660–662).
 *
 * Kern der Prüfung: **Anonyme Antworten sind technisch nicht rückführbar**
 * (kein Einladungsbezug, kein Antwortzeitpunkt), der Ermüdungsschutz deckelt
 * über alle Fragebögen, und der NPS rechnet Promotoren minus Detraktoren.
 */
final class SurveyEngineTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function survey(array $attributes = []): Survey {
        $survey = Survey::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'title' => 'Jahres-NPS',
            'active' => true,
            'anonymous' => false,
            'created_by' => $this->admin->id,
        ], $attributes));
        $survey->questions()->create([
            'organization_id' => $this->organization->id,
            'type' => 'nps',
            'label' => 'Wie wahrscheinlich empfehlen Sie uns weiter?',
            'required' => true,
            'position' => 1,
        ]);

        return $survey;
    }

    public function test_participation_via_public_token_link(): void {
        $survey = $this->survey();
        $issued = app(SurveyService::class)->invite($survey, 'kunde@example.test');

        $this->get(route('surveys.public-show', ['token' => $issued['token']]))
            ->assertOk()
            ->assertSee('Jahres-NPS');

        $question = $survey->questions()->firstOrFail();
        $this->post(route('surveys.public-store', ['token' => $issued['token']]), [
            'q' . $question->id => 9,
        ])->assertOk()->assertSee('Vielen Dank');

        $this->assertSame(1, SurveyResponse::query()->count());
        // Der Link ist verbraucht.
        $this->get(route('surveys.public-show', ['token' => $issued['token']]))->assertNotFound();
    }

    /** Anonym: kein Einladungsbezug, kein Antwortzeitpunkt. */
    public function test_anonymous_answers_are_technically_untraceable(): void {
        $survey = $this->survey(['anonymous' => true]);
        $issued = app(SurveyService::class)->invite($survey, 'anonym@example.test');
        $question = $survey->questions()->firstOrFail();

        app(SurveyService::class)->submit($issued['invitation'], [$question->id => 10]);

        $response = SurveyResponse::query()->firstOrFail();
        $this->assertNull($response->survey_invitation_id);

        $invitation = $issued['invitation']->fresh();
        $this->assertSame(SurveyInvitation::STATUS_RESPONDED, $invitation?->status);
        $this->assertNull($invitation?->responded_at);
    }

    /** Der Deckel gilt über ALLE Fragebögen der Organisation. */
    public function test_fatigue_guard_spans_all_surveys(): void {
        $first = $this->survey();
        $second = $this->survey(['title' => 'Projektabschluss']);

        app(SurveyService::class)->invite($first, 'gleich@example.test');

        $this->expectException(\RuntimeException::class);
        app(SurveyService::class)->invite($second, 'gleich@example.test');
    }

    public function test_opted_out_customer_is_not_invited(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'survey_opt_out' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        app(SurveyService::class)->invite($this->survey(), 'optout@example.test', $customer);
    }

    public function test_nps_score_is_promoters_minus_detractors(): void {
        $survey = $this->survey();
        $service = app(SurveyService::class);
        $question = $survey->questions()->firstOrFail();

        // 2 Promotoren (10, 9), 1 Passiver (8), 1 Detraktor (3) → (2-1)/4 = 25.
        foreach ([['a', 10], ['b', 9], ['c', 8], ['d', 3]] as [$who, $score]) {
            $issued = $service->invite($survey, $who . '@example.test');
            $service->submit($issued['invitation'], [$question->id => $score]);
        }

        $this->assertSame(25, $service->npsScore($survey));
    }

    /** Ohne Antworten kein Score — null, nicht 0. */
    public function test_nps_without_answers_is_null(): void {
        $this->assertNull(app(SurveyService::class)->npsScore($this->survey()));
    }

    public function test_expired_or_used_token_is_404(): void {
        $survey = $this->survey();
        $issued = app(SurveyService::class)->invite($survey, 'ablauf@example.test');
        $issued['invitation']->forceFill(['expires_at' => now()->subDay()])->save();

        $this->get(route('surveys.public-show', ['token' => $issued['token']]))->assertNotFound();
        $this->get(route('surveys.public-show', ['token' => str_repeat('x', 48)]))->assertNotFound();
    }

    public function test_admin_page_requires_customer_rights(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('surveys.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('surveys.index'))->assertOk();
    }
}
