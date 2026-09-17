<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NoteAndCourseTagsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Knowledge;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Enums\Search\SearchSourceType;
use App\Enums\User\Permission;
use App\Models\{CommunicationNote, Tag, User};
use App\Models\Learning\LearningCourse;
use App\Services\Communication\CommunicationNoteService;
use App\Services\Learning\LearningCourseService;
use App\Services\Search\{ActivitySearchCriteria, ActivitySearchService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Schlagwörter für Notizen und Lernkurse (MVP-810, Feature 155): die beiden
 * Modelle der Wissenswelt, die bis dahin in keiner Schlagwortordnung vorkamen.
 */
final class NoteAndCourseTagsTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        config(['search.indexing' => true]);
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
    }

    public function test_note_keeps_its_tags_and_the_list_filters_by_tag(): void {
        $user = $this->member();

        $this->actingAs($user)->post(route('communication-notes.store'), $this->quickPayload([
            'subject' => 'Heizung Halle B',
            'tags' => 'Wartung, Heizung , wartung',
        ]))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('communication-notes.store'), $this->quickPayload([
            'subject' => 'Rückruf Lieferant',
        ]))->assertSessionHasNoErrors();

        $tagged = CommunicationNote::query()->where('subject', 'Heizung Halle B')->firstOrFail();
        // Doppelte Schreibweise ergibt ein Schlagwort, nicht zwei.
        $this->assertEqualsCanonicalizing(['Wartung', 'Heizung'], $tagged->tags->pluck('name')->all());

        $wartung = Tag::query()->where('name', 'Wartung')->firstOrFail();
        $this->actingAs($user)->get(route('communication-notes.index', ['tag' => $wartung->sqid]))
            ->assertOk()
            ->assertSee('Heizung Halle B')
            ->assertDontSee('Rückruf Lieferant');
    }

    public function test_editing_without_the_tag_field_keeps_existing_tags(): void {
        $user = $this->member();
        $note = app(CommunicationNoteService::class)->create($this->organization, $user, $this->serviceAttributes(['tags' => 'Wartung']));

        app(CommunicationNoteService::class)->update($note, $user, ['subject' => 'Neuer Betreff']);
        $this->assertSame(['Wartung'], $note->fresh()?->tags->pluck('name')->all());

        app(CommunicationNoteService::class)->update($note, $user, ['tags' => '']);
        $this->assertSame([], $note->fresh()?->tags->pluck('name')->all());
    }

    public function test_tag_filter_offers_no_tags_from_confidential_notes_of_others(): void {
        $author = $this->member();
        $this->grantPermissions($author, [Permission::CommunicationConfidentialManage]);
        app(CommunicationNoteService::class)->create($this->organization, $author, $this->serviceAttributes([
            'subject' => 'Personalgespräch',
            'confidential' => true,
            'tags' => 'Abmahnung',
        ]));
        app(CommunicationNoteService::class)->create($this->organization, $author, $this->serviceAttributes(['tags' => 'Wartung']));

        $colleague = $this->member();
        $this->actingAs($colleague)->get(route('communication-notes.index'))
            ->assertOk()
            ->assertSee('Wartung')
            ->assertDontSee('Abmahnung');
    }

    public function test_activity_search_finds_a_note_by_its_tag(): void {
        $user = $this->member();
        app(CommunicationNoteService::class)->create($this->organization, $user, $this->serviceAttributes([
            'subject' => 'Rückruf Kunde',
            'body' => 'Termin bestätigt.',
            'tags' => 'Brandschutzklappe',
        ]));

        $this->actAsTeam($this->organization);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $result = app(ActivitySearchService::class)->search($user, new ActivitySearchCriteria(query: 'brandschutzklappe'));

        $this->assertSame(1, $result->hits->total());
        $this->assertSame(SearchSourceType::CommunicationNote, $result->hits->first()->type);
    }

    public function test_course_tags_stay_editable_after_release_and_filter_the_catalog(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $courses = app(LearningCourseService::class);
        $fire = $courses->createCourse($this->organization, $admin, ['title' => 'Brandschutz', 'tags' => 'Pflicht, Sicherheit']);
        $courses->createCourse($this->organization, $admin, ['title' => 'Excel für Einsteiger']);
        $courses->addUnit($fire, ['title' => 'Einführung']);
        $courses->release($fire->refresh(), null);

        // Ordnen ist kein Inhalt: nach der Freigabe weiter pflegbar.
        $courses->updateCourse($fire->refresh(), ['tags' => 'Pflicht, Sicherheit, Jährlich']);
        $this->assertEqualsCanonicalizing(['Pflicht', 'Sicherheit', 'Jährlich'], $fire->fresh()?->tags->pluck('name')->all());

        $pflicht = Tag::query()->where('name', 'Pflicht')->firstOrFail();
        $this->actingAs($admin)->get(route('learning.courses.index', ['tag' => $pflicht->sqid, 'view' => 'list']))
            ->assertOk()
            ->assertSee('Brandschutz')
            ->assertDontSee('Excel für Einsteiger');
    }

    public function test_course_form_saves_tags(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $course = app(LearningCourseService::class)->createCourse($this->organization, $admin, ['title' => 'Staplerschein']);

        $this->actingAs($admin)->get(route('learning.courses.edit', $course))->assertOk()->assertSee('name="tags"', false);

        $this->actingAs($admin)->put(route('learning.courses.update', $course), [
            'title' => 'Staplerschein',
            'access_kind' => $course->access_kind->value,
            'time_policy' => $course->time_policy->value,
            'instruction_suitability' => $course->instruction_suitability->value,
            'tags' => 'Logistik, Pflicht',
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['Logistik', 'Pflicht'], LearningCourse::query()->findOrFail($course->id)->tags->pluck('name')->all());
    }

    private function member(): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->grantPermissions($user, [
            Permission::CommunicationViewAny,
            Permission::CommunicationView,
            Permission::CommunicationCreate,
            Permission::CommunicationUpdate,
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function quickPayload(array $overrides = []): array {
        return [
            'storage' => 'internal',
            'type' => CommunicationNoteType::General->value,
            'occurred_at' => now()->subMinutes(30)->format('Y-m-d H:i'),
            'subject' => 'Notiz',
            'body' => 'Inhalt der Notiz.',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function serviceAttributes(array $overrides = []): array {
        return [
            'type' => CommunicationNoteType::General->value,
            'direction' => CommunicationDirection::Internal->value,
            'occurred_at' => now()->subHour()->toDateTimeString(),
            'subject' => 'Notiz für den Test',
            'body' => 'Notiztext für den Test.',
            ...$overrides,
        ];
    }
}
