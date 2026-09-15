<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCatalogPlayerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningAudience;
use App\Models\{Article, Customer, User};
use App\Models\Learning\{LearningCourse, LearningEnrollment};
use App\Models\Survey\{Survey, SurveyAnswer, SurveyInvitation, SurveyQuestion, SurveyResponse};
use App\Services\Learning\{LearningCourseRatingService, LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Katalog und Player (Feature 149, MVP-794): Sternewert erst ab fünf
 * Feedback-Antworten, Liste/Kacheln als Nutzerpräferenz, Preis im Portal,
 * Fokusmodus im Player und „Duplizieren" als neuer Entwurf.
 */
class LearningCatalogPlayerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function course(string $title, array $attributes = []): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, array_merge(['title' => $title], $attributes));
        $courses->addUnit($course, ['title' => 'Grundlagen']);
        $courses->release($course->refresh(), null);

        return $course->refresh();
    }

    private function manager(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    /** Kursfeedback: eine Skalenfrage, `$values` Antworten mit Kursbezug. */
    private function feedback(LearningCourse $course, array $values): void {
        $survey = Survey::query()->create([
            'organization_id' => $this->organization->id, 'title' => 'Kursfeedback', 'active' => true,
            'anonymous' => true, 'trigger_on_course_completion' => true,
        ]);
        $question = SurveyQuestion::query()->create([
            'organization_id' => $this->organization->id, 'survey_id' => $survey->id, 'type' => 'scale',
            'label' => 'Wie hilfreich war der Kurs?', 'required' => true, 'position' => 1,
        ]);
        foreach ($values as $i => $value) {
            $invitation = SurveyInvitation::query()->create([
                'organization_id' => $this->organization->id, 'survey_id' => $survey->id, 'learning_course_id' => $course->id,
                'email' => "p{$i}@example.test", 'context_kind' => 'learning', 'token_hash' => hash('sha256', 'tok' . $i . $course->id),
                'expires_at' => now()->addWeek(), 'status' => 'responded', 'responded_at' => now(),
            ]);
            $response = SurveyResponse::query()->create([
                'organization_id' => $this->organization->id, 'survey_id' => $survey->id,
                'survey_invitation_id' => $invitation->id, 'context_kind' => 'learning',
            ]);
            SurveyAnswer::query()->create([
                'organization_id' => $this->organization->id, 'survey_response_id' => $response->id,
                'survey_question_id' => $question->id, 'value_int' => $value,
            ]);
        }
    }

    public function test_sternewert_erscheint_erst_ab_fuenf_antworten(): void {
        $rated = $this->course('Brandschutz');
        $sparse = $this->course('Erste Hilfe');
        $this->feedback($rated, [5, 4, 4, 5, 3]);
        $this->feedback($sparse, [1, 1, 1, 1]);

        $ratings = app(LearningCourseRatingService::class)->ratingsFor([$rated->id, $sparse->id]);
        $this->assertSame(['average' => 4.2, 'count' => 5], $ratings[$rated->id]);
        $this->assertArrayNotHasKey($sparse->id, $ratings, 'Unter fünf Antworten kein Wert — Rückrechenbarkeit.');

        $this->actingAs($this->manager())
            ->get(route('learning.courses.index'))
            ->assertOk()
            ->assertSee('★ 4,2');
    }

    public function test_kachelansicht_ist_eine_nutzerpraeferenz(): void {
        $this->course('Brandschutz', ['subtitle' => 'Kompakt für alle']);
        $manager = $this->manager();

        $this->actingAs($manager)
            ->get(route('learning.courses.index', ['view' => 'tiles']))
            ->assertOk()
            ->assertSee('Kompakt für alle')
            ->assertSee(__('learning.action.view_list'));
        $this->assertSame('tiles', $manager->refresh()->preferences['learning']['catalog_view']);

        // Ohne Parameter bleibt die gewählte Ansicht.
        $this->actingAs($manager)
            ->get(route('learning.courses.index'))
            ->assertOk()
            ->assertSee(__('learning.action.view_list'));

        $this->actingAs($manager)->get(route('learning.courses.index', ['view' => 'list']))->assertOk()->assertSee(__('learning.action.view_tiles'));
        $this->assertSame('list', $manager->refresh()->preferences['learning']['catalog_view']);
    }

    public function test_fokusmodus_blendet_die_seitenleiste_aus(): void {
        $course = $this->course('Brandschutz');
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $learner);

        $this->actingAs($learner)->get(route('learning.my.show', $enrollment))->assertOk()->assertSee(__('learning.action.focus_on'))->assertDontSee('data-player-sidebar hidden', false);

        $this->actingAs($learner)
            ->post(route('learning.my.focus', $enrollment))
            ->assertRedirect(route('learning.my.show', $enrollment));
        $this->assertTrue($learner->refresh()->preferences['learning']['focus_mode']);

        $this->actingAs($learner)
            ->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertSee(__('learning.action.focus_off'))
            ->assertSee('data-player-sidebar hidden', false);

        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($other)->post(route('learning.my.focus', $enrollment))->assertNotFound();
    }

    public function test_duplizieren_erzeugt_neuen_entwurf_ohne_einschreibungen(): void {
        $course = $this->course('Brandschutz', ['description' => 'Fluchtwege kennen']);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        app(LearningEnrollmentService::class)->enroll($course, $learner);

        $this->actingAs($this->manager())
            ->post(route('learning.courses.duplicate', $course))
            ->assertRedirect();

        $copy = LearningCourse::query()->where('title', 'Brandschutz (Kopie)')->firstOrFail();
        $this->assertNotSame($course->code, $copy->code);
        $this->assertSame('draft', $copy->status->value);
        $this->assertSame('Fluchtwege kennen', $copy->description);
        $this->assertSame(1, $copy->units()->count());
        $this->assertSame(0, LearningEnrollment::query()->where('learning_course_id', $copy->id)->count());
    }

    public function test_portal_zeigt_preis_aus_dem_artikel(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'default_sale_price' => '149.00']);
        $this->course('Verkaufstraining', ['audiences' => [LearningAudience::Customer->value], 'article_id' => $article->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer);
        $portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create();

        $this->actingAs($portalUser, 'customer')
            ->get(route('customer.learning.index'))
            ->assertOk()
            ->assertSee(__('learning.field.price'))
            ->assertSee('149,00');
    }
}
