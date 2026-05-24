<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Protocol;

use App\Enums\Protocol\{ProtocolEventType, ProtocolItemResult, ProtocolSignatureMethod, ProtocolSignatureRole, ProtocolStatus, ProtocolType};
use App\Exceptions\InvalidProtocolTransitionException;
use App\Models\{DiaryEntry, User};
use App\Services\Protocol\ProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProtocolServiceTest extends TestCase {
    use RefreshDatabase;

    private ProtocolService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = app(ProtocolService::class);
    }

    public function test_create_starts_in_draft_and_logs_event(): void {
        [$creator, $entry] = $this->makeContext();

        $protocol = $this->service->create($entry, $creator, [
            'type' => ProtocolType::Service->value,
            'title' => 'Servicebesuch',
        ]);

        $this->assertSame(ProtocolStatus::Draft, $protocol->status);
        $this->assertSame(ProtocolType::Service, $protocol->type);
        $this->assertSame(1, $protocol->revision);
        $this->assertSame($creator->id, $protocol->created_by_user_id);

        $this->assertDatabaseHas('protocol_events', [
            'protocol_id' => $protocol->id,
            'event' => ProtocolEventType::Created,
        ]);
    }

    public function test_lifecycle_draft_review_signed_archived(): void {
        [$creator, $entry] = $this->makeContext();
        $protocol = $this->service->create($entry, $creator, [
            'type' => ProtocolType::Acceptance->value,
            'title' => 'Abnahme',
        ]);

        $this->service->requestReview($protocol, $creator);
        $this->assertSame(ProtocolStatus::InReview, $protocol->refresh()->status);

        $this->service->sign($protocol, $creator);
        $protocol->refresh();
        $this->assertSame(ProtocolStatus::Signed, $protocol->status);
        $this->assertNotNull($protocol->signed_at);

        $this->service->archive($protocol, $creator);
        $protocol->refresh();
        $this->assertSame(ProtocolStatus::Archived, $protocol->status);
        $this->assertNotNull($protocol->archived_at);
    }

    public function test_sign_with_signature_creates_signature_row(): void {
        [$creator, $entry] = $this->makeContext();
        $protocol = $this->service->create($entry, $creator, [
            'type' => ProtocolType::Acceptance->value,
            'title' => 'Abnahme mit Unterschrift',
        ]);

        $this->service->sign($protocol, $creator, [
            'role' => ProtocolSignatureRole::Contractor->value,
            'signer_name' => 'Daniel Schuppelius',
            'method' => ProtocolSignatureMethod::Onscreen->value,
        ]);

        $this->assertDatabaseHas('protocol_signatures', [
            'protocol_id' => $protocol->id,
            'role' => ProtocolSignatureRole::Contractor->value,
            'signer_name' => 'Daniel Schuppelius',
            'method' => ProtocolSignatureMethod::Onscreen->value,
        ]);

        $row = $protocol->signatures()->first();
        $this->assertNotNull($row);
        $this->assertSame(64, strlen((string) $row->hash));
    }

    public function test_signed_protocol_cannot_be_edited(): void {
        [$creator, $entry] = $this->makeContext();
        $protocol = $this->service->create($entry, $creator, [
            'type' => ProtocolType::Service->value,
            'title' => 'X',
        ]);
        $this->service->sign($protocol, $creator);

        $this->expectException(InvalidProtocolTransitionException::class);
        $this->service->update($protocol->refresh(), $creator, ['title' => 'Y']);
    }

    public function test_supersede_creates_new_revision_and_marks_old(): void {
        [$creator, $entry] = $this->makeContext();
        $protocol = $this->service->create($entry, $creator, [
            'type' => ProtocolType::Acceptance->value,
            'title' => 'Original',
        ]);
        $this->service->sign($protocol, $creator);

        $copy = $this->service->supersede($protocol->refresh(), $creator, 'Tippfehler korrigiert');

        $this->assertSame(ProtocolStatus::Superseded, $protocol->refresh()->status);
        $this->assertSame(ProtocolStatus::Draft, $copy->status);
        $this->assertSame(2, $copy->revision);
        $this->assertSame($protocol->id, $copy->supersedes_id);
        $this->assertSame($protocol->title, $copy->title);

        $this->assertDatabaseHas('protocol_events', [
            'protocol_id' => $protocol->id,
            'event' => ProtocolEventType::SupersededBy,
        ]);
    }

    public function test_supersede_requires_reason(): void {
        [$creator, $entry] = $this->makeContext();
        $protocol = $this->service->create($entry, $creator, [
            'type' => ProtocolType::Acceptance->value,
            'title' => 'X',
        ]);
        $this->service->sign($protocol, $creator);

        $this->expectException(InvalidArgumentException::class);
        $this->service->supersede($protocol->refresh(), $creator, '');
    }

    public function test_invalid_transition_from_archived_throws(): void {
        [$creator, $entry] = $this->makeContext();
        $protocol = $this->service->create($entry, $creator, [
            'type' => ProtocolType::Service->value,
            'title' => 'X',
        ]);
        $this->service->sign($protocol, $creator);
        $this->service->archive($protocol->refresh(), $creator);

        $this->expectException(InvalidProtocolTransitionException::class);
        $this->service->requestReview($protocol->refresh(), $creator);
    }

    public function test_add_and_fill_item_only_in_draft(): void {
        [$creator, $entry] = $this->makeContext();
        $protocol = $this->service->create($entry, $creator, [
            'type' => ProtocolType::Service->value,
            'title' => 'X',
        ]);

        $item = $this->service->addItem($protocol, $creator, [
            'label' => 'Sichtprüfung',
            'item_type' => \App\Enums\Protocol\ProtocolItemType::Boolean->value,
            'required' => true,
        ]);
        $this->assertSame('Sichtprüfung', $item->label);
        $this->assertTrue($item->required);

        $filled = $this->service->fillItem($item, $creator, [
            'value_json' => ['value' => true],
            'note' => 'Alles in Ordnung',
        ]);

        $this->assertSame(ProtocolItemResult::Ok, $filled->result);
        $this->assertSame('Alles in Ordnung', $filled->note);
        $this->assertSame($creator->id, $filled->measured_by_user_id);

        $this->service->sign($protocol->refresh(), $creator);

        $this->expectException(InvalidProtocolTransitionException::class);
        $this->service->addItem($protocol->refresh(), $creator, ['label' => 'too late']);
    }

    /**
     * @return array{0: User, 1: DiaryEntry}
     */
    private function makeContext(): array {
        $creator = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($creator)->create();

        return [$creator, $entry];
    }
}
