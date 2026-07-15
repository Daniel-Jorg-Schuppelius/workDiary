<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeRunnerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeItemStatus, CloudIntakeRouteTarget};
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem, CloudDocumentRoute};
use App\Models\{Customer, Document, DocumentVersion, IntegrationInboxItem, User};
use App\Plugins\Support\Intake\{IntakeChangePage, IntakeItem};
use App\Services\CloudIntake\{CloudIntakeRunner, StaleCheckpointException};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeIntakeAdapter;
use Tests\TestCase;

/**
 * Intake-Pipeline + Routing (Feature 080, MVP-356/357): Regel-Treffer,
 * Quarantäne-Blocklisten, Idempotenz-/Inhalts-Dedup, DMS-/Inbox-Routing,
 * Versionsvorschlag, Checkpoint-Disziplin, Vollabgleich, Lease, Tombstones.
 */
class CloudIntakeRunnerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CloudDocumentConnection $connection;

    private FakeIntakeAdapter $adapter;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $creator = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->connection = CloudDocumentConnection::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $creator->id,
        ]);
        $this->adapter = new FakeIntakeAdapter();

        app()->instance('currentOrganization', $this->organization);
    }

    private function route(array $attributes = []): CloudDocumentRoute {
        return CloudDocumentRoute::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'connection_id' => $this->connection->id,
            'path_pattern' => 'Dokumente/**',
            'target' => CloudIntakeRouteTarget::Document,
        ]);
    }

    private function item(string $id, string $path, string $revision = 'r1', int $size = 100): IntakeItem {
        return new IntakeItem(itemId: $id, path: $path, name: basename($path), revision: $revision, size: $size);
    }

    public function test_imports_matching_document_writes_evidence_checkpoint_and_audit(): void {
        $this->route();
        $this->adapter->pages = [new IntakeChangePage([$this->item('f-1', 'Dokumente/bericht.pdf')], [], 'cp-1', false)];
        $this->adapter->contents = ['f-1' => '%PDF-1.4 fake'];

        $result = app(CloudIntakeRunner::class)->run($this->connection, $this->adapter);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(1, $result['imported']);
        $this->assertSame('cp-1', $this->connection->fresh()->checkpoint);

        $evidence = CloudDocumentItem::query()->sole();
        $this->assertSame(CloudIntakeItemStatus::Imported, $evidence->status);
        $this->assertSame(Document::class, $evidence->imported_type);
        $this->assertNotNull(Document::query()->find($evidence->imported_id));

        $this->assertDatabaseHas('audit_logs', ['event' => 'cloudIntake.imported', 'organization_id' => $this->organization->id]);
    }

    public function test_unmatched_files_are_skipped_without_evidence(): void {
        $this->route(['path_pattern' => 'Eingangsrechnungen/**']);
        $this->adapter->pages = [new IntakeChangePage([$this->item('f-1', 'Sonstiges/anders.pdf')], [], 'cp-1', false)];

        $result = app(CloudIntakeRunner::class)->run($this->connection, $this->adapter);

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, CloudDocumentItem::query()->count());
    }

    public function test_customer_variable_resolves_or_lands_in_inbox(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-1001']);
        $this->route(['path_pattern' => 'Kunden/{customer_number}/**']);

        $this->adapter->pages = [new IntakeChangePage([
            $this->item('f-ok', 'Kunden/K-1001/vertrag.pdf'),
            $this->item('f-nix', 'Kunden/K-9999/vertrag.pdf'),
        ], [], 'cp-1', false)];
        $this->adapter->contents = ['f-ok' => 'inhalt-1', 'f-nix' => 'inhalt-2'];

        $result = app(CloudIntakeRunner::class)->run($this->connection, $this->adapter);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['inbox']);

        $document = Document::query()->sole();
        $this->assertSame(Customer::class, $document->documentable_type);

        $inbox = IntegrationInboxItem::query()->sole();
        $this->assertSame('cloud_intake', $inbox->source);
        $this->assertSame(IntegrationInboxItem::STATUS_OPEN, $inbox->status);
    }

    public function test_new_revision_creates_version_proposal_or_auto_version(): void {
        $route = $this->route(); // auto_version = false
        $this->adapter->pages = [new IntakeChangePage([$this->item('f-1', 'Dokumente/bericht.pdf', 'r1')], [], 'cp-1', false)];
        $this->adapter->contents = ['f-1' => 'inhalt-r1'];
        app(CloudIntakeRunner::class)->run($this->connection, $this->adapter);

        // Neue Revision ⇒ Versionsvorschlag in die Inbox (kein Auto-Update).
        $this->adapter->pages = [new IntakeChangePage([$this->item('f-1', 'Dokumente/bericht.pdf', 'r2')], [], 'cp-2', false)];
        $this->adapter->contents = ['f-1' => 'inhalt-r2'];
        $second = app(CloudIntakeRunner::class)->run($this->connection, $this->adapter);

        $this->assertSame(1, $second['inbox']);
        $this->assertSame(1, Document::query()->count());
        $this->assertSame(1, DocumentVersion::query()->count()); // nur Erst-Version
        $this->assertSame(1, IntegrationInboxItem::query()->count());

        // Freigegebene Route ⇒ automatische neue Version.
        $route->update(['auto_version' => true]);
        $this->adapter->pages = [new IntakeChangePage([$this->item('f-1', 'Dokumente/bericht.pdf', 'r3')], [], 'cp-3', false)];
        $this->adapter->contents = ['f-1' => 'inhalt-r3'];
        $third = app(CloudIntakeRunner::class)->run($this->connection->fresh(), $this->adapter);

        $this->assertSame(1, $third['imported']);
        $this->assertSame(2, DocumentVersion::query()->count());
    }

    public function test_blocked_extension_content_duplicate_and_tombstones(): void {
        $this->route();
        $this->adapter->pages = [new IntakeChangePage([
            $this->item('f-zip', 'Dokumente/archiv.zip'),
            $this->item('f-a', 'Dokumente/a.pdf'),
            $this->item('f-b', 'Dokumente/b.pdf'), // gleicher Inhalt wie a
        ], [], 'cp-1', false)];
        $this->adapter->contents = ['f-a' => 'derselbe-inhalt', 'f-b' => 'derselbe-inhalt'];

        $result = app(CloudIntakeRunner::class)->run($this->connection, $this->adapter);

        $this->assertSame(1, $result['rejected']);   // zip blockiert
        $this->assertSame(1, $result['imported']);   // a importiert
        $this->assertSame(1, $result['duplicates']); // b = Inhalts-Dublette

        // Tombstones: per Item-ID und per Pfad — nur der Nachweis wird markiert.
        $this->adapter->pages = [new IntakeChangePage([], ['f-a', 'path:Dokumente/b.pdf'], 'cp-2', false)];
        $second = app(CloudIntakeRunner::class)->run($this->connection->fresh(), $this->adapter);

        $this->assertSame(2, $second['tombstones']);
        $this->assertSame(2, CloudDocumentItem::query()->where('status', CloudIntakeItemStatus::SourceGone->value)->count());
        $this->assertSame(1, Document::query()->count()); // Dokument bleibt
    }

    public function test_stale_checkpoint_triggers_exactly_one_resync(): void {
        $this->route();
        $this->connection->forceFill(['checkpoint' => 'cp-alt'])->save();

        $this->adapter->pages = [
            new StaleCheckpointException('abgelaufen'),
            new IntakeChangePage([$this->item('f-1', 'Dokumente/neu.pdf')], [], 'cp-neu', false),
        ];
        $this->adapter->contents = ['f-1' => 'inhalt'];

        $result = app(CloudIntakeRunner::class)->run($this->connection->fresh(), $this->adapter);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(1, $result['imported']);
        // Erst mit altem Cursor, nach dem Reset mit null.
        $this->assertSame(['cp-alt', null], $this->adapter->seenCheckpoints);

        // Zweiter Stale in Folge ⇒ failed + Health-Fehler.
        $this->adapter->pages = [new StaleCheckpointException('1'), new StaleCheckpointException('2')];
        $failed = app(CloudIntakeRunner::class)->run($this->connection->fresh(), $this->adapter);
        $this->assertSame('failed', $failed['status']);
        $this->assertNotNull($this->connection->fresh()->last_error);
    }

    public function test_lease_prevents_parallel_runs(): void {
        $this->route();
        $lock = Cache::lock('cloud-intake:run:' . $this->connection->id, 60);
        $this->assertTrue($lock->get());

        try {
            $result = app(CloudIntakeRunner::class)->run($this->connection, $this->adapter);
            $this->assertSame('locked', $result['status']);
        } finally {
            $lock->release();
        }
    }

    public function test_invoice_route_delegates_to_incoming_pipeline(): void {
        $this->route([
            'path_pattern' => 'Eingangsrechnungen/**',
            'target' => CloudIntakeRouteTarget::IncomingInvoice,
        ]);
        $this->adapter->pages = [new IntakeChangePage([$this->item('f-re', 'Eingangsrechnungen/re.pdf')], [], 'cp-1', false)];
        $this->adapter->contents = ['f-re' => 'kein-e-rechnungs-inhalt'];

        $result = app(CloudIntakeRunner::class)->run($this->connection, $this->adapter);

        // Unlesbare Rechnung ⇒ rejected-Nachweis aus der BESTEHENDEN Pipeline.
        $this->assertSame(1, $result['rejected']);
        $this->assertDatabaseHas('cloud_document_items', [
            'external_item_id' => 'f-re',
            'status' => CloudIntakeItemStatus::Rejected->value,
            'status_reason' => 'invoice_unreadable',
        ]);
    }
}
