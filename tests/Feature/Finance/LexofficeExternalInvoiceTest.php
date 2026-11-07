<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeExternalInvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{BillingMode, ExternalInvoiceFormat, ExternalInvoiceStatus, TransferChannel, TransferTarget};
use App\Enums\Invoicing\{EInvoiceRecipientProfile, ExternalDispatchRoute};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Mail\ExternalInvoiceMail;
use App\Models\Customer\Customer;
use App\Models\Finance\{BillingTransfer, ExternalInvoice, ExternalInvoicePackage};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Services\Finance\BillingTransferService;
use App\Services\Finance\ExternalInvoices\ExternalInvoicePackager;
use DateTimeImmutable;
use ERechnungToolkit\Builders\ERechnungDocumentBuilder;
use ERechnungToolkit\Enums\PaymentMeansCode;
use ERechnungToolkit\Parsers\ERechnungParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Storage};
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Pilotweg Lexware Office (Feature 196, Nachtrag 07.11.2026): Lexware Office
 * führt und finalisiert, WorkDiary ruft die XRechnung über `/file` mit
 * `Accept: application/xml` ab, ergänzt den Leistungsnachweis als BG-24 und
 * stellt zu – ohne lokale Rechnung und ohne Nummernvergabe.
 */
final class LexofficeExternalInvoiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private User $accountant;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Mail::fake();
        Storage::fake('local');

        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->accountant);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Landesamt Muster',
            'email' => 'rechnung@landesamt.example',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'billing_mode' => BillingMode::Lexoffice,
            'einvoice_recipient_profile' => EInvoiceRecipientProfile::PublicSector,
            'einvoice_dispatch_route' => ExternalDispatchRoute::WorkDiary,
            'created_by' => $this->accountant->id,
        ]);
        $settings = (array) $this->organization->fresh()->settings;
        $settings['einvoice']['provider_send_disabled'] = true;
        $this->organization->forceFill(['settings' => $settings])->save();
        $this->pluginSecret('lexoffice', ['base_url' => 'https://api.lexware.io/v1', 'api_key' => 'test-key']);
    }

    public function test_finalized_lexoffice_xrechnung_is_delivered_with_the_activity_report(): void {
        $transfer = $this->transferredToLexoffice();
        $xml = $this->xrechnung();
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices/lex-invoice-1/file' => FakePluginHttp::response($xml, 200, ['Content-Type' => 'application/xml']),
            'https://api.lexware.io/v1/invoices/lex-invoice-1' => FakePluginHttp::response([
                'id' => 'lex-invoice-1',
                'voucherStatus' => 'open',
                'voucherNumber' => 'RE-2030-0007',
                'voucherDate' => '2030-04-30T00:00:00.000+02:00',
                'electronicDocumentProfile' => 'XRechnung',
                'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => 180.0, 'totalGrossAmount' => 214.2],
                'lineItems' => [['type' => 'custom', 'name' => 'Beratung', 'quantity' => 2, 'unitName' => 'Std.', 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 90, 'taxRatePercentage' => 19], 'lineItemAmount' => 180]],
            ]),
        ]);
        $invoices = Invoice::query()->count();

        $this->post(route('finance.transfers.external-invoice', $transfer))->assertSessionHasNoErrors()->assertRedirect();

        $invoice = ExternalInvoice::query()->sole();
        $this->assertSame('RE-2030-0007', $invoice->external_number);
        $this->assertSame(ExternalInvoiceStatus::Open, $invoice->status);
        $this->assertSame(ExternalInvoiceFormat::XRechnungUbl, $invoice->original_format);
        $this->assertSame(hash('sha256', $xml), $invoice->original_sha256);
        $fake->assertSent(fn (RequestInterface $request): bool => str_ends_with((string) $request->getUri(), '/invoices/lex-invoice-1/file') && $request->getHeaderLine('Accept') === 'application/xml');

        $this->post(route('finance.external-invoices.send', $invoice), [
            'kind' => 'initial',
            'dispatch_channel' => 'email',
            'to' => 'rechnung@landesamt.example',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $package = ExternalInvoicePackage::query()->sole();
        $sent = app(ExternalInvoicePackager::class)->files($package);
        $this->assertCount(1, $sent, 'Behörden erhalten genau eine XML');
        $document = (new ERechnungParser)->parse($sent[0]['content']);
        $this->assertSame('RE-2030-0007', $document->getId());
        $this->assertSame('991-12345-67', $document->getBuyerReference());
        $this->assertSame('RE-2030-0007-A1', $document->getSupportingDocuments()[0]->getReference());
        $this->assertSame($invoices, Invoice::query()->count(), 'Keine lokale Rechnung, keine Nummer');
        Mail::assertQueued(ExternalInvoiceMail::class, fn (ExternalInvoiceMail $mail): bool => $mail->hasTo('rechnung@landesamt.example'));
    }

    public function test_draft_at_lexoffice_has_no_original_yet(): void {
        $transfer = $this->transferredToLexoffice();
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices/lex-invoice-1' => FakePluginHttp::response(['id' => 'lex-invoice-1', 'voucherStatus' => 'draft', 'voucherDate' => '2030-04-30T00:00:00.000+02:00']),
        ]);

        $this->post(route('finance.transfers.external-invoice', $transfer))->assertRedirect();

        $invoice = ExternalInvoice::query()->sole();
        $this->assertSame(ExternalInvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->original_sha256);
        $fake->assertNotSent(fn (RequestInterface $request): bool => str_ends_with((string) $request->getUri(), '/file'));
    }

    private function transferredToLexoffice(): BillingTransfer {
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Betreuung',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->accountant->id,
        ]);
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->accountant->id,
            'date' => '2030-04-01',
            'started_at' => '2030-04-01 08:00:00',
            'ended_at' => '2030-04-01 10:00:00',
            'minutes' => 120,
            'description' => 'Fachverfahren aktualisiert',
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'hourly_rate' => '90.00',
        ]);
        $service = app(BillingTransferService::class);
        $transfer = $service->createDraft($this->customer, TransferChannel::Time, TransferTarget::Lexoffice, ['from' => '2030-04-01', 'to' => '2030-04-30'], null, $this->accountant);
        $service->confirm($transfer, $this->accountant);
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/contacts*' => FakePluginHttp::response(['content' => [['id' => 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d']]], 200),
            'https://api.lexware.io/v1/invoices*' => FakePluginHttp::response(['id' => 'lex-invoice-1'], 201),
        ]);
        $this->post(route('finance.transfers.execute', $transfer->fresh()))->assertSessionHasNoErrors();

        return $transfer->fresh();
    }

    private function xrechnung(): string {
        return ERechnungDocumentBuilder::xrechnung('RE-2030-0007', '991-12345-67')
            ->withIssueDate(new DateTimeImmutable('2030-04-30'))
            ->withDueDate(new DateTimeImmutable('2030-05-30'))
            ->withSeller('Dienstleister GmbH', 'DE123456789')
            ->withSellerAddress('Werkstraße 1', '12345', 'Berlin')
            ->withSellerEndpoint('rechnung@dienstleister.example', 'EM')
            ->withSellerContact('Dana Dienst', '+49 30 1234', 'rechnung@dienstleister.example')
            ->withSellerBankAccount('DE89370400440532013000')
            ->withBuyer('Landesamt Muster', null)
            ->withBuyerAddress('Amtsplatz 1', '30159', 'Hannover')
            ->withBuyerEndpoint('rechnung@landesamt.example', 'EM')
            ->withPaymentMeans(PaymentMeansCode::SEPA_CREDIT_TRANSFER)
            ->addLine('Beratung', 2, 90.00)
            ->build()->toUblXml();
    }
}
