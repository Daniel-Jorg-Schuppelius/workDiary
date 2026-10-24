<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenSourceFakturaVoucherPullTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\Vouchers;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Plugins\Akaunting\AkauntingPlugin;
use App\Plugins\Akaunting\Services\AkauntingVoucherPullService;
use App\Plugins\InvoiceNinja\InvoiceNinjaPlugin;
use App\Plugins\InvoiceNinja\Services\InvoiceNinjaVoucherPullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Invoice Ninja und Akaunting im Belegspiegel (MVP-1331–1335): Abruf nur mit
 * Schalter, Art in der Fremd-ID, Status aus festen Werten, Folgelauf ab dem
 * Änderungsstand, kein Schreiben, Link zum Original im Belegfluss.
 */
class OpenSourceFakturaVoucherPullTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const NINJA = 'https://ninja.example.test';

    private const AKAUNTING = 'https://akaunting.example.test';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        app()->instance('currentOrganization', $this->organization);
        $this->pluginSecret(InvoiceNinjaPlugin::ID, ['base_url' => self::NINJA, 'api_token' => 'ninja-token', 'pull_vouchers' => true]);
        $this->pluginSecret(AkauntingPlugin::ID, ['base_url' => self::AKAUNTING, 'email' => 'api@example.test', 'password' => 'geheim', 'company_id' => 3, 'pull_vouchers' => true]);
    }

    /** Seiten, die Invoice Ninja für Ausgaben meldet (Abbruch am Seitenlimit). */
    private int $expensePages = 1;

    private function serveNinja(): FakePluginHttp {
        $page = static fn (array $rows, int $pages = 1): array => ['data' => $rows, 'meta' => ['pagination' => ['total' => count($rows), 'total_pages' => $pages]]];

        return FakePluginHttp::fake([
            self::NINJA . '/api/v1/statics' => FakePluginHttp::response(['currencies' => [['id' => '3', 'code' => 'eur'], ['id' => '1', 'code' => 'usd'], ['id' => '17', 'code' => 'chf']]]),
            self::NINJA . '/api/v1/companies/current' => FakePluginHttp::response(['data' => ['id' => 'VolejRejNm', 'settings' => ['currency_id' => '17']]]),
            self::NINJA . '/api/v1/group_settings/grp1' => FakePluginHttp::response(['data' => ['id' => 'grp1', 'settings' => ['currency_id' => '1']]]),
            self::NINJA . '/api/v1/invoices*' => FakePluginHttp::response($page([
                ['id' => 'Wpmbk5ezJn', 'number' => 'R-0001', 'status_id' => '3', 'amount' => 119, 'balance' => 50, 'total_taxes' => 19, 'date' => '2026-10-01', 'due_date' => '2026-10-15', 'client_id' => 'ab12', 'is_deleted' => false, 'archived_at' => 0, 'updated_at' => 1792000000, 'client' => ['name' => 'Kundin', 'number' => 'K-100', 'settings' => ['currency_id' => '3']]],
                ['id' => 'Opnel5aKBz', 'number' => 'R-0002', 'status_id' => '2', 'amount' => 10, 'balance' => 10, 'total_taxes' => 0, 'date' => '2026-10-02', 'client_id' => 'ab12', 'is_deleted' => true, 'updated_at' => 1792000100, 'client' => ['settings' => ['currency_id' => '3']]],
                // Ohne eigene Währung: erst die Kundengruppe, dann die Firma.
                ['id' => 'Grp00001', 'number' => 'R-0003', 'status_id' => '2', 'amount' => 10, 'balance' => 10, 'total_taxes' => 0, 'date' => '2026-10-02', 'client_id' => 'cd34', 'updated_at' => 1792000050, 'client' => ['group_settings_id' => 'grp1', 'settings' => ['currency_id' => '']]],
                ['id' => 'Firm0001', 'number' => 'R-0004', 'status_id' => '2', 'amount' => 10, 'balance' => 10, 'total_taxes' => 0, 'date' => '2026-10-02', 'client_id' => 'ef56', 'updated_at' => 1792000060, 'client' => ['group_settings_id' => '', 'settings' => new \stdClass()]],
            ])),
            self::NINJA . '/api/v1/credits*' => FakePluginHttp::response($page([
                ['id' => 'Wpmbk5ezJn', 'number' => 'G-0001', 'status_id' => '4', 'amount' => 20, 'balance' => 0, 'total_taxes' => 0, 'date' => '2026-10-03', 'client_id' => 'ab12', 'updated_at' => 1792000200, 'client' => ['settings' => ['currency_id' => '1']]],
            ])),
            self::NINJA . '/api/v1/expenses*' => fn (): \GuzzleHttp\Psr7\Response => FakePluginHttp::response($page([
                ['id' => 'xYz9', 'number' => 'A-7', 'amount' => 42.5, 'date' => '2026-10-04', 'payment_date' => '2026-10-05', 'vendor_id' => 'v1', 'currency_id' => '3', 'updated_at' => 1792000300, 'vendor' => ['name' => 'Papier AG']],
                // Steuer aufgeschlagen: `amount` ist netto.
                ['id' => 'Exkl0001', 'number' => 'A-8', 'amount' => 100, 'tax_rate1' => 19, 'uses_inclusive_taxes' => false, 'calculate_tax_by_amount' => false, 'currency_id' => '3', 'updated_at' => 1792000310],
                // Steuer enthalten: `amount` ist brutto.
                ['id' => 'Inkl0001', 'number' => 'A-9', 'amount' => 119, 'tax_rate1' => 19, 'uses_inclusive_taxes' => true, 'calculate_tax_by_amount' => false, 'currency_id' => '3', 'updated_at' => 1792000320],
                // Ausgabe in USD; Invoice Ninja führt den Gegenwert in der Firmenwährung.
                ['id' => 'Frmd0001', 'number' => 'A-11', 'amount' => 100, 'currency_id' => '1', 'invoice_currency_id' => '3', 'foreign_amount' => 92.5, 'exchange_rate' => 0.925, 'updated_at' => 1792000340],
                // Steuer als Betrag, aufgeschlagen.
                ['id' => 'Betr0001', 'number' => 'A-10', 'amount' => 50, 'tax_amount1' => 9.5, 'uses_inclusive_taxes' => false, 'calculate_tax_by_amount' => true, 'currency_id' => '3', 'updated_at' => 1792000330],
            ], $this->expensePages)),
        ]);
    }

    public function test_invoice_ninja_mirrors_invoices_credits_and_expenses(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Papier AG']);
        $http = $this->serveNinja();

        $counters = app(InvoiceNinjaVoucherPullService::class)->pull((int) $this->organization->id);

        $this->assertSame(10, $counters['created']);
        $invoice = AccountingVoucher::query()->where('plugin_id', InvoiceNinjaPlugin::ID)->where('external_id', 'invoice:Wpmbk5ezJn')->sole();
        $this->assertSame(AccountingVoucherState::Open, $invoice->voucher_state, 'Teilbezahlt bleibt offen.');
        $this->assertSame(DocumentDirection::Outgoing->value, $invoice->direction);
        $this->assertSame('119.00', (string) $invoice->total_amount);
        $this->assertSame('100.00', (string) $invoice->net_amount);
        $this->assertSame('50.00', (string) $invoice->open_amount);
        $this->assertSame('client:ab12', $invoice->contact_external_id);
        $this->assertArrayNotHasKey('client', (array) $invoice->payload, 'Kundendaten des Includes landen nicht im Nachweis.');
        $this->assertSame(AccountingVoucherState::Cancelled, AccountingVoucher::query()->where('external_id', 'invoice:Opnel5aKBz')->sole()->voucher_state, 'Gelöscht gilt als storniert.');
        $credit = AccountingVoucher::query()->where('external_id', 'credit:Wpmbk5ezJn')->sole();
        $this->assertSame(DocumentKind::CreditNote->value, $credit->document_kind, 'Gleiche ID, andere Art: eigener Beleg.');
        $this->assertSame('USD', $credit->currency);
        $this->assertSame('USD', AccountingVoucher::query()->where('external_id', 'invoice:Grp00001')->sole()->currency, 'Währung der Kundengruppe.');
        $this->assertSame('CHF', AccountingVoucher::query()->where('external_id', 'invoice:Firm0001')->sole()->currency, 'Firmenwährung statt fest EUR.');
        $foreign = AccountingVoucher::query()->where('external_id', 'expense:Frmd0001')->sole();
        $this->assertSame(['USD', '92.50', 'EUR'], [$foreign->currency, (string) $foreign->foreign_amount, $foreign->foreign_currency]);
        $this->assertNull(AccountingVoucher::query()->where('external_id', 'expense:xYz9')->sole()->foreign_amount, 'Ohne Umrechnung kein zweiter Betrag.');
        foreach (['expense:Exkl0001' => ['119.00', '100.00'], 'expense:Inkl0001' => ['119.00', '100.00'], 'expense:Betr0001' => ['59.50', '50.00']] as $externalId => [$gross, $net]) {
            $voucher = AccountingVoucher::query()->where('external_id', $externalId)->sole();
            $this->assertSame([$gross, $net], [(string) $voucher->total_amount, (string) $voucher->net_amount], $externalId);
        }
        $expense = AccountingVoucher::query()->where('external_id', 'expense:xYz9')->sole();
        $this->assertSame(DocumentDirection::Incoming->value, $expense->direction);
        $this->assertSame(AccountingVoucherState::Paid, $expense->voucher_state);
        $this->assertSame($supplier->id, $expense->supplier_id);
        $this->assertSame('https://ninja.example.test/#/invoices/Wpmbk5ezJn/edit?company=VolejRejNm', app(InvoiceNinjaVoucherPullService::class)->voucherUrl($invoice));

        $http->assertSent(static fn (RequestInterface $r): bool => $r->getHeaderLine('X-API-TOKEN') === 'ninja-token');
        $http->assertNotSent(static fn (RequestInterface $r): bool => $r->getMethod() !== 'GET' && ! str_ends_with($r->getUri()->getPath(), '/companies/current'));
        $http->assertSent(static function (RequestInterface $r): bool {
            parse_str($r->getUri()->getQuery(), $q);

            return str_ends_with($r->getUri()->getPath(), '/invoices') && ($q['sort'] ?? null) === ['updated_at|asc', 'id|asc'];
        });
        $http->assertNotSent(static fn (RequestInterface $r): bool => str_ends_with($r->getUri()->getPath(), '/expenses') && str_contains($r->getUri()->getQuery(), 'sort'));

        $http = $this->serveNinja();
        app(InvoiceNinjaVoucherPullService::class)->pull((int) $this->organization->id);
        $http->assertSent(static function (RequestInterface $r): bool {
            parse_str($r->getUri()->getQuery(), $q);

            return str_ends_with($r->getUri()->getPath(), '/invoices') && ($q['updated_at'] ?? null) === '1792000100';
        }, 'Jede Liste setzt an ihrer eigenen Marke an, nicht an der jüngsten Ausgabe.');
        $this->assertSame(10, AccountingVoucher::query()->where('plugin_id', InvoiceNinjaPlugin::ID)->count());
    }

    public function test_invoice_ninja_expenses_are_mirrored_only_after_a_complete_read(): void {
        $this->serveNinja();
        app(InvoiceNinjaVoucherPullService::class)->pull((int) $this->organization->id);
        AccountingVoucher::query()->where('external_id', 'expense:Betr0001')->delete();

        // Folgelauf: Ausgaben kommen nach ID absteigend; endet das Lesen am Seitenlimit, wird nichts gespiegelt.
        $this->expensePages = 1000;
        $this->serveNinja();
        try {
            app(InvoiceNinjaVoucherPullService::class)->pull((int) $this->organization->id, 1);
            $this->fail('Ein abgebrochenes Lesen der Ausgaben darf die Marke nicht vorrücken.');
        } catch (\App\Plugins\Support\TaskSync\IncompleteRemoteReadException) {
        }
        $this->assertNull(AccountingVoucher::query()->where('external_id', 'expense:Betr0001')->first());
    }

    public function test_nothing_is_fetched_without_switch_or_plugin(): void {
        $this->pluginSecret(InvoiceNinjaPlugin::ID, ['pull_vouchers' => false]);
        $http = $this->serveNinja();
        $this->assertSame(0, app(InvoiceNinjaVoucherPullService::class)->pull((int) $this->organization->id)['read']);
        $http->assertNothingSent();

        $this->pluginSecret(InvoiceNinjaPlugin::ID, ['pull_vouchers' => true])->forceFill(['enabled' => false])->save();
        $this->artisan('accounting:pull-vouchers', ['--plugin' => InvoiceNinjaPlugin::ID])->assertSuccessful();
        $this->assertSame(0, AccountingVoucher::query()->count());
    }

    public function test_akaunting_mirrors_invoices_and_bills_with_company_header(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-9']);
        $http = FakePluginHttp::fake([
            self::AKAUNTING . '/api/documents*' => function (RequestInterface $request) {
                parse_str($request->getUri()->getQuery(), $q);
                $rows = str_starts_with((string) ($q['search'] ?? ''), 'type:invoice ')
                    ? [['id' => 5, 'type' => 'invoice', 'document_number' => 'INV-5', 'status' => 'partial', 'issued_at' => '2026-10-01T00:00:00+00:00', 'due_at' => '2026-10-31T00:00:00+00:00', 'updated_at' => '2026-10-05T10:00:00+02:00', 'amount' => 238.0, 'currency_code' => 'EUR', 'contact_id' => 9, 'contact_name' => 'Kundin',
                        'contact' => ['id' => 9, 'reference' => 'K-9'],
                        'totals' => ['data' => [['code' => 'sub_total', 'amount' => 200.0], ['code' => 'tax', 'amount' => 38.0], ['code' => 'total', 'amount' => 238.0]]],
                        'transactions' => ['data' => [['type' => 'income', 'amount' => 100.0, 'currency_code' => 'EUR']]]]]
                    : [['id' => 5, 'type' => 'bill', 'document_number' => 'B-5', 'status' => 'paid', 'issued_at' => '2026-10-02T00:00:00+00:00', 'updated_at' => '2026-10-02T09:00:00+02:00', 'amount' => 12.5, 'currency_code' => 'EUR', 'contact_id' => 11, 'contact_name' => 'Strom GmbH']];

                return FakePluginHttp::response(['data' => $rows, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($rows)]]);
            },
        ]);

        $counters = app(AkauntingVoucherPullService::class)->pull((int) $this->organization->id);

        $this->assertSame(2, $counters['created']);
        $invoice = AccountingVoucher::query()->where('plugin_id', AkauntingPlugin::ID)->where('external_id', 'invoice:5')->sole();
        $this->assertSame(AccountingVoucherState::Open, $invoice->voucher_state);
        $this->assertSame('2026-10-31', $invoice->due_date?->toDateString());
        $this->assertSame('200.00', (string) $invoice->net_amount, 'Summe ohne Steuerzeilen.');
        $this->assertSame('138.00', (string) $invoice->open_amount, 'Summe abzüglich Zahlungen.');
        $this->assertSame($customer->id, $invoice->customer_id, 'Referenz des Kontakts = Kundennummer.');
        $bill = AccountingVoucher::query()->where('external_id', 'bill:5')->sole();
        $this->assertSame(DocumentDirection::Incoming->value, $bill->direction);
        $this->assertSame(AccountingVoucherState::Paid, $bill->voucher_state);
        $this->assertSame(self::AKAUNTING . '/3/purchases/bills/5', app(AkauntingVoucherPullService::class)->voucherUrl($bill));
        $http->assertSent(static fn (RequestInterface $r): bool => $r->getHeaderLine('X-Company') === '3'
            && $r->getHeaderLine('Authorization') === 'Basic ' . base64_encode('api@example.test:geheim'));

        $this->actingAs($this->admin)
            ->withSession(['ui.daterange.preset' => 'custom', 'ui.daterange.from' => '2026-10-01', 'ui.daterange.to' => '2026-10-31'])
            ->get(route('billing.feed'))
            ->assertOk()
            ->assertSee('href="' . self::AKAUNTING . '/3/sales/invoices/5"', false)
            ->assertSee('target="_blank" rel="noopener noreferrer"', false);
        $http->assertSent(static function (RequestInterface $r): bool {
            parse_str($r->getUri()->getQuery(), $q);

            return ($q['search'] ?? null) === 'type:invoice sort:-issued_at,-id';
        });

        // Folgelauf je Art ab der eigenen Marke (Ortszeit der Instanz, zwei Stunden Überlappung), in Änderungsfolge.
        app(AkauntingVoucherPullService::class)->pull((int) $this->organization->id);
        $http->assertSent(static function (RequestInterface $r): bool {
            parse_str($r->getUri()->getQuery(), $q);

            return ($q['search'] ?? null) === 'type:invoice updated_at>="2026-10-05 08:00:00" sort:updated_at,id';
        });
        $http->assertSent(static function (RequestInterface $r): bool {
            parse_str($r->getUri()->getQuery(), $q);

            return ($q['search'] ?? null) === 'type:bill updated_at>="2026-10-02 07:00:00" sort:updated_at,id';
        });
    }

    public function test_akaunting_marks_documents_it_no_longer_holds_once_a_day(): void {
        $invoices = [5, 6];
        $serve = function () use (&$invoices): void {
            FakePluginHttp::fake([
                self::AKAUNTING . '/api/documents*' => function (RequestInterface $request) use (&$invoices) {
                    parse_str($request->getUri()->getQuery(), $q);
                    $type = str_starts_with((string) ($q['search'] ?? ''), 'type:invoice ') ? 'invoice' : 'bill';
                    $rows = $type === 'invoice'
                        ? array_map(static fn (int $id): array => ['id' => $id, 'type' => 'invoice', 'document_number' => 'INV-' . $id, 'status' => 'sent', 'issued_at' => '2026-10-01T00:00:00+00:00', 'updated_at' => '2026-10-01T00:00:00+00:00', 'amount' => 10.0, 'currency_code' => 'EUR'], $invoices)
                        : [];

                    return FakePluginHttp::response(['data' => $rows, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($rows)]]);
                },
            ]);
        };
        $serve();
        app(AkauntingVoucherPullService::class)->pull((int) $this->organization->id);
        $this->assertSame(2, AccountingVoucher::query()->where('plugin_id', AkauntingPlugin::ID)->whereNull('source_deleted_at')->count());

        $invoices = [5];
        $serve();
        app(AkauntingVoucherPullService::class)->pull((int) $this->organization->id);
        $this->assertNull(AccountingVoucher::query()->where('external_id', 'invoice:6')->sole()->source_deleted_at, 'Der Abgleich läuft einmal am Tag.');

        $this->travel(1)->days();
        $serve();
        app(AkauntingVoucherPullService::class)->pull((int) $this->organization->id);
        $this->assertNotNull(AccountingVoucher::query()->where('external_id', 'invoice:6')->sole()->source_deleted_at);
        $this->assertNull(AccountingVoucher::query()->where('external_id', 'invoice:5')->sole()->source_deleted_at);
    }

    public function test_akaunting_rejects_rows_of_another_type(): void {
        // Versteht Akaunting den Suchtext nicht, liefert es alle Belege aller Arten.
        FakePluginHttp::fake([
            self::AKAUNTING . '/api/documents*' => FakePluginHttp::response(['data' => [['id' => 7, 'type' => 'bill', 'amount' => 1.0]], 'meta' => ['last_page' => 1]]),
        ]);

        $this->expectException(\App\Plugins\Support\TaskSync\IncompleteRemoteReadException::class);
        app(AkauntingVoucherPullService::class)->pull((int) $this->organization->id);
    }
}
