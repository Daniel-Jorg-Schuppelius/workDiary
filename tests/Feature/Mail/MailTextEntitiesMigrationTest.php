<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailTextEntitiesMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Mail;

use App\Models\Communication\CommunicationNote;
use App\Models\Invoicing\InvoiceInquiry;
use App\Models\ServiceTicket\ServiceTicket;
use App\Support\MorphMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Befund 2026-10-19: Mail-Texte aus HTML trugen `&nbsp;`/`&amp;` wörtlich.
 * Die Migration dekodiert nur Texte aus dem Mail-Eingang; von Hand erfasste
 * Notizen und fremde Eingänge bleiben unverändert.
 */
class MailTextEntitiesMigrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function migration(): Migration {
        return require database_path('migrations/2027_03_27_090000_decode_entities_in_mail_texts.php');
    }

    /** @param array<string, mixed> $attributes */
    private function inboxItem(string $plugin, array $attributes = []): int {
        return (int) DB::table('integration_inbox_items')->insertGetId($attributes + [
            'organization_id' => $this->organization->id,
            'plugin_id' => $plugin,
            'target_type' => 'customer',
            'external_type' => 'message',
            'external_id' => '<' . $plugin . '@mail.test>',
            'dedupe_key' => $plugin . ':message:1',
            'case_type' => 'unmatched',
            'status' => 'open',
            'remote_snapshot' => json_encode(['subject' => 'Mahnung', 'body' => 'Hallo&nbsp;Welt &amp; Co']),
        ]);
    }

    private function snapshotBody(int $id): string {
        $raw = (string) DB::table('integration_inbox_items')->where('id', $id)->value('remote_snapshot');

        return (string) (json_decode($raw, true)['body'] ?? '');
    }

    public function test_only_texts_from_the_mail_intake_lose_their_entities(): void {
        $orgId = $this->organization->id;
        $mailItem = $this->inboxItem('email');
        $calendarItem = $this->inboxItem('caldav');

        $mailNote = CommunicationNote::factory()->create(['organization_id' => $orgId, 'body' => 'Hallo Simone,&nbsp;sorry &amp; Gru&szlig;']);
        $manualNote = CommunicationNote::factory()->create(['organization_id' => $orgId, 'body' => 'Tom &amp; Jerry']);
        $inquiryNote = CommunicationNote::factory()->create(['organization_id' => $orgId, 'body' => 'Rechnung&nbsp;4711 &euro;']);
        $referenceId = (int) DB::table('external_references')->insertGetId([
            'organization_id' => $orgId,
            'plugin_id' => 'email',
            'external_type' => 'message',
            'referenceable_type' => MorphMap::alias(CommunicationNote::class),
            'referenceable_id' => $mailNote->id,
            'external_id' => '<email@mail.test>',
            'payload' => json_encode(['body' => 'Hallo&nbsp;Welt &amp; Co']),
        ]);
        InvoiceInquiry::factory()->create(['organization_id' => $orgId, 'communication_note_id' => $inquiryNote->id]);

        $mailTicket = ServiceTicket::factory()->create(['organization_id' => $orgId, 'source' => 'email', 'description' => 'Drucker&nbsp;kaputt']);
        $manualTicket = ServiceTicket::factory()->create(['organization_id' => $orgId, 'source' => 'manual', 'description' => 'A &amp; B']);
        $mailMessage = (int) DB::table('service_ticket_messages')->insertGetId([
            'organization_id' => $orgId, 'service_ticket_id' => $mailTicket->id, 'kind' => 'public_reply', 'channel' => 'mail', 'body' => 'Bitte&nbsp;pr&uuml;fen',
        ]);
        $manualMessage = (int) DB::table('service_ticket_messages')->insertGetId([
            'organization_id' => $orgId, 'service_ticket_id' => $mailTicket->id, 'kind' => 'internal_note', 'channel' => 'manual', 'body' => 'A &amp; B',
        ]);

        $this->migration()->up();

        $this->assertSame('Hallo Welt & Co', $this->snapshotBody($mailItem));
        $this->assertSame('Hallo&nbsp;Welt &amp; Co', $this->snapshotBody($calendarItem));
        $payload = json_decode((string) DB::table('external_references')->where('id', $referenceId)->value('payload'), true);
        $this->assertSame('Hallo Welt & Co', $payload['body']);

        $this->assertSame('Hallo Simone, sorry & Gruß', $mailNote->fresh()?->body);
        $this->assertSame('Tom &amp; Jerry', $manualNote->fresh()?->body);
        $this->assertSame('Rechnung 4711 €', $inquiryNote->fresh()?->body);

        $this->assertSame('Drucker kaputt', $mailTicket->fresh()?->description);
        $this->assertSame('A &amp; B', $manualTicket->fresh()?->description);
        $this->assertSame('Bitte prüfen', DB::table('service_ticket_messages')->where('id', $mailMessage)->value('body'));
        $this->assertSame('A &amp; B', DB::table('service_ticket_messages')->where('id', $manualMessage)->value('body'));
    }

    public function test_migration_runs_twice_without_changing_decoded_text(): void {
        $note = CommunicationNote::factory()->create(['organization_id' => $this->organization->id, 'body' => 'Code: &amp;lt;b&amp;gt;']);
        DB::table('external_references')->insert([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'email',
            'external_type' => 'message',
            'referenceable_type' => MorphMap::alias(CommunicationNote::class),
            'referenceable_id' => $note->id,
            'external_id' => '<twice@mail.test>',
        ]);

        $this->migration()->up();
        $this->assertSame('Code: &lt;b&gt;', $note->fresh()?->body);
    }
}
