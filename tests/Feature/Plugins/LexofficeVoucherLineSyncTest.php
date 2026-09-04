<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherLineSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\{Customer, ExternalReference, LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Plugins\Lexoffice\{LexofficeInvoiceParser, LexofficePlugin, LexofficeVoucherLineSync};
use App\Services\Reselling\Register\MirroredInvoiceLineReader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Positionen im Belegspiegel (Feature 152, MVP-760 = Feature 140 Schnitt 2):
 * Nachladen je Rechnung, Artikelbezug, Belegtexte, Backfill-Befehl und der
 * Spiegel-Leser als InvoiceLineSource.
 */
class LexofficeVoucherLineSyncTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function voucher(string $externalId, string $number, string $date, string $type = 'invoice', ?int $customerId = null): LexofficeVoucher {
        return LexofficeVoucher::create([
            'organization_id' => $this->organization->id,
            'external_id' => $externalId,
            'contact_external_id' => 'c-lds',
            'customer_id' => $customerId,
            'voucher_type' => $type,
            'voucher_status' => 'paid',
            'voucher_number' => $number,
            'voucher_date' => $date,
            'total_amount' => 100,
            'currency' => 'EUR',
            'archived' => false,
        ]);
    }

    private function fakeInvoices(): void {
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices/inv-1' => FakePluginHttp::response([
                'id' => 'inv-1', 'title' => 'Rechnung', 'introduction' => 'Lizenzen für Steuerbüro Kaik', 'remark' => 'Danke', 'address' => ['name' => 'LDS Systems GmbH'],
                'taxConditions' => ['taxType' => 'net'], 'totalPrice' => ['currency' => 'EUR'],
                'lineItems' => [
                    ['type' => 'service', 'id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'description' => '', 'quantity' => 12, 'unitName' => 'Monat', 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 20.6, 'grossAmount' => 24.51, 'taxRatePercentage' => 19]],
                    ['type' => 'text', 'name' => 'Hinweis', 'description' => 'nur Text'],
                    ['type' => 'custom', 'name' => 'Business Support', 'description' => 'Stunden', 'quantity' => 2, 'unitPrice' => ['currency' => 'EUR', 'grossAmount' => 107.10, 'taxRatePercentage' => 19], 'discountPercentage' => 10],
                ],
            ]),
            'https://api.lexoffice.io/v1/invoices/inv-gone' => FakePluginHttp::response(['message' => 'not found'], 404),
        ]);
    }

    public function test_parser_normalizes_lines_and_texts(): void {
        $parsed = LexofficeInvoiceParser::parse([
            'title' => 'T', 'introduction' => 'I', 'remark' => '', 'address' => ['name' => 'Empfänger'], 'totalPrice' => ['currency' => 'EUR'],
            'lineItems' => [
                ['type' => 'custom', 'name' => 'A', 'quantity' => 3, 'unitPrice' => ['grossAmount' => 119, 'taxRatePercentage' => 19]],
                ['type' => 'text', 'name' => 'nur Text'],
            ],
        ]);
        $this->assertSame('T I', $parsed['voucher_text']);
        $this->assertSame('Empfänger', $parsed['recipient']);
        $this->assertCount(1, $parsed['lines']);
        $this->assertSame(100.0, $parsed['lines'][0]['unit_net']);
        $this->assertSame(300.0, $parsed['lines'][0]['total_net']);
        $this->assertSame(19.0, $parsed['lines'][0]['tax_rate']);
    }

    public function test_sync_missing_loads_lines_texts_and_article_links(): void {
        LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'article_number' => 'DCF-IT-MCLD-O001BP',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $invoice = $this->voucher('inv-1', 'RE/2025/0001', '2025-08-06');
        $gone = $this->voucher('inv-gone', 'RE/2025/0002', '2025-08-07');
        $booking = $this->voucher('bk-1', '10021-01-2025', '2025-01-05', 'salesinvoice');
        $this->fakeInvoices();

        $sync = (new LexofficeVoucherLineSync('lex-key', 'https://api.lexoffice.io/v1'))->withoutThrottle();
        $result = $sync->syncMissing($this->organization, 10);
        $this->assertSame(['synced' => 2, 'lines' => 2, 'failed' => 0, 'remaining' => 0], $result);

        $invoice->refresh();
        $this->assertNotNull($invoice->lines_synced_at);
        $this->assertSame('Rechnung Lizenzen für Steuerbüro Kaik Danke', $invoice->voucher_text);
        $this->assertSame('LDS Systems GmbH', $invoice->recipient_name);
        $lines = LexofficeVoucherLine::query()->where('voucher_id', $invoice->id)->orderBy('position')->get();
        $this->assertCount(2, $lines, 'Textposition entfällt');
        $this->assertSame('Microsoft 365 Business Premium', $lines[0]->name);
        $this->assertSame('20.6000', $lines[0]->unit_net->getAmount());
        $this->assertSame('247.20', $lines[0]->total_net->getAmount());
        $this->assertSame('Monat', $lines[0]->unit_name);
        $this->assertNotNull($lines[0]->lexoffice_article_id, 'Artikel über die Lexoffice-ID verknüpft');
        $this->assertSame('81.0000', $lines[1]->unit_net->getAmount(), '107,10 brutto → 90 netto − 10 % Rabatt');
        $this->assertSame(3, $lines[1]->position, 'Position zählt die Textzeile mit');

        $this->assertNotNull($gone->fresh()?->lines_synced_at, 'gelöschte Rechnung gilt als erledigt');
        $this->assertNull($booking->fresh()?->lines_synced_at, 'Buchungsbelege werden nie geladen');

        // Zweiter Lauf: nichts mehr offen.
        $this->assertSame(['synced' => 0, 'lines' => 0, 'failed' => 0, 'remaining' => 0], $sync->syncMissing($this->organization, 10));
    }

    public function test_backfill_command_runs_per_enabled_organization(): void {
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $this->voucher('inv-1', 'RE/2025/0001', '2025-08-06');
        $this->fakeInvoices();

        $this->artisan('lexoffice:sync-voucher-lines', ['--limit' => 5])
            ->expectsOutputToContain('1 Rechnungen, 2 Positionen')
            ->assertSuccessful();
        $this->assertSame(2, LexofficeVoucherLine::query()->count());
    }

    public function test_mirrored_reader_serves_lines_and_finds_contacts(): void {
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH', 'number' => '10021']);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-lds',
            'referenceable_type' => $partner->getMorphClass(),
            'referenceable_id' => $partner->getKey(),
        ]);
        $this->voucher('inv-1', 'RE/2025/0001', '2025-08-06', 'invoice', $partner->id);
        $this->fakeInvoices();
        (new LexofficeVoucherLineSync('lex-key', 'https://api.lexoffice.io/v1'))->withoutThrottle()->syncMissing($this->organization, 10);

        $reader = new MirroredInvoiceLineReader($this->organization->id);
        $this->assertTrue($reader->hasLines());
        $lines = $reader->linesForContact('c-lds', CarbonImmutable::parse('2025-01-01'), CarbonImmutable::parse('2025-12-31'));
        $this->assertCount(2, $lines);
        $this->assertSame('RE/2025/0001', $lines[0]->voucherNumber);
        $this->assertSame(12.0, $lines[0]->quantity);
        $this->assertSame(2060, $lines[0]->unitNet->getMinorAmount());
        $this->assertSame('art-bp', $lines[0]->articleId);
        $this->assertStringContainsString('Steuerbüro Kaik', $lines[0]->voucherText);
        $this->assertSame('LDS Systems GmbH', $lines[0]->recipient);
        $this->assertSame([], $reader->linesForContact('c-lds', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31')));

        $this->assertSame([['id' => 'c-lds', 'name' => 'LDS Systems GmbH']], $reader->findContactsByName('LDS Systems'));
        $this->assertSame([['id' => 'c-lds', 'name' => 'LDS Systems GmbH']], $reader->findContactsByNumber('10021'));
        $this->assertSame([], $reader->findContactsByName('Unbekannt'));
    }
}
