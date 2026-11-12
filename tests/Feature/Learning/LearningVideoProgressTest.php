<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningVideoProgressTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningBlockKind;
use App\Enums\Media\{MediaRenditionKind, MediaState};
use App\Models\Attachments\Attachment;
use App\Models\Learning\{LearningEnrollment, LearningUnit, LearningVideoProgress};
use App\Models\Media\MediaRendition;
use App\Models\Platform\User;
use App\Services\Learning\{LearningContentService, LearningCourseService, LearningEnrollmentService, LearningVideoProgressService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Pflichtanteil eines Kursvideos (MVP-1598): erfassen, plausibilisieren, Abschluss sperren. */
final class LearningVideoProgressTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        Carbon::setTestNow('2026-09-01 08:00:00');
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: LearningEnrollment, 1: LearningUnit, 2: Attachment, 3: User} */
    private function enrollmentWithVideo(int $requirePercent = 80, int $duration = 200): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Ladungssicherung']);
        $courses->addUnit($course, ['title' => 'Lehrfilm']);
        $unit = $course->refresh()->units()->firstOrFail();

        $attachment = Attachment::query()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => $unit->getMorphClass(),
            'attachable_id' => $unit->id,
            'disk' => 'local',
            'path' => 'videos/film.mp4',
            'original_name' => 'film.mp4',
            'mime' => 'video/mp4',
            'size' => 1000,
            'media_state' => MediaState::Ready,
            'media_duration_seconds' => $duration,
        ]);
        MediaRendition::query()->create([
            'organization_id' => $this->organization->id,
            'attachment_id' => $attachment->id,
            'kind' => MediaRenditionKind::Video->value,
            'variant' => '480p',
            'disk' => 'local',
            'path' => 'videos/renditions/' . $attachment->id . '/480p.mp4',
            'mime' => 'video/mp4',
            'size_bytes' => 100,
        ]);
        app(LearningContentService::class)->appendBlock($unit, LearningBlockKind::Video, [
            'attachment_id' => $attachment->id,
            'require_percent' => $requirePercent,
        ]);
        $courses->release($course->refresh(), null);

        $learner = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        return [$enrollment, $unit->refresh(), $attachment, $learner];
    }

    public function test_unit_cannot_be_completed_below_the_required_share(): void {
        [$enrollment, $unit, $attachment] = $this->enrollmentWithVideo();
        $enrollments = app(LearningEnrollmentService::class);
        $progress = app(LearningVideoProgressService::class);

        try {
            $enrollments->completeUnit($enrollment, $unit);
            $this->fail('Ohne gesehenes Video darf die Einheit nicht abschließbar sein.');
        } catch (ValidationException $exception) {
            $this->assertSame(__('learning.errors.video_percent_not_reached', ['percent' => 0, 'required' => 80]), $exception->errors()['unit'][0]);
        }

        // 200 s Video, 80 % = 160 s: im Takt der Uhr gemeldet.
        $progress->record($enrollment, $unit, $attachment, 20);
        for ($i = 0; $i < 7; $i++) {
            Carbon::setTestNow(Carbon::now()->addSeconds(20));
            $reply = $progress->record($enrollment, $unit, $attachment, 20);
        }
        $this->assertSame(160, LearningVideoProgress::query()->firstOrFail()->watched_seconds);
        $this->assertSame(80, $reply['percent']);
        $this->assertTrue($reply['unit_satisfied']);

        $enrollments->completeUnit($enrollment->refresh(), $unit);
        $this->assertTrue($enrollment->refresh()->status->isFinal());
    }

    public function test_reports_are_capped_by_the_clock_and_the_video_length(): void {
        [$enrollment, $unit, $attachment] = $this->enrollmentWithVideo(100, 100);
        $progress = app(LearningVideoProgressService::class);

        // Die erste Meldung hat keine Vergleichszeit: nur der Vorschuss zählt.
        $this->assertSame(30, $progress->record($enrollment, $unit, $attachment, 600)['accepted']);
        // Sofort noch einmal: die Uhr ist nicht weitergelaufen.
        $this->assertSame(0, $progress->record($enrollment, $unit, $attachment, 600)['accepted']);

        Carbon::setTestNow(Carbon::now()->addSeconds(10));
        $this->assertSame(25, $progress->record($enrollment, $unit, $attachment, 600)['accepted'], 'Zehn Sekunden Uhrzeit tragen höchstens 25 Sekunden Video.');

        Carbon::setTestNow(Carbon::now()->addHour());
        $this->assertSame(45, $progress->record($enrollment, $unit, $attachment, 600)['accepted'], 'Nie über die Videodauer hinaus.');
        $row = LearningVideoProgress::query()->firstOrFail();
        $this->assertSame(100, $row->watched_seconds);
        $this->assertSame(100, $row->watched_percent);

        // Der Anteil fällt nicht, wenn das Video später länger wird.
        $attachment->update(['media_duration_seconds' => 400]);
        Carbon::setTestNow(Carbon::now()->addMinute());
        $this->assertSame(100, $progress->record($enrollment, $unit, $attachment->refresh(), 1)['percent']);
    }

    public function test_player_endpoint_records_only_for_the_own_enrollment(): void {
        [$enrollment, $unit, $attachment, $learner] = $this->enrollmentWithVideo();
        $url = route('learning.my.units.video-progress', [$enrollment->sqid, $unit->sqid, $attachment->sqid]);

        $this->actingAs($learner)->postJson($url, ['watched_seconds' => 12])
            ->assertOk()
            ->assertJson(['accepted' => 12, 'percent' => 6, 'required' => 80, 'satisfied' => false, 'unit_satisfied' => false]);
        $this->actingAs($learner)->postJson($url, ['watched_seconds' => 5000])->assertStatus(422);

        $other = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($other)->postJson($url, ['watched_seconds' => 12])->assertNotFound();

        // Gespeichert ist nur die Summe.
        $this->assertSame(['watched_seconds' => 12, 'watched_percent' => 6], LearningVideoProgress::query()->firstOrFail()->only(['watched_seconds', 'watched_percent']));
    }

    public function test_player_shows_the_share_and_locks_the_complete_button(): void {
        [$enrollment, $unit, $attachment, $learner] = $this->enrollmentWithVideo();

        $this->actingAs($learner)->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertSee('data-video-progress="' . route('learning.my.units.video-progress', [$enrollment->sqid, $unit->sqid, $attachment->sqid]) . '"', false)
            ->assertSee(__('learning.help.video_progress', ['percent' => 0, 'required' => 80]))
            ->assertSee(__('learning.help.video_gate'))
            ->assertSee('data-video-gate-hint="' . $unit->sqid . '"', false);

        $this->actingAs($learner)->post(route('learning.my.units.complete', [$enrollment, $unit]))
            ->assertSessionHasErrors('unit');

        LearningVideoProgress::query()->create([
            'organization_id' => $this->organization->id, 'learning_enrollment_id' => $enrollment->id,
            'learning_unit_id' => $unit->id, 'attachment_id' => $attachment->id, 'watched_seconds' => 170, 'watched_percent' => 85,
        ]);
        $this->actingAs($learner)->get(route('learning.my.show', $enrollment))
            ->assertOk()
            ->assertSee(__('learning.help.video_progress', ['percent' => 85, 'required' => 80]))
            ->assertDontSee(__('learning.help.video_gate'));
    }

    public function test_required_share_needs_an_uploaded_video(): void {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Einweisung']);
        $courses->addUnit($course, ['title' => 'Film']);
        $unit = $course->refresh()->units()->firstOrFail();
        $this->organization->update(['settings' => ['learning' => ['embed_hosts' => ['video.example.test']]]]);

        try {
            app(LearningContentService::class)->appendBlock($unit->refresh(), LearningBlockKind::Video, [
                'url' => 'https://video.example.test/embed/1', 'require_percent' => 50,
            ]);
            $this->fail('Ein Pflichtanteil an einer fremden Einbettung ist nicht messbar.');
        } catch (ValidationException $exception) {
            $this->assertSame(__('learning.errors.video_percent_needs_upload'), $exception->errors()['require_percent'][0]);
        }

        // Ohne Pflichtanteil bleibt die Einbettung erlaubt.
        $blocks = app(LearningContentService::class)->appendBlock($unit->refresh(), LearningBlockKind::Video, ['url' => 'https://video.example.test/embed/1']);
        $this->assertSame('https://video.example.test/embed/1', $blocks[0]['url']);
    }
}
