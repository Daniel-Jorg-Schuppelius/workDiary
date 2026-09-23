<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningNotesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Learning;

use App\Enums\Communication\CommunicationVisibility;
use App\Models\Communication\CommunicationNote;
use App\Models\Learning\{LearningCourse, LearningEnrollment};
use App\Models\Platform\User;
use App\Models\Search\SearchDocument;
use App\Services\Communication\CommunicationNoteService;
use App\Services\Learning\{LearningCourseService, LearningEnrollmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Private Lernnotizen (Feature 149, MVP-789): eine Notiz an der eigenen
 * Einschreibung sieht nur die verfassende Person — auch keine Admins —,
 * sie lässt sich nicht veröffentlichen, steht nicht im Tätigkeitsindex
 * und erscheint in „Meine Schulungen".
 */
class LearningNotesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        config(['search.indexing' => true]);
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** @return array{0: LearningEnrollment, 1: User, 2: LearningCourse} */
    private function enrolled(): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        $courses->addUnit($course, ['title' => 'Grundlagen']);
        $courses->release($course->refresh(), null);
        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);

        return [app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner), $learner, $course->refresh()];
    }

    public function test_notiz_im_player_ist_privat_und_nur_fuer_die_verfasserin_sichtbar(): void {
        [$enrollment, $learner, $course] = $this->enrolled();
        $unit = $course->units()->firstOrFail();

        $this->actingAs($learner)
            ->post(route('learning.my.notes.store', $enrollment), ['body' => 'Merken: Fluchtweg links.', 'unit' => $unit->sqid])
            ->assertRedirect(route('learning.my.show', $enrollment) . '#learning-notes');

        $note = CommunicationNote::query()->firstOrFail();
        $this->assertSame(CommunicationVisibility::Private, $note->visibility);
        $this->assertSame('Grundlagen', $note->subject);
        $this->assertSame($enrollment->id, (int) $note->notable_id);

        $this->actingAs($learner)
            ->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertSee('Merken: Fluchtweg links.');
        $this->actingAs($learner)
            ->get(route('learning.my.index'))
            ->assertOk()
            ->assertSee('Merken: Fluchtweg links.');

        $admin = $this->orgAdmin();
        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->assertSame(0, CommunicationNote::query()->visibleTo($admin)->count(), 'Admins sehen private Notizen nicht.');
        $this->assertSame(0, CommunicationNote::query()->visibleTo($other)->count());
        $this->assertSame(1, CommunicationNote::query()->visibleTo($learner)->count());
        $this->assertFalse($other->can('view', $note));
        $this->assertTrue($learner->can('view', $note));
        // Der Admin-Bypass des Gates greift nicht: die Notizseite antwortet 404.
        $this->actingAs($admin)->get(route('communication-notes.show', $note))->assertNotFound();
        $this->actingAs($learner)->get(route('communication-notes.show', $note))->assertOk();

        // Kein Eintrag im Tätigkeitsindex — ein privater Merkzettel ist kein Organisationswissen.
        $this->assertSame(0, SearchDocument::query()->where('source_type', 'communication_note')->count());
    }

    public function test_private_notiz_laesst_sich_nicht_veroeffentlichen(): void {
        [$enrollment, $learner] = $this->enrolled();
        $note = app(CommunicationNoteService::class)->create($enrollment, $learner, [
            'type' => 'internal', 'direction' => 'internal', 'subject' => 'Privat', 'body' => 'Nur für mich.', 'visibility' => 'private',
        ]);

        $this->expectException(ValidationException::class);
        app(CommunicationNoteService::class)->publishToCustomer($note, $this->orgAdmin());
    }

    public function test_nur_die_verfasserin_loescht_ihre_notiz(): void {
        [$enrollment, $learner] = $this->enrolled();
        $this->actingAs($learner)->post(route('learning.my.notes.store', $enrollment), ['body' => 'Weg damit.']);
        $note = CommunicationNote::query()->firstOrFail();

        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($other)->delete(route('learning.my.notes.destroy', [$enrollment, $note]))->assertNotFound();

        $this->actingAs($learner)
            ->delete(route('learning.my.notes.destroy', [$enrollment, $note]))
            ->assertRedirect(route('learning.my.show', $enrollment) . '#learning-notes');
        $this->assertNull(CommunicationNote::query()->find($note->id));
    }

    public function test_fremde_einheit_wird_abgelehnt(): void {
        [$enrollment, $learner] = $this->enrolled();
        $foreign = app(LearningCourseService::class)->createCourse($this->organization, null, ['title' => 'Anderer']);
        $unit = app(LearningCourseService::class)->addUnit($foreign, ['title' => 'Fremd']);

        $this->actingAs($learner)
            ->post(route('learning.my.notes.store', $enrollment), ['body' => 'Falscher Kurs.', 'unit' => $unit->sqid])
            ->assertNotFound();
    }
}
