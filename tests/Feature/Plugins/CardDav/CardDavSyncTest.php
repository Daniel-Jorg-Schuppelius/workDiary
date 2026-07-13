<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\CardDav;

use App\Models\{CardDavConnection, Customer, ExternalReference, IntegrationInboxItem, Organization};
use App\Plugins\CardDav\CardDavPlugin;
use App\Plugins\CardDav\Contracts\{CardDavGateway, CardDavGatewayFactory};
use App\Plugins\CardDav\Services\{CardDavCardChange, CardDavContactImporter, CardDavSyncPage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeCardDavGateway;
use Tests\TestCase;

/**
 * Bauturbo A9 (MVP-329): Lese-Sync als Matching-Quelle. Inbox-First
 * (Vorschläge statt Auto-Merge, kein Direkt-Write auf Kundenfelder),
 * sync-collection-Fortschreibung (Token-Weitergabe), ETag-Grundlage für den
 * Fallback, Idempotenz über UID+ETag (2. Lauf ohne Duplikate), Löschungen
 * ohne Löschweitergabe, Auto-Disable-Zählung und Org-Isolation.
 */
final class CardDavSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function bindGateway(FakeCardDavGateway $gateway): void {
        $this->app->instance(CardDavGatewayFactory::class, new class($gateway) implements CardDavGatewayFactory {
            public function __construct(private CardDavGateway $gateway) {}

            public function for(CardDavConnection $connection): CardDavGateway {
                return $this->gateway;
            }
        });
    }

    private function connection(?Organization $organization = null): CardDavConnection {
        $organization ??= $this->organization;

        return CardDavConnection::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav',
            'username' => 'svc',
            'app_password' => 'secret',
            'addressbook_url' => 'https://cloud.example.com/dav/addressbooks/users/svc/contacts-' . $organization->id . '/',
            'addressbook_name' => 'Kontakte',
            'active' => true,
        ]);
    }

    private function vcard(string $uid, string $fn, string $email, ?string $phone = null): VCard {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'UID:' . $uid,
            'FN:' . $fn,
            'EMAIL;TYPE=WORK:' . $email,
        ];
        if ($phone !== null) {
            $lines[] = 'TEL;TYPE=WORK,VOICE:' . $phone;
        }
        $lines[] = 'END:VCARD';

        /** @var VCard $card */
        $card = Reader::read(implode("\r\n", $lines));

        return $card;
    }

    public function test_first_sync_stages_contacts_as_open_inbox_suggestions(): void {
        $this->connection();
        $this->bindGateway(new FakeCardDavGateway(pages: [
            new CardDavSyncPage([
                new CardDavCardChange('/dav/ab/a.vcf', 'etag-a', $this->vcard('uid-a', 'Jürgen Müller', 'juergen@example.com')),
                new CardDavCardChange('/dav/ab/b.vcf', 'etag-b', $this->vcard('uid-b', 'Erika Beispiel', 'erika@example.com')),
            ], [], 'tok-1'),
        ]));

        $result = app(CardDavContactImporter::class)->sync($this->organization);

        $this->assertSame(2, $result['changed']);
        $this->assertSame(2, $result['staged']);
        $this->assertSame(0, $result['linked']);

        $items = IntegrationInboxItem::query()->get();
        $this->assertCount(2, $items);
        $this->assertSame([CardDavPlugin::ID], $items->pluck('plugin_id')->unique()->all());
        $this->assertSame(['carddav'], $items->pluck('source')->unique()->all());
        $this->assertSame([IntegrationInboxItem::CASE_UNMATCHED], $items->pluck('case_type')->unique()->all());
        $this->assertSame([(new Customer)->getMorphClass()], $items->pluck('target_type')->unique()->all());
        $this->assertSame([$this->organization->id], $items->pluck('organization_id')->unique()->all());

        // Kein Direkt-Write: keine Kunden angelegt, nur Vorschläge.
        $this->assertSame(0, Customer::query()->count());

        // Spiegel + Sync-Token fortgeschrieben.
        $this->assertDatabaseHas('carddav_cards', ['href' => '/dav/ab/a.vcf', 'uid' => 'uid-a', 'etag' => 'etag-a']);
        $this->assertDatabaseHas('carddav_connections', ['sync_token' => 'tok-1']);
    }

    public function test_second_run_forwards_sync_token_and_skips_unchanged_cards(): void {
        $this->connection();
        $gateway = new FakeCardDavGateway(pages: [
            new CardDavSyncPage([
                new CardDavCardChange('/dav/ab/a.vcf', 'etag-a', $this->vcard('uid-a', 'Jürgen Müller', 'juergen@example.com')),
            ], [], 'tok-1'),
            // ETag-Fallback-Situation: Server meldet dieselbe (unveränderte) Karte erneut.
            new CardDavSyncPage([
                new CardDavCardChange('/dav/ab/a.vcf', 'etag-a', $this->vcard('uid-a', 'Jürgen Müller', 'juergen@example.com')),
            ], [], 'tok-2'),
        ]);
        $this->bindGateway($gateway);

        $importer = app(CardDavContactImporter::class);
        $importer->sync($this->organization);
        $second = $importer->sync($this->organization);

        // sync-collection-Fortschreibung: der 2. Lauf sendet das gespeicherte Token …
        $this->assertSame(['', 'tok-1'], $gateway->receivedTokens);
        // … und liefert den lokalen ETag-Stand als Fallback-Grundlage mit.
        $this->assertSame(['/dav/ab/a.vcf' => 'etag-a'], $gateway->receivedLocalEtags[1]);

        // Idempotenz: kein Duplikat, unveränderte Karte übersprungen.
        $this->assertSame(1, IntegrationInboxItem::query()->count());
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(0, $second['changed']);
        $this->assertDatabaseHas('carddav_connections', ['sync_token' => 'tok-2']);
    }

    public function test_changed_card_updates_suggestion_without_duplicate(): void {
        $this->connection();
        $this->bindGateway(new FakeCardDavGateway(pages: [
            new CardDavSyncPage([
                new CardDavCardChange('/dav/ab/a.vcf', 'etag-1', $this->vcard('uid-a', 'Jürgen Müller', 'alt@example.com')),
            ], [], 'tok-1'),
            new CardDavSyncPage([
                new CardDavCardChange('/dav/ab/a.vcf', 'etag-2', $this->vcard('uid-a', 'Jürgen Müller', 'neu@example.com')),
            ], [], 'tok-2'),
        ]));

        $importer = app(CardDavContactImporter::class);
        $importer->sync($this->organization);
        $importer->sync($this->organization);

        $items = IntegrationInboxItem::query()->get();
        $this->assertCount(1, $items);
        $this->assertSame('neu@example.com', $items->first()->mapped_snapshot['email'] ?? null);
        $this->assertDatabaseHas('carddav_cards', ['href' => '/dav/ab/a.vcf', 'etag' => 'etag-2']);
    }

    public function test_matching_customer_becomes_suggestion_not_merge(): void {
        $this->connection();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Match GmbH',
            'email' => 'match@example.com',
            'phone' => '+49 30 111',
        ]);
        $this->bindGateway(new FakeCardDavGateway(pages: [
            new CardDavSyncPage([
                new CardDavCardChange('/dav/ab/m.vcf', 'etag-m', $this->vcard('uid-m', 'Match GmbH', 'match@example.com', '+49 30 999')),
            ], [], 'tok-1'),
        ]));

        app(CardDavContactImporter::class)->sync($this->organization);

        // E-Mail-Treffer ist „likely" → Zuordnungsvorschlag (ambiguous), KEIN Auto-Link.
        $item = IntegrationInboxItem::query()->firstOrFail();
        $this->assertSame(IntegrationInboxItem::CASE_AMBIGUOUS, $item->case_type);
        $this->assertSame($customer->id, $item->candidate_ids[0]['id'] ?? null);
        $this->assertSame(0, ExternalReference::query()->where('plugin_id', CardDavPlugin::ID)->count());

        // Kein Direkt-Write auf Kundenfelder.
        $this->assertSame('+49 30 111', $customer->refresh()->phone);
    }

    public function test_linked_reference_with_diff_stages_conflict_without_write(): void {
        $connection = $this->connection();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Verknüpft AG',
            'email' => 'link@example.com',
            'phone' => '+49 30 111',
        ]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => CardDavPlugin::ID,
            'external_type' => CardDavContactImporter::EXT_TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => 'uid-l',
            'payload' => [],
            'synced_at' => now(),
        ]);
        $this->bindGateway(new FakeCardDavGateway(pages: [
            new CardDavSyncPage([
                new CardDavCardChange('/dav/ab/l.vcf', 'etag-l', $this->vcard('uid-l', 'Verknüpft AG', 'link@example.com', '+49 30 999')),
            ], [], 'tok-1'),
        ]));

        app(CardDavContactImporter::class)->sync($this->organization);

        $item = IntegrationInboxItem::query()->firstOrFail();
        $this->assertSame(IntegrationInboxItem::CASE_CONFLICT, $item->case_type);
        $this->assertContains('phone', $item->diff_fields ?? []);

        // ManualReview: der abweichende Wert wird NICHT auf den Kunden geschrieben.
        $this->assertSame('+49 30 111', $customer->refresh()->phone);
        $this->assertNotNull($connection->refresh()->last_synced_at);
    }

    public function test_remote_deletion_dismisses_open_suggestion_and_keeps_local_data(): void {
        $this->connection();
        $this->bindGateway(new FakeCardDavGateway(pages: [
            new CardDavSyncPage([
                new CardDavCardChange('/dav/ab/a.vcf', 'etag-a', $this->vcard('uid-a', 'Jürgen Müller', 'juergen@example.com')),
            ], [], 'tok-1'),
            new CardDavSyncPage([], ['/dav/ab/a.vcf'], 'tok-2'),
        ]));

        $importer = app(CardDavContactImporter::class);
        $importer->sync($this->organization);
        $second = $importer->sync($this->organization);

        $this->assertSame(1, $second['deleted']);
        $this->assertDatabaseMissing('carddav_cards', ['href' => '/dav/ab/a.vcf']);
        $this->assertSame(
            IntegrationInboxItem::STATUS_DISMISSED,
            IntegrationInboxItem::query()->firstOrFail()->status,
        );
        // Keine Löschweitergabe an lokale Daten.
        $this->assertSame(0, Customer::query()->count());
    }

    public function test_failed_card_is_skipped_and_retried_later(): void {
        $this->connection();
        $this->bindGateway(new FakeCardDavGateway(pages: [
            new CardDavSyncPage([
                // Abruf-/Parse-Fehler der Lib → vcard null.
                new CardDavCardChange('/dav/ab/broken.vcf', 'etag-x', null),
            ], [], 'tok-1'),
        ]));

        $result = app(CardDavContactImporter::class)->sync($this->organization);

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, IntegrationInboxItem::query()->count());
        // ETag NICHT fortgeschrieben → nächster Lauf versucht die Karte erneut.
        $this->assertDatabaseMissing('carddav_cards', ['href' => '/dav/ab/broken.vcf']);
    }

    public function test_failures_count_towards_auto_disable_and_disabled_connection_is_skipped(): void {
        $connection = $this->connection();
        $connection->forceFill(['consecutive_failures' => 9])->save();
        $gateway = new FakeCardDavGateway(failSync: true);
        $this->bindGateway($gateway);

        $importer = app(CardDavContactImporter::class);
        $result = $importer->sync($this->organization);

        $this->assertSame(1, $result['failed']);
        $connection->refresh();
        $this->assertSame(10, $connection->consecutive_failures);
        $this->assertNotNull($connection->disabled_at);
        $this->assertNotNull($connection->last_error);

        // Auto-Disable greift: der nächste Lauf fasst die Verbindung nicht mehr an.
        $again = $importer->sync($this->organization);
        $this->assertSame(0, $again['connections']);
        $this->assertSame(1, $gateway->syncCalls);
    }

    public function test_sync_is_org_isolated(): void {
        $this->connection();
        $otherOrg = Organization::factory()->create();
        $this->connection($otherOrg);

        $gateway = new FakeCardDavGateway(pages: [
            new CardDavSyncPage([
                new CardDavCardChange('/dav/ab/a.vcf', 'etag-a', $this->vcard('uid-a', 'Jürgen Müller', 'juergen@example.com')),
            ], [], 'tok-1'),
        ]);
        $this->bindGateway($gateway);

        app(CardDavContactImporter::class)->sync($this->organization);

        // Nur das Adressbuch der eigenen Organisation wurde synchronisiert …
        $this->assertSame(1, $gateway->syncCalls);
        $this->assertStringContainsString('contacts-' . $this->organization->id, $gateway->receivedAddressbookUrls[0]);
        // … und alle Vorschläge gehören zur eigenen Organisation.
        $this->assertDatabaseHas('integration_inbox_items', ['organization_id' => $this->organization->id]);
        $this->assertDatabaseMissing('integration_inbox_items', ['organization_id' => $otherOrg->id]);
    }

    public function test_sync_command_processes_single_organization(): void {
        $this->connection();
        $this->bindGateway(new FakeCardDavGateway(pages: [
            new CardDavSyncPage([
                new CardDavCardChange('/dav/ab/a.vcf', 'etag-a', $this->vcard('uid-a', 'Jürgen Müller', 'juergen@example.com')),
            ], [], 'tok-1'),
        ]));

        $this->artisan('carddav:sync', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('connections 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('integration_inbox_items', [
            'organization_id' => $this->organization->id,
            'plugin_id' => CardDavPlugin::ID,
        ]);
    }
}
