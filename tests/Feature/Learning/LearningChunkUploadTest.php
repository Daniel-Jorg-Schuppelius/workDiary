<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningChunkUploadTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Enums\Learning\LearningBlockKind;
use App\Models\Learning\LearningUnit;
use App\Models\Platform\User;
use App\Services\Attachments\ChunkedUploadStore;
use App\Services\Learning\LearningCourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Wiederaufnehmbarer Upload großer Medien im Einheiten-Editor (MVP-1265). */
final class LearningChunkUploadTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    private function unit(): LearningUnit {
        $course = app(LearningCourseService::class)->createCourse($this->organization, null, ['title' => 'Brandschutz']);
        app(LearningCourseService::class)->addUnit($course, ['title' => 'Einführung']);

        return $course->refresh()->units()->firstOrFail();
    }

    private function author(): User {
        return User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);
    }

    public function test_chunks_resume_and_become_a_file_block(): void {
        $unit = $this->unit();
        $author = $this->author();
        $content = "%PDF-1.4\n" . str_repeat('A', 5 * 1024 * 1024) . "\n%%EOF\n";
        $start = route('learning.courses.units.uploads.start', [$unit->course->sqid, $unit->sqid]);

        $state = $this->actingAs($author)->postJson($start, ['name' => 'Handbuch.pdf', 'size' => strlen($content), 'type' => 'application/pdf'])
            ->assertOk()->json();
        $this->assertGreaterThan(1, $state['chunks']);

        $send = fn (int $index) => $this->actingAs($author)->post($start . '/' . $state['token'] . '/' . $index, [
            'chunk' => UploadedFile::fake()->createWithContent('part', substr($content, $index * $state['chunk_size'], $state['chunk_size'])),
        ], ['Accept' => 'application/json']);

        $send(0)->assertOk();
        // Abbruch nach dem ersten Teil: der Stand nennt, was schon da ist.
        $this->actingAs($author)->getJson($start . '/' . $state['token'])->assertOk()->assertJsonPath('received', [0]);
        for ($index = 1; $index < $state['chunks']; $index++) {
            $send($index)->assertOk();
        }

        $this->actingAs($author)
            ->post(route('learning.courses.units.blocks.store', [$unit->course->sqid, $unit->sqid]), [
                'type' => LearningBlockKind::File->value,
                'caption' => 'Handbuch',
                'media_upload' => $state['token'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $attachment = $unit->refresh()->attachments()->firstOrFail();
        $this->assertSame(strlen($content), (int) $attachment->size);
        $this->assertSame($attachment->id, $unit->blocks()[0]['attachment_id'] ?? null);
        $this->assertFalse(Storage::disk('local')->exists(ChunkedUploadStore::DIRECTORY . '/' . $state['token']));
    }

    public function test_foreign_tokens_wrong_sizes_and_incomplete_uploads_are_refused(): void {
        $unit = $this->unit();
        $author = $this->author();
        $start = route('learning.courses.units.uploads.start', [$unit->course->sqid, $unit->sqid]);
        $state = $this->actingAs($author)->postJson($start, ['name' => 'a.pdf', 'size' => 1000])->assertOk()->json();

        $this->actingAs($this->author())->getJson($start . '/' . $state['token'])->assertUnprocessable();
        $this->actingAs($author)->post($start . '/' . $state['token'] . '/0', [
            'chunk' => UploadedFile::fake()->createWithContent('part', str_repeat('A', 10)),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->actingAs($author)
            ->post(route('learning.courses.units.blocks.store', [$unit->course->sqid, $unit->sqid]), [
                'type' => LearningBlockKind::File->value,
                'media_upload' => $state['token'],
            ])
            ->assertSessionHasErrors('media');
        $this->actingAs($author)->postJson($start, ['name' => 'riesig.mp4', 'size' => 10 * 1024 * 1024 * 1024])->assertUnprocessable();
    }

    public function test_stale_uploads_are_pruned(): void {
        $store = app(ChunkedUploadStore::class);
        $fresh = $store->start(1, 'scope', 'a.pdf', 100, null);
        $old = $store->start(1, 'scope', 'b.pdf', 100, null);
        touch(Storage::disk('local')->path(ChunkedUploadStore::DIRECTORY . '/' . $old['token'] . '/meta.json'), Carbon::now()->subDays(2)->getTimestamp());

        $this->artisan('uploads:prune-chunks')->assertSuccessful();

        $this->assertTrue(Storage::disk('local')->exists(ChunkedUploadStore::DIRECTORY . '/' . $fresh['token'] . '/meta.json'));
        $this->assertFalse(Storage::disk('local')->exists(ChunkedUploadStore::DIRECTORY . '/' . $old['token']));
    }
}
