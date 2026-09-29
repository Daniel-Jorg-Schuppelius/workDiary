<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VideoQualityPreferenceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Enums\Media\{MediaRenditionKind, MediaState};
use App\Http\Controllers\Learning\MyLearningController;
use App\Models\Attachments\Attachment;
use App\Models\Media\MediaRendition;
use App\Services\Learning\LearningCourseService;
use App\Services\Media\MediaPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1022: Fassung im Player wählen, als Nutzerpräferenz gemerkt. */
final class VideoQualityPreferenceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    /** @param  list<string>  $variants */
    private function videoWith(array $variants): Attachment {
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
        foreach ($variants as $variant) {
            MediaRendition::query()->create([
                'organization_id' => $this->organization->id,
                'attachment_id' => $attachment->id,
                'kind' => MediaRenditionKind::Video->value,
                'variant' => $variant,
                'disk' => 'local',
                'path' => 'videos/renditions/' . $attachment->id . '/' . $variant . '.mp4',
                'mime' => 'video/mp4',
                'size_bytes' => 100,
            ]);
        }

        return $attachment;
    }

    /** @return array<string, mixed> */
    private function presented(Attachment $attachment, ?string $preferred): array {
        return app(MediaPresenter::class)->forAttachments(
            [$attachment],
            static fn (MediaRendition $rendition): string => 'https://example.test/' . $rendition->variant,
            $preferred,
        )[(int) $attachment->id];
    }

    public function test_the_smallest_rendition_is_the_default_and_a_choice_never_overshoots(): void {
        $attachment = $this->videoWith(['720p', '480p', '1080p']);

        $state = $this->presented($attachment, null);
        $this->assertSame('480p', $state['variant']);
        $this->assertSame(['480p', '720p', '1080p'], array_column($state['videos'], 'variant'));
        $this->assertSame('720p', $this->presented($attachment, '720p')['variant']);
        $this->assertSame('480p', $this->presented($attachment, 'unbekannt')['variant']);

        $partial = $this->videoWith(['720p']);
        $this->assertSame('720p', $this->presented($partial, '480p')['variant'], 'Liegt jede Fassung über der Wahl, bleibt die kleinste.');
        $this->assertSame('720p', $this->presented($this->videoWith(['480p', '720p']), '1080p')['variant']);
    }

    public function test_the_player_offers_the_renditions_and_the_choice_is_remembered(): void {
        $attachment = $this->videoWith(['480p', '720p']);
        $html = view('learning._blocks', [
            'blocks' => [['type' => 'video', 'attachment_id' => $attachment->id, 'remember_position' => true]],
            'mediaState' => [$attachment->id => $this->presented($attachment, '720p')],
        ])->render();

        $this->assertStringContainsString('data-video-quality=', $html);
        $this->assertStringContainsString('value="720p" data-src="https://example.test/720p" selected', $html);
        $this->assertStringContainsString('src="https://example.test/720p"', $html);
        // Der Positionsmerker hängt an der kleinsten Fassung und übersteht den Wechsel.
        $this->assertStringContainsString('data-remember-position="lrn-video-' . md5('https://example.test/480p') . '"', $html);

        $single = $this->videoWith(['480p']);
        $this->assertStringNotContainsString('data-video-quality=', view('learning._blocks', [
            'blocks' => [['type' => 'video', 'attachment_id' => $single->id]],
            'mediaState' => [$single->id => $this->presented($single, null)],
        ])->render());

        $learner = $this->orgUser();
        $this->actingAs($learner)->postJson(route('learning.my.video-quality'), ['quality' => '720p'])
            ->assertOk()->assertJson(['quality' => '720p']);
        $this->assertSame('720p', $learner->fresh()?->getPreference(MyLearningController::VIDEO_QUALITY_PREFERENCE));
        $this->actingAs($learner)->postJson(route('learning.my.video-quality'), ['quality' => '4k'])
            ->assertUnprocessable()->assertJsonValidationErrors('quality');
    }
}
