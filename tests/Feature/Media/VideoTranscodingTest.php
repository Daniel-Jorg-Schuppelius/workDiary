<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VideoTranscodingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Media;

use App\Enums\Media\MediaState;
use App\Jobs\TranscodeVideoJob;
use App\Models\Attachment;
use App\Services\Media\VideoTranscodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Video-Transcoding (Feature 150).
 *
 * Geprüft wird die **Entscheidungslogik**, nicht ffmpeg selbst: welche
 * Auflösungen entstehen, wie der Platzbedarf geschätzt wird, wie ein
 * Fehlschlag sichtbar bleibt. Der Aufruf des Kodierers liegt im
 * common-toolkit und wird dort getestet.
 */
class VideoTranscodingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function service(): VideoTranscodingService {
        return app(VideoTranscodingService::class);
    }

    private function videoAttachment(int $bytes = 1_000_000): Attachment {
        Storage::fake('local');

        return Attachment::query()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => \App\Models\Organization::class,
            'attachable_id' => $this->organization->id,
            'disk' => 'local',
            'path' => 'videos/clip.mp4',
            'original_name' => 'clip.mp4',
            'mime' => 'video/mp4',
            'size' => $bytes,
        ]);
    }

    public function test_es_wird_nie_hochgerechnet(): void {
        // Aus einem 480p-Video ein 1080p zu machen kostet Platz und
        // Rechenzeit und sieht schlechter aus als das Original.
        $this->assertSame(['480p'], $this->service()->variantsFor(480));
        $this->assertSame(['480p', '720p'], $this->service()->variantsFor(720));
        $this->assertSame(['480p', '720p', '1080p'], $this->service()->variantsFor(1080));
    }

    public function test_kleinere_quelle_bekommt_trotzdem_eine_fassung(): void {
        // Format normalisieren muss auch bei 360p sein — HEVC/MOV spielt
        // sonst nicht überall.
        $this->assertSame(['480p'], $this->service()->variantsFor(360));
    }

    public function test_platzbedarf_wird_vor_dem_rechnen_geschaetzt(): void {
        // Die genaue Größe kennt man erst hinterher — dann ist die CPU-Zeit
        // schon verbraucht. Deshalb eine Schätzung vorab.
        $bytes = $this->service()->estimatedBytes(1_000_000, ['480p', '720p']);

        $this->assertGreaterThan(600_000, $bytes);
        $this->assertLessThan(1_000_000, $bytes);
    }

    public function test_fehlende_quelldatei_wird_als_fehler_vermerkt(): void {
        $attachment = $this->videoAttachment();

        $result = $this->service()->process($attachment);

        // Kein Wurf: der Zustand muss lesbar bleiben, damit die Oberfläche
        // sagen kann, WAS schiefging.
        $this->assertSame(MediaState::Failed, $result->media_state);
        $this->assertNotEmpty($result->media_error);
    }

    public function test_job_haengt_in_der_eigenen_warteschlange(): void {
        // ffmpeg läuft auf demselben Server: läge der Job in der
        // Standard-Warteschlange, blockierte er Mails und Exporte.
        $job = new TranscodeVideoJob(1);

        $this->assertSame('media', $job->queue);
    }

    public function test_job_wiederholt_nicht(): void {
        // Ein gescheitertes Transcoding scheitert beim zweiten Mal genauso —
        // Wiederholung kostet nur CPU-Zeit.
        $this->assertSame(1, (new TranscodeVideoJob(1))->tries);
    }

    public function test_untertitel_muessen_webvtt_sein(): void {
        $attachment = $this->videoAttachment();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service()->attachSubtitle($attachment, "1\n00:00:01 --> 00:00:02\nHallo\n", 'de');
    }

    public function test_untertitel_werden_je_sprache_abgelegt(): void {
        $attachment = $this->videoAttachment();

        $de = $this->service()->attachSubtitle($attachment, "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHallo\n", 'de');
        $en = $this->service()->attachSubtitle($attachment, "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHello\n", 'en');

        $this->assertNotSame($de->id, $en->id);
        $this->assertSame(2, $attachment->renditions()->count());
    }

    public function test_untertitel_upload_ueber_die_oberflaeche(): void {
        Storage::fake('local');

        $courses = app(\App\Services\Learning\LearningCourseService::class);
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

        $author = \App\Models\User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($author)
            ->post(route('learning.courses.units.subtitles.store', [$course->sqid, $unit->sqid, $attachment->sqid]), [
                'locale' => 'de',
                'vtt' => \Illuminate\Http\UploadedFile::fake()->createWithContent(
                    'de.vtt',
                    "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nFluchtwege freihalten\n"
                ),
            ])
            ->assertRedirect();

        $this->assertSame(1, $attachment->renditions()->count());
    }

    public function test_untertitel_fremder_einheit_werden_abgelehnt(): void {
        Storage::fake('local');

        $courses = app(\App\Services\Learning\LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        $courses->addUnit($course, ['title' => 'Einheit A']);
        $courses->addUnit($course, ['title' => 'Einheit B']);
        $units = $course->refresh()->units()->orderBy('position')->get();

        // Anhang haengt an Einheit B …
        $attachment = Attachment::query()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => $units[1]->getMorphClass(),
            'attachable_id' => $units[1]->id,
            'disk' => 'local',
            'path' => 'videos/clip.mp4',
            'original_name' => 'clip.mp4',
            'mime' => 'video/mp4',
            'size' => 1000,
            'media_state' => MediaState::Ready,
        ]);

        $author = \App\Models\User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        // … der Aufruf nennt Einheit A.
        $this->actingAs($author)
            ->post(route('learning.courses.units.subtitles.store', [$course->sqid, $units[0]->sqid, $attachment->sqid]), [
                'locale' => 'de',
                'vtt' => \Illuminate\Http\UploadedFile::fake()->createWithContent('de.vtt', "WEBVTT\n"),
            ])
            ->assertNotFound();
    }

    // ── Auslieferung mit Sprungmöglichkeit (Feature 150) ────────────────

    public function test_video_laesst_sich_springen(): void {
        Storage::fake('local');

        $courses = app(\App\Services\Learning\LearningCourseService::class);
        $course = $courses->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        $courses->addUnit($course, ['title' => 'Video']);
        $unit = $course->refresh()->units()->firstOrFail();

        Storage::disk('local')->put('videos/clip.mp4', str_repeat('x', 5000));

        $attachment = Attachment::query()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => $unit->getMorphClass(),
            'attachable_id' => $unit->id,
            'disk' => 'local',
            'path' => 'videos/clip.mp4',
            'original_name' => 'clip.mp4',
            'mime' => 'video/mp4',
            'size' => 5000,
            'media_state' => MediaState::Ready,
        ]);

        $courses->release($course->refresh(), null);
        $learner = \App\Models\User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $enrollment = app(\App\Services\Learning\LearningEnrollmentService::class)->enroll($course->refresh(), $learner);

        // Ohne Range-Unterstützung müsste der Browser von vorn laden, um in
        // die Mitte eines zwanzigminütigen Videos zu kommen.
        $response = $this->actingAs($learner)
            ->withHeaders(['Range' => 'bytes=1000-1999'])
            ->get(route('learning.my.units.media', [$enrollment->sqid, $unit->sqid, $attachment->sqid]));

        $response->assertStatus(206);
        $this->assertSame('bytes', $response->headers->get('Accept-Ranges'));
        $this->assertSame('bytes 1000-1999/5000', $response->headers->get('Content-Range'));
    }
}
