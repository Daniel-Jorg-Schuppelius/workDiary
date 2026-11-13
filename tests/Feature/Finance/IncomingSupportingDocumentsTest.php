<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingSupportingDocumentsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingSupportingDocuments};
use DateTimeImmutable;
use ERechnungToolkit\Builders\ERechnungDocumentBuilder;
use ERechnungToolkit\Entities\SupportingDocument;
use ERechnungToolkit\Enums\{AttachmentMimeCode, PaymentMeansCode};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Eingebettete Unterlagen eingehender E-Rechnungen (Feature 196, MVP-1487):
 * Ablage als Begleitdatei, Prüfungen, Grenzen, Anzeige und Nachlauf.
 */
final class IncomingSupportingDocumentsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_embedded_documents_become_attachments_of_the_incoming_invoice(): void {
        $incoming = $this->receive([
            SupportingDocument::embedded('LN-1', "%PDF-1.4\n%%EOF\n", AttachmentMimeCode::PDF, 'nachweis-1.pdf', 'Leistungsnachweis'),
            SupportingDocument::embedded('LN-2', "datum;minuten\n2026-10-02;90\n", AttachmentMimeCode::CSV, 'nachweis-2.csv'),
            SupportingDocument::external('LN-3', 'https://rechnung.example.org/anlage/1', 'Große Anlage'),
        ]);

        $rows = $incoming->summary['supporting_documents'];
        $this->assertCount(3, $rows);
        $attachments = $incoming->attachments()->where('meta_type', IncomingSupportingDocuments::META_TYPE)->get();
        $this->assertCount(2, $attachments);
        $this->assertSame(['nachweis-1.pdf', 'nachweis-2.csv'], $attachments->pluck('original_name')->sort()->values()->all());
        $this->assertNull($rows[2]['attachment_id']);
        $this->assertSame('https://rechnung.example.org/anlage/1', $rows[2]['external_url']);
        $this->assertFalse($rows[0]['mime_mismatch']);
        $this->assertSame(['from' => '2026-09-01', 'to' => '2026-09-30'], $incoming->summary['invoicing_period']);
        $this->assertSame('SLA-2026-01', $incoming->summary['invoiced_object']);
    }

    public function test_declared_type_that_does_not_fit_the_content_is_flagged(): void {
        $png = "\x89PNG\r\n\x1a\n" . str_repeat("\0", 32);
        $incoming = $this->receive([SupportingDocument::embedded('LN-1', $png, AttachmentMimeCode::PDF, 'nachweis-1.pdf')]);

        $this->assertTrue($incoming->summary['supporting_documents'][0]['mime_mismatch']);
    }

    public function test_attachment_over_the_limit_is_noted_but_not_stored(): void {
        $settings = (array) $this->organization->fresh()->settings;
        $settings['einvoice']['incoming_attachment_limit_mb'] = 1;
        $this->organization->update(['settings' => $settings]);

        $incoming = $this->receive([SupportingDocument::embedded('LN-1', "%PDF-1.4\n" . str_repeat('A', 1100 * 1024), AttachmentMimeCode::PDF, 'gross.pdf')]);

        $this->assertSame('limit', $incoming->summary['supporting_documents'][0]['skipped']);
        $this->assertSame(0, $incoming->attachments()->count());
        $this->assertNotEmpty($incoming->summary['supporting_notices']);
    }

    public function test_show_page_lists_documents_without_fetching_links(): void {
        Http::preventStrayRequests();
        $incoming = $this->receive([
            SupportingDocument::embedded('LN-1', "%PDF-1.4\n%%EOF\n", AttachmentMimeCode::PDF, 'nachweis-1.pdf'),
            SupportingDocument::external('LN-3', 'https://rechnung.example.org/anlage/1', 'Große Anlage'),
        ]);

        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $incoming->document))
            ->assertOk()
            ->assertSee(__('invoicing.incoming_documents.heading'))
            ->assertSee('nachweis-1.pdf')
            ->assertSee('rechnung.example.org')
            ->assertSee(__('invoicing.incoming_documents.period'));
    }

    public function test_command_extracts_documents_of_existing_invoices_once(): void {
        $incoming = $this->receive([SupportingDocument::embedded('LN-1', "%PDF-1.4\n%%EOF\n", AttachmentMimeCode::PDF, 'nachweis-1.pdf')]);
        $incoming->attachments()->get()->each->delete();
        $summary = (array) $incoming->summary;
        unset($summary['supporting_documents'], $summary['supporting_notices'], $summary['pdf_attachments']);
        $incoming->forceFill(['summary' => $summary])->save();

        $this->artisan('incoming-invoices:extract-documents', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, $incoming->attachments()->count());

        $this->artisan('incoming-invoices:extract-documents')->assertSuccessful();
        $this->assertSame(1, $incoming->fresh()->attachments()->count());

        $this->artisan('incoming-invoices:extract-documents')->assertSuccessful();
        $this->assertSame(1, $incoming->fresh()->attachments()->count(), 'A second run leaves processed invoices alone');
    }

    /** @param list<SupportingDocument> $documents */
    private function receive(array $documents): IncomingEInvoice {
        $builder = ERechnungDocumentBuilder::xrechnung('LF-2026-' . random_int(1000, 9999), '991-12345-73')
            ->withIssueDate(new DateTimeImmutable('2026-10-01'))
            ->withDueDate(new DateTimeImmutable('2026-10-31'))
            ->withSeller('Lieferant GmbH', 'DE987654321')
            ->withSellerAddress('Lieferweg 2', '20095', 'Hamburg')
            ->withSellerEndpoint('rechnung@lieferant.example', 'EM')
            ->withSellerContact('Lia Lieferant', '+49 40 1234', 'rechnung@lieferant.example')
            ->withSellerBankAccount('DE89370400440532013000')
            ->withBuyer('WorkDiary Org', 'DE123456789')
            ->withBuyerAddress('Musterstraße 1', '12345', 'Berlin')
            ->withBuyerEndpoint('einkauf@workdiary.example', 'EM')
            ->withPaymentMeans(PaymentMeansCode::SEPA_CREDIT_TRANSFER)
            ->withInvoicingPeriod(new DateTimeImmutable('2026-09-01'), new DateTimeImmutable('2026-09-30'))
            ->withInvoicedObject('SLA-2026-01')
            ->addLine('Wartung', 1, 100.00);
        foreach ($documents as $document) {
            $builder->addSupportingDocument($document);
        }

        $result = app(IncomingEInvoiceService::class)->storeIncoming($this->admin, $builder->build()->toUblXml(), 'application/xml', null, 'upload', null, 'rechnung.xml');
        $this->assertSame('created', $result['status']);

        return $result['incoming']->fresh();
    }
}
