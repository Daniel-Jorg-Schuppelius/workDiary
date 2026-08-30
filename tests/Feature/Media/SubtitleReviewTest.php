<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubtitleReviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Media;

use App\Enums\Media\{MediaRenditionKind, MediaState, SubtitleSource};
use App\Jobs\TranscribeSubtitleJob;
use App\Models\{Attachment, User};
use App\Models\Learning\{LearningCourse, LearningUnit};
use App\Models\Media\MediaRendition;
use App\Services\Learning\LearningCourseService;
use App\Services\Media\{MediaPresenter, VideoTranscodingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Maschinelle Untertitel (Feature 150).
 *
 * Der Prüfgegenstand ist **nicht** die Spracherkennung — die läuft im
 * common-toolkit und braucht Whisper auf dem Rechner. Geprüft wird die
 * Regel darum herum: eine maschinelle Spur ist ein Entwurf, sie wird als
 * solcher ausgespielt, und erst die Durchsicht durch einen Menschen macht
 * daraus einen Nachweis nach WCAG 1.2.2.
 */
class SubtitleReviewTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_maschinelle_spur_wartet_auf_durchsicht(): void {
        [, , $attachment] = $this->courseWithVideo();
        $track = $this->machineTrack($attachment);

        $this->assertTrue($track->awaitsReview());
        $this->assertTrue($this->presented($attachment)['machine']);
    }

    public function test_handspur_braucht_keine_durchsicht(): void {
        [, , $attachment] = $this->courseWithVideo();

        $track = app(VideoTranscodingService::class)->attachSubtitle(
            $attachment,
            "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nFluchtwege freihalten\n",
            'de',
        );

        $this->assertSame(SubtitleSource::Manual, $track->source);
        $this->assertFalse($track->awaitsReview());
        $this->assertFalse($this->presented($attachment)['machine']);
    }

    public function test_durchsicht_haelt_fest_wer_gelesen_hat(): void {
        [$course, $unit, $attachment] = $this->courseWithVideo();
        $track = $this->machineTrack($attachment);
        $author = $this->author();

        $this->actingAs($author)
            ->post(route('learning.courses.units.subtitles.review', [$course->sqid, $unit->sqid, $track->sqid]))
            ->assertRedirect();

        $track->refresh();

        $this->assertNotNull($track->reviewed_at);
        $this->assertSame($author->id, $track->reviewed_by);
        $this->assertFalse($track->awaitsReview());
        // Ab jetzt ohne Warnhinweis in der Spurauswahl des Abspielers.
        $this->assertFalse($this->presented($attachment)['machine']);
    }

    public function test_ersetzte_spur_verliert_die_durchsicht(): void {
        [, , $attachment] = $this->courseWithVideo();
        $track = $this->machineTrack($attachment);

        app(VideoTranscodingService::class)->markSubtitleReviewed($track, $this->author());

        // Dieselbe Sprache erneut hochgeladen: ein anderer Text, für den die
        // alte Durchsicht nichts aussagt.
        $replaced = app(VideoTranscodingService::class)->attachSubtitle(
            $attachment,
            "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nGeänderter Text\n",
            'de',
        );

        $this->assertSame($track->id, $replaced->id);
        $this->assertNull($replaced->reviewed_at);
    }

    public function test_spur_einer_fremden_einheit_wird_abgelehnt(): void {
        [$course, , $attachment] = $this->courseWithVideo();
        $track = $this->machineTrack($attachment);

        app(LearningCourseService::class)->addUnit($course, ['title' => 'Andere Einheit']);
        $other = $course->refresh()->units()->orderBy('position')->get()->last();

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.subtitles.review', [$course->sqid, $other->sqid, $track->sqid]))
            ->assertNotFound();
    }

    public function test_misslungene_spur_laesst_sich_verwerfen(): void {
        [$course, $unit, $attachment] = $this->courseWithVideo();
        $track = $this->machineTrack($attachment);

        Storage::disk('local')->put($track->path, "WEBVTT\n");

        $this->actingAs($this->author())
            ->delete(route('learning.courses.units.subtitles.destroy', [$course->sqid, $unit->sqid, $track->sqid]))
            ->assertRedirect();

        $this->assertSame(0, $attachment->renditions()->count());
        Storage::disk('local')->assertMissing($track->path);
    }

    public function test_erzeugung_laeuft_in_der_medien_warteschlange(): void {
        Queue::fake();

        [$course, $unit, $attachment] = $this->courseWithVideo();

        // Whisper ist nicht auf jedem Rechner eingerichtet; geprüft wird die
        // Einreihung, nicht die Erkennung.
        $this->partialMock(
            VideoTranscodingService::class,
            fn ($mock) => $mock->shouldReceive('isTranscriptionAvailable')->andReturnTrue()
        );

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.subtitles.transcribe', [$course->sqid, $unit->sqid, $attachment->sqid]), [
                'locale' => 'de',
            ])
            ->assertRedirect();

        Queue::assertPushed(
            TranscribeSubtitleJob::class,
            fn (TranscribeSubtitleJob $job): bool => $job->queue === 'media'
                && $job->attachmentId === (int) $attachment->id
                && $job->locale === 'de'
        );
    }

    public function test_ohne_spracherkennung_wird_nichts_eingereiht(): void {
        Queue::fake();

        [$course, $unit, $attachment] = $this->courseWithVideo();

        $this->partialMock(
            VideoTranscodingService::class,
            fn ($mock) => $mock->shouldReceive('isTranscriptionAvailable')->andReturnFalse()
        );

        $this->actingAs($this->author())
            ->post(route('learning.courses.units.subtitles.transcribe', [$course->sqid, $unit->sqid, $attachment->sqid]), [
                'locale' => 'de',
            ])
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_job_wiederholt_nicht_und_bleibt_in_der_medien_warteschlange(): void {
        $job = new TranscribeSubtitleJob(1, 'de', 1);

        $this->assertSame('media', $job->queue);
        $this->assertSame(1, $job->tries);
    }

    /** @return array{0: LearningCourse, 1: LearningUnit, 2: Attachment} */
    private function courseWithVideo(): array {
        $courses = app(LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        $courses->addUnit($course, ['title' => 'Video']);
        $unit = $course->refresh()->units()->firstOrFail();

        $attachment = Attachment::query()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => $unit->getMorphClass(),
            'attachable_id' => $unit->id,
            'disk' => 'local',
            'path' => 'videos/clip.mp4',
            'original_name' => 'clip.mp4',
            'mime' => 'video/mp4',
            'size' => 1000,
            'media_state' => MediaState::Ready,
        ]);

        return [$course, $unit, $attachment];
    }

    private function machineTrack(Attachment $attachment): MediaRendition {
        return MediaRendition::query()->create([
            'organization_id' => $this->organization->id,
            'attachment_id' => $attachment->id,
            'kind' => MediaRenditionKind::Subtitle->value,
            'variant' => null,
            'disk' => 'local',
            'path' => 'videos/renditions/' . $attachment->id . '/de.vtt',
            'mime' => 'text/vtt',
            'size_bytes' => 42,
            'locale' => 'de',
            'source' => SubtitleSource::Machine->value,
        ]);
    }

    /** @return array<string, mixed> Erste Untertitelspur, wie der Abspieler sie sieht. */
    private function presented(Attachment $attachment): array {
        $state = app(MediaPresenter::class)->forAttachments(
            [$attachment->refresh()],
            static fn (MediaRendition $r): string => '/media/' . $r->id,
        );

        return $state[(int) $attachment->id]['subtitles'][0];
    }

    private function author(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }
}
