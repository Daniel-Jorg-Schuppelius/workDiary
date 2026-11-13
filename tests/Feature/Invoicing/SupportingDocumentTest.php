<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportingDocumentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\{EInvoiceRecipientProfile, InvoiceDeliveryFormat, InvoiceStatus, SupportingDocumentKind};
use App\Mail\InvoiceMail;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{Invoice, InvoiceMailTemplate, InvoiceSupportingDocument};
use App\Models\Platform\User;
use App\Models\Project\{Project, Task};
use App\Models\Time\TimeEntry;
use App\Services\Invoicing\EInvoice\{SupportingDocumentService, XRechnungGenerator};
use App\Services\Invoicing\InvoiceIssueService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Rechnungsbegründende Unterlagen der E-Rechnung (Feature 196): Leistungsnachweis,
 * Hochladen, Ausstellung mit Einfrieren, Inhalt der XRechnung, Preflight und Versand.
 */
class SupportingDocumentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private static int $invoiceNo = 0;

    private User $admin;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'WorkDiary Org']);
        $this->organization->update(['settings' => ['einvoice' => [
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
            'activity_report_include_signed_timesheets' => false,
        ]]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id, 'name' => 'Erika Mustermann']);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Stadtverwaltung Musterhausen',
            'currency' => 'EUR',
            'email' => 'rechnung@musterhausen.example',
            'address_street' => 'Rathausplatz 1',
            'address_zip' => '54321',
            'address_city' => 'Musterhausen',
            'country' => 'DE',
            'buyer_reference' => '991-12345-73',
            'created_by' => $this->admin->id,
        ]);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
    }

    public function test_activity_report_is_generated_from_linked_time_entries(): void {
        $invoice = $this->draftWithTimes();

        $this->actingAs($this->admin)->post(route('invoices.supporting-documents.generate', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));

        $documents = $invoice->supportingDocuments()->get();
        $this->assertCount(1, $documents);
        $report = $documents->first();
        $this->assertSame(SupportingDocumentKind::ActivityReport, $report->kind);
        $this->assertSame('nachweis-1-leistungsnachweis.pdf', $report->filename);
        $this->assertSame('application/pdf', $report->mime);
        $this->assertSame($invoice->number . '-A1', $report->reference);
        $content = app(SupportingDocumentService::class)->content($report);
        $this->assertNotNull($content);
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertSame(hash('sha256', $content), $report->sha256);
        $this->assertNull($report->frozen_at);
    }

    public function test_invoice_without_times_creates_no_report(): void {
        $invoice = $this->draft();

        $this->actingAs($this->admin)->post(route('invoices.supporting-documents.generate', $invoice))
            ->assertSessionHas('status', __('invoicing.supporting_documents.status.generated_none'));

        $this->assertSame(0, $invoice->supportingDocuments()->count());
    }

    public function test_csv_option_adds_the_times_as_table(): void {
        $this->setEinvoiceSetting('activity_report_csv', true);
        $invoice = $this->draftWithTimes();

        app(SupportingDocumentService::class)->generateActivityReport($invoice, $this->admin);

        $csv = $invoice->supportingDocuments()->get()->firstWhere('mime', 'text/csv');
        $this->assertNotNull($csv);
        $this->assertSame('nachweis-2-zeiten.csv', $csv->filename);
        $content = (string) app(SupportingDocumentService::class)->content($csv);
        $this->assertStringContainsString('Wartung Server', $content);
        $this->assertStringContainsString('Erika Mustermann', $content);
    }

    public function test_person_display_initials_hides_full_names(): void {
        $this->setEinvoiceSetting('activity_report_person_display', 'initials');
        $this->setEinvoiceSetting('activity_report_csv', true);
        $invoice = $this->draftWithTimes();

        app(SupportingDocumentService::class)->generateActivityReport($invoice, $this->admin);

        $csv = (string) app(SupportingDocumentService::class)->content($invoice->supportingDocuments()->get()->firstWhere('mime', 'text/csv'));
        $this->assertStringContainsString('E.M.', $csv);
        $this->assertStringNotContainsString('Erika Mustermann', $csv);
    }

    public function test_upload_accepts_pdf_and_rejects_other_formats(): void {
        $invoice = $this->draft();

        $this->actingAs($this->admin)->post(route('invoices.supporting-documents.upload', $invoice), [
            'file' => UploadedFile::fake()->createWithContent('Stundenzettel Oktober.pdf', "%PDF-1.4\n%%EOF\n"),
            'description' => 'Stundenzettel Oktober',
        ])->assertRedirect(route('invoices.show', $invoice));

        $document = $invoice->supportingDocuments()->firstOrFail();
        $this->assertSame('nachweis-1-stundenzettel-oktober.pdf', $document->filename);
        $this->assertSame(SupportingDocumentKind::Custom, $document->kind);

        $this->actingAs($this->admin)->post(route('invoices.supporting-documents.upload', $invoice), [
            'file' => UploadedFile::fake()->createWithContent('bild.png', "GIF89a\x01\x00\x01\x00"),
        ])->assertSessionHasErrors('file');
        $this->assertSame(1, $invoice->supportingDocuments()->count());
    }

    public function test_link_order_and_removal_in_draft(): void {
        $invoice = $this->draft();
        $service = app(SupportingDocumentService::class);
        $service->addUpload($invoice, UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF\n"), null, $this->admin);

        $this->actingAs($this->admin)->post(route('invoices.supporting-documents.link', $invoice), [
            'url' => 'https://rechnung.example.org/anlage/1',
            'description' => 'Große Anlage',
        ])->assertRedirect(route('invoices.show', $invoice));

        $link = $invoice->supportingDocuments()->get()->last();
        $this->assertFalse($link->is_embedded);
        $this->assertSame(2, $link->position);

        $this->actingAs($this->admin)->post(route('invoices.supporting-documents.move', [$invoice, $link]), ['direction' => 'up'])->assertRedirect();
        $this->assertSame([2, 1], $invoice->supportingDocuments()->get()->sortBy('id')->pluck('position')->values()->all());
        $this->assertSame('nachweis-2-a.pdf', $invoice->supportingDocuments()->get()->firstWhere('is_embedded', true)->filename);

        $this->actingAs($this->admin)->delete(route('invoices.supporting-documents.destroy', [$invoice, $link]))->assertRedirect();
        $remaining = $invoice->supportingDocuments()->get();
        $this->assertCount(1, $remaining);
        $this->assertSame(1, $remaining->first()->position);
        $this->assertSame('nachweis-1-a.pdf', $remaining->first()->filename);
    }

    public function test_a_document_belongs_to_exactly_one_carrier(): void {
        $document = app(SupportingDocumentService::class)->addUpload($this->draft(), UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF\n"), null, $this->admin);

        foreach ([['invoice_id' => null], ['external_invoice_id' => 999999]] as $carrier) {
            try {
                $document->fresh()->forceFill($carrier)->save();
                $this->fail('Ohne oder mit zwei Trägern hätte das Speichern scheitern müssen.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('genau einer Rechnung', $e->getMessage());
            }
        }
        $this->assertNotNull($document->fresh()->invoice_id);
    }

    public function test_issue_freezes_documents_with_final_reference(): void {
        $invoice = $this->draftWithTimes();
        app(SupportingDocumentService::class)->generateActivityReport($invoice, $this->admin);

        app(InvoiceIssueService::class)->issue($invoice);

        $document = $invoice->supportingDocuments()->firstOrFail();
        $this->assertNotNull($document->frozen_at);
        $this->assertSame($invoice->fresh()->number . '-A1', $document->reference);

        $this->expectException(\RuntimeException::class);
        $document->update(['description' => 'geändert']);
    }

    public function test_frozen_documents_cannot_be_changed_through_the_routes(): void {
        $invoice = $this->draftWithTimes();
        app(SupportingDocumentService::class)->generateActivityReport($invoice, $this->admin);
        app(InvoiceIssueService::class)->issue($invoice);
        $document = $invoice->supportingDocuments()->firstOrFail();

        // Die Rechnung ist ausgestellt: bearbeiten darf sie niemand mehr, also auch nicht ihre Unterlagen.
        $this->actingAs($this->admin)->delete(route('invoices.supporting-documents.destroy', [$invoice, $document]))
            ->assertForbidden();
        $this->actingAs($this->admin)->delete(route('attachments.destroy', $document->attachment))->assertForbidden();
        $this->assertSame(1, $invoice->supportingDocuments()->count());
    }

    public function test_public_sector_customer_gets_the_report_automatically_on_issue(): void {
        $this->customer->update(['einvoice_recipient_profile' => EInvoiceRecipientProfile::PublicSector]);
        $invoice = $this->draftWithTimes();

        app(InvoiceIssueService::class)->issue($invoice);

        $document = $invoice->supportingDocuments()->firstOrFail();
        $this->assertSame(SupportingDocumentKind::ActivityReport, $document->kind);
        $this->assertNotNull($document->frozen_at);
    }

    public function test_standard_customer_gets_no_automatic_report(): void {
        $invoice = $this->draftWithTimes();

        app(InvoiceIssueService::class)->issue($invoice);

        $this->assertSame(0, $invoice->supportingDocuments()->count());
    }

    public function test_xrechnung_carries_documents_periods_and_line_details(): void {
        $invoice = $this->draftWithTimes(withTask: true);
        app(SupportingDocumentService::class)->generateActivityReport($invoice, $this->admin);
        app(InvoiceIssueService::class)->issue($invoice);
        $invoice = $invoice->fresh(['items', 'customer']);

        $x = $this->ubl(app(XRechnungGenerator::class)->generate($invoice));
        $document = $invoice->supportingDocuments()->firstOrFail();

        $ref = "/inv:Invoice/cac:AdditionalDocumentReference[cbc:ID='" . $document->reference . "']";
        $this->assertSame('nachweis-1-leistungsnachweis.pdf', $x->evaluate("string({$ref}/cac:Attachment/cbc:EmbeddedDocumentBinaryObject/@filename)"));
        $this->assertSame('application/pdf', $x->evaluate("string({$ref}/cac:Attachment/cbc:EmbeddedDocumentBinaryObject/@mimeCode)"));
        $this->assertSame(app(SupportingDocumentService::class)->content($document), base64_decode((string) $x->evaluate("string({$ref}/cac:Attachment/cbc:EmbeddedDocumentBinaryObject)"), true));
        $this->assertSame('2026-10-02', $x->evaluate('string(/inv:Invoice/cac:InvoicePeriod/cbc:StartDate)'));
        $this->assertSame('2026-10-09', $x->evaluate('string(/inv:Invoice/cac:InvoicePeriod/cbc:EndDate)'));
        $this->assertSame('2026-10-02', $x->evaluate('string(/inv:Invoice/cac:InvoiceLine/cac:InvoicePeriod/cbc:StartDate)'));
        $this->assertStringContainsString('2 ', $x->evaluate('string(/inv:Invoice/cac:InvoiceLine/cbc:Note)'));
        $this->assertSame('TICKET-4711', $x->evaluate("string(/inv:Invoice/cac:InvoiceLine/cac:DocumentReference[cbc:DocumentTypeCode='130']/cbc:ID)"));
        $this->assertStringContainsString($document->reference, $x->evaluate('string(/inv:Invoice/cbc:Note[contains(., "' . $document->reference . '")])'));
    }

    public function test_kosit_accepts_the_xrechnung_with_documents(): void {
        if (! (new \ERechnungToolkit\Validators\KositValidator)->isAvailable()) {
            $this->markTestSkipped('KoSIT-Validator nicht verfügbar.');
        }
        $invoice = $this->draftWithTimes(withTask: true);
        $service = app(SupportingDocumentService::class);
        $service->generateActivityReport($invoice, $this->admin);
        $service->addLink($invoice, 'https://rechnung.example.org/anlage/1', 'Große Anlage', $this->admin);
        app(InvoiceIssueService::class)->issue($invoice);

        $report = app(\App\Services\Invoicing\EInvoice\EInvoiceValidationService::class)->validate($invoice->fresh(['items', 'customer']));

        $this->assertSame([], $report['preflight_errors']);
        $this->assertSame([], $report['schema_errors']);
        $this->assertTrue($report['kosit_valid'], implode('; ', [...$report['kosit_errors'], ...$report['kosit_warnings']]));
    }

    public function test_single_service_date_becomes_the_delivery_date(): void {
        $invoice = $this->draft(serviceDate: '2026-10-05');
        app(InvoiceIssueService::class)->issue($invoice);

        $x = $this->ubl(app(XRechnungGenerator::class)->generate($invoice->fresh(['items', 'customer'])));

        $this->assertSame('2026-10-05', $x->evaluate('string(/inv:Invoice/cac:Delivery/cbc:ActualDeliveryDate)'));
        $this->assertSame(0.0, $x->evaluate('count(/inv:Invoice/cac:InvoicePeriod)'));
    }

    public function test_missing_service_date_falls_back_to_invoice_date_with_warning(): void {
        $invoice = $this->draft();
        app(InvoiceIssueService::class)->issue($invoice);
        $invoice = $invoice->fresh(['items', 'customer']);

        $preflight = app(XRechnungGenerator::class)->preflight($invoice);
        $this->assertContains(__('invoicing.einvoice.warning.service_date_fallback'), $preflight['warnings']);

        $x = $this->ubl(app(XRechnungGenerator::class)->generate($invoice));
        $this->assertSame($invoice->issued_on->toDateString(), $x->evaluate('string(/inv:Invoice/cac:Delivery/cbc:ActualDeliveryDate)'));
    }

    public function test_preflight_blocks_documents_over_the_recipient_limit(): void {
        $this->customer->update(['einvoice_attachment_limit_mb' => 1]);
        $invoice = $this->draft();
        app(SupportingDocumentService::class)->addUpload($invoice, UploadedFile::fake()->createWithContent('gross.pdf', "%PDF-1.4\n" . str_repeat('A', 900 * 1024)), null, $this->admin);
        app(InvoiceIssueService::class)->issue($invoice);

        $preflight = app(XRechnungGenerator::class)->preflight($invoice->fresh(['items', 'customer']));

        $this->assertNotEmpty(array_filter($preflight['errors'], static fn (string $e): bool => str_contains($e, '1 MB')));
    }

    public function test_public_sector_send_rejects_pdf_formats(): void {
        Mail::fake();
        $this->customer->update(['einvoice_recipient_profile' => EInvoiceRecipientProfile::PublicSector]);
        $invoice = $this->draftWithTimes();

        $this->actingAs($this->admin)->post(route('invoices.send', $invoice), $this->sendPayload(InvoiceDeliveryFormat::PdfAndXRechnung))
            ->assertSessionHas('error', __('invoicing.recipient_profile.error.xml_only'));
        Mail::assertNothingQueued();
    }

    public function test_electronic_send_issues_first_so_the_xml_carries_the_report(): void {
        Mail::fake();
        $this->customer->update(['einvoice_recipient_profile' => EInvoiceRecipientProfile::PublicSector]);
        $invoice = $this->draftWithTimes();

        $this->actingAs($this->admin)->post(route('invoices.send', $invoice), $this->sendPayload(InvoiceDeliveryFormat::XRechnung))
            ->assertRedirect(route('invoices.show', $invoice));

        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
        $document = $invoice->supportingDocuments()->firstOrFail();
        $this->assertNotNull($document->frozen_at);
        Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $mail) use ($document): bool {
            $attachments = $mail->attachments();
            if (count($attachments) !== 1) {
                return false;
            }
            $xml = '';
            $attachments[0]->attachWith(static fn (): string => '', static function (\Closure $data) use (&$xml): string {
                $xml = (string) $data();

                return '';
            });

            return str_contains($xml, 'filename="' . $document->filename . '"');
        });
    }

    public function test_pdf_mail_attaches_frozen_documents_as_files(): void {
        Mail::fake();
        $invoice = $this->draftWithTimes();
        app(SupportingDocumentService::class)->generateActivityReport($invoice, $this->admin);

        $this->actingAs($this->admin)->post(route('invoices.send', $invoice), $this->sendPayload(InvoiceDeliveryFormat::Pdf))
            ->assertRedirect();

        Mail::assertQueued(InvoiceMail::class, static function (InvoiceMail $mail): bool {
            $names = array_map(static fn ($a): string => (string) $a->as, $mail->attachments());

            return in_array('nachweis-1-leistungsnachweis.pdf', $names, true);
        });
    }

    public function test_portal_offers_frozen_documents_only(): void {
        // Alles vor dem ersten Request anlegen: ein Portal-Request bindet den Organisationskontext neu.
        $service = app(SupportingDocumentService::class);
        $draft = $this->draftWithTimes();
        $service->generateActivityReport($draft, $this->admin);
        $issued = $this->draftWithTimes();
        $service->generateActivityReport($issued, $this->admin);
        app(InvoiceIssueService::class)->issue($issued);
        $draftDocument = $draft->supportingDocuments()->firstOrFail();
        $issuedDocument = $issued->supportingDocuments()->firstOrFail();
        $this->allowPortal($this->customer, ['invoices']);
        $portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create();

        $this->actingAs($portalUser, 'customer')
            ->get(route('customer.invoices.supporting-documents.download', [$draft, $draftDocument]))
            ->assertNotFound();
        $response = $this->actingAs($portalUser, 'customer')
            ->get(route('customer.invoices.supporting-documents.download', [$issued, $issuedDocument]));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_deleting_a_draft_removes_the_files(): void {
        $invoice = $this->draft();
        $document = app(SupportingDocumentService::class)->addUpload($invoice, UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF\n"), null, $this->admin);
        $path = (string) $document->attachment?->path;
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($path));

        $this->actingAs($this->admin)->delete(route('invoices.destroy', $invoice))->assertRedirect();

        $this->assertFalse(\Illuminate\Support\Facades\Storage::disk('local')->exists($path));
        $this->assertSame(0, InvoiceSupportingDocument::query()->count());
    }

    public function test_show_page_renders_the_card(): void {
        $invoice = $this->draftWithTimes();
        app(SupportingDocumentService::class)->generateActivityReport($invoice, $this->admin);

        $this->actingAs($this->admin)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee(__('invoicing.supporting_documents.heading'))
            ->assertSee('nachweis-1-leistungsnachweis.pdf');
    }

    public function test_transfer_offers_an_activity_report_of_its_times(): void {
        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => '2026-10-02',
            'started_at' => '2026-10-02 09:00',
            'ended_at' => '2026-10-02 10:30',
            'minutes' => 90,
            'description' => 'Wartung Server',
        ]);
        $transfer = \App\Models\Finance\BillingTransfer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'channel' => \App\Enums\Finance\TransferChannel::Time,
            'target' => \App\Enums\Finance\TransferTarget::Lexoffice,
            'status' => \App\Enums\Finance\TransferStatus::Transferred,
        ]);
        \App\Models\Finance\BillingTransferItem::create([
            'billing_transfer_id' => $transfer->id,
            'source_type' => \App\Support\MorphMap::alias(TimeEntry::class),
            'source_id' => $entry->id,
            'quantity' => '1.50',
            'amount' => '150.00',
        ]);

        $response = $this->actingAs($this->admin)->get(route('finance.transfers.activity-report', $transfer));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->actingAs($this->admin)->get(route('finance.transfers.show', $transfer))
            ->assertSee(__('finance.action.activity_report'));
    }

    public function test_program_issued_invoice_notes_that_documents_stay_in_workdiary(): void {
        $invoice = $this->draftWithTimes();
        app(SupportingDocumentService::class)->generateActivityReport($invoice, $this->admin);
        $this->app->instance(\App\Services\Numbering\NumberAuthority::class, new class extends \App\Services\Numbering\NumberAuthority {
            public function sourceFor(\App\Models\Platform\Organization|int|null $organization, \App\Enums\Numbering\NumberScope $scope): string {
                return 'lexoffice';
            }

            public function sourceLabel(string $source): string {
                return 'Lexware Office';
            }
        });

        app(SupportingDocumentService::class)->prepareForIssue($invoice->fresh());

        $document = $invoice->supportingDocuments()->firstOrFail();
        $this->assertStringContainsString('Lexware Office', (string) $document->transfer_note);
        $item = \App\Models\Integration\IntegrationInboxItem::query()->where('dedupe_key', 'supporting-documents:' . $invoice->id)->firstOrFail();
        $this->assertSame(\App\Models\Integration\IntegrationInboxItem::REASON_SUPPORTING_DOCUMENTS, $item->remote_snapshot['reason']);
        $this->assertSame(__('invoicing.supporting_documents.inbox_title', ['plugin' => 'Lexware Office']), $item->displayTitleText('Lexware Office'));
    }

    private function setEinvoiceSetting(string $key, mixed $value): void {
        $settings = (array) $this->organization->fresh()->settings;
        $settings['einvoice'][$key] = $value;
        $this->organization->update(['settings' => $settings]);
    }

    /** @return array<string, mixed> */
    private function sendPayload(InvoiceDeliveryFormat $format): array {
        $template = InvoiceMailTemplate::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'subject' => 'Rechnung {{invoice_number}}',
            'body_html' => '<p>Anbei die Rechnung.</p>',
            'body_text' => 'Anbei die Rechnung.',
            'document_kind' => 'invoice',
            'is_default' => true,
        ]);

        return ['delivery_format' => $format->value, 'template_id' => $template->id, 'to' => ['rechnung@musterhausen.example']];
    }

    private function draft(?string $serviceDate = null): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-' . str_pad((string) ++self::$invoiceNo, 4, '0', STR_PAD_LEFT),
            'status' => InvoiceStatus::Draft,
            'issued_on' => '2026-10-15',
            'due_on' => '2026-10-29',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Wartung Server',
            'quantity' => '2.50',
            'unit' => 'Std.',
            'unit_price' => '100.00',
            'position' => 1,
            'service_date' => $serviceDate,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh(['items', 'customer']);
    }

    private function draftWithTimes(bool $withTask = false): Invoice {
        $invoice = $this->draft();
        $item = $invoice->items->first();
        $task = null;
        if ($withTask) {
            $task = Task::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->admin->id, 'organization_id' => $this->organization->id]);
            ExternalReference::query()->create([
                'organization_id' => $this->organization->id,
                'plugin_id' => 'jira',
                'external_type' => 'issue',
                'referenceable_type' => $task->getMorphClass(),
                'referenceable_id' => $task->id,
                'external_id' => '10042',
                'external_number' => 'TICKET-4711',
            ]);
        }
        foreach ([['2026-10-02 09:00', '2026-10-02 10:30', 90], ['2026-10-09 13:00', '2026-10-09 14:00', 60]] as [$from, $to, $minutes]) {
            $entry = TimeEntry::factory()->create([
                'organization_id' => $this->organization->id,
                'project_id' => $this->project->id,
                'user_id' => $this->admin->id,
                'task_id' => $task?->id,
                'date' => substr($from, 0, 10),
                'started_at' => $from,
                'ended_at' => $to,
                'minutes' => $minutes,
                'description' => 'Wartung Server',
            ]);
            $item->timeEntries()->attach($entry->id, ['organization_id' => $this->organization->id]);
        }
        $item->update(['service_from' => '2026-10-02', 'service_to' => '2026-10-09']);

        return $invoice->fresh(['items', 'customer']);
    }

    private function ubl(string $xml): DOMXPath {
        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $x = new DOMXPath($dom);
        $x->registerNamespace('inv', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $x->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $x->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        return $x;
    }
}
