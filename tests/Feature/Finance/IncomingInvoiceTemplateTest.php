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
use App\Services\Invoicing\Recognition\{PageImages, TemplateLearner, TemplateRecognizer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PDFToolkit\Entities\WordBox;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Belegvorlagen mit Zonen, Lernen aus Korrekturen und Zonen-Editor (Phase 151,
 * MVP-1219/1220) sowie Anker-Zonen, Belegart-Sperre, Lieferantenbindung,
 * Probelauf und Vorlagenseite (Phase 155, MVP-1252–1254) — mit gefälschtem
 * Wortgitter statt Toolkit. Der Dateiname des Originals wählt das Gitter.
 */
final class IncomingInvoiceTemplateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    /** @var array<string, list<WordBox>> Gitter je Kennung im Dateinamen */
    private array $grids = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        Storage::fake('local');
        $header = [
            new WordBox(1, 0.10, 0.05, 0.20, 0.02, 'Rechenzentrum'),
            new WordBox(1, 0.31, 0.05, 0.12, 0.02, 'Ludwigs'),
            new WordBox(1, 0.10, 0.92, 0.12, 0.02, 'Sparkasse'),
            new WordBox(1, 0.23, 0.92, 0.12, 0.02, 'Berlin'),
        ];
        $this->grids = [
            'default' => [
                ...$header,
                new WordBox(1, 0.60, 0.12, 0.12, 0.02, 'Rechnungs-Nr:'),
                new WordBox(1, 0.74, 0.12, 0.14, 0.02, 'RZLRE2501475'),
                new WordBox(1, 0.60, 0.15, 0.08, 0.02, 'Berlin,'),
                new WordBox(1, 0.69, 0.15, 0.09, 0.02, '17.06.2025'),
                new WordBox(1, 0.60, 0.70, 0.10, 0.02, 'Zu'),
                new WordBox(1, 0.71, 0.70, 0.06, 0.02, 'zahlen:'),
                new WordBox(1, 0.80, 0.70, 0.06, 0.02, '13,51'),
            ],
            // Längerer Beleg: der Summenblock steht auf Seite 2 weiter oben, die feste Zone träfe nichts.
            'shifted' => [
                ...$header,
                new WordBox(1, 0.60, 0.12, 0.12, 0.02, 'Rechnungs-Nr:'),
                new WordBox(1, 0.74, 0.12, 0.14, 0.02, 'RZLRE2501999'),
                new WordBox(1, 0.60, 0.70, 0.10, 0.02, 'Übertrag'),
                new WordBox(1, 0.80, 0.70, 0.06, 0.02, '70,00'),
                new WordBox(2, 0.60, 0.25, 0.10, 0.02, 'Zu'),
                new WordBox(2, 0.71, 0.25, 0.06, 0.02, 'zahlen:'),
                new WordBox(2, 0.80, 0.25, 0.06, 0.02, '99,00'),
            ],
            // Gleiche Form, aber keine Werte an den Zonen.
            'blank' => $header,
            // Fünf Seiten: der Editor zeigt die ersten drei und die letzte.
            'long' => [...$header, new WordBox(5, 0.60, 0.25, 0.10, 0.02, 'Zu'), new WordBox(5, 0.71, 0.25, 0.06, 0.02, 'zahlen:'), new WordBox(5, 0.80, 0.25, 0.06, 0.02, '5,00')],
        ];
        $this->app->bind(WordBoxSource::class, fn (): WordBoxSource => new class($this->grids) implements WordBoxSource {
            /** @param  array<string, list<WordBox>>  $grids */
            public function __construct(private readonly array $grids) {}

            public function boxesFor(string $pdfPath): array {
                foreach ($this->grids as $marker => $grid) {
                    if ($marker !== 'default' && str_contains(basename($pdfPath), $marker)) {
                        return $grid;
                    }
                }

                return $this->grids['default'];
            }
        });
        $this->app->bind(PageImageSource::class, fn (): PageImageSource => new class implements PageImageSource {
            public function render(string $pdfPath, string $outputDir, int $firstPage, int $lastPage, int $dpi): array {
                // Nur „lange“ Belege bekommen Seitenbilder; eine PNG-Signatur reicht der Auslieferung.
                if (! str_contains(basename($pdfPath), 'long')) {
                    return [];
                }
                $file = rtrim($outputDir, '/') . '/page-' . $firstPage . '.png';
                file_put_contents($file, "\x89PNG\r\n\x1a\n");

                return [$file];
            }
        });
    }

    public function test_corrections_learn_an_anchored_zone_and_the_second_hit_confirms_it(): void {
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
        $this->assertSame('Rechnungs-Nr:', $zone['anchor'], 'Die Beschriftung links des Wertes wird zum Anker.');
        $this->assertSame('any', $zone['scope']);
        $gross = collect($template->zoneList())->firstWhere('field', 'amount_gross');
        $this->assertSame('Zu zahlen:', $gross['anchor'] ?? null);
        $this->assertSame($template->id, $first->refresh()->template_id);

        // Zweite Korrektur mit gleichem Anker bestätigt die Zone; eine unbestätigte Zone füllt nur Lücken.
        $second = $this->incoming(['supplier_id' => $supplier->id]);
        app(TemplateLearner::class)->learnFromValues($second, ['invoice_number' => 'RZLRE2501475'], $this->admin);
        $zone = collect(IncomingInvoiceTemplate::query()->sole()->zoneList())->firstWhere('field', 'invoice_number');
        $this->assertIsArray($zone);
        $this->assertTrue($zone['confirmed']);

        $result = ['number' => 'FALSCH', 'issued_on' => null, 'gross' => null, 'seller_vat' => null, 'kind_hint' => null, 'sources' => ['number' => 'text']];
        app(TemplateRecognizer::class)->apply($result, '/tmp/egal.pdf', Organization::query()->findOrFail($this->organization->id));
        $this->assertSame('RZLRE2501475', $result['number'], 'bestätigte Zone schlägt die Heuristik');
        $this->assertSame('13.51', $result['gross'], 'unbestätigte Zone füllt die Lücke');
        $this->assertSame('zone', $result['sources']['gross']);
        $this->assertSame($template->id, $result['template']['id']);
        $this->assertSame($supplier->id, $result['template']['supplier_id']);
        $this->assertSame(1, IncomingInvoiceTemplate::query()->sole()->hits);
    }

    public function test_the_editor_previews_splits_anchor_and_value_saves_a_manual_template_and_applies_it_to_open_documents(): void {
        $incoming = $this->incoming(['invoice_number' => null, 'amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None, 'seller_name' => 'Rechenzentrum Christoph Ludwigs']);
        $sibling = $this->incoming(['invoice_number' => null, 'amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None]);

        $this->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()->assertSee(route('finance.incoming-invoices.template.edit', $incoming->document), false);
        $this->get(route('finance.incoming-invoices.template.edit', $incoming->document))->assertOk()
            ->assertSee('data-zone-editor', false)->assertSee('RZLRE2501475')->assertSee(__('Keine Seitenbilder'))->assertSee(__('Probelauf'));

        $drawn = ['field' => 'amount_gross', 'page' => 1, 'x' => 0.55, 'y' => 0.69, 'w' => 0.35, 'h' => 0.04];
        $this->postJson(route('finance.incoming-invoices.template.preview', $incoming->document), $drawn)
            ->assertOk()->assertJson(['raw' => 'Zu zahlen: 13,51', 'value' => '13.51', 'anchor' => null, 'scope' => 'page', 'hits' => 1]);
        $split = $this->postJson(route('finance.incoming-invoices.template.preview', $incoming->document), $drawn + ['detect' => 1])
            ->assertOk()->assertJson(['value' => '13.51', 'anchor' => 'Zu zahlen:', 'scope' => 'any', 'hits' => 1])->json();
        $this->assertEqualsWithDelta(0.60, $split['anchor_box']['x'], 1e-6);
        $this->assertEqualsWithDelta(0.55, $split['x'], 1e-9, 'Das Kästchen bleibt, wie gezeichnet.');
        $this->postJson(route('finance.incoming-invoices.template.preview', $incoming->document), $drawn + ['pattern' => '(unclosed'])->assertStatus(422);
        $this->postJson(route('finance.incoming-invoices.template.preview', $incoming->document), array_merge($drawn, $split, ['pattern' => 'zahlen:\s+([\d,]+)']))
            ->assertOk()->assertJson(['value' => '13.51']);

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

        $this->get(route('finance.incoming-invoices.rules'))->assertOk()->assertSee(route('finance.incoming-invoices.templates.index'), false);
        $this->get(route('finance.incoming-invoices.templates.index'))->assertOk()->assertSee('RZL Rechnung')->assertSee(__('Rechnungsnummer'));
        $this->delete(route('finance.incoming-invoices.templates.destroy', $template))->assertRedirect(route('finance.incoming-invoices.templates.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('incoming_invoice_templates', ['id' => $template->id]);
        $this->assertNull($incoming->refresh()->template_id);
    }

    public function test_an_anchored_zone_follows_its_label_to_another_page_while_a_fixed_zone_stays_empty(): void {
        $template = $this->template([
            ['field' => 'invoice_number', 'page' => 1, 'x' => 0.73, 'y' => 0.11, 'w' => 0.17, 'h' => 0.04, 'scope' => 'page'],
            ['field' => 'amount_gross', 'page' => 1, 'x' => 0.79, 'y' => 0.696, 'w' => 0.08, 'h' => 0.028, 'anchor' => 'Zu zahlen:', 'anchor_box' => ['x' => 0.60, 'y' => 0.70, 'w' => 0.17, 'h' => 0.02], 'scope' => 'any'],
        ]);
        $fixed = $this->template([
            ['field' => 'amount_gross', 'page' => 1, 'x' => 0.79, 'y' => 0.696, 'w' => 0.08, 'h' => 0.028, 'scope' => 'page'],
        ], 'Fest');
        $shifted = $this->incoming(['invoice_number' => null, 'amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None], 'shifted');

        $this->assertSame(['invoice_number', 'amount_gross'], app(TemplateRecognizer::class)->applyToIncoming($shifted, $template, $this->admin));
        $shifted->refresh();
        $this->assertSame('RZLRE2501999', $shifted->invoice_number);
        $this->assertSame('99.00', $shifted->amount_gross?->getAmount(), 'Der Anker findet die Summe auf Seite 2.');
        $this->assertSame(IncomingInvoiceRecognition::Extracted, $shifted->recognition);

        // Die feste Zone liest auf dem längeren Beleg den Übertrag — genau der Fehler, den der Anker vermeidet.
        $other = $this->incoming(['amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None], 'shifted');
        $this->assertSame(['amount_gross'], app(TemplateRecognizer::class)->applyToIncoming($other, $fixed, $this->admin));
        $this->assertSame('70.00', $other->refresh()->amount_gross?->getAmount());

        // Fehlt der Anker, liefert die Ankerzone nichts und fällt nicht auf feste Koordinaten zurück (E1); kein Treffer (E6).
        $blank = $this->incoming(['amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None], 'blank');
        $this->assertSame([], app(TemplateRecognizer::class)->applyToIncoming($blank, $template, $this->admin));
        $this->assertNull($blank->refresh()->amount_gross);
        $this->assertSame(1, $template->refresh()->hits);
    }

    public function test_templates_fill_only_invoices_and_bind_their_supplier(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Rechenzentrum Christoph Ludwigs', 'vat_id' => 'DE123456789']);
        $template = $this->template([
            ['field' => 'amount_gross', 'page' => 1, 'x' => 0.79, 'y' => 0.696, 'w' => 0.08, 'h' => 0.028, 'anchor' => 'Zu zahlen:', 'anchor_box' => ['x' => 0.60, 'y' => 0.70, 'w' => 0.17, 'h' => 0.02], 'scope' => 'any'],
        ], 'RZL', $supplier->id);

        // Mahnung desselben Absenders: gleicher Kopf und Fuß, aber keine Rechnung (E4).
        $reminder = $this->incoming(['amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None, 'summary' => ['kind_hint' => 'reminder']]);
        $this->assertSame([], app(TemplateRecognizer::class)->applyToIncoming($reminder, $template, $this->admin));
        $this->assertNull($reminder->refresh()->amount_gross);
        $this->assertNull($reminder->supplier_id);
        $result = ['number' => null, 'gross' => null, 'seller_vat' => null, 'kind_hint' => 'reminder', 'sources' => []];
        app(TemplateRecognizer::class)->apply($result, '/tmp/egal.pdf', Organization::query()->findOrFail($this->organization->id));
        $this->assertArrayNotHasKey('template', $result);

        // Rechnung ohne Lieferant: die Vorlage bringt ihn mit und nennt den Verkäufer (E5).
        $invoice = $this->incoming(['amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None, 'seller_name' => null, 'seller_vat_id' => null]);
        $this->assertSame(['amount_gross', 'supplier'], app(TemplateRecognizer::class)->applyToIncoming($invoice, $template, $this->admin));
        $invoice->refresh();
        $this->assertSame($supplier->id, $invoice->supplier_id);
        $this->assertSame('Rechenzentrum Christoph Ludwigs', $invoice->seller_name);
        $this->assertSame('DE123456789', $invoice->seller_vat_id);
        $this->assertSame(1, $template->refresh()->hits);

        // Beleg ohne Werte an den Zonen: nur der Lieferant wird gesetzt, kein Treffer.
        $blank = $this->incoming(['amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None, 'seller_name' => null], 'blank');
        $this->assertSame(['supplier'], app(TemplateRecognizer::class)->applyToIncoming($blank, $template, $this->admin));
        $this->assertSame(1, $template->refresh()->hits);

        // In der Erkennung liefert die Vorlage die USt-IdNr. ihres Lieferanten, damit der Eingang ihn findet.
        $result = ['number' => 'X1', 'gross' => null, 'seller_vat' => null, 'kind_hint' => null, 'sources' => []];
        app(TemplateRecognizer::class)->apply($result, '/tmp/egal.pdf', Organization::query()->findOrFail($this->organization->id));
        $this->assertSame('13.51', $result['gross']);
        $this->assertSame('DE123456789', $result['seller_vat']);
        $this->assertSame('template', $result['sources']['seller_vat']);
        $this->assertSame($supplier->id, $result['template']['supplier_id']);
    }

    public function test_the_template_page_edits_merges_trials_and_deletes_templates(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Rechenzentrum Christoph Ludwigs']);
        $target = $this->template([
            ['field' => 'invoice_number', 'page' => 1, 'x' => 0.73, 'y' => 0.11, 'w' => 0.17, 'h' => 0.04, 'scope' => 'page'],
            ['field' => 'amount_gross', 'page' => 1, 'x' => 0.79, 'y' => 0.696, 'w' => 0.08, 'h' => 0.028, 'anchor' => 'Zu zahlen:', 'anchor_box' => ['x' => 0.60, 'y' => 0.70, 'w' => 0.17, 'h' => 0.02], 'scope' => 'any'],
        ], 'RZL Rechnung');
        $source = $this->template([
            ['field' => 'issue_date', 'page' => 1, 'x' => 0.55, 'y' => 0.14, 'w' => 0.3, 'h' => 0.04, 'scope' => 'page'],
            ['field' => 'invoice_number', 'page' => 1, 'x' => 0.0, 'y' => 0.0, 'w' => 0.1, 'h' => 0.04, 'scope' => 'page'],
        ], 'RZL alt');
        $target->forceFill(['hits' => 3])->save();
        $source->forceFill(['hits' => 1])->save();
        $linked = $this->incoming(['template_id' => $source->id]);
        $open = $this->incoming(['invoice_number' => null, 'amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None]);

        $this->get(route('finance.incoming-invoices.templates.index'))->assertOk()
            ->assertSee('RZL Rechnung')->assertSee('RZL alt')->assertSee(__('Kein Lieferant hinterlegt'))
            ->assertSee(route('finance.incoming-invoices.template.edit', $linked->document), false);
        $this->get(route('finance.incoming-invoices.templates.edit', $target))->assertOk()->assertSee('RZL Rechnung')->assertSee($supplier->name);
        $this->put(route('finance.incoming-invoices.templates.update', $target), ['name' => 'RZL neu', 'supplier' => $supplier->sqid])
            ->assertRedirect(route('finance.incoming-invoices.templates.index'))->assertSessionHas('success');
        $target->refresh();
        $this->assertSame('RZL neu', $target->name);
        $this->assertSame($supplier->id, $target->supplier_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'incoming_einvoice.template_updated']);

        $this->get(route('finance.incoming-invoices.templates.merge.form', $source))->assertOk()->assertSee('RZL neu');
        $this->post(route('finance.incoming-invoices.templates.merge', $source), ['target' => $source->sqid])->assertSessionHasErrors('target');
        $this->post(route('finance.incoming-invoices.templates.merge', $source), ['target' => $target->sqid])
            ->assertRedirect(route('finance.incoming-invoices.templates.index'))
            ->assertSessionHas('success', static fn (string $m): bool => str_contains($m, 'aufgegangen') && str_contains($m, '1 Eingang umgehängt') && str_contains($m, __('Rechnungsdatum')));
        $this->assertDatabaseMissing('incoming_invoice_templates', ['id' => $source->id]);
        $target->refresh();
        $this->assertEqualsCanonicalizing(['invoice_number', 'amount_gross', 'issue_date'], $target->fieldKeys(), 'Nur fehlende Felder wandern mit (E7).');
        $this->assertSame(4, $target->hits);
        $this->assertSame($target->id, $linked->refresh()->template_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'incoming_einvoice.template_merged']);

        $this->post(route('finance.incoming-invoices.templates.trial', $target))
            ->assertRedirect(route('finance.incoming-invoices.templates.index'))
            ->assertSessionHas('success', static fn (string $m): bool => str_contains($m, 'RZL neu') && str_contains($m, __('Brutto') . ' '));
        $this->assertNull($open->refresh()->amount_gross, 'Der Probelauf schreibt nichts.');

        $this->delete(route('finance.incoming-invoices.templates.destroy', $target))->assertRedirect(route('finance.incoming-invoices.templates.index'));
        $this->assertDatabaseMissing('incoming_invoice_templates', ['id' => $target->id]);
        $this->assertNull($linked->refresh()->template_id);
    }

    public function test_the_editor_trial_checks_unsaved_zones_against_open_documents_and_shows_the_last_page(): void {
        $incoming = $this->incoming(['invoice_number' => null, 'amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None]);
        $sibling = $this->incoming(['invoice_number' => null, 'amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None], 'shifted');
        $zones = json_encode([
            ['field' => 'amount_gross', 'page' => 1, 'x' => 0.79, 'y' => 0.696, 'w' => 0.08, 'h' => 0.028, 'anchor' => 'Zu zahlen:', 'anchor_box' => ['x' => 0.60, 'y' => 0.70, 'w' => 0.17, 'h' => 0.02], 'scope' => 'any'],
        ], JSON_THROW_ON_ERROR);
        $trial = $this->postJson(route('finance.incoming-invoices.template.trial', $incoming->document), ['zones' => $zones])->assertOk()->json();
        $this->assertSame(1, $trial['total']);
        $this->assertSame(1, $trial['filled']);
        $this->assertSame(['amount_gross' => 1], $trial['fields']);
        $this->assertSame('99.00', $trial['rows'][0]['fields']['amount_gross']);
        $this->assertNull($sibling->refresh()->amount_gross, 'Der Probelauf schreibt nichts.');

        $this->assertSame([1, 2, 3, 5], PageImages::shownPages(5));
        $this->assertSame([1, 2], PageImages::shownPages(2));
        $long = $this->incoming(['invoice_number' => null, 'amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None], 'long');
        $this->get(route('finance.incoming-invoices.template.edit', $long->document))->assertOk()->assertSee(__('Gezeigt werden die ersten Seiten und die letzte Seite (:count Seiten insgesamt).', ['count' => 5]));
        $this->get(route('finance.incoming-invoices.template.page', [$long->document, 5]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('finance.incoming-invoices.template.page', [$long->document, 4]))->assertNotFound();
    }

    public function test_saving_replaces_the_zone_list_keeps_learned_zones_and_edits_the_chosen_template(): void {
        $template = $this->template([
            ['field' => 'invoice_number', 'page' => 1, 'x' => 0.73, 'y' => 0.11, 'w' => 0.17, 'h' => 0.04, 'scope' => 'page'],
            ['field' => 'amount_gross', 'page' => 1, 'x' => 0.79, 'y' => 0.696, 'w' => 0.08, 'h' => 0.028, 'scope' => 'page'],
            ['field' => 'issue_date', 'page' => 1, 'x' => 0.55, 'y' => 0.14, 'w' => 0.3, 'h' => 0.04, 'scope' => 'page', 'source' => IncomingInvoiceTemplate::SOURCE_LEARNED, 'confirmed' => false],
        ], 'RZL Rechnung');
        $other = $this->template([['field' => 'due_date', 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.04, 'scope' => 'page']], 'Andere Form');
        $other->forceFill(['fingerprint' => ['tokens' => ['fremd', 'firma', 'ort']]])->save();
        $incoming = $this->incoming(['invoice_number' => null, 'amount_gross' => null, 'recognition' => IncomingInvoiceRecognition::None]);

        // Der Editor lädt die ähnlichste Vorlage und bietet die Wahl an.
        $this->get(route('finance.incoming-invoices.template.edit', $incoming->document))->assertOk()
            ->assertSee('data-zone-template', false)->assertSee('RZL Rechnung')->assertSee('Andere Form')->assertSee(__('Neue Vorlage anlegen'));

        // Brutto-Zone entfernt, gelernte Datumszone unverändert zurückgegeben: die Liste ist der neue Stand.
        $zones = json_encode([
            ['field' => 'invoice_number', 'page' => 1, 'x' => 0.73, 'y' => 0.11, 'w' => 0.17, 'h' => 0.04, 'scope' => 'page'],
            ['field' => 'issue_date', 'page' => 1, 'x' => 0.55, 'y' => 0.14, 'w' => 0.3, 'h' => 0.04, 'scope' => 'page', 'source' => IncomingInvoiceTemplate::SOURCE_LEARNED, 'confirmed' => false],
        ], JSON_THROW_ON_ERROR);
        $this->post(route('finance.incoming-invoices.template.store', $incoming->document), ['name' => 'RZL Rechnung', 'zones' => $zones, 'template' => $template->sqid])
            ->assertRedirect(route('finance.incoming-invoices.show', $incoming->document))->assertSessionHas('success');
        $template->refresh();
        $this->assertEqualsCanonicalizing(['invoice_number', 'issue_date'], $template->fieldKeys(), 'Die entfernte Brutto-Zone ist weg.');
        $date = collect($template->zoneList())->firstWhere('field', 'issue_date');
        $this->assertSame(IncomingInvoiceTemplate::SOURCE_LEARNED, $date['source']);
        $this->assertFalse($date['confirmed']);
        $this->assertSame(2, IncomingInvoiceTemplate::query()->count(), 'Keine Doppelvorlage.');

        // Ausdrücklich die andere Vorlage bearbeiten: sie bekommt die Zonen und die Kopfform dieses Belegs als Variante.
        $this->post(route('finance.incoming-invoices.template.store', $incoming->document), ['name' => 'Andere Form', 'zones' => $zones, 'template' => $other->sqid])->assertRedirect();
        $other->refresh();
        $this->assertEqualsCanonicalizing(['invoice_number', 'issue_date'], $other->fieldKeys());
        $this->assertSame(['fremd', 'firma', 'ort'], $other->fingerprint['tokens'], 'Der erste Fingerabdruck bleibt.');
        $this->assertContains('rechenzentrum', $other->fingerprint['variants'][0] ?? [], 'Die Kopfform des Belegs ist als Variante gemerkt.');
        $this->assertSame($other->id, $incoming->refresh()->template_id);

        // „Neue Vorlage anlegen“ legt eine dritte an, auch wenn eine ähnliche existiert.
        $this->post(route('finance.incoming-invoices.template.store', $incoming->document), ['name' => 'Dritte', 'zones' => $zones, 'template' => 'new'])->assertRedirect();
        $this->assertSame(3, IncomingInvoiceTemplate::query()->count());
    }

    /** @param  list<array<string, mixed>>  $zones */
    private function template(array $zones, string $name = 'RZL', ?int $supplierId = null): IncomingInvoiceTemplate {
        return IncomingInvoiceTemplate::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $supplierId,
            'name' => $name,
            'fingerprint' => ['tokens' => ['berlin', 'ludwigs', 'rechenzentrum', 'sparkasse']],
            'zones' => array_map(static fn (array $zone): array => $zone + ['parse' => \App\Services\Invoicing\Recognition\ZoneFields::parseOf((string) $zone['field']), 'source' => IncomingInvoiceTemplate::SOURCE_MANUAL, 'confirmed' => true], $zones),
            'source' => IncomingInvoiceTemplate::SOURCE_MANUAL,
            'created_by' => $this->admin->id,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function incoming(array $attributes, string $marker = ''): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice, 'created_by_user_id' => $this->admin->id]);
        $path = 'documents/test/' . $marker . uniqid('inv', true) . '.pdf';
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
