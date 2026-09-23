<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningSearchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Search\SearchSourceType;
use App\Models\Learning\LearningCourse;
use App\Models\Platform\User;
use App\Models\Search\SearchDocument;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService};
use App\Services\Search\{ActivitySearchCriteria, ActivitySearchResult, ActivitySearchService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Lernkurse in der Tätigkeitsrecherche (Feature 149, MVP-789): nur
 * freigegebene Kurse stehen im Index; Lernende finden ihre eigenen Kurse,
 * Autoren mit `learning.viewAny` alle; der Treffer führt in den Player bzw.
 * die Kursakte.
 */
class LearningSearchTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        config(['search.indexing' => true]);
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
    }

    private function search(User $user, string $query): ActivitySearchResult {
        $this->actAsTeam($user->organization_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return app(ActivitySearchService::class)->search($user, new ActivitySearchCriteria(query: $query));
    }

    private function course(string $title, bool $release = true): LearningCourse {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => $title, 'description' => 'Fluchtwege und Feuerlöscher richtig einsetzen.']);
        $courses->addUnit($course, ['title' => 'Grundlagen']);
        if ($release) {
            $courses->release($course->refresh(), null);
        }

        return $course->refresh();
    }

    public function test_nur_freigegebene_kurse_stehen_im_index(): void {
        $released = $this->course('Brandschutz kompakt');
        $draft = $this->course('Brandschutz Entwurf', release: false);

        $ids = SearchDocument::query()->where('source_type', SearchSourceType::LearningCourse->value)->pluck('source_id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$released->id], $ids);
        $this->assertNotContains($draft->id, $ids);

        // Zurück in den Entwurf: der Kurs verlässt den Index.
        app(LearningCourseService::class)->reopen($released);
        $this->assertSame(0, SearchDocument::query()->where('source_type', SearchSourceType::LearningCourse->value)->count());
    }

    public function test_lernende_finden_nur_eigene_kurse_und_landen_im_player(): void {
        $course = $this->course('Brandschutz kompakt');
        $this->course('Brandschutz für Führungskräfte');
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course, $learner);

        $result = $this->search($learner, 'Brandschutz');
        $this->assertSame(1, $result->hits->total());
        $hit = $result->hits->first();
        $this->assertSame(SearchSourceType::LearningCourse, $hit->type);
        $this->assertSame($course->id, $hit->sourceId);

        $this->actingAs($learner);
        $this->assertSame(
            route('learning.my.show', $enrollment),
            app(\App\Services\Search\SearchResultLinker::class)->urls(SearchDocument::query()->where('source_id', $course->id)->get())[SearchSourceType::LearningCourse->value . ':' . $course->id],
        );

        $stranger = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->assertSame(0, $this->search($stranger, 'Brandschutz')->hits->total());
    }

    public function test_autorin_mit_viewany_findet_alle_freigegebenen_kurse(): void {
        $this->course('Brandschutz kompakt');
        $this->course('Brandschutz für Führungskräfte');
        $author = $this->actorIn($this->organization, [\App\Enums\User\Permission::LearningViewAny, \App\Enums\User\Permission::LearningAuthor]);

        $result = $this->search($author, 'Feuerlöscher');
        $this->assertSame(2, $result->hits->total());
        $this->assertContains(SearchSourceType::LearningCourse, app(\App\Services\Search\ActivitySearchVisibility::class)->types($author));
    }
}
