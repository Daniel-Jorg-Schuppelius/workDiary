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

use App\Enums\Reselling\LinkOrigin;
use App\Models\{Customer, ExternalReference, LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\{LexofficeInvoiceParser, LexofficePlugin, LexofficeVoucherLineSync, LexofficeVoucherSync};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Positionen im Belegspiegel (Feature 152, MVP-760 = Feature 140 Schnitt 2):
 * Nachladen je Rechnung, Artikelbezug, Belegtexte, Backfill-Befehl; seit dem
 * Review 2026-09-10: In-Place-Refresh (B1), Entwürfe/Änderungen (B20),
 * Ratenlimit über den Client (C6), Fehlermarker mit Backoff (C7),
 * Fehlerisolation (C8).
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

    /** @var list<string> Mitschnitt der Log-Meldungen (siehe listenToLog) */
    private array $logMessages = [];

    private function fakeInvoices(): void {
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices/inv-1' => FakePluginHttp::response($this->invoicePayload()),
            'https://api.lexoffice.io/v1/invoices/inv-gone' => FakePluginHttp::response(['message' => 'not found'], 404),
        ]);
    }

    /** @return array<string, mixed> */
    private function invoicePayload(): array {
        return [
            'id' => 'inv-1', 'title' => 'Rechnung', 'introduction' => 'Lizenzen für Steuerbüro Kaik', 'remark' => 'Danke', 'address' => ['name' => 'LDS Systems GmbH'],
            'taxConditions' => ['taxType' => 'net'], 'totalPrice' => ['currency' => 'EUR'],
            'shippingConditions' => ['shippingDate' => '2025-08-05T00:00:00.000+02:00', 'shippingEndDate' => '2026-08-04T00:00:00.000+02:00', 'shippingType' => 'serviceperiod'],
            'lineItems' => [
                ['type' => 'service', 'id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'description' => '', 'quantity' => 12, 'unitName' => 'Monat', 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 20.6, 'grossAmount' => 24.51, 'taxRatePercentage' => 19]],
                ['type' => 'text', 'name' => 'Hinweis', 'description' => 'nur Text'],
                ['type' => 'custom', 'name' => 'Business Support', 'description' => 'Stunden', 'quantity' => 2, 'unitPrice' => ['currency' => 'EUR', 'grossAmount' => 107.10, 'taxRatePercentage' => 19], 'discountPercentage' => 10],
            ],
        ];
    }

    /** Abo + Periode + Rechnungsbezug auf die Position (Reselling-Register). */
    private function periodLink(LexofficeVoucherLine $line, ?int $articleId = null, ?string $note = null): ResalePeriodLink {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'lexoffice_article_id' => $articleId, 'quantity' => 1, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ]);
        $period = ResalePeriod::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $subscription->id, 'starts_on' => '2025-08-05', 'ends_on' => '2026-08-04', 'quantity' => 1, 'currency' => 'EUR', 'status' => 'billed',
        ]);

        return ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $period->id, 'subscription_id' => $subscription->id,
            'linkable_type' => $line->getMorphClass(), 'linkable_id' => $line->id, 'voucher_number' => $line->voucher->voucher_number, 'voucher_date' => $line->voucher->voucher_date,
            'quantity' => 1, 'months' => 12, 'amount' => '247.20', 'currency' => 'EUR', 'origin' => LinkOrigin::Confirmed, 'note' => $note, 'confirmed_at' => now(),
        ]);
    }

    private function listenToLog(): void {
        Log::listen(function (MessageLogged $event): void {
            $this->logMessages[] = $event->message;
        });
    }

    /** @return list<string> nur die Meldungen des Positions-Syncs (Toolkit-Debug-Logs ausgeblendet) */
    private function syncLog(): array {
        return array_values(array_filter($this->logMessages, static fn(string $m): bool => str_starts_with($m, 'LexofficeVoucherLineSync:')));
    }

    private function lineSync(): LexofficeVoucherLineSync {
        return (new LexofficeVoucherLineSync('lex-key', 'https://api.lexoffice.io/v1'))->withoutThrottle();
    }

    private function linkContact(string $externalId, Customer $customer): void {
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => $externalId, 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
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
        $this->assertNull($parsed['service_from'], 'ohne shippingConditions kein Leistungszeitraum');

        // Leistungsdatum (Einzeltag) → nur Beginn; Zeitraum → Beginn und Ende; „none" → nichts.
        $single = LexofficeInvoiceParser::parse(['lineItems' => [], 'shippingConditions' => ['shippingDate' => '2025-08-05T00:00:00.000+02:00', 'shippingType' => 'service']]);
        $this->assertSame(['2025-08-05', null], [$single['service_from'], $single['service_to']]);
        $period = LexofficeInvoiceParser::parse(['lineItems' => [], 'shippingConditions' => ['shippingDate' => '2025-08-05T00:00:00.000+02:00', 'shippingEndDate' => '2026-08-04T00:00:00.000+02:00', 'shippingType' => 'serviceperiod']]);
        $this->assertSame(['2025-08-05', '2026-08-04'], [$period['service_from'], $period['service_to']]);
        $none = LexofficeInvoiceParser::parse(['lineItems' => [], 'shippingConditions' => ['shippingDate' => '2025-08-05T00:00:00.000+02:00', 'shippingType' => 'none']]);
        $this->assertNull($none['service_from']);
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
        $this->assertSame('05.08.2025 – 04.08.2026', $invoice->servicePeriodLabel());
        $this->assertSame(12, $invoice->serviceMonths());
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

        // Zweiter Lauf: nichts mehr offen. --refresh markiert alles neu (Buchungsbelege bleiben außen vor).
        $this->assertSame(['synced' => 0, 'lines' => 0, 'failed' => 0, 'remaining' => 0], $sync->syncMissing($this->organization, 10));
        $this->assertSame(2, $sync->resetSynced($this->organization));
        $this->assertSame(2, $sync->syncMissing($this->organization, 10)['synced']);
        $this->assertCount(2, LexofficeVoucherLine::query()->where('voucher_id', $invoice->id)->get(), 'Neuladen ersetzt, verdoppelt nicht');
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

    public function test_refresh_keeps_line_ids_and_period_links_valid(): void {
        $this->listenToLog();
        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'article_number' => 'BP',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $invoice = $this->voucher('inv-1', 'RE/2025/0001', '2025-08-06');
        $changed = $this->invoicePayload();
        // Zweiter Stand aus Lexoffice: Lizenzzeile umbenannt (gleicher Artikel), Support-Zeile entfällt.
        $changed['lineItems'][0]['name'] = 'Microsoft 365 Business Premium (Jahresvertrag)';
        unset($changed['lineItems'][2]);
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices/inv-1' => [FakePluginHttp::response($this->invoicePayload()), FakePluginHttp::response($this->invoicePayload()), FakePluginHttp::response($changed)],
        ]);

        $sync = $this->lineSync();
        $this->assertSame(1, $sync->syncMissing($this->organization, 10)['synced']);
        $licence = LexofficeVoucherLine::query()->where('voucher_id', $invoice->id)->where('position', 1)->firstOrFail();
        $support = LexofficeVoucherLine::query()->where('voucher_id', $invoice->id)->where('position', 3)->firstOrFail();
        $licenceLink = $this->periodLink($licence, $article->id);
        $supportLink = $this->periodLink($support);

        // --refresh: dieselbe Antwort erneut → IDs bleiben, Bezug bleibt gültig.
        $this->assertSame(1, $sync->resetSynced($this->organization));
        $this->assertSame(1, $sync->syncMissing($this->organization, 10)['synced']);
        $this->assertSame([$licence->id, $support->id], LexofficeVoucherLine::query()->where('voucher_id', $invoice->id)->orderBy('position')->pluck('id')->all(), 'In-Place-Upsert erneuert keine IDs');
        $this->assertInstanceOf(LexofficeVoucherLine::class, $licenceLink->fresh()?->linkable, 'Bezug zeigt weiter auf die Position');
        $this->assertSame([], $this->syncLog(), 'unveränderte Positionen lösen keine Warnung aus');
        $this->assertSame($licenceLink->id, $licence->fresh()?->periodLinks()->first()?->id);

        // Geänderter Stand: Position 1 gleicher Artikel (Umbenennung = gleiche Position), Position 3 entfällt → Bezug verwaist, aber im Log.
        $sync->resetSynced($this->organization);
        $this->assertSame(1, $sync->syncMissing($this->organization, 10)['lines']);
        $this->assertSame('Microsoft 365 Business Premium (Jahresvertrag)', $licence->fresh()?->name);
        $this->assertNull($support->fresh(), 'überzählige Position gelöscht');
        $this->assertNull($supportLink->fresh()?->linkable);
        $this->assertNotEmpty(array_filter($this->logMessages, static fn(string $m): bool => str_contains($m, 'entfällt in Lexoffice')), 'verwaister Bezug wird protokolliert');
        $this->assertEmpty(array_filter($this->logMessages, static fn(string $m): bool => str_contains($m, 'fachlich geändert')), 'gleicher Artikel = gleiche Position');
    }

    public function test_changed_article_at_same_position_is_logged_but_updated(): void {
        $this->listenToLog();
        $invoice = $this->voucher('inv-1', 'RE/2025/0001', '2025-08-06');
        $other = $this->invoicePayload();
        $other['lineItems'][0]['id'] = 'art-exo';
        $other['lineItems'][0]['name'] = 'Exchange Online (Plan 1)';
        FakePluginHttp::fake(['https://api.lexoffice.io/v1/invoices/inv-1' => [FakePluginHttp::response($this->invoicePayload()), FakePluginHttp::response($other)]]);

        $sync = $this->lineSync();
        $sync->syncMissing($this->organization, 10);
        $line = LexofficeVoucherLine::query()->where('voucher_id', $invoice->id)->where('position', 1)->firstOrFail();
        $link = $this->periodLink($line);
        $sync->syncVoucher($invoice->fresh() ?? $invoice);

        $this->assertSame('Exchange Online (Plan 1)', $line->fresh()?->name, 'Position wird trotzdem aktualisiert');
        $this->assertSame($line->id, (int) $link->fresh()?->linkable_id, 'Bezug bleibt bestehen');
        $this->assertNotEmpty(array_filter($this->logMessages, static fn(string $m): bool => str_contains($m, 'fachlich geändert')));
    }

    public function test_failed_voucher_is_marked_with_backoff_and_does_not_block_others(): void {
        $this->travelTo('2026-09-10 08:00:00');
        $broken = $this->voucher('inv-err', 'RE/2025/0009', '2025-09-01');
        $fine = $this->voucher('inv-1', 'RE/2025/0001', '2025-08-06');
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices/inv-err' => [
                FakePluginHttp::response(['message' => 'boom'], 500),
                FakePluginHttp::response(['message' => 'boom'], 500),
                FakePluginHttp::response($this->invoicePayload()),
            ],
            'https://api.lexoffice.io/v1/invoices/inv-1' => FakePluginHttp::response($this->invoicePayload()),
        ]);
        $sync = $this->lineSync();

        // Neueste zuerst: der 500er-Beleg ist dran, scheitert, wird markiert …
        $this->assertSame(['synced' => 0, 'lines' => 0, 'failed' => 1, 'remaining' => 2], $sync->syncMissing($this->organization, 1));
        $this->assertSame(1, (int) $broken->fresh()?->lines_sync_attempts);
        $this->assertNotNull($broken->fresh()?->lines_sync_failed_at);
        // … und blockiert den nächsten Lauf nicht mehr: der ältere Beleg kommt dran.
        $this->assertSame(['synced' => 1, 'lines' => 2, 'failed' => 0, 'remaining' => 0], $sync->syncMissing($this->organization, 1));
        $this->assertNotNull($fine->fresh()?->lines_synced_at);
        $this->assertSame(0, $sync->syncMissing($this->organization, 10)['failed'], 'im Backoff wird nicht erneut angefragt');

        // Backoff 2^1 h: nach 1 h noch nicht, nach 3 h wieder — und scheitert erneut (2 Versuche → 4 h).
        $this->travel(1)->hours();
        $this->assertSame(0, $sync->pending($this->organization->id)->count());
        $this->travel(2)->hours();
        $this->assertSame(['synced' => 0, 'lines' => 0, 'failed' => 1, 'remaining' => 1], $sync->syncMissing($this->organization, 10));
        $this->assertSame(2, (int) $broken->fresh()?->lines_sync_attempts);
        $this->travel(3)->hours();
        $this->assertSame(0, $sync->pending($this->organization->id)->count());

        // Nach dem Backoff gelingt der Abruf: Marker zurückgesetzt.
        $this->travel(2)->hours();
        $this->assertSame(1, $sync->syncMissing($this->organization, 10)['synced']);
        $broken->refresh();
        $this->assertNotNull($broken->lines_synced_at);
        $this->assertNull($broken->lines_sync_failed_at);
        $this->assertSame(0, (int) $broken->lines_sync_attempts);
    }

    public function test_rate_limit_is_retried_by_the_client_and_counts_as_failure_when_exhausted(): void {
        $fine = $this->voucher('inv-1', 'RE/2025/0001', '2025-08-06');
        $limited = $this->voucher('inv-429', 'RE/2025/0002', '2025-08-07');
        $fake = FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices/inv-1' => [
                FakePluginHttp::response(['message' => 'Rate limit exceeded'], 429, ['Retry-After' => '1']),
                FakePluginHttp::response(['message' => 'Rate limit exceeded'], 429, ['Retry-After' => '1']),
                FakePluginHttp::response($this->invoicePayload()),
            ],
            'https://api.lexoffice.io/v1/invoices/inv-429' => FakePluginHttp::response(['message' => 'Rate limit exceeded'], 429, ['Retry-After' => '1']),
        ]);

        $result = $this->lineSync()->syncMissing($this->organization, 10);
        $this->assertSame(['synced' => 1, 'lines' => 2, 'failed' => 1, 'remaining' => 1], $result);
        $this->assertNotNull($fine->fresh()?->lines_synced_at, '429 → Retry im Client → Erfolg');
        $this->assertSame(1, (int) $limited->fresh()?->lines_sync_attempts, 'nach erschöpften Wiederholungen ein normaler Fehlschlag');
        $requests = array_filter($fake->recorded(), static fn(array $entry): bool => str_ends_with((string) $entry['request']->getUri(), '/invoices/inv-429'));
        $this->assertCount(LexofficeVoucherLineSync::MAX_RETRIES, $requests, 'keine zusätzliche Schleife über den Client-Retries');
    }

    public function test_non_api_errors_are_isolated_per_voucher(): void {
        $this->listenToLog();
        $broken = $this->voucher('inv-broken', 'RE/2025/0003', '2025-08-08');
        $fine = $this->voucher('inv-1', 'RE/2025/0001', '2025-08-06');
        FakePluginHttp::fake([
            // Kaputte Antwort (Name als Objekt) → Parserfehler statt LexofficeApiException.
            'https://api.lexoffice.io/v1/invoices/inv-broken' => FakePluginHttp::response(['lineItems' => [['type' => 'custom', 'name' => ['kein' => 'string'], 'unitPrice' => ['netAmount' => 1], 'quantity' => 1]]]),
            'https://api.lexoffice.io/v1/invoices/inv-1' => FakePluginHttp::response($this->invoicePayload()),
        ]);

        $result = $this->lineSync()->syncMissing($this->organization, 10);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, $result['synced'], 'der Lauf geht nach dem Fehler weiter');
        $this->assertNotNull($fine->fresh()?->lines_synced_at);
        $this->assertSame(1, (int) $broken->fresh()?->lines_sync_attempts);
        $this->assertNotEmpty(array_filter($this->logMessages, static fn(string $m): bool => str_contains($m, 'Positionen nicht geladen')));
    }

    public function test_drafts_are_skipped_and_changed_vouchers_reload_their_lines(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->linkContact('c-lds', $customer);
        $item = ['id' => 'inv-1', 'voucherType' => 'invoice', 'voucherStatus' => 'draft', 'voucherNumber' => 'RE/2025/0001', 'voucherDate' => '2025-08-06', 'totalAmount' => 294.17, 'currency' => 'EUR', 'updatedDate' => '2025-08-06T10:00:00.000+02:00'];
        $finalized = ['voucherStatus' => 'open', 'updatedDate' => '2025-08-07T09:00:00.000+02:00'] + $item;
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/voucherlist*' => [
                FakePluginHttp::response(['content' => [$item], 'totalPages' => 1]),
                FakePluginHttp::response(['content' => [$finalized], 'totalPages' => 1]),
            ],
            'https://api.lexoffice.io/v1/invoices/inv-1' => FakePluginHttp::response($this->invoicePayload()),
        ]);
        $sync = new LexofficeVoucherSync('lex-key', 'https://api.lexoffice.io/v1', 0.0);

        $this->assertSame(0, $sync->sync($this->organization)['lines'], 'Entwürfe werden nicht gespiegelt');
        $voucher = LexofficeVoucher::query()->where('external_id', 'inv-1')->firstOrFail();
        $this->assertNull($voucher->lines_synced_at);
        // Positionen eines alten Stands vorhanden → Finalisierung setzt den Spiegel zurück und lädt neu.
        $voucher->forceFill(['lines_synced_at' => now()])->save();

        $result = $sync->sync($this->organization);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['lines'], 'Statuswechsel/updatedDate → Positionen neu geladen');
        $voucher->refresh();
        $this->assertSame('open', $voucher->voucher_status);
        $this->assertNotNull($voucher->lines_synced_at);
        $this->assertSame(2, $voucher->lines()->count());

        // Unveränderter Stand: kein erneutes Laden.
        $before = $voucher->lines_synced_at;
        $this->assertSame(0, $sync->sync($this->organization)['lines']);
        $this->assertEquals($before, $voucher->fresh()?->lines_synced_at);
    }

    public function test_sync_vouchers_command_runs_retainer_step_even_if_voucher_sync_fails(): void {
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->linkContact('c-lds', $customer);
        FakePluginHttp::fake(['https://api.lexoffice.io/v1/voucherlist*' => FakePluginHttp::response(['message' => 'down'], 503)]);

        $this->artisan('lexoffice:sync-vouchers', ['--organization' => (string) $this->organization->id])
            ->expectsOutputToContain('Fehler:')
            ->expectsOutputToContain('Retainer: gebucht 0')
            ->assertSuccessful();
    }

    public function test_credit_notes_are_mirrored_with_negative_totals(): void {
        $creditNote = $this->voucher('cn-1', 'GS/2025/0001', '2025-09-01', 'creditnote');
        $payload = $this->invoicePayload();
        $payload['id'] = 'cn-1';
        $payload['title'] = 'Gutschrift';
        unset($payload['shippingConditions']);
        $fake = FakePluginHttp::fake(['https://api.lexoffice.io/v1/credit-notes/cn-1' => FakePluginHttp::response($payload)]);

        $sync = $this->lineSync();
        $this->assertSame(['synced' => 1, 'lines' => 2, 'failed' => 0, 'remaining' => 0], $sync->syncMissing($this->organization, 10));
        $fake->assertSent(static fn($request): bool => str_ends_with((string) $request->getUri(), '/credit-notes/cn-1'));
        $fake->assertNotSent(static fn($request): bool => str_contains((string) $request->getUri(), '/invoices/'));

        $line = LexofficeVoucherLine::query()->where('voucher_id', $creditNote->id)->where('position', 1)->firstOrFail();
        $this->assertSame('12.000', $line->quantity, 'Menge bleibt positiv (LicenseMonths rechnet damit)');
        $this->assertSame('20.6000', $line->unit_net->getAmount(), 'Stückpreis bleibt positiv');
        $this->assertSame('-247.20', $line->total_net->getAmount(), 'Positionsbetrag der Gutschrift ist negativ');
        $this->assertTrue($line->isCreditNote());
        $this->assertNotNull($creditNote->fresh()?->lines_synced_at);

        // --refresh markiert auch Gutschriften neu.
        $this->assertSame(1, $sync->resetSynced($this->organization));
    }

    public function test_all_option_loops_in_chunks_until_nothing_is_pending_and_skips_failures_in_backoff(): void {
        // Review 2026-09-10 (G): `--all --limit 2` lädt in Häppchen, bis nichts mehr fehlt; ein Fehlschlag im Häppchen
        // hält die Schleife nicht an und wird in der nächsten Runde nicht erneut angefragt (Backoff).
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $newest = $this->voucher('inv-3', 'RE/2025/0003', '2025-08-08');
        $broken = $this->voucher('inv-broken', 'RE/2025/0002', '2025-08-07');
        $oldest = $this->voucher('inv-1', 'RE/2025/0001', '2025-08-06');
        $fake = FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices/inv-3' => FakePluginHttp::response($this->invoicePayload()),
            // Kaputte Antwort → Parserfehler, sofortiger Fehlschlag ohne Client-Retry.
            'https://api.lexoffice.io/v1/invoices/inv-broken' => FakePluginHttp::response(['lineItems' => [['type' => 'custom', 'name' => ['kein' => 'string'], 'unitPrice' => ['netAmount' => 1], 'quantity' => 1]]]),
            'https://api.lexoffice.io/v1/invoices/inv-1' => FakePluginHttp::response($this->invoicePayload()),
        ]);

        $this->artisan('lexoffice:sync-voucher-lines', ['--limit' => 2, '--all' => true])
            ->expectsOutputToContain('1 Rechnungen, 2 Positionen, 1 Fehler, 2 offen')
            ->expectsOutputToContain('1 Rechnungen, 2 Positionen, 0 Fehler, 0 offen')
            ->assertSuccessful();

        $this->assertNotNull($newest->fresh()?->lines_synced_at);
        $this->assertNotNull($oldest->fresh()?->lines_synced_at, 'zweites Häppchen geladen');
        $this->assertNull($broken->fresh()?->lines_synced_at);
        $this->assertSame(1, (int) $broken->fresh()?->lines_sync_attempts, 'im Backoff: in Runde zwei nicht erneut angefragt');
        $this->assertSame(4, LexofficeVoucherLine::query()->count());
        $requests = array_map(static fn(array $entry): string => (string) $entry['request']->getUri(), $fake->recorded());
        $this->assertSame(1, count(array_filter($requests, static fn(string $uri): bool => str_ends_with($uri, '/invoices/inv-broken'))));
        $this->assertCount(3, $requests, 'jeder Beleg genau einmal');

        // Ohne --all bleibt es bei einem Häppchen.
        $this->lineSync()->resetSynced($this->organization);
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices/inv-3' => FakePluginHttp::response($this->invoicePayload()),
            'https://api.lexoffice.io/v1/invoices/inv-1' => FakePluginHttp::response($this->invoicePayload()),
        ]);
        $this->artisan('lexoffice:sync-voucher-lines', ['--limit' => 1])
            ->expectsOutputToContain('1 Rechnungen, 2 Positionen, 0 Fehler, 1 offen')
            ->assertSuccessful();
        $this->assertNull($oldest->fresh()?->lines_synced_at, 'nur ein Häppchen ohne --all');
    }

    public function test_delivery_period_counts_as_service_period_and_an_end_before_the_start_is_dropped(): void {
        // Review 2026-09-10 (G, A13): shippingType „deliveryperiod" ist ebenso ein Zeitraum wie „serviceperiod";
        // ein Ende vor dem Beginn wird verworfen (nur der Beginn bleibt), ein Einzeldatum liefert nur den Beginn.
        $delivery = LexofficeInvoiceParser::parse(['lineItems' => [], 'shippingConditions' => ['shippingDate' => '2025-08-05T00:00:00.000+02:00', 'shippingEndDate' => '2026-08-04T00:00:00.000+02:00', 'shippingType' => 'deliveryperiod']]);
        $this->assertSame(['2025-08-05', '2026-08-04'], [$delivery['service_from'], $delivery['service_to']]);
        $single = LexofficeInvoiceParser::parse(['lineItems' => [], 'shippingConditions' => ['shippingDate' => '2025-08-05T00:00:00.000+02:00', 'shippingEndDate' => '2026-08-04T00:00:00.000+02:00', 'shippingType' => 'delivery']]);
        $this->assertSame(['2025-08-05', null], [$single['service_from'], $single['service_to']], 'Einzeldatum: das Ende zählt nicht');
        $reversed = LexofficeInvoiceParser::parse(['lineItems' => [], 'shippingConditions' => ['shippingDate' => '2026-08-04T00:00:00.000+02:00', 'shippingEndDate' => '2025-08-05T00:00:00.000+02:00', 'shippingType' => 'serviceperiod']]);
        $this->assertSame(['2026-08-04', null], [$reversed['service_from'], $reversed['service_to']], 'Ende vor Beginn wird verworfen');
        $sameDay = LexofficeInvoiceParser::parse(['lineItems' => [], 'shippingConditions' => ['shippingDate' => '2025-08-05T00:00:00.000+02:00', 'shippingEndDate' => '2025-08-05T00:00:00.000+02:00', 'shippingType' => 'deliveryperiod']]);
        $this->assertSame(['2025-08-05', '2025-08-05'], [$sameDay['service_from'], $sameDay['service_to']], 'gleicher Tag ist ein gültiger Zeitraum');
        $onlyEnd = LexofficeInvoiceParser::parse(['lineItems' => [], 'shippingConditions' => ['shippingEndDate' => '2026-08-04T00:00:00.000+02:00', 'shippingType' => 'deliveryperiod']]);
        $this->assertSame([null, null], [$onlyEnd['service_from'], $onlyEnd['service_to']], 'ohne Beginn kein Zeitraum');

        // Über den Sync landet der Lieferzeitraum am Beleg; der verworfene Endwert bleibt leer.
        $invoice = $this->voucher('inv-dp', 'RE/2025/0010', '2025-08-06');
        $twisted = $this->voucher('inv-tw', 'RE/2025/0011', '2025-08-07');
        $payload = $this->invoicePayload();
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices/inv-dp' => FakePluginHttp::response(['shippingConditions' => ['shippingDate' => '2025-08-05T00:00:00.000+02:00', 'shippingEndDate' => '2026-08-04T00:00:00.000+02:00', 'shippingType' => 'deliveryperiod']] + $payload),
            'https://api.lexoffice.io/v1/invoices/inv-tw' => FakePluginHttp::response(['shippingConditions' => ['shippingDate' => '2026-08-04T00:00:00.000+02:00', 'shippingEndDate' => '2025-08-05T00:00:00.000+02:00', 'shippingType' => 'deliveryperiod']] + $payload),
        ]);
        $this->assertSame(2, $this->lineSync()->syncMissing($this->organization, 10)['synced']);
        $invoice->refresh();
        $this->assertSame('2025-08-05', $invoice->service_starts_on?->toDateString());
        $this->assertSame('2026-08-04', $invoice->service_ends_on?->toDateString());
        $this->assertSame(12, $invoice->serviceMonths(), 'Lieferzeitraum zählt als Leistungszeitraum für die Lizenzmonate');
        $twisted->refresh();
        $this->assertSame('2026-08-04', $twisted->service_starts_on?->toDateString());
        $this->assertNull($twisted->service_ends_on, 'Ende vor Beginn: nicht gespeichert');
        $this->assertNull($twisted->serviceMonths());
    }

    public function test_pending_backoff_caps_at_seven_days_from_the_eighth_attempt(): void {
        // Review 2026-09-10 (C7/G): 2^n Stunden je Versuch (1 → 2 h … 7 → 128 h), ab dem achten Versuch fest 7 Tage;
        // Obergrenze halboffen — genau an der Grenze noch nicht fällig.
        $this->travelTo('2026-09-10 08:00:00');
        $sync = $this->lineSync();
        $voucher = $this->voucher('inv-old', 'RE/2025/0020', '2025-08-06');
        $pending = fn(): int => $sync->pending($this->organization->id)->count();
        $failedAt = static fn(int $hours, int $seconds = 0): string => now()->subHours($hours)->subSeconds($seconds)->toDateTimeString();

        // Sieben Versuche: 128 h.
        $voucher->forceFill(['lines_sync_attempts' => 7, 'lines_sync_failed_at' => $failedAt(127)])->save();
        $this->assertSame(0, $pending(), '7 Versuche, 127 h: noch im Backoff');
        $voucher->forceFill(['lines_sync_failed_at' => $failedAt(128)])->save();
        $this->assertSame(0, $pending(), 'genau 128 h: Grenze halboffen');
        $voucher->forceFill(['lines_sync_failed_at' => $failedAt(128, 1)])->save();
        $this->assertSame(1, $pending(), '128 h + 1 s: fällig');

        // Ab acht Versuchen: 7 Tage — nicht 256 h, nicht länger.
        $voucher->forceFill(['lines_sync_attempts' => 8, 'lines_sync_failed_at' => $failedAt(167)])->save();
        $this->assertSame(0, $pending(), '8 Versuche, 167 h: noch nicht');
        $voucher->forceFill(['lines_sync_failed_at' => $failedAt(LexofficeVoucherLineSync::MAX_BACKOFF_HOURS)])->save();
        $this->assertSame(0, $pending(), 'genau 168 h: Grenze halboffen');
        $voucher->forceFill(['lines_sync_failed_at' => $failedAt(LexofficeVoucherLineSync::MAX_BACKOFF_HOURS, 1)])->save();
        $this->assertSame(1, $pending(), 'nach 7 Tagen wieder fällig');
        $voucher->forceFill(['lines_sync_attempts' => 40, 'lines_sync_failed_at' => $failedAt(200)])->save();
        $this->assertSame(1, $pending(), 'auch nach vielen Versuchen bleibt es bei 7 Tagen (kein 2^40)');
        $voucher->forceFill(['lines_sync_failed_at' => $failedAt(100)])->save();
        $this->assertSame(0, $pending());

        // Null Versuche mit Fehlermarker (Altbestand) zählt wie ein Versuch: 2 h.
        $voucher->forceFill(['lines_sync_attempts' => 0, 'lines_sync_failed_at' => $failedAt(1)])->save();
        $this->assertSame(0, $pending());
        $voucher->forceFill(['lines_sync_failed_at' => $failedAt(2, 1)])->save();
        $this->assertSame(1, $pending());

        // Erfolg räumt den Marker ab, unabhängig vom Zähler.
        FakePluginHttp::fake(['https://api.lexoffice.io/v1/invoices/inv-old' => FakePluginHttp::response($this->invoicePayload())]);
        $voucher->forceFill(['lines_sync_attempts' => 12, 'lines_sync_failed_at' => $failedAt(500)])->save();
        $this->assertSame(['synced' => 1, 'lines' => 2, 'failed' => 0, 'remaining' => 0], $sync->syncMissing($this->organization, 10));
        $voucher->refresh();
        $this->assertNull($voucher->lines_sync_failed_at);
        $this->assertSame(0, (int) $voucher->lines_sync_attempts);
        $this->assertSame(0, $pending(), 'gespiegelt: nicht mehr offen');
    }
}
