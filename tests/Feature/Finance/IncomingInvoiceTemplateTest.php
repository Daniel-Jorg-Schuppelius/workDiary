<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTemplateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Models\Document\{Document, DocumentVersion};
use App\Models\Invoicing\{IncomingEInvoice, IncomingInvoiceTemplate};
use App\Models\Platform\{Organization, User};
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\Recognition\Contracts\{PageImageSource, WordBoxSource};
use App\Services\Invoicing\Recognition\{TemplateLearner, TemplateRecognizer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PDFToolkit\Entities\WordBox;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Belegvorlagen mit Zonen, Lernen aus Korrekturen und Zonen-Editor (Phase 151,
 * MVP-1219/1220) — mit gefälschtem Wortgitter statt Toolkit.
 */
final class IncomingInvoiceTemplateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    /** @var list<WordBox> */
    private array $grid = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        Storage::fake('local');
        $this->grid = [
            new WordBox(1, 0.10, 0.05, 0.20, 0.02, 'Rechenzentrum'),
            new WordBox(1, 0.31, 0.05, 0.12, 0.02, 'Ludwigs'),
            new WordBox(1, 0.60, 0.12, 0.12, 0.02, 'Rechnungs-Nr:'),
            new WordBox(1, 0.74, 0.12, 0.14, 0.02, 'RZLRE2501475'),
            new WordBox(1, 0.60, 0.15, 0.08, 0.02, 'Berlin,'),
            new WordBox(1, 0.69, 0.15, 0.09, 0.02, '17.06.2025'),
            new WordBox(1, 0.60, 0.70, 0.10, 0.02, 'Zu'),
            new WordBox(1, 0.71, 0.70, 0.06, 0.02, 'zahlen:'),
            new WordBox(1, 0.80, 0.70, 0.06, 0.02, '13,51'),
            new WordBox(1, 0.10, 0.92, 0.12, 0.02, 'Sparkasse'),
            new WordBox(1, 0.23, 0.92, 0.12, 0.02, 'Berlin'),
        ];
        $this->app->bind(WordBoxSource::class, fn (): WordBoxSource => new class($this->grid) implements WordBoxSource {
            /** @param  list<WordBox>  $grid */
            public function __construct(private readonly array $grid) {}

            public function boxesFor(string $pdfPath): array {
                return $this->grid;
            }
        });
        $this->app->bind(PageImageSource::class, fn (): PageImageSource => new class implements PageImageSource {
            public function render(string $pdfPath, string $outputDir, int $firstPage, int $lastPage, int $dpi): array {
                return [];
            }
        });
    }

    public function test_corrections_learn_a_template_and_the_second_hit_confirms_the_zone(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Rechenzentrum Christoph Ludwigs']);
        $first = $this->incoming(['supplier_id' => $supplier->id, 'invoice_number' => null, 'amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None]);

        $this->post(route('finance.incoming-invoices.values', $first), [
            'invoice_number' => 'rzlre 2501475', 'issue_date' => '2025-06-17', 'currency' => 'EUR', 'amount_gross' => '13.51',
        ])->assertRedirect()->assertSessionHas('success', static fn (string $m): bool => str_contains($m, 'hat gelernt') && str_contains($m, 'Rechnungsnummer'));

        $template = IncomingInvoiceTemplate::query()->sole();
        $this->assertSame($supplier->id, $template->supplier_id);
        $this->assertSame(IncomingInvoiceTemplate::SOURCE_LEARNED, $template->source);
        $this->assertEqualsCanonicalizing(['invoice_number', 'issue_date', 'amount_gross'], $template->fieldKeys());
        $zone = collect($template->zoneList())->firstWhere('field', 'invoice_number');
        $this->assertIsArray($zone);
        $this->assertFalse($zone['confirmed']);
        $this->assertEqualsWithDelta(0.74, $zone['x'] + 0.01, 1e-6);
        $this->assertSame($template->id, $first->refresh()->template_id);

        // Zweite Korrektur am selben Ort bestätigt die Zone; eine unbestätigte Zone füllt nur Lücken.
        $second = $this->incoming(['supplier_id' => $supplier->id]);
        app(TemplateLearner::class)->learnFromValues($second, ['invoice_number' => 'RZLRE2501475'], $this->admin);
        $zone = collect(IncomingInvoiceTemplate::query()->sole()->zoneList())->firstWhere('field', 'invoice_number');
        $this->assertIsArray($zone);
        $this->assertTrue($zone['confirmed']);

        $result = ['number' => 'FALSCH', 'issued_on' => null, 'gross' => null, 'seller_vat' => null, 'sources' => ['number' => 'text']];
        app(TemplateRecognizer::class)->apply($result, '/tmp/egal.pdf', Organization::query()->findOrFail($this->organization->id));
        $this->assertSame('RZLRE2501475', $result['number'], 'bestätigte Zone schlägt die Heuristik');
        $this->assertSame('13.51', $result['gross'], 'unbestätigte Zone füllt die Lücke');
        $this->assertSame('zone', $result['sources']['gross']);
        $this->assertSame($template->id, $result['template']['id']);
        $this->assertSame(1, IncomingInvoiceTemplate::query()->sole()->hits);
    }

    public function test_the_editor_previews_a_zone_saves_a_manual_template_and_applies_it_to_open_documents(): void {
        $incoming = $this->incoming(['invoice_number' => null, 'amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None, 'seller_name' => 'Rechenzentrum Christoph Ludwigs']);
        $sibling = $this->incoming(['invoice_number' => null, 'amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None]);

        $this->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()->assertSee(route('finance.incoming-invoices.template.edit', $incoming->document), false);
        $this->get(route('finance.incoming-invoices.template.edit', $incoming->document))->assertOk()
            ->assertSee('data-zone-editor', false)->assertSee('RZLRE2501475')->assertSee(__('Keine Seitenbilder'));

        $this->postJson(route('finance.incoming-invoices.template.preview', $incoming->document), ['field' => 'amount_gross', 'page' => 1, 'x' => 0.55, 'y' => 0.69, 'w' => 0.35, 'h' => 0.04])
            ->assertOk()->assertJson(['raw' => 'Zu zahlen: 13,51', 'value' => '13.51']);

        $zones = json_encode([
            ['field' => 'invoice_number', 'page' => 1, 'x' => 0.73, 'y' => 0.11, 'w' => 0.17, 'h' => 0.04],
            ['field' => 'issue_date', 'page' => 1, 'x' => 0.55, 'y' => 0.14, 'w' => 0.3, 'h' => 0.04],
            ['field' => 'amount_gross', 'page' => 1, 'x' => 0.55, 'y' => 0.69, 'w' => 0.35, 'h' => 0.04],
        ], JSON_THROW_ON_ERROR);
        $this->post(route('finance.incoming-invoices.template.store', $incoming->document), ['name' => 'RZL Rechnung', 'zones' => $zones, 'apply_to_open' => '1'])
            ->assertRedirect(route('finance.incoming-invoices.show', $incoming->document))
            ->assertSessionHas('success', static fn (string $m): bool => str_contains($m, 'RZL Rechnung') && str_contains($m, '1 weiterer offener Eingang'));

        $template = IncomingInvoiceTemplate::query()->sole();
        $this->assertSame(IncomingInvoiceTemplate::SOURCE_MANUAL, $template->source);
        $this->assertContains('rechenzentrum', $template->fingerprint['tokens']);
        $incoming->refresh();
        $this->assertSame('RZLRE2501475', $incoming->invoice_number);
        $this->assertSame('2025-06-17', $incoming->issue_date?->toDateString());
        $this->assertSame('13.51', $incoming->amount_gross?->getAmount());
        $this->assertSame(IncomingInvoiceRecognition::Extracted, $incoming->recognition);
        $this->assertSame($template->id, $incoming->template_id);
        $this->assertSame('RZLRE2501475', $sibling->refresh()->invoice_number);
        $this->assertSame(2, $template->refresh()->hits);

        $this->get(route('finance.incoming-invoices.rules'))->assertOk()->assertSee('RZL Rechnung')->assertSee(__('Belegvorlage'));
        $this->delete(route('finance.incoming-invoices.rules.template.destroy', $template))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('incoming_invoice_templates', ['id' => $template->id]);
        $this->assertNull($incoming->refresh()->template_id);
    }

    /** @param  array<string, mixed>  $attributes */
    private function incoming(array $attributes): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice, 'created_by_user_id' => $this->admin->id]);
        $path = 'documents/test/' . uniqid('inv', true) . '.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 probe');
        $version = DocumentVersion::factory()->create(['document_id' => $document->id, 'path' => $path, 'mime' => 'application/pdf', 'uploaded_by_user_id' => $this->admin->id]);
        $document->forceFill(['current_version_id' => $version->id])->save();
        $incoming = IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'mail', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'recognition' => IncomingInvoiceRecognition::Extracted,
            'invoice_number' => 'RE-' . uniqid(), 'seller_name' => 'Irgendwer', 'currency' => 'EUR', 'amount_gross' => '119.00',
            'summary' => [],
        ]);
        if ($attributes !== []) {
            $incoming->forceFill($attributes)->save();
        }

        return $incoming;
    }
}
