<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolItemPhotoServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\ProtocolEventType;
use App\Enums\Protocol\ProtocolItemPhotoPhase;
use App\Enums\Protocol\ProtocolItemType;
use App\Enums\Protocol\ProtocolType;
use App\Models\Attachment;
use App\Models\DiaryEntry;
use App\Models\Protocol;
use App\Models\ProtocolEvent;
use App\Models\ProtocolItem;
use App\Models\ProtocolItemPhoto;
use App\Models\User;
use App\Services\Protocol\ProtocolItemPhotoService;
use App\Services\Protocol\ProtocolItemValidator;
use App\Services\Protocol\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ProtocolItemPhotoServiceTest extends TestCase {
    use RefreshDatabase;

    private ProtocolItemPhotoService $photos;
    private ProtocolService $protocols;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->photos = app(ProtocolItemPhotoService::class);
        $this->protocols = app(ProtocolService::class);
    }

    public function test_upload_creates_attachment_photo_and_event(): void {
        [$user, $item] = $this->makeFotoItem();
        $file = UploadedFile::fake()->image('vorher.jpg', 800, 600);

        $photo = $this->photos->upload($item, $file, ProtocolItemPhotoPhase::Before, $user, [
            'caption' => 'Ausgangszustand',
        ]);

        $this->assertSame(ProtocolItemPhotoPhase::Before, $photo->phase);
        $this->assertSame('Ausgangszustand', $photo->caption);
        $this->assertSame(1, $photo->sort_order);
        $this->assertNotNull($photo->attachment_id);

        $attachment = Attachment::query()->find($photo->attachment_id);
        $this->assertNotNull($attachment);
        Storage::disk($attachment->disk)->assertExists($attachment->path);

        $this->assertDatabaseHas('protocol_events', [
            'protocol_id' => $item->protocol_id,
            'event' => ProtocolEventType::ItemPhotoAdded,
        ]);
    }

    public function test_upload_rejects_invalid_mime(): void {
        [$user, $item] = $this->makeFotoItem();
        $file = UploadedFile::fake()->create('schaden.txt', 10, 'text/plain');

        $this->expectException(InvalidArgumentException::class);
        $this->photos->upload($item, $file, ProtocolItemPhotoPhase::Detail, $user);
    }

    public function test_detach_removes_row_and_logs_event(): void {
        [$user, $item] = $this->makeFotoItem();
        $photo = $this->photos->upload(
            $item,
            UploadedFile::fake()->image('nachher.jpg'),
            ProtocolItemPhotoPhase::After,
            $user,
        );

        $photoId = $photo->id;
        $this->photos->detach($photo, $user);

        $this->assertNull(ProtocolItemPhoto::query()->find($photoId));
        $this->assertTrue(ProtocolEvent::query()
            ->where('protocol_id', $item->protocol_id)
            ->where('event', ProtocolEventType::ItemPhotoRemoved)
            ->exists());
    }

    public function test_update_caption_logs_event(): void {
        [$user, $item] = $this->makeFotoItem();
        $photo = $this->photos->upload(
            $item,
            UploadedFile::fake()->image('foto.jpg'),
            ProtocolItemPhotoPhase::Detail,
            $user,
            ['caption' => 'Alt'],
        );

        $updated = $this->photos->updateCaption($photo, 'Neu', $user);

        $this->assertSame('Neu', $updated->caption);
        $this->assertTrue(ProtocolEvent::query()
            ->where('event', ProtocolEventType::ItemPhotoUpdatedCaption)
            ->exists());
    }

    public function test_missing_photo_phases_returns_required_phases(): void {
        [$user, $item] = $this->makeFotoItem(['min_per_phase' => ['before' => 1, 'after' => 1]]);

        /** @var ProtocolItemValidator $validator */
        $validator = app(ProtocolItemValidator::class);
        $errorsBefore = $validator->missingPhotoPhases($item);
        $this->assertCount(2, $errorsBefore);

        $this->photos->upload($item, UploadedFile::fake()->image('a.jpg'), ProtocolItemPhotoPhase::Before, $user);
        $item->refresh();
        $errorsAfterOne = $validator->missingPhotoPhases($item);
        $this->assertCount(1, $errorsAfterOne);

        $this->photos->upload($item, UploadedFile::fake()->image('b.jpg'), ProtocolItemPhotoPhase::After, $user);
        $item->refresh();
        $this->assertSame([], $validator->missingPhotoPhases($item));
    }

    public function test_reorder_persists_sort_order(): void {
        [$user, $item] = $this->makeFotoItem();
        $a = $this->photos->upload($item, UploadedFile::fake()->image('a.jpg'), ProtocolItemPhotoPhase::Detail, $user);
        $b = $this->photos->upload($item, UploadedFile::fake()->image('b.jpg'), ProtocolItemPhotoPhase::Detail, $user);
        $c = $this->photos->upload($item, UploadedFile::fake()->image('c.jpg'), ProtocolItemPhotoPhase::Detail, $user);

        $this->photos->reorder($item, ProtocolItemPhotoPhase::Detail, [$c->id, $a->id, $b->id], $user);

        $this->assertSame(1, $c->refresh()->sort_order);
        $this->assertSame(2, $a->refresh()->sort_order);
        $this->assertSame(3, $b->refresh()->sort_order);
    }

    /**
     * @param  array<string, mixed>  $valueJson
     * @return array{0: User, 1: ProtocolItem}
     */
    private function makeFotoItem(array $valueJson = []): array {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create();
        $protocol = $this->protocols->create($entry, $user, [
            'type' => ProtocolType::Service->value,
            'title' => 'Mit Fotos',
        ]);
        $item = $this->protocols->addItem($protocol, $user, [
            'label' => 'Fotopunkt',
            'item_type' => ProtocolItemType::Photo->value,
        ]);
        if ($valueJson !== []) {
            $item->forceFill(['value_json' => $valueJson])->save();
        }
        return [$user, $item];
    }
}
