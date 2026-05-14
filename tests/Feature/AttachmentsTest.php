<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\DiaryEntry;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Storage::fake('local');
    }

    public function test_user_can_upload_attachment_to_diary_entry(): void
    {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();
        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $this->actingAs($owner)
            ->post(route('attachments.store', ['type' => 'diary', 'id' => $entry->id]), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $entry->id,
            'user_id' => $owner->id,
            'original_name' => 'report.pdf',
        ]);
        $stored = Attachment::firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists($stored->path));
    }

    public function test_executable_extensions_are_rejected(): void
    {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();
        $file = UploadedFile::fake()->create('evil.php', 10, 'application/x-php');

        $this->actingAs($owner)
            ->post(route('attachments.store', ['type' => 'diary', 'id' => $entry->id]), ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::count());
    }

    public function test_oversize_file_is_rejected(): void
    {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();
        // 26 MB > 25 MB limit
        $file = UploadedFile::fake()->create('big.bin', 26 * 1024, 'application/octet-stream');

        $this->actingAs($owner)
            ->post(route('attachments.store', ['type' => 'diary', 'id' => $entry->id]), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_signed_download_succeeds_and_unsigned_fails(): void
    {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();
        $this->actingAs($owner)
            ->post(
                route('attachments.store', ['type' => 'diary', 'id' => $entry->id]),
                ['file' => UploadedFile::fake()->create('a.txt', 1, 'text/plain')]
            );

        $attachment = Attachment::firstOrFail();

        $this->actingAs($owner)
            ->get(route('attachments.download', $attachment))
            ->assertForbidden();

        $url = URL::temporarySignedRoute('attachments.download', now()->addMinutes(5), ['attachment' => $attachment->id]);
        $this->actingAs($owner)->get($url)->assertOk();
    }

    public function test_only_uploader_or_admin_can_delete(): void
    {
        $uploader = User::factory()->user()->create();
        $other = User::factory()->user()->create();
        $admin = User::factory()->admin()->create();
        $entry = DiaryEntry::factory()->for($uploader)->create();
        $attachment = Attachment::factory()->for($uploader, 'uploader')->create([
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $entry->id,
        ]);

        $this->actingAs($other)
            ->delete(route('attachments.destroy', $attachment))
            ->assertForbidden();

        $this->actingAs($uploader)
            ->delete(route('attachments.destroy', $attachment))
            ->assertRedirect();
        $this->assertNull(Attachment::find($attachment->id));

        // Admin
        $attachment2 = Attachment::factory()->for($uploader, 'uploader')->create([
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $entry->id,
        ]);
        $this->actingAs($admin)
            ->delete(route('attachments.destroy', $attachment2))
            ->assertRedirect();
        $this->assertNull(Attachment::find($attachment2->id));
    }

    public function test_attachments_panel_shown_on_diary_show(): void
    {
        $owner = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->get(route('diary.show', $entry))
            ->assertOk()
            ->assertSee(__('Anhänge'))
            ->assertSee(__('Hochladen'));
    }
}
