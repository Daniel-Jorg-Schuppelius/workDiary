<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceReassignmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceMatchKind, IncomingInvoiceRecognition, IncomingInvoiceTransferStatus};
use App\Models\Document\Document;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{IncomingEInvoice, IncomingEInvoiceTransfer};
use App\Models\Platform\{Organization, User};
use App\Models\Supplier\Supplier;
use App\Modules\ModuleRegistry;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Invoicing\Contracts\{IncomingInvoiceTransferTarget, IncomingPartyLookup};
use App\Services\Invoicing\Dto\{IncomingInvoiceTransferResult, IncomingPartyCandidate};
use App\Services\Stammdaten\CollectiveContacts;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Gefälschte Kontaktsuche eines Buchhaltungsprogramms (MVP-1226). */
final class ReassignmentFakeLookup implements IncomingPartyLookup {
    public function key(): string {
        return 'fake';
    }

    public function label(): string {
        return 'Fakebuch';
    }

    public function isEnabled(Organization $organization): bool {
        return true;
    }

    public function lookup(IncomingEInvoice $incoming): array {
        return [
            new IncomingPartyCandidate('fake', 'Fakebuch', 'c-1', 'JTL-Software GmbH', 'K-10023', 'DE257864472', 'rechnung@jtl.example', 'Rheinstraße 7', '41836', 'Hückelhoven', 'DE'),
            new IncomingPartyCandidate('fake', 'Fakebuch', 'c-2', 'Ganz Anders AG', 'K-10024', 'DE111111111'),
        ];
    }
}

/** Gefälschtes Übergabeziel, damit der Hinweis zur Stammdaten-Hoheit einen Anlass hat. */
final class ReassignmentFakeTarget implements IncomingInvoiceTransferTarget {
    public function key(): string {
        return 'fake';
    }

    public function label(): string {
        return 'Fakebuch';
    }

    public function isEnabled(Organization $organization): bool {
        return true;
    }

    public function appliesTo(IncomingEInvoice $incoming): bool {
        return true;
    }

    public function transfer(IncomingEInvoice $incoming, IncomingEInvoiceTransfer $journal): IncomingInvoiceTransferResult {
        return IncomingInvoiceTransferResult::waiting('Fake');
    }
}

/**
 * Phase 152 (MVP-1222–1228): Sammelaktion mit Rückfrage, „Neu aus Belegdaten“
 * je Gegenpartei, Zuordnung aufheben, Sammelkontakt-Übersicht, Kandidaten
 * aus dem Buchhaltungsprogramm, Dublettenwarnung und Hinweise.
 */
final class IncomingInvoiceReassignmentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => 'DE811907980']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_the_bulk_action_requires_a_choice_and_asks_before_the_collective_contact(): void {
        $a = $this->incoming(['seller_name' => 'JTL-Software-GmbH', 'seller_vat_id' => 'DE257864472']);
        $b = $this->incoming(['seller_name' => 'JTL-Software-GmbH', 'seller_vat_id' => 'DE257864472']);

        $this->post(route('finance.incoming-invoices.bulk-assign'), ['ids' => [$a->sqid, $b->sqid]])->assertSessionHasErrors('party');

        $this->post(route('finance.incoming-invoices.bulk-assign'), ['ids' => [$a->sqid, $b->sqid], 'party' => 'collective'])
            ->assertRedirect()->assertSessionHas('open_dialog');
        $dialog = (string) session('open_dialog');
        $this->assertStringContainsString('sammel-zuordnen/bestaetigen', $dialog);
        $this->get($dialog)->assertOk()
            ->assertSee('JTL-Software-GmbH')->assertSee('DE257864472')->assertSee('2 Eingänge')
            ->assertSee(__('nennt eine USt-IdNr. — besser „Neu aus Belegdaten“'));
        $this->assertNull($a->refresh()->supplier_id, 'ohne Bestätigung passiert nichts');

        $this->post(route('finance.incoming-invoices.bulk-assign'), ['ids' => [$a->sqid, $b->sqid], 'party' => 'collective', 'confirmed' => '1'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertTrue((bool) $a->refresh()->supplier?->is_collective);
        $this->assertTrue((bool) $b->refresh()->supplier?->is_collective);
    }

    public function test_bulk_new_creates_one_party_per_counterparty_and_uses_existing_ones(): void {
        $pinsel = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH']);
        $a = $this->incoming(['seller_name' => 'JTL-Software-GmbH', 'seller_vat_id' => 'DE257864472', 'summary' => [
            'seller_address' => ['street' => 'Rheinstraße 7', 'zip' => '41836', 'city' => 'Hückelhoven', 'country' => 'de'], 'seller_email' => 'rechnung@jtl.example',
        ]]);
        $b = $this->incoming(['seller_name' => 'JTL-Software-GmbH', 'seller_vat_id' => 'DE257864472']);
        $c = $this->incoming(['seller_name' => 'Pinsel-GmbH']);
        $later = $this->incoming(['seller_name' => 'JTL-Software-GmbH', 'seller_vat_id' => 'DE257864472']);

        $this->post(route('finance.incoming-invoices.bulk-assign'), ['ids' => [$a->sqid, $b->sqid, $c->sqid], 'party' => 'new'])->assertRedirect()->assertSessionHas('open_dialog');
        $this->get((string) session('open_dialog'))->assertOk()->assertSee(__('wird angelegt'))->assertSee('Pinsel GmbH');

        $this->post(route('finance.incoming-invoices.bulk-assign'), ['ids' => [$a->sqid, $b->sqid, $c->sqid], 'party' => 'new', 'confirmed' => '1'])
            ->assertRedirect()
            ->assertSessionHas('success', static fn (string $message): bool => str_contains($message, '3 Eingänge zugeordnet, 0 übersprungen.')
                && str_contains($message, '1 Partei angelegt: JTL-Software-GmbH.')
                && str_contains($message, '1 bestehende Partei übernommen: Pinsel GmbH.')
                && str_contains($message, '1 weiterer offener Eingang automatisch zugeordnet.'));

        $jtl = Supplier::query()->where('name', 'JTL-Software-GmbH')->sole();
        $this->assertSame('DE257864472', $jtl->vat_id);
        $this->assertSame('rechnung@jtl.example', $jtl->email);
        $this->assertSame('Hückelhoven', $jtl->address_city);
        $this->assertSame($jtl->id, $a->refresh()->supplier_id);
        $this->assertSame($jtl->id, $b->refresh()->supplier_id);
        $this->assertSame($pinsel->id, $c->refresh()->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::Manual, $a->match_kind);
        // Der nicht gewählte offene Eingang derselben Partei trifft jetzt über die USt-IdNr.
        $this->assertSame($jtl->id, $later->refresh()->supplier_id);
        $this->assertSame(IncomingInvoiceMatchKind::VatId, $later->match_kind);
    }

    public function test_an_assignment_can_be_removed_in_the_dialog_and_in_bulk_and_open_transfers_vanish(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH']);
        $assigned = $this->incoming(['supplier_id' => $supplier->id, 'match_kind' => IncomingInvoiceMatchKind::Manual]);
        IncomingEInvoiceTransfer::query()->create([
            'organization_id' => $this->organization->id, 'incoming_einvoice_id' => $assigned->id, 'target' => 'lexoffice',
            'status' => IncomingInvoiceTransferStatus::Waiting->value, 'error' => 'Kontakt fehlt',
        ]);
        $transferred = $this->incoming(['supplier_id' => $supplier->id]);
        $transferred->forceFill(['transferred_at' => now()])->save();
        $open = $this->incoming([]);

        $this->get(route('finance.incoming-invoices.assign.form', $assigned))->assertOk()->assertSee('value="unassign"', false);
        $this->get(route('finance.incoming-invoices.assign.form', $open))->assertOk()->assertDontSee('value="unassign"', false);

        $this->post(route('finance.incoming-invoices.assign', $assigned), ['direction' => 'incoming', 'mode' => 'unassign'])
            ->assertRedirect()->assertSessionHas('success', __('Zuordnung aufgehoben.'));
        $assigned->refresh();
        $this->assertNull($assigned->supplier_id);
        $this->assertNull($assigned->match_kind);
        $this->assertDatabaseMissing('incoming_einvoice_transfers', ['incoming_einvoice_id' => $assigned->id]);
        $this->assertTrue($assigned->auditLogs()->where('event', 'incoming_einvoice.unassigned')->exists());

        // Reiter „Alle“ mit Sammelaktion: übergebene Eingänge bekommen keinen Kasten.
        $this->get(route('finance.incoming-invoices.index', ['tab' => 'all']))->assertOk()
            ->assertSee('name="party"', false)
            ->assertSee('value="' . $open->sqid . '"', false)
            ->assertDontSee('value="' . $transferred->sqid . '"', false);

        $again = $this->incoming(['supplier_id' => $supplier->id]);
        $notAssigned = __('Der Eingang ist keiner Partei zugeordnet.');
        $this->post(route('finance.incoming-invoices.bulk-assign'), ['ids' => [$again->sqid, $transferred->sqid, $open->sqid], 'party' => 'unassign'])
            ->assertRedirect()
            ->assertSessionHas('warning', static fn (string $message): bool => str_contains($message, '1 Zuordnungen aufgehoben, 2 übersprungen.')
                && str_contains($message, '1 × ' . $notAssigned) && str_contains($message, 'übergeben'));
        $this->assertNull($again->refresh()->supplier_id);
        $this->assertSame($supplier->id, $transferred->refresh()->supplier_id);
    }

    public function test_the_rules_page_lists_parked_documents_and_moves_them_to_a_real_party(): void {
        $collective = app(CollectiveContacts::class)->supplier($this->organization);
        $open = $this->incoming(['supplier_id' => $collective->id, 'seller_name' => 'JTL-Software-GmbH', 'seller_vat_id' => 'DE257864472', 'match_kind' => IncomingInvoiceMatchKind::Manual]);
        $done = $this->incoming(['supplier_id' => $collective->id, 'seller_name' => 'JTL-Software-GmbH', 'seller_vat_id' => 'DE257864472', 'match_kind' => IncomingInvoiceMatchKind::Manual]);
        $done->forceFill(['transferred_at' => now()])->save();

        $this->get(route('finance.incoming-invoices.rules'))->assertOk()
            ->assertSee(__('Am Sammelkontakt geparkt'))->assertSee('JTL-Software-GmbH')->assertSee('1 offen von 2')->assertSee(__('Anlegen und umhängen'));

        $this->post(route('finance.incoming-invoices.rules.collective.promote'), ['direction' => 'incoming', 'key' => 'vat:DE257864472'])
            ->assertRedirect(route('finance.incoming-invoices.rules'))
            ->assertSessionHas('success', static fn (string $message): bool => str_contains($message, '1 Eingänge umgehängt') && str_contains($message, '1 bereits übergebener Eingang bleibt'));

        $jtl = Supplier::query()->where('name', 'JTL-Software-GmbH')->sole();
        $this->assertSame('DE257864472', $jtl->vat_id);
        $this->assertSame($jtl->id, $open->refresh()->supplier_id);
        $this->assertSame($collective->id, $done->refresh()->supplier_id);
        $this->assertTrue($open->auditLogs()->where('event', 'incoming_einvoice.collective_promoted')->exists());
        $this->get(route('finance.incoming-invoices.rules'))->assertOk()->assertDontSee('1 offen von 2');

        $this->post(route('finance.incoming-invoices.rules.collective.promote'), ['direction' => 'incoming', 'key' => 'vat:DE000000000'])
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_the_dialog_warns_before_the_collective_contact_blocks_a_duplicate_vat_and_names_similar_parties(): void {
        Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'JTL Software GmbH', 'vat_id' => 'DE257864472']);
        $incoming = $this->incoming(['seller_name' => 'JTL-Software-GmbH', 'seller_vat_id' => 'DE257864472']);

        $this->get(route('finance.incoming-invoices.assign.form', $incoming))->assertOk()
            ->assertSee('Ein Sammelkontakt ist für Belege ohne Stammsatz gedacht')
            ->assertSee('trägt dieselbe USt-IdNr.')
            ->assertSee('(ähnlich)');
        $this->post(route('finance.incoming-invoices.assign', $incoming), ['direction' => 'incoming', 'mode' => 'new', 'new_name' => 'JTL-Software-GmbH', 'new_vat_id' => 'DE 257864472'])
            ->assertRedirect()->assertSessionHas('error', static fn (string $message): bool => str_contains($message, 'JTL Software GmbH'));
        $this->assertNull($incoming->refresh()->supplier_id);

        // Ohne USt-IdNr. nur die Warnung — die Neuanlage bleibt möglich.
        Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH']);
        $other = $this->incoming(['seller_name' => 'Pinsel-GmbH', 'seller_vat_id' => null]);
        $this->get(route('finance.incoming-invoices.assign.form', $other))->assertOk()->assertSee('Ähnliche Stammsätze: Pinsel GmbH');
    }

    public function test_a_contact_from_the_accounting_program_can_be_adopted_and_the_authority_hint_appears(): void {
        app(ModuleRegistry::class)->contribute(IncomingPartyLookup::class, ReassignmentFakeLookup::class);
        $incoming = $this->incoming(['seller_name' => 'JTL-Software-GmbH', 'seller_vat_id' => 'DE257864472']);

        $this->get(route('finance.incoming-invoices.assign.form', $incoming))->assertOk()
            ->assertSee(__('Im Buchhaltungsprogramm gefunden'))->assertSee('JTL-Software GmbH')->assertSee('Nr. K-10023')
            ->assertDontSee('Ganz Anders AG')->assertSee('Fakebuch: Treffer');

        $this->post(route('finance.incoming-invoices.assign', $incoming), ['direction' => 'incoming', 'mode' => 'adopt', 'candidate' => 'fake:c-1'])
            ->assertRedirect()->assertSessionHas('success');
        $supplier = Supplier::query()->where('name', 'JTL-Software GmbH')->sole();
        $this->assertSame('DE257864472', $supplier->vat_id);
        $this->assertSame('rechnung@jtl.example', $supplier->email);
        $this->assertSame($supplier->id, $incoming->refresh()->supplier_id);
        $this->assertTrue(ExternalReference::query()->where('plugin_id', 'fake')->where('external_id', 'c-1')->where('referenceable_id', $supplier->id)->exists());
        $this->assertTrue($incoming->auditLogs()->where('event', 'incoming_einvoice.party_adopted')->exists());

        $second = $this->incoming(['seller_name' => 'JTL-Software-GmbH', 'seller_vat_id' => 'DE257864472']);
        $this->post(route('finance.incoming-invoices.assign', $second), ['direction' => 'incoming', 'mode' => 'adopt', 'candidate' => 'fake:nope'])
            ->assertRedirect()->assertSessionHas('error');

        // Hinweis zur Stammdaten-Hoheit nur, wenn die Buchhaltung führt und ein Übergabeziel besteht (MVP-1228).
        app(ModuleRegistry::class)->contribute(IncomingInvoiceTransferTarget::class, ReassignmentFakeTarget::class);
        $this->get(route('finance.incoming-invoices.assign.form', $second))->assertOk()->assertDontSee('Die Buchhaltung führt die Stammdaten');
        Setting::set(ContactPushService::AUTHORITY_KEY, ContactPushService::AUTHORITY_ACCOUNTING, SettingScope::Organization, $this->organization);
        // Die Organisation am angemeldeten Nutzer ist aus dem ersten Request gecacht (Falle aus Phase 147).
        $this->admin->refresh();
        $this->get(route('finance.incoming-invoices.assign.form', $second))->assertOk()->assertSee('Die Buchhaltung führt die Stammdaten');
    }

    /** @param  array<string, mixed>  $attributes */
    /** Befund 2026-10-20 (MVP-1250): weitergeleitete Mails tragen den eigenen Absender — Stammsätze mit der eigenen Adresse galten als „ähnlich“ und bekamen sie bei „Neu aus Belegdaten“ als E-Mail. */
    public function test_own_addresses_and_domains_never_make_a_party_similar(): void {
        $this->admin->update(['email' => 'buero@eigene-firma.example']);
        Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Elovade Deutschland GmbH', 'company' => 'Elovade Deutschland GmbH', 'email' => 'buero@eigene-firma.example']);
        Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Synology GmbH', 'company' => 'Synology GmbH', 'email' => 'konto@eigene-firma.example']);
        Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Fahrradladen GmbH', 'company' => 'Fahrradladen GmbH', 'email' => 'billing@fahrradladen.example']);

        $forwarded = $this->incoming(['seller_name' => 'Eins und Eins Telecom GmbH', 'sender_email' => 'buero@eigene-firma.example']);
        $this->get(route('finance.incoming-invoices.assign.form', $forwarded))->assertOk()
            ->assertDontSee('(ähnlich)')->assertDontSee('Ähnliche Stammsätze');

        // Der Absender eines fremden Shops bleibt ein Ähnlichkeitssignal.
        $direct = $this->incoming(['seller_name' => 'Fahrradladen', 'sender_email' => 'noreply@fahrradladen.example']);
        $this->get(route('finance.incoming-invoices.assign.form', $direct))->assertOk()->assertSee('Ähnliche Stammsätze: Fahrradladen GmbH');

        // „Neu aus Belegdaten“ übernimmt den eigenen Absender nicht als E-Mail der Partei.
        $this->post(route('finance.incoming-invoices.assign', $forwarded), ['direction' => 'incoming', 'mode' => 'new', 'new_name' => 'Eins und Eins Telecom GmbH'])->assertRedirect();
        $this->assertNull(Supplier::query()->where('name', 'Eins und Eins Telecom GmbH')->sole()->email);
    }

    private function incoming(array $attributes): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice, 'created_by_user_id' => $this->admin->id]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'mail', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'recognition' => IncomingInvoiceRecognition::Extracted,
            'invoice_number' => 'RE-' . uniqid(), 'seller_name' => 'Irgendwer', 'currency' => 'EUR', 'amount_gross' => '119.00',
            'summary' => [],
            ...$attributes,
        ]);
    }

    /** Sicherheitsaudit 2026-10-21, li-4: läuft die Übergabe gerade, bleibt die Zuordnung samt Journal stehen. */
    public function test_an_assignment_is_not_removed_while_the_transfer_runs(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Pinsel GmbH']);
        $assigned = $this->incoming(['supplier_id' => $supplier->id, 'match_kind' => IncomingInvoiceMatchKind::Manual]);
        IncomingEInvoiceTransfer::query()->create([
            'organization_id' => $this->organization->id, 'incoming_einvoice_id' => $assigned->id, 'target' => 'lexoffice',
            'status' => IncomingInvoiceTransferStatus::Waiting->value,
        ]);
        $lock = \Illuminate\Support\Facades\Cache::lock(\App\Services\Invoicing\EInvoice\IncomingInvoiceTransferService::LOCK_PREFIX . $assigned->id, 300);
        $this->assertTrue($lock->get());

        try {
            app(\App\Services\Invoicing\EInvoice\IncomingInvoiceMatcher::class)->unassign($assigned, null);
            $this->fail('Während der Übergabe darf die Zuordnung nicht fallen.');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('Die Übergabe dieses Eingangs läuft gerade — bitte gleich noch einmal versuchen.'), $e->getMessage());
        } finally {
            $lock->release();
        }
        $this->assertSame($supplier->id, $assigned->refresh()->supplier_id);
        $this->assertDatabaseHas('incoming_einvoice_transfers', ['incoming_einvoice_id' => $assigned->id]);
    }
}
