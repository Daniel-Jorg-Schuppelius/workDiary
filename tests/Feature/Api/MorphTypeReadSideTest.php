<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MorphTypeReadSideTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Api;

use App\Enums\Protocol\ProtocolType;
use App\Http\Resources\{AttachmentResource, CommentResource, ProtocolResource};
use App\Models\Attachments\Attachment;
use App\Models\Diary\DiaryEntry;
use App\Models\Protocol\Protocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Seit MVP-860 steht in den Typspalten der Alias. Wer ihn als Klassenname las,
 * bekam `null`-IDs und Tabellennamen (Konsolidierungs-Audit 2026-10, k3-2).
 */
final class MorphTypeReadSideTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_resources_resolve_the_alias_to_sqid_and_short_name(): void {
        $this->setUpOrganization();
        $user = $this->orgAdmin();
        $order = DiaryEntry::factory()->for($user)->create(['organization_id' => $this->organization->id]);
        $this->assertSame('diary_entries', $order->getMorphClass());

        $comment = $order->comments()->create(['user_id' => $user->id, 'body' => 'x']);
        $this->assertSame($order->sqid, (new CommentResource($comment))->toArray(request())['commentable_id']);

        $attachment = $this->attachment($order, ['organization_id' => $this->organization->id]);
        $data = (new AttachmentResource($attachment))->toArray(request());
        $this->assertSame($order->sqid, $data['attachable_id']);
        $this->assertSame('DiaryEntry', $data['attachable_type']);

        $protocol = Protocol::create([
            'organization_id' => $this->organization->id,
            'subject_type' => $order->getMorphClass(),
            'subject_id' => $order->id,
            'type' => ProtocolType::Service->value,
            'title' => 'Wartung',
            'occurred_at' => now(),
            'created_by_user_id' => $user->id,
        ]);
        $subject = (new ProtocolResource($protocol->refresh()))->toArray(request())['subject'];
        $this->assertSame(['type' => 'DiaryEntry', 'id' => $order->sqid], $subject);
    }

    public function test_attachment_inherits_the_organization_of_its_carrier_without_context(): void {
        $this->setUpOrganization();
        $order = DiaryEntry::factory()->for($this->orgAdmin())->create(['organization_id' => $this->organization->id]);
        app()->forgetInstance('currentOrganization');

        $attachment = $this->attachment($order);

        $this->assertSame($this->organization->id, (int) $attachment->organization_id);
    }

    /** @param array<string, mixed> $attributes */
    private function attachment(DiaryEntry $order, array $attributes = []): Attachment {
        return Attachment::query()->create($attributes + [
            'attachable_type' => $order->getMorphClass(),
            'attachable_id' => $order->id,
            'disk' => 'local',
            'path' => 'attachments/plan.pdf',
            'original_name' => 'plan.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'uploaded_by_user_id' => null,
        ]);
    }
}
