<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceDispatchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentDispatchStatus;
use App\Enums\Finance\{ExternalInvoiceFormat, ExternalPackageKind, ProviderSendState, TransferChannel, TransferStatus, TransferTarget};
use App\Enums\Invoicing\{EInvoiceRecipientProfile, ExternalDispatchRoute};
use App\Mail\ExternalInvoiceMail;
use App\Models\Customer\Customer;
use App\Models\Document\DocumentDispatch;
use App\Models\Finance\{BillingTransfer, BillingTransferPosition, ExternalInvoice, ExternalInvoicePackage};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Models\Time\TimeEntry;
use App\Modules\ModuleRegistry;
use App\Services\Finance\Contracts\ExternalInvoiceProvider;
use App\Services\Finance\ExternalInvoices\{ExternalInvoiceDispatchService, ExternalInvoicePackager, ExternalInvoiceService};
use DateTimeImmutable;
use ERechnungToolkit\Builders\ERechnungDocumentBuilder;
use ERechnungToolkit\Enums\PaymentMeansCode;
use ERechnungToolkit\Parsers\ERechnungParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeExternalInvoiceProvider;
use Tests\TestCase;

/**
 * Externe Rechnungshoheit mit Nachweisen und WorkDiary-Versand (Feature 196,
 * Nachtrag 07.11.2026): Abruf ohne lokale Rechnung, Zuordnung, Abgleich,
 * Sperrgründe, Versandfassung, Wiederholung, ungeklärter Ausgang, Nachsendung,
 * Rechte und Mandant.
 */
final class ExternalInvoiceDispatchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private Customer $customer;

    private BillingTransfer $transfer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        FakeExternalInvoiceProvider::reset();
        app(ModuleRegistry::class)->contribute(ExternalInvoiceProvider::class, FakeExternalInvoiceProvider::class);
        Mail::fake();
        Storage::fake('local');

        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Musterkunde GmbH',
            'email' => 'rechnung@kunde.example',
            'einvoice_dispatch_route' => ExternalDispatchRoute::WorkDiary,
            'einvoice_attach_activity_report' => true,
        ]);
        $this->setting('provider_send_disabled', true);
        $this->transfer = $this->transferWithTimes(2);
    }

    public function test_sync_assigns_positions_and_stores_the_original_without_a_local_invoice(): void {
        $invoices = Invoice::query()->count();

        $invoice = $this->sync();

        $this->assertSame('RE-2026-0042', $invoice->external_number);
        $this->assertSame(ExternalInvoiceFormat::Pdf, $invoice->original_format);
        $this->assertSame(hash('sha256', (string) FakeExternalInvoiceProvider::$original), $invoice->original_sha256);
        $this->assertCount(2, $invoice->positionLinks);
        $this->assertSame($invoices, Invoice::query()->count(), 'Keine lokale Scheinrechnung');
        $this->assertSame(FakeExternalInvoiceProvider::$original, app(ExternalInvoiceService::class)->originalContent($invoice));
    }

    public function test_pdf_invoice_goes_out_unchanged_with_the_activity_report_as_attachment(): void {
        $invoice = $this->sync();

        $dispatch = $this->send($invoice);

        $package = ExternalInvoicePackage::query()->sole();
        $this->assertSame(ExternalPackageKind::Initial, $package->kind);
        $this->assertSame(['invoice', 'evidence'], array_column($package->files, 'role'));
        $this->assertSame(hash('sha256', (string) FakeExternalInvoiceProvider::$original), $package->files[0]['sha256'], 'Original unverändert');
        $this->assertSame($invoice->original_sha256, $package->original_sha256);
        $this->assertSame(DocumentDispatchStatus::Queued, $dispatch->status);
        $this->assertSame($package->sha256, $dispatch->sha256);

        $documents = $invoice->supportingDocuments()->get();
        $this->assertCount(1, $documents);
        $this->assertNotNull($documents[0]->frozen_at);
        $this->assertSame('RE-2026-0042-A1', $documents[0]->reference);
        Mail::assertQueued(ExternalInvoiceMail::class, fn (ExternalInvoiceMail $mail): bool => $mail->package->is($package) && count($mail->attachments()) === 2 && $mail->hasTo('rechnung@kunde.example'));
    }

    public function test_retry_after_a_failure_sends_the_same_bytes(): void {
        $invoice = $this->sync();
        $first = $this->send($invoice);
        $first->forceFill(['status' => DocumentDispatchStatus::Failed])->save();

        $package = ExternalInvoicePackage::query()->sole();
        $second = app(ExternalInvoiceDispatchService::class)->retry($package, $this->accountant);

        $this->assertSame(1, ExternalInvoicePackage::query()->count());
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($first->sha256, $second->sha256);
        $files = app(ExternalInvoicePackager::class)->files($package);
        $this->assertSame(FakeExternalInvoiceProvider::$original, $files[0]['content']);
    }

    public function test_unclear_outcome_blocks_further_delivery_until_it_is_resolved(): void {
        $invoice = $this->sync();
        $dispatch = $this->send($invoice);
        $dispatch->forceFill(['created_at' => now()->subHour()])->save();
        $package = ExternalInvoicePackage::query()->sole();
        $service = app(ExternalInvoiceDispatchService::class);

        $this->assertTrue($service->isUnclear($package));
        $this->assertContains(__('finance.external_invoice.error.unclear'), $service->preflight($invoice->fresh(), DocumentDispatch::CHANNEL_EMAIL, ExternalPackageKind::Initial)['errors']);
        $this->expectBlocked(fn () => $service->retry($package, $this->accountant), 'finance.external_invoice.error.unclear');

        $service->resolve($package, false, $this->accountant);
        $this->assertSame(DocumentDispatchStatus::Failed, $dispatch->fresh()->status);
        $this->assertSame('not_delivered', $dispatch->fresh()->meta['resolution']);
        $retry = $service->retry($package, $this->accountant);
        $this->assertSame($dispatch->sha256, $retry->sha256);
    }

    public function test_concrete_blockers_before_anything_leaves_the_house(): void {
        $service = app(ExternalInvoiceDispatchService::class);

        FakeExternalInvoiceProvider::$status = 'draft';
        $draft = $this->sync();
        $this->assertNull($draft->original_sha256, 'Entwürfe liefern kein Original');
        $this->assertContains(__('finance.external_invoice.error.not_finalized'), $this->errors($draft));

        FakeExternalInvoiceProvider::$status = 'open';
        $invoice = app(ExternalInvoiceService::class)->refresh($draft, $this->accountant);
        $this->assertSame([], $this->errors($invoice));

        $this->customer->forceFill(['einvoice_dispatch_route' => ExternalDispatchRoute::Provider])->save();
        $this->assertContains(__('finance.external_invoice.error.route_provider'), $this->errors($invoice->fresh()));
        $this->customer->forceFill(['einvoice_dispatch_route' => ExternalDispatchRoute::WorkDiary])->save();

        $this->setting('provider_send_disabled', false);
        $this->assertContains(__('finance.external_invoice.error.provider_unknown'), $this->errors($invoice->fresh()));
        $this->assertSame([], $service->preflight($invoice->fresh(), DocumentDispatch::CHANNEL_EMAIL, ExternalPackageKind::Initial, ['provider_not_sent' => true])['errors']);

        FakeExternalInvoiceProvider::$sent = ProviderSendState::Sent;
        $invoice = app(ExternalInvoiceService::class)->refresh($invoice, $this->accountant);
        $this->assertContains(__('finance.external_invoice.error.provider_sent'), $this->errors($invoice));
        $this->assertSame([], $service->preflight($invoice, DocumentDispatch::CHANNEL_EMAIL, ExternalPackageKind::Initial, ['redelivery' => true])['errors']);

        $this->assertContains(__('finance.external_invoice.error.peppol_format'), $service->preflight($invoice, DocumentDispatch::CHANNEL_PEPPOL, ExternalPackageKind::Initial, ['redelivery' => true])['errors']);
        $this->assertSame(0, ExternalInvoicePackage::query()->count());
    }

    public function test_missing_sources_and_external_changes_block_until_reconciled(): void {
        FakeExternalInvoiceProvider::$invoiceId = null;
        $this->expectBlocked(fn () => app(ExternalInvoiceService::class)->syncForTransfer($this->transfer, $this->accountant), 'finance.external_invoice.error.no_invoice');

        FakeExternalInvoiceProvider::$invoiceId = 'ext-1';
        FakeExternalInvoiceProvider::$lines = [['name' => 'Wartung', 'quantity' => '3.000', 'unit_price' => '90.0000', 'net' => '270.00']];
        $invoice = $this->sync();
        $result = app(ExternalInvoiceService::class)->reconciliation($invoice);
        $this->assertCount(3, $result['deviations']);
        $this->assertContains(__('finance.external_invoice.error.deviation'), $this->errors($invoice));

        $this->actingAs($this->accountant)->post(route('finance.external-invoices.reconcile', $invoice), ['note' => 'Menge beim Kunden abgestimmt'])->assertRedirect();
        $this->assertNotContains(__('finance.external_invoice.error.deviation'), $this->errors($invoice->fresh()));

        FakeExternalInvoiceProvider::$lines = [['name' => 'Wartung', 'quantity' => '4.000', 'unit_price' => '90.0000', 'net' => '360.00']];
        $invoice = app(ExternalInvoiceService::class)->refresh($invoice->fresh(), $this->accountant);
        $this->assertContains(__('finance.external_invoice.error.deviation'), $this->errors($invoice), 'Neuer externer Stand hebt die Bestätigung auf');
    }

    public function test_split_and_bundled_transfers_are_assigned_per_position(): void {
        $service = app(ExternalInvoiceService::class);
        [$first, $second] = $this->transfer->positions()->get()->all();

        FakeExternalInvoiceProvider::$lines = [['name' => (string) $first->name, 'quantity' => '1.000', 'unit_price' => '90.0000', 'net' => '90.00']];
        $a = $service->link($this->transfer, 'ext-a', [$first->id], $this->accountant);
        $this->expectBlocked(fn () => $service->link($this->transfer, 'ext-b', [$first->id], $this->accountant), 'finance.external_invoice.error.position_taken');

        $other = $this->transferWithTimes(1);
        $b = $service->link($this->transfer, 'ext-b', [$second->id], $this->accountant);
        $service->link($other, 'ext-b', $other->positions()->pluck('id')->all(), $this->accountant);

        $this->assertSame([$first->id], $a->positionLinks()->pluck('billing_transfer_position_id')->all());
        $this->assertCount(2, $b->fresh()->positionLinks);
        $this->assertSame(2, $b->positionLinks()->distinct()->count('billing_transfer_id'));
        $this->assertSame([], $service->freePositionIds($this->transfer));

        $foreign = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $alien = $this->transferWithTimes(1, $foreign);
        $this->expectBlocked(fn () => $service->link($alien, 'ext-b', $alien->positions()->pluck('id')->all(), $this->accountant), 'finance.external_invoice.error.other_customer');
    }

    public function test_follow_up_delivery_carries_only_new_evidence(): void {
        $invoice = $this->sync();
        $this->send($invoice)->forceFill(['status' => DocumentDispatchStatus::Sent])->save();
        $service = app(ExternalInvoiceDispatchService::class);
        $this->assertContains(__('finance.external_invoice.error.already_delivered'), $this->errors($invoice->fresh()));
        $this->assertContains(__('finance.external_invoice.error.resend_nothing'), $service->preflight($invoice->fresh(), DocumentDispatch::CHANNEL_EMAIL, ExternalPackageKind::Resend)['errors']);

        $this->actingAs($this->accountant)->post(route('finance.external-invoices.documents.upload', $invoice), [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('abnahme.pdf', "%PDF-1.4\n%%EOF\n"),
            'description' => 'Abnahmeprotokoll',
        ])->assertRedirect();

        $service->dispatch($invoice->fresh(), DocumentDispatch::CHANNEL_EMAIL, ExternalPackageKind::Resend, ['to' => ['rechnung@kunde.example']], $this->accountant);
        $resend = ExternalInvoicePackage::query()->where('kind', ExternalPackageKind::Resend->value)->sole();
        $this->assertSame(['evidence'], array_column($resend->files, 'role'));
        $this->assertStringContainsString('abnahme', $resend->files[0]['name']);

        $this->customer->forceFill(['einvoice_recipient_profile' => EInvoiceRecipientProfile::PublicSector])->save();
        $this->assertContains(__('finance.external_invoice.error.resend_public_sector'), $service->preflight($invoice->fresh(), DocumentDispatch::CHANNEL_EMAIL, ExternalPackageKind::Resend)['errors']);
    }

    public function test_public_sector_recipient_needs_an_xrechnung(): void {
        $this->customer->forceFill(['einvoice_recipient_profile' => EInvoiceRecipientProfile::PublicSector])->save();

        $this->assertContains(__('finance.external_invoice.error.xml_only'), $this->errors($this->sync()));
    }

    public function test_xrechnung_is_supplemented_without_touching_the_business_content(): void {
        $this->customer->forceFill(['einvoice_recipient_profile' => EInvoiceRecipientProfile::PublicSector])->save();
        FakeExternalInvoiceProvider::$original = $this->ublInvoice();
        $invoice = $this->sync();
        $this->assertSame(ExternalInvoiceFormat::XRechnungUbl, $invoice->original_format);

        $this->send($invoice);

        $package = ExternalInvoicePackage::query()->sole();
        $this->assertCount(1, $package->files);
        $xml = app(ExternalInvoicePackager::class)->files($package)[0]['content'];
        $before = (new ERechnungParser)->parse((string) FakeExternalInvoiceProvider::$original);
        $after = (new ERechnungParser)->parse($xml);
        $this->assertSame($before->getId(), $after->getId());
        $this->assertSame($before->getPayableAmount()->getAmount(), $after->getPayableAmount()->getAmount());
        $this->assertCount(1, $after->getSupportingDocuments());
        $this->assertSame('RE-2026-0042-A1', $after->getSupportingDocuments()[0]->getReference());
        $this->assertSame('application/pdf', $after->getSupportingDocuments()[0]->getMimeCode());
    }

    public function test_zugferd_is_rewritten_with_supplemented_xml_and_supplement_files(): void {
        $generator = new \ERechnungToolkit\Generators\ZugferdPdfGenerator;
        if (! $generator->isAvailable() || ! (new \ERechnungToolkit\Generators\ZugferdPdfAugmenter)->isAvailable()) {
            $this->markTestSkipped('pdf-toolkit für ZUGFeRD nicht verfügbar');
        }
        $document = ERechnungDocumentBuilder::zugferd('RE-2026-0042', \ERechnungToolkit\Enums\ERechnungProfile::EN16931)
            ->withIssueDate(new DateTimeImmutable('2026-10-31'))
            ->withSeller('Dienstleister GmbH', 'DE123456789')
            ->withSellerAddress('Werkstraße 1', '12345', 'Berlin')
            ->withBuyer('Musterkunde GmbH')
            ->withBuyerAddress('Kundenweg 2', '20095', 'Hamburg')
            ->addLine('Wartung', 2, 90.00)
            ->build();
        FakeExternalInvoiceProvider::$original = (string) $generator->generate($document, '<h1>Rechnung RE-2026-0042</h1>', ['render_engine' => \PDFToolkit\Writers\ZugferdWriter::ENGINE_TCPDF]);
        $invoice = $this->sync();
        $this->assertSame(ExternalInvoiceFormat::Zugferd, $invoice->original_format);

        $this->send($invoice);

        $package = ExternalInvoicePackage::query()->sole();
        $pdf = app(ExternalInvoicePackager::class)->files($package)[0]['content'];
        $xml = (string) (new \ERechnungToolkit\Parsers\ZugferdPdfParser)->extractXmlFromString($pdf);
        $after = (new ERechnungParser)->parse($xml);
        $this->assertSame('RE-2026-0042', $after->getId());
        $this->assertSame('RE-2026-0042-A1', $after->getSupportingDocuments()[0]->getReference());
        $names = \CommonToolkit\Helper\FileSystem\File::withTemp($pdf, static fn (string $path): array => (new \ERechnungToolkit\Parsers\ZugferdPdfParser)->listAttachments($path), 'eid-test-', 'pdf');
        $this->assertContains($after->getSupportingDocuments()[0]->getFilename(), $names, 'Nachweis zusätzlich als Supplement');
    }

    public function test_signed_xml_is_never_modified(): void {
        $xml = $this->ublInvoice();
        FakeExternalInvoiceProvider::$original = (string) preg_replace('#</Invoice>\s*$#', '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo/></ds:Signature></Invoice>', $xml);

        $this->assertContains(__('finance.external_invoice.blocked.signed'), $this->errors($this->sync()));
    }

    public function test_cii_cannot_go_via_peppol(): void {
        FakeExternalInvoiceProvider::$original = $this->builder()->build()->toCiiXml();
        $invoice = $this->sync();
        $this->assertSame(ExternalInvoiceFormat::XRechnungCii, $invoice->original_format);

        $this->assertContains(__('finance.external_invoice.error.peppol_format'), app(ExternalInvoiceDispatchService::class)->preflight($invoice, DocumentDispatch::CHANNEL_PEPPOL, ExternalPackageKind::Initial)['errors']);
    }

    public function test_rights_and_tenant_boundaries(): void {
        $invoice = $this->sync();
        $employee = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($employee)->get(route('finance.external-invoices.show', $invoice))->assertOk();
        $this->actingAs($employee)->post(route('finance.external-invoices.send', $invoice), ['kind' => 'initial', 'dispatch_channel' => 'email', 'to' => 'x@kunde.example'])->assertForbidden();

        $otherOrg = Organization::factory()->create();
        $stranger = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        $this->actingAs($stranger)->get(route('finance.external-invoices.show', $invoice))->assertNotFound();
    }

    public function test_pages_render_for_the_transfer_and_the_invoice(): void {
        $invoice = $this->sync();
        $this->send($invoice);

        $this->actingAs($this->accountant)->get(route('finance.transfers.show', $this->transfer))
            ->assertOk()->assertSee(__('finance.external_invoice.transfer.heading'))->assertSee('RE-2026-0042');
        $this->actingAs($this->accountant)->get(route('finance.external-invoices.show', $invoice))
            ->assertOk()->assertSee(__('finance.external_invoice.packages.heading'))->assertSee('RE-2026-0042-A1');
        $this->actingAs($this->accountant)->get(route('finance.external-invoices.send.form', [$invoice, 'kind' => 'resend']))->assertOk();
        $this->actingAs($this->accountant)->get(route('finance.transfers.external-invoice.link.form', $this->transfer))->assertOk();
    }

    private function sync(): ExternalInvoice {
        return app(ExternalInvoiceService::class)->syncForTransfer($this->transfer, $this->accountant)->fresh(['positionLinks']);
    }

    private function send(ExternalInvoice $invoice): DocumentDispatch {
        return app(ExternalInvoiceDispatchService::class)->dispatch($invoice->fresh(), DocumentDispatch::CHANNEL_EMAIL, ExternalPackageKind::Initial, ['to' => ['rechnung@kunde.example']], $this->accountant);
    }

    /** @return list<string> */
    private function errors(ExternalInvoice $invoice): array {
        return app(ExternalInvoiceDispatchService::class)->preflight($invoice, DocumentDispatch::CHANNEL_EMAIL, ExternalPackageKind::Initial)['errors'];
    }

    private function expectBlocked(callable $call, string $key): void {
        try {
            $call();
            $this->fail('Sperre erwartet: ' . $key);
        } catch (ValidationException $e) {
            $this->assertStringContainsString((string) __($key, ['program' => 'Testfaktura']), implode(' ', $e->validator->errors()->all()));
        }
    }

    private function setting(string $key, mixed $value): void {
        $settings = (array) $this->organization->fresh()->settings;
        $settings['einvoice'][$key] = $value;
        $this->organization->forceFill(['settings' => $settings])->save();
    }

    private function transferWithTimes(int $count, ?Customer $customer = null): BillingTransfer {
        $customer ??= $this->customer;
        $transfer = BillingTransfer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'channel' => TransferChannel::Time,
            'target' => TransferTarget::File,
            'status' => TransferStatus::Transferred,
            'transferred_at' => now(),
            'position_count' => $count,
        ]);
        for ($i = 1; $i <= $count; $i++) {
            $entry = TimeEntry::factory()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $this->accountant->id,
                'date' => '2026-10-0' . $i,
                'started_at' => '2026-10-0' . $i . ' 08:00',
                'ended_at' => '2026-10-0' . $i . ' 09:00',
                'minutes' => 60,
                'description' => 'Wartung Serverraum ' . $i,
            ]);
            BillingTransferPosition::factory()->create([
                'organization_id' => $this->organization->id,
                'billing_transfer_id' => $transfer->id,
                'position' => $i,
                'source_kind' => BillingTransferPosition::KIND_TIME,
                'source_ids' => [$entry->id],
                'name' => 'Wartung',
                'quantity' => '1.000',
                'unit_price' => '90.0000',
                'amount' => '90.00',
            ]);
        }

        return $transfer;
    }

    private function builder(): ERechnungDocumentBuilder {
        return ERechnungDocumentBuilder::xrechnung('RE-2026-0042', '991-12345-73')
            ->withIssueDate(new DateTimeImmutable('2026-10-31'))
            ->withDueDate(new DateTimeImmutable('2026-11-30'))
            ->withSeller('Dienstleister GmbH', 'DE123456789')
            ->withSellerAddress('Werkstraße 1', '12345', 'Berlin')
            ->withSellerEndpoint('rechnung@dienstleister.example', 'EM')
            ->withSellerContact('Dana Dienst', '+49 30 1234', 'rechnung@dienstleister.example')
            ->withSellerBankAccount('DE89370400440532013000')
            ->withBuyer('Musterkunde GmbH', 'DE987654321')
            ->withBuyerAddress('Kundenweg 2', '20095', 'Hamburg')
            ->withBuyerEndpoint('rechnung@kunde.example', 'EM')
            ->withPaymentMeans(PaymentMeansCode::SEPA_CREDIT_TRANSFER)
            ->withInvoicingPeriod(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31'))
            ->addLine('Wartung', 2, 90.00);
    }

    private function ublInvoice(): string {
        return $this->builder()->build()->toUblXml();
    }
}
