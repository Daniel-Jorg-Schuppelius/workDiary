<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolDispatchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Peppol;

use App\Models\{Customer, DocumentDispatch, Invoice, PeppolParticipantLookup, PluginSetting, User};
use App\Plugins\PeppolAccessPoint\PeppolAccessPointPlugin;
use App\Plugins\PluginManager;
use App\Services\Peppol\{PeppolInvoiceDispatcher, PeppolParticipantService};
use ERechnungToolkit\Contracts\{DnsNaptrResolverInterface, ValidatorInterface};
use ERechnungToolkit\Enums\ValidationSeverity;
use ERechnungToolkit\Peppol\{DocumentTypeId, ParticipantId, Sbdh};
use ERechnungToolkit\Validators\{ValidationMessage, ValidationResult};
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeNaptrResolver, FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Peppol-Versand (Feature 066, MVP-734).
 *
 * Geprüft werden vor allem die Tore, an denen der Versand scheitern MUSS:
 * unregistrierter Empfänger, nicht angenommenes Format, Validator-Befund und
 * fehlende Zugangsdaten. Eine Rechnung, die trotzdem hinausgeht, wäre teurer
 * als eine, die zu Recht liegen bleibt.
 *
 * Kein Netzzugriff: HTTP läuft über den Guzzle-MockHandler
 * ({@see FakePluginHttp}), DNS über {@see FakeNaptrResolver}.
 */
class PeppolDispatchTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private const SMP_BASE = 'https://smp.example.test';

    private const AP_BASE = 'https://ap.example.test/v1';

    private const RECEIVER = '9930:DE987654321';

    private static int $invoiceNo = 0;

    private User $admin;

    private Customer $customer;

    private FakeNaptrResolver $dns;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'WorkDiary Org']);
        $this->organization->update(['settings' => ['einvoice' => $this->sellerSettings()]]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'currency' => 'EUR',
            'email' => 'buchhaltung@acme.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
            'buyer_reference' => '991-12345-67',
            'peppol_participant_id' => self::RECEIVER,
            'created_by' => $this->admin->id,
        ]);

        $this->configurePlugin();
        $this->fakeDns([self::SMP_BASE]);
    }

    // --- Aufbau ----------------------------------------------------------

    /** @param array<string, mixed> $overrides */
    private function configurePlugin(array $overrides = []): void {
        $settings = array_merge([
            'base_url' => self::AP_BASE,
            'api_key' => 'geheim',
            'sender_participant_id' => '9930:DE123456789',
            'sender_country' => 'DE',
            'sml_zone' => \ERechnungToolkit\Enums\SmlZone::TEST->value,
        ], $overrides);

        // Ein zweiter Aufruf ändert die bestehende Zeile (Unique je Org+Plugin).
        PluginSetting::query()->updateOrCreate(
            ['organization_id' => $this->organization->id, 'plugin_id' => PeppolAccessPointPlugin::ID],
            ['enabled' => true, 'settings' => $settings],
        );
        app(PluginManager::class)->flushRuntimeCaches();
    }

    /** @param list<string> $smpUrls */
    private function fakeDns(array $smpUrls): void {
        $this->dns = new FakeNaptrResolver($smpUrls);
        $this->app->instance(DnsNaptrResolverInterface::class, $this->dns);
    }

    /**
     * SMP-ServiceGroup-Antwort mit genau den registrierten Dokumenttypen.
     *
     * @param  list<DocumentTypeId>  $types
     */
    private static function serviceGroupXml(array $types): string {
        $references = '';
        foreach ($types as $type) {
            $references .= '<ServiceMetadataReference href="' . self::SMP_BASE . '/'
                . rawurlencode(ParticipantId::DEFAULT_SCHEME . '::' . self::RECEIVER)
                . '/services/' . $type->urlEncoded() . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<ServiceGroup xmlns="http://busdox.org/serviceMetadata/publishing/1.0/">'
            . '<ParticipantIdentifier scheme="' . ParticipantId::DEFAULT_SCHEME . '">' . self::RECEIVER . '</ParticipantIdentifier>'
            . '<ServiceMetadataReferenceCollection>' . $references . '</ServiceMetadataReferenceCollection>'
            . '</ServiceGroup>';
    }

    /**
     * @param  list<DocumentTypeId>|null  $registeredTypes  null = SMP meldet 404
     * @param  array<string, mixed>  $extra  zusätzliche Stubs (gewinnen vor den Vorgaben)
     */
    private function fakeHttp(?array $registeredTypes = null, array $extra = []): FakePluginHttp {
        $registeredTypes ??= [DocumentTypeId::peppolBisBillingInvoice()];

        return FakePluginHttp::fake($extra + [
            self::SMP_BASE . '/*' => new Psr7Response(200, ['Content-Type' => 'application/xml'], self::serviceGroupXml($registeredTypes)),
            self::AP_BASE . '/outbox*' => FakePluginHttp::response([
                'messageId' => 'MSG-1',
                'status' => 'delivered',
                'receiverAccessPoint' => 'https://ap.receiver.example/as4',
            ]),
            self::AP_BASE . '/*' => FakePluginHttp::response([], 200),
        ]);
    }

    /** @return array<string, mixed> */
    private function sellerSettings(): array {
        return [
            'seller_name' => 'WorkDiary GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE123456789',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@workdiary.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'WorkDiary GmbH',
            'payment_terms_days' => 14,
        ];
    }

    private function makeInvoice(string $status = Invoice::STATUS_ISSUED): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2027-' . str_pad((string) ++self::$invoiceNo, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'issued_on' => '2027-02-01',
            'due_on' => '2027-02-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Beratung',
            'quantity' => '2.00',
            'unit' => 'Std.',
            'unit_price' => '100.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh(['items', 'customer']);
    }

    // --- Versand ---------------------------------------------------------

    public function test_send_wraps_the_invoice_and_records_the_transport_receipt(): void {
        $fake = $this->fakeHttp();
        $invoice = $this->makeInvoice();

        $this->actingAs($this->admin)
            ->post(route('invoices.peppol.send', $invoice))
            ->assertRedirect();

        $dispatch = DocumentDispatch::query()
            ->where('invoice_id', $invoice->id)
            ->where('channel', DocumentDispatch::CHANNEL_PEPPOL)
            ->firstOrFail();

        $this->assertSame('sent', $dispatch->status);
        $this->assertSame('xrechnung_ubl', $dispatch->format);
        $this->assertSame(ParticipantId::DEFAULT_SCHEME . '::' . self::RECEIVER, $dispatch->recipient);
        $this->assertSame('MSG-1', $dispatch->meta['message_id'] ?? null);
        $this->assertSame('delivered', $dispatch->meta['transport_status'] ?? null);
        $this->assertNotEmpty($dispatch->meta['instance_identifier'] ?? null);
        $this->assertNotEmpty($dispatch->sha256);

        // Der Provider bekommt einen SBDH-Umschlag mit beiden Kennungen — nicht
        // das nackte UBL.
        $fake->assertSent(function (RequestInterface $request): bool {
            if (! str_contains((string) $request->getUri(), '/outbox')) {
                return false;
            }
            $payload = json_decode((string) $request->getBody(), true);
            $envelope = is_array($payload) ? (string) ($payload['document'] ?? '') : '';
            $sbdh = Sbdh::parse($envelope);

            return $sbdh->getReceiver()->getValue() === self::RECEIVER
                && $sbdh->getSender()->getValue() === '9930:DE123456789'
                && $sbdh->getSenderCountry() === 'DE'
                && str_contains(Sbdh::payloadOf($envelope), '<cbc:CustomizationID>');
        });

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $invoice->getMorphClass(),
            'auditable_id' => $invoice->id,
            'event' => 'invoice.peppolSent',
        ]);
    }

    public function test_invoice_page_offers_the_peppol_action_only_when_it_can_work(): void {
        $this->fakeHttp();
        $invoice = $this->makeInvoice();

        $this->actingAs($this->admin)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee(route('invoices.peppol.send', $invoice));

        // Ohne Zugangsdaten darf die Aktion gar nicht erst erscheinen.
        $this->configurePlugin(['base_url' => '', 'api_key' => '']);

        $this->actingAs($this->admin)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee(route('invoices.peppol.send', $invoice));
    }

    public function test_smp_result_is_reused_instead_of_resolved_again(): void {
        $this->fakeHttp();

        $first = $this->makeInvoice();
        $second = $this->makeInvoice();

        $this->actingAs($this->admin)->post(route('invoices.peppol.send', $first))->assertRedirect();
        $this->actingAs($this->admin)->post(route('invoices.peppol.send', $second))->assertRedirect();

        $this->assertCount(1, $this->dns->queried, 'Die SML-Auflösung darf nicht bei jedem Versand laufen.');
        $this->assertSame(1, PeppolParticipantLookup::query()->count());
    }

    public function test_unregistered_recipient_blocks_the_dispatch(): void {
        $this->fakeHttp();
        // Kein SML-Eintrag ⇒ der Teilnehmer ist nicht in Peppol.
        $this->fakeDns([]);
        $invoice = $this->makeInvoice();

        $this->actingAs($this->admin)
            ->post(route('invoices.peppol.send', $invoice))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'nicht registriert'));

        $this->assertSame(0, DocumentDispatch::query()->where('channel', DocumentDispatch::CHANNEL_PEPPOL)->count());
    }

    public function test_recipient_without_the_document_format_blocks_the_dispatch(): void {
        // Registriert, nimmt aber nur Gutschriften an.
        $this->fakeHttp([DocumentTypeId::peppolBisBillingCreditNote()]);
        $invoice = $this->makeInvoice();

        $this->actingAs($this->admin)
            ->post(route('invoices.peppol.send', $invoice))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'nimmt das Format'));

        $this->assertSame(0, DocumentDispatch::query()->where('channel', DocumentDispatch::CHANNEL_PEPPOL)->count());
    }

    public function test_validator_finding_blocks_the_dispatch_and_names_the_subset(): void {
        $this->fakeHttp();
        $this->app->when(PeppolInvoiceDispatcher::class)
            ->needs(ValidatorInterface::class)
            ->give(fn (): ValidatorInterface => new class implements ValidatorInterface {
                public function isAvailable(): bool {
                    return true;
                }

                public function validate(string $xml): ValidationResult {
                    return new ValidationResult(false, false, [
                        new ValidationMessage(ValidationSeverity::ERROR, 'BR-16', 'Die Rechnung muss mindestens eine Position enthalten.'),
                    ], 'Peppol BIS Billing 3.0 (Kernregeln, Teilmenge)');
                }

                public function validateFile(string $filePath): ValidationResult {
                    return $this->validate('');
                }
            });

        $invoice = $this->makeInvoice();

        $this->actingAs($this->admin)
            ->post(route('invoices.peppol.send', $invoice))
            ->assertSessionHas('error', function (string $message): bool {
                return str_contains($message, 'BR-16')
                    // Teilmengen-Kennzeichnung muss im UI-Text stehen — ein
                    // grüner Lauf ist kein Konformitätsnachweis.
                    && str_contains($message, 'Teilmenge')
                    && str_contains($message, 'kein Vollkonformitätsnachweis');
            });

        $this->assertSame(0, DocumentDispatch::query()->where('channel', DocumentDispatch::CHANNEL_PEPPOL)->count());
    }

    public function test_transport_error_is_logged_as_a_failed_dispatch(): void {
        $this->fakeHttp(extra: [self::AP_BASE . '/outbox*' => FakePluginHttp::response(['error' => 'quota'], 402)]);
        $invoice = $this->makeInvoice();

        $this->actingAs($this->admin)
            ->post(route('invoices.peppol.send', $invoice))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'nicht angenommen'));

        $dispatch = DocumentDispatch::query()->where('channel', DocumentDispatch::CHANNEL_PEPPOL)->firstOrFail();
        $this->assertSame('failed', $dispatch->status, 'Ein abgelehnter Zustellversuch gehört trotzdem ins Protokoll.');
    }

    public function test_missing_credentials_yield_a_clear_message(): void {
        $this->fakeHttp();
        $this->configurePlugin(['base_url' => '', 'api_key' => '']);
        $invoice = $this->makeInvoice();

        $this->actingAs($this->admin)
            ->post(route('invoices.peppol.send', $invoice))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Peppol Access Point'));
    }

    public function test_customer_without_participant_id_blocks_the_dispatch(): void {
        $this->fakeHttp();
        $this->customer->forceFill(['peppol_participant_id' => null])->save();
        $invoice = $this->makeInvoice();

        $this->actingAs($this->admin)
            ->post(route('invoices.peppol.send', $invoice))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'keine Peppol-Teilnehmer-ID'));
    }

    public function test_draft_invoice_is_not_delivered(): void {
        $this->fakeHttp();
        $invoice = $this->makeInvoice(Invoice::STATUS_DRAFT);

        $this->actingAs($this->admin)
            ->post(route('invoices.peppol.send', $invoice))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'gestellte Rechnungen'));
    }

    public function test_lookup_is_scoped_to_the_organization(): void {
        $this->fakeHttp();
        $invoice = $this->makeInvoice();
        $this->actingAs($this->admin)->post(route('invoices.peppol.send', $invoice))->assertRedirect();

        $lookup = PeppolParticipantLookup::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame((int) $this->organization->id, (int) $lookup->organization_id);
        $this->assertTrue($lookup->registered);
    }

    public function test_participant_ids_are_parsed_and_rejected_by_the_toolkit_rules(): void {
        $this->assertNull(PeppolParticipantService::parse(''));
        $this->assertNull(PeppolParticipantService::parse('DE123456789'), 'Ohne ICD ist die Kennung unbrauchbar.');
        $this->assertNull(PeppolParticipantService::parse('99A0:DE123456789'));

        $parsed = PeppolParticipantService::parse('9930:DE123456789');
        $this->assertInstanceOf(ParticipantId::class, $parsed);
        $this->assertSame(ParticipantId::DEFAULT_SCHEME . '::9930:DE123456789', $parsed->canonical());
    }

    public function test_lookup_failure_is_not_stored_as_not_registered(): void {
        $this->fakeHttp();
        FakePluginHttp::fake([self::SMP_BASE . '/*' => FakePluginHttp::response([], 500)]);

        $participant = PeppolParticipantService::forCustomer($this->customer);
        $this->assertInstanceOf(ParticipantId::class, $participant);

        $this->expectException(RuntimeException::class);
        try {
            app(PeppolParticipantService::class)->lookup((int) $this->organization->id, $participant);
        } finally {
            $this->assertSame(0, PeppolParticipantLookup::query()->withoutGlobalScopes()->count());
        }
    }
}
