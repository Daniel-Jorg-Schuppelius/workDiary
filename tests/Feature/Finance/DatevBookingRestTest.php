<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingRestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\{Customer, Expense, ExpenseCategory, Invoice, Organization, User};
use App\Services\Finance\Datev\{DatevBookingConfig, DatevMasterDataExporter};
use App\Services\Finance\{DatevBookingException, DatevBookingService, FinancialFormatsSupport};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * DATEV-Buchungsstapel-Rest (MVP-334, Bauturbo A15): Teilauswahl/mehrere
 * Stapel je Zeitraum (Draft-Reservierung, Zuschnitt am Nachweis), Storno-
 * Übergabe mit Generalumkehr-Kennzeichen, differenzierte Aufwands-/Vorsteuer-
 * konten je Spesenkategorie und Sachkonten-Beistellung (EXTF Kategorie 20).
 */
class DatevBookingRestTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }
        $this->setUpOrganization([
            'settings' => ['datev' => ['advisor_number' => 12345, 'client_number' => 1, 'skr' => 'skr03', 'debtor_base' => 10000]],
        ]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Acme GmbH']);
    }

    private function config(): DatevBookingConfig {
        return DatevBookingConfig::forOrganization($this->organization->refresh());
    }

    private function service(): DatevBookingService {
        return app(DatevBookingService::class);
    }

    private function makeInvoice(string $number, string $status = Invoice::STATUS_ISSUED): Invoice {
        return Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => $number,
            'status' => $status,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-05-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
        ]);
    }

    /** @return array{from: string, to: string} */
    private function period(): array {
        return ['from' => '2026-05-01', 'to' => '2026-05-31'];
    }

    /** @return list<array<int, string>> CSV-Zeilen (UTF-8, geparst) */
    private function csvLines(string $csv): array {
        $lines = preg_split('/\r?\n/', trim($csv));

        return array_map(
            static fn (string $line): array => str_getcsv(mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1'), ';', '"', '\\'),
            $lines,
        );
    }

    public function test_remove_sources_reshapes_draft_and_frees_invoice(): void {
        $this->makeInvoice('RE-A');
        $this->makeInvoice('RE-B');
        $this->makeInvoice('RE-C');

        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $batch = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);
        $this->assertSame(3, (int) $batch->booking_count);
        $this->assertSame('all', $batch->selection_mode);

        $removeId = (int) $batch->sources->firstWhere('document_ref', 'RE-A')->id;
        $batch = $this->service()->removeSources($batch, [$removeId], $this->admin);

        // Zuschnitt persistiert: Kennzahlen neu, selection_mode=manual, Event mit Belegen.
        $this->assertSame(2, (int) $batch->booking_count);
        $this->assertSame('238.00', (string) $batch->total_amount);
        $this->assertSame('manual', $batch->selection_mode);
        $event = $batch->events()->where('event', 'sources_removed')->firstOrFail();
        $this->assertSame(['RE-A'], data_get($event->payload, 'removed_refs'));

        // Entfernte Quelle ist sofort wieder buchungsreif (zweiter Stapel je Zeitraum).
        $again = $this->service()->collectBookingReady($this->organization, $this->period());
        $this->assertSame(['RE-A'], $again->pluck('number')->all());

        // Finalisierung exportiert exakt die verbliebene Auswahl.
        $batch = $this->service()->finalize($batch, $this->config(), $this->admin);
        $csv = (string) \Illuminate\Support\Facades\Storage::disk(DatevBookingService::DISK)->get((string) $batch->file_path);
        $this->assertStringContainsString('RE-B', $csv);
        $this->assertStringContainsString('RE-C', $csv);
        $this->assertStringNotContainsString('RE-A', $csv);
    }

    public function test_draft_reserves_sources_and_discard_frees_them(): void {
        $this->makeInvoice('RE-RESERVED');

        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $batch = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);

        // Draft reserviert: dieselbe Quelle erscheint nicht mehr im Sammellauf.
        $this->assertCount(0, $this->service()->collectBookingReady($this->organization, $this->period()));

        // Verwerfen gibt die Quelle wieder frei (SoftDelete + Event).
        $this->service()->discardDraft($batch, $this->admin);
        $this->assertSoftDeleted('datev_booking_batches', ['id' => $batch->id]);
        $this->assertCount(1, $this->service()->collectBookingReady($this->organization, $this->period()));
    }

    public function test_remove_all_sources_is_rejected(): void {
        $this->makeInvoice('RE-ONLY');
        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $batch = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);

        $this->expectException(DatevBookingException::class);
        $this->service()->removeSources($batch, $batch->sources->pluck('id')->all(), $this->admin);
    }

    public function test_expense_category_mapping_drives_account_and_tax_key(): void {
        $category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id, 'label' => 'Reisekosten']);
        $this->organization->update([
            'settings' => array_replace((array) $this->organization->settings, [
                'datev' => [
                    'advisor_number' => 12345,
                    'client_number' => 1,
                    'expense_accounts' => [
                        (string) $category->id => ['account' => '4980', 'tax_key' => '9'],
                    ],
                ],
            ]),
        ]);
        Expense::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'expense_category_id' => $category->id,
            'date' => '2026-05-10',
            'amount_net' => '100.00',
            'tax_rate' => '19.00',
            'tax_amount' => '19.00',
            'amount_gross' => '119.00',
            'reimbursed_at' => null,
        ]);

        $sources = $this->service()->collectBookingReady($this->organization, $this->period(), includeExpenses: true);
        $rows = $this->service()->buildBookingRows($sources, $this->config());

        $this->assertCount(1, $rows);
        // Kategorie-Mapping: Aufwandskonto + Vorsteuer-BU statt vereinfachter Abbildung.
        $this->assertSame('4980', $rows[0]['revenue_account']);
        $this->assertSame('9', $rows[0]['tax_key']);

        // Und im EXTF-Output landet das gemappte Konto.
        $batch = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);
        $batch = $this->service()->finalize($batch, $this->config(), $this->admin);
        $csv = (string) \Illuminate\Support\Facades\Storage::disk(DatevBookingService::DISK)->get((string) $batch->file_path);
        $this->assertStringContainsString('4980', $csv);
    }

    public function test_cancelled_exported_invoice_is_resubmitted_with_general_reversal(): void {
        $invoice = $this->makeInvoice('RE-STORNO');

        // Erst regulär übergeben (exportierter Stapel) …
        $sources = $this->service()->collectBookingReady($this->organization, $this->period());
        $first = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);
        $this->service()->finalize($first, $this->config(), $this->admin);

        // … dann stornieren.
        $invoice->refresh()->cancel('Kunde reklamiert', $this->admin->id);

        // Ohne Storno-Option bleibt der Sammellauf leer.
        $this->assertCount(0, $this->service()->collectBookingReady($this->organization, $this->period()));

        // Mit Storno-Option wird der Beleg als Generalumkehr nachgereicht
        // (Zeitraum-Anker = Stornodatum).
        $reversalPeriod = ['from' => '2026-05-01', 'to' => now()->toDateString()];
        $reversals = $this->service()->collectBookingReady($this->organization, $reversalPeriod, includeReversals: true);
        $this->assertSame(['RE-STORNO'], $reversals->pluck('number')->all());

        $batch = $this->service()->createDraft($this->organization, $reversalPeriod, $reversals, $this->config(), $this->admin);
        $source = $batch->sources->firstOrFail();
        $this->assertTrue((bool) $source->is_reversal);
        // Konten/S-H wie das Original — die Umkehr erledigt das GU-Kennzeichen.
        $this->assertSame('S', (string) $source->soll_haben);

        $batch = $this->service()->finalize($batch, $this->config(), $this->admin);
        $csv = (string) \Illuminate\Support\Facades\Storage::disk(DatevBookingService::DISK)->get((string) $batch->file_path);
        $lines = $this->csvLines($csv);
        $guIndex = array_search('Generalumkehr (GU)', $lines[1], true);
        $this->assertIsInt($guIndex);
        $this->assertSame('1', $lines[2][$guIndex]);

        // Idempotenz: der Generalumkehr-Satz wird nicht erneut eingesammelt.
        $this->assertCount(0, $this->service()->collectBookingReady($this->organization, $reversalPeriod, includeReversals: true));
    }

    public function test_finalize_blocks_source_already_exported_elsewhere(): void {
        $this->makeInvoice('RE-RACE');
        $sources = $this->service()->collectBookingReady($this->organization, $this->period());

        // Zwei Drafts mit derselben Quelle (Race vor der Draft-Reservierung).
        $first = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);
        $second = $this->service()->createDraft($this->organization, $this->period(), $sources, $this->config(), $this->admin);

        $this->service()->finalize($first, $this->config(), $this->admin);

        $this->expectException(DatevBookingException::class);
        $this->service()->finalize($second, $this->config(), $this->admin);
    }

    public function test_gl_account_export_produces_category20_extf(): void {
        $category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id, 'label' => 'Reisekosten']);
        $this->organization->update([
            'settings' => array_replace((array) $this->organization->settings, [
                'datev' => [
                    'advisor_number' => 12345,
                    'client_number' => 1,
                    'revenue_account' => '8400',
                    'revenue_account_tax_free' => '8125',
                    'expense_accounts' => [
                        (string) $category->id => ['account' => '4980', 'tax_key' => '9'],
                    ],
                ],
            ]),
        ]);

        $result = app(DatevMasterDataExporter::class)->generateGlAccounts($this->organization, $this->config());

        $this->assertSame(3, $result['count']);
        $lines = $this->csvLines($result['csv']);
        // Golden-Header: EXTF, Kategorie 20 (Kontenbeschriftungen), Formatversion 3.
        $meta = mb_convert_encoding((string) preg_split('/\r?\n/', trim($result['csv']))[0], 'UTF-8', 'ISO-8859-1');
        $this->assertStringStartsWith('EXTF;700;20;', $meta);
        $this->assertStringContainsString('"Kontenbeschriftungen";3;', $meta);

        $accounts = array_column(array_slice($lines, 2), 1, 0);
        $this->assertSame('Erlöse', $accounts['8400']);
        $this->assertSame('Erlöse steuerfrei', $accounts['8125']);
        $this->assertSame('Reisekosten', $accounts['4980']);
    }

    public function test_gl_account_export_route_downloads_and_audits(): void {
        $response = $this->actingAs($this->admin)->post(route('finance.datev.gl-accounts.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertSame(
            1,
            \App\Models\AuditLog::query()->where('event', 'finance.datev.gl_accounts_exported')->count(),
        );
    }

    public function test_remove_sources_route_is_org_scoped(): void {
        $other = Organization::factory()->create();
        $foreign = \App\Models\Finance\DatevBookingBatch::factory()->create(['organization_id' => $other->id]);

        $this->actingAs($this->admin)
            ->post(route('finance.datev.sources.remove', $foreign->sqid), ['sources' => [1]])
            ->assertNotFound();
    }
}
