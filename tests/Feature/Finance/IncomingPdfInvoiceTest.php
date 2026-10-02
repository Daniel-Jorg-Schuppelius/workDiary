<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingPdfInvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Supplier\Supplier;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1066: Lieferantenrechnungen ohne E-Rechnungsdaten — Erkennung als Vorschlag, Mehrfach-Upload. */
class IncomingPdfInvoiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => 'DE123456789']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function pdf(string $bodyHtml): string {
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<!doctype html><html lang="de"><body>' . $bodyHtml . '</body></html>');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function test_plain_pdf_invoice_is_recognised_and_matched(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Malerbetrieb Pinsel', 'vat_id' => 'DE987654321']);
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'number' => 'P-2026-017']);

        $invoice = $this->pdf(<<<'HTML'
            <p>Rechnung an: Muster GmbH, USt-IdNr. DE123456789</p>
            <h1>Rechnung</h1>
            <p>Rechnungsnummer: MP-4711</p>
            <p>Rechnungsdatum: 14.09.2026</p>
            <p>Ihr Projekt: P-2026-017</p>
            <p>Nettobetrag 1.000,00 EUR</p>
            <p>Umsatzsteuer 19 % 190,00 EUR</p>
            <p>Gesamtbetrag 1.190,00 EUR</p>
            <p>Malerbetrieb Pinsel · USt-IdNr. DE987654321 · IBAN DE89 3704 0044 0532 0130 00</p>
            HTML);
        $terms = $this->pdf('<h1>Allgemeine Geschäftsbedingungen</h1><p>Es gilt deutsches Recht.</p>');

        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), ['files' => [
            UploadedFile::fake()->createWithContent('rechnung.pdf', $invoice),
            UploadedFile::fake()->createWithContent('agb.pdf', $terms),
        ]])->assertRedirect(route('finance.incoming-invoices.index'))->assertSessionHas('success');

        $incoming = IncomingEInvoice::query()->sole();
        $this->assertTrue((bool) data_get($incoming->summary, 'unstructured'));
        $this->assertSame('MP-4711', $incoming->invoice_number);
        $this->assertSame('DE987654321', $incoming->seller_vat_id, 'Die eigene USt-IdNr. ist nie die des Ausstellers.');
        $this->assertSame('1190.00', $incoming->amount_gross?->getAmount());
        $this->assertContains($supplier->id, array_column((array) data_get($incoming->summary, 'suggestions.suppliers'), 'id'));
        $this->assertContains($project->id, array_column((array) data_get($incoming->summary, 'suggestions.projects'), 'id'));
        $this->assertContains(__('Ohne E-Rechnungsdaten aus PDF bzw. Bild erkannt — alle Werte am Original prüfen.'), (array) data_get($incoming->summary, 'deviations'));

        $this->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()->assertSee('MP-4711');
    }

    public function test_single_unreadable_file_is_rejected(): void {
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), [
            'file' => UploadedFile::fake()->createWithContent('agb.pdf', $this->pdf('<p>Nur Text ohne Rechnung.</p>')),
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, IncomingEInvoice::query()->count());
    }
}
