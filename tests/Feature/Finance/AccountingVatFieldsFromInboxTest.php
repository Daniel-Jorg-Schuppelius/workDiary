<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVatFieldsFromInboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{PostingSourceKind, ProfitDetermination};
use App\Enums\Invoicing\{IncomingEInvoiceStatus, InvoiceStatus};
use App\Models\Accounting\AccountingEntry;
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, Invoice};
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsTemplateService, FiscalYearService, JournalService};
use App\Services\Accounting\Filing\VatFieldBreakdownService;
use App\Services\Accounting\Posting\{PostingInboxService, PostingSourceRegistry};
use App\Services\Accounting\Reports\VatPreviewBuilder;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kennziffern der Voranmeldung für Buchungen aus der Buchungs-Inbox mit der
 * SKR03-Vorlage (Nachzug zu MVP-1558): Bemessungsgrundlage ist der
 * Nettobetrag, nicht die Steuer — auch nach einem Storno.
 */
class AccountingVatFieldsFromInboxTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private CarbonImmutable $startsOn;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $this->startsOn = CarbonImmutable::create(2026, 1, 1);
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $this->startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $this->startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);
        app(ChartOfAccountsTemplateService::class)->apply($this->org, 'skr03', $this->startsOn);
    }

    private function postFromInbox(PostingSourceKind $kind, Invoice|IncomingEInvoice $source): AccountingEntry {
        $proposal = app(PostingSourceRegistry::class)->for($kind)->proposalFor($this->org, $source);
        $this->assertSame([], $proposal->blockers);

        return app(JournalService::class)->post(app(PostingInboxService::class)->prepare($this->org, $proposal, $this->admin), $this->admin);
    }

    private function salesInvoice(): Invoice {
        return Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->org->id])->id,
            'number' => 'RE-2026-200',
            'status' => InvoiceStatus::Issued,
            'issued_on' => '2026-02-10',
            'currency' => 'EUR',
            'subtotal' => '310.53',
            'tax_amount' => '47.00',
            'total' => '357.53',
            'tax_breakdown' => [
                ['rate' => '19.00', 'net' => '200.00', 'tax' => '38.00'],
                ['rate' => '7.00', 'net' => '100.00', 'tax' => '7.00'],
                // Zweite Gruppe zum selben Satz: Die Steuerzeilen stehen nicht paarweise neben dem Erlös.
                ['rate' => '19.00', 'net' => '10.53', 'tax' => '2.00'],
            ],
        ])->refresh();
    }

    private function incomingInvoice(): IncomingEInvoice {
        return IncomingEInvoice::query()->create([
            'organization_id' => $this->org->id,
            'document_id' => Document::factory()->create(['organization_id' => $this->org->id])->id,
            'sha256' => hash('sha256', 'vat-fields-incoming'),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoiceStatus::Approved,
            'invoice_number' => 'ER-200',
            'seller_name' => 'Lieferant Beispiel',
            'issue_date' => '2026-02-12',
            'currency' => 'EUR',
            'amount_net' => '50.00',
            'amount_tax' => '9.50',
            'amount_gross' => '59.50',
        ]);
    }

    /** @return array<string, array{base: string, tax: string}> */
    private function fields(): array {
        $breakdown = app(VatFieldBreakdownService::class)->forRange($this->org, $this->startsOn, $this->startsOn->endOfYear());
        $this->assertSame([], $breakdown['unclear']);

        return collect($breakdown['fields'])->mapWithKeys(fn (array $field): array => [(string) $field['field'] => ['base' => $field['base'], 'tax' => $field['tax']]])->all();
    }

    public function test_inbox_postings_report_the_net_amount_as_tax_base(): void {
        $this->postFromInbox(PostingSourceKind::SalesInvoice, $this->salesInvoice());
        $this->postFromInbox(PostingSourceKind::IncomingInvoice, $this->incomingInvoice());

        $fields = $this->fields();
        // Kennziffer 81: Umsätze zu 19 % — Bemessungsgrundlage netto.
        $this->assertSame('210.53', $fields['81']['base'] ?? null);
        // Kennziffer 86: Umsätze zu 7 %.
        $this->assertSame('100.00', $fields['86']['base'] ?? null);
        // Kennziffer 66: abziehbare Vorsteuer.
        $this->assertSame('9.50', $fields['66']['tax'] ?? null);

        // Die kontenbasierte Vorschau bleibt die Gegenprobe.
        $preview = app(VatPreviewBuilder::class)->build($this->org, $this->startsOn, $this->startsOn->endOfYear());
        $this->assertSame(['47.00', '9.50', '37.50'], [$preview['output'], $preview['input'], $preview['payable']]);
    }

    /** MVP-1543: Das Storno hebt Bemessungsgrundlage und Steuer auf. */
    public function test_a_reversal_cancels_the_fields_of_an_inbox_posting(): void {
        $sales = $this->postFromInbox(PostingSourceKind::SalesInvoice, $this->salesInvoice());
        $incoming = $this->postFromInbox(PostingSourceKind::IncomingInvoice, $this->incomingInvoice());

        app(JournalService::class)->reverse($sales, 'Falsch gebucht', $this->admin);
        app(JournalService::class)->reverse($incoming, 'Falsch gebucht', $this->admin);

        foreach ($this->fields() as $field) {
            $this->assertSame(['0.00', '0.00'], [$field['base'], $field['tax']]);
        }
    }

    /**
     * Rechnung ohne Steuer mit eingefrorener Steuerkategorie.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function zeroRatedInvoice(string $number, string $category, string $kind, string $net, array $attributes = []): Invoice {
        return Invoice::query()->create($attributes + [
            'organization_id' => $this->org->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->org->id, 'vat_id' => 'ATU12345678'])->id,
            'number' => $number,
            'status' => InvoiceStatus::Issued,
            'type' => Invoice::TYPE_INVOICE,
            'category' => $kind,
            'issued_on' => '2026-03-05',
            'currency' => 'EUR',
            'subtotal' => $net,
            'tax_amount' => '0.00',
            'total' => $net,
            'is_reverse_charge' => $category === 'AE',
            'tax_breakdown' => [['rate' => '0.00', 'net' => $net, 'tax' => '0.00']],
            'tax_context' => ['category' => $category],
        ])->refresh();
    }

    /** Neue Form: Kennzeichen und Steuer an der Erlös- bzw. Aufwandszeile, die Steuerzeile trägt keines. */
    public function test_inbox_postings_tag_the_net_line(): void {
        $sales = $this->postFromInbox(PostingSourceKind::SalesInvoice, $this->salesInvoice())->load('lines.account', 'lines.taxCode');
        $incoming = $this->postFromInbox(PostingSourceKind::IncomingInvoice, $this->incomingInvoice())->load('lines.account', 'lines.taxCode');

        $tags = fn (AccountingEntry $entry): array => $entry->lines
            ->map(fn ($line): array => [$line->account->number, $line->taxCode?->code, $line->tax_amount?->getAmount()])->all();
        $this->assertSame([
            ['1400', null, null],
            ['8400', 'USt19', '38.00'],
            ['1776', null, null],
            ['8300', 'USt7', '7.00'],
            ['1771', null, null],
            ['8400', 'USt19', '2.00'],
            ['1776', null, null],
        ], $tags($sales));
        $this->assertSame([
            ['4900', 'VSt19', '9.50'],
            ['1576', null, null],
            ['1600', null, null],
        ], $tags($incoming));
    }

    /** Umsätze ohne Steuer erscheinen in ihrer Kennziffer; die kontenbasierte Vorschau bleibt bei null. */
    public function test_zero_rated_sales_reach_their_field_by_tax_category(): void {
        $this->postFromInbox(PostingSourceKind::SalesInvoice, $this->zeroRatedInvoice('RE-IGL', 'AE', Invoice::CATEGORY_MATERIAL, '1000.00'));
        $this->postFromInbox(PostingSourceKind::SalesInvoice, $this->zeroRatedInvoice('RE-RC', 'AE', Invoice::CATEGORY_SERVICE, '500.00'));
        $this->postFromInbox(PostingSourceKind::SalesInvoice, $this->zeroRatedInvoice('RE-AUSF', 'G', Invoice::CATEGORY_MATERIAL, '300.00'));
        $this->postFromInbox(PostingSourceKind::SalesInvoice, $this->zeroRatedInvoice('RE-NSTB', 'G', Invoice::CATEGORY_SERVICE, '200.00'));

        $fields = $this->fields();
        $this->assertEquals(
            ['41' => '1000.00', '21' => '500.00', '43' => '300.00', '45' => '200.00'],
            array_map(static fn (array $field): string => $field['base'], $fields),
        );
        foreach (['41', '21', '43', '45'] as $field) {
            $this->assertSame('0.00', $fields[$field]['tax']);
        }

        $preview = app(VatPreviewBuilder::class)->build($this->org, $this->startsOn, $this->startsOn->endOfYear());
        $this->assertSame(['0.00', '0.00'], [$preview['output'], $preview['input']]);

        // Lieferung (Kz 41) und sonstige Leistung (Kz 21) stehen mit der USt-IdNr. und ihrer Art in der Zusammenfassenden Meldung (MVP-1622).
        $report = app(\App\Services\Accounting\Filing\RecapitulativeStatementService::class)->report($this->org, $this->startsOn, $this->startsOn->endOfYear());
        $this->assertSame(['1500.00', '1000.00', '500.00'], [$report['total'], $report['total_goods'], $report['total_services']]);
        $this->assertSame([['ATU12345678', 'goods', '1000.00'], ['ATU12345678', 'services', '500.00']], array_map(static fn (array $row): array => [$row['vat_id'], $row['kind'], $row['amount']], $report['rows']));
        $this->assertSame([], $report['unclear']);
    }

    /** Steuerfrei ohne nähere Kategorie ist ein Klärungsfall (Kennzeichen ohne Kennziffer), kein stiller Ausfall. */
    public function test_a_plain_tax_free_sale_is_listed_as_unclear(): void {
        $this->postFromInbox(PostingSourceKind::SalesInvoice, $this->zeroRatedInvoice('RE-FREI', 'S', Invoice::CATEGORY_SERVICE, '80.00'));

        $breakdown = app(VatFieldBreakdownService::class)->forRange($this->org, $this->startsOn, $this->startsOn->endOfYear());
        $this->assertSame([], $breakdown['fields']);
        $this->assertCount(1, $breakdown['unclear']);
        $this->assertStringContainsString('FREI', $breakdown['unclear'][0]);
    }

    /** Kleinunternehmer melden keine Kennziffern: kein Kennzeichen an der Erlöszeile. */
    public function test_small_business_sales_carry_no_tax_code(): void {
        $settings = (array) $this->org->settings;
        $settings['einvoice']['small_business'] = '1';
        $this->org->forceFill(['settings' => $settings])->save();

        $entry = $this->postFromInbox(PostingSourceKind::SalesInvoice, $this->zeroRatedInvoice('RE-KU', 'E', Invoice::CATEGORY_SERVICE, '80.00'));

        $this->assertSame([], array_filter($entry->lines->pluck('accounting_tax_code_id')->all()));
        $breakdown = app(VatFieldBreakdownService::class)->forRange($this->org, $this->startsOn, $this->startsOn->endOfYear());
        $this->assertSame([[], []], [$breakdown['fields'], $breakdown['unclear']]);
    }

    /** Altbuchung in alter Form bleibt richtig; trägt eine Buchung das Kennzeichen an Netto- UND Steuerzeile, zählt sie einmal. */
    public function test_old_form_stays_right_and_both_forms_are_never_counted_twice(): void {
        $account = fn (string $number): int => \App\Models\Accounting\AccountingAccount::query()->where('organization_id', $this->org->id)->where('number', $number)->sole()->id;
        $code = \App\Models\Accounting\AccountingTaxCode::query()->where('organization_id', $this->org->id)->where('code', 'USt19')->sole();
        $journal = app(JournalService::class);

        $journal->postDirect($this->org, [
            'booked_on' => CarbonImmutable::create(2026, 2, 1),
            'memo' => 'Alte Form',
            'lines' => [
                ['accounting_account_id' => $account('1400'), 'debit' => '119.00', 'credit' => '0.00'],
                ['accounting_account_id' => $account('8400'), 'debit' => '0.00', 'credit' => '100.00'],
                ['accounting_account_id' => $account('1776'), 'debit' => '0.00', 'credit' => '19.00', 'accounting_tax_code_id' => $code->id, 'tax_amount' => '19.00'],
            ],
        ], $this->admin);
        $this->assertSame(['base' => '100.00', 'tax' => '0.00'], $this->fields()['81']);

        $journal->postDirect($this->org, [
            'booked_on' => CarbonImmutable::create(2026, 2, 2),
            'memo' => 'Beide Formen',
            'lines' => [
                ['accounting_account_id' => $account('1400'), 'debit' => '238.00', 'credit' => '0.00'],
                ['accounting_account_id' => $account('8400'), 'debit' => '0.00', 'credit' => '200.00', 'accounting_tax_code_id' => $code->id, 'tax_amount' => '38.00'],
                ['accounting_account_id' => $account('1776'), 'debit' => '0.00', 'credit' => '38.00', 'accounting_tax_code_id' => $code->id, 'tax_amount' => '38.00'],
            ],
        ], $this->admin);
        $this->assertSame('300.00', $this->fields()['81']['base']);
    }

    /** Nachzug für bestehende Kontenpläne aus der Vorlage: Steuerkategorien, fehlende Kennzeichen, Skontokonto 7 %. */
    public function test_the_backfill_adds_zero_rated_tax_codes_idempotently(): void {
        $codes = \App\Models\Accounting\AccountingTaxCode::query()->where('organization_id', $this->org->id);
        // Stand vor dem Nachzug: auch RC60 (MVP-1622) gab es noch nicht.
        (clone $codes)->whereIn('code', ['RC21', 'AUSF', 'NSTB', 'RC60'])->delete();
        (clone $codes)->whereIn('code', ['IGL', 'FREI'])->update(['tax_category' => null]);
        \App\Models\Accounting\AccountingAccount::query()->where('organization_id', $this->org->id)->where('number', '3731')->delete();
        // Eigene Entscheidung der Organisation: ein eigenes Kennzeichen für die Ausfuhr bleibt das einzige.
        \App\Models\Accounting\AccountingTaxCode::query()->create([
            'organization_id' => $this->org->id, 'code' => 'EXPORT', 'name' => 'Eigene Ausfuhr', 'direction' => 'none', 'rate' => '0.00',
            'tax_category' => 'G', 'valid_from' => '2026-01-01', 'is_active' => true,
        ]);
        $foreign = Organization::factory()->create();

        $migration = require database_path('migrations/2027_04_17_120300_add_zero_rated_tax_codes_and_reduced_discount_accounts.php');
        $migration->up();
        $migration->up();

        $this->assertEquals(
            ['EXPORT' => 'G', 'FREI' => 'E', 'IGL' => 'K', 'NSTB' => 'O', 'RC21' => 'AE'],
            (clone $codes)->whereIn('code', ['IGL', 'FREI', 'RC21', 'AUSF', 'NSTB', 'EXPORT'])->pluck('tax_category', 'code')->all(),
        );
        $this->assertSame(0, (clone $codes)->where('code', 'AUSF')->count());
        $this->assertSame('21', (clone $codes)->where('code', 'RC21')->sole()->ustva_base_field);
        $this->assertSame(1, \App\Models\Accounting\AccountingAccount::query()->where('organization_id', $this->org->id)->where('number', '3731')->count());
        $this->assertSame(0, \App\Models\Accounting\AccountingTaxCode::query()->withoutGlobalScopes()->where('organization_id', $foreign->id)->count());
    }
}
