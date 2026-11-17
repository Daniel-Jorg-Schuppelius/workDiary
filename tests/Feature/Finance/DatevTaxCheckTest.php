<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevTaxCheckTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{DatevTaxCheckKind, ProfitDetermination};
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\User\Permission;
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingEvent, AccountingTaxCode};
use App\Models\Customer\Customer;
use App\Models\Finance\DatevBookingEvent;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsTemplateService, FiscalYearService, JournalService};
use App\Services\Finance\Datev\DatevBookingConfig;
use App\Services\Finance\Datev\TaxCheck\{DatevTaxCheckFinding, DatevTaxCheckService};
use App\Services\Finance\DatevBookingService;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Steuerprüfung vor dem DATEV-Export (Feature 207, MVP-1661).
 *
 * Abnahme: Ein Stapel mit einer innergemeinschaftlichen Lieferung ohne
 * USt-IdNr. des Kunden lässt sich nicht exportieren, bis die Zeile berichtigt
 * oder begründet freigegeben ist; eine aufgehobene Automatik erscheint nur als
 * Hinweis. Kunden und Kennungen sind erfunden.
 */
class DatevTaxCheckTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Customer $customer;

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create([
            'settings' => ['datev' => ['advisor_number' => 12345, 'client_number' => 1, 'skr' => 'skr03']],
        ]);
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $startsOn = CarbonImmutable::parse('2026-01-01');
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, $startsOn);
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);
        app(ChartOfAccountsTemplateService::class)->apply($this->org, 'skr03', $startsOn);

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Musterkunde SARL',
            'country' => 'FR',
            'vat_id' => null,
        ]);
        $this->from = CarbonImmutable::parse('2026-02-01');
        $this->to = CarbonImmutable::parse('2026-02-28');
    }

    private function account(string $number): AccountingAccount {
        return AccountingAccount::query()->where('organization_id', $this->org->id)->where('number', $number)->sole();
    }

    private function code(string $code): AccountingTaxCode {
        return AccountingTaxCode::query()->where('organization_id', $this->org->id)->where('code', $code)->sole();
    }

    private function intraCommunityInvoice(string $number, string $issuedOn): Invoice {
        return Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => $number,
            'status' => InvoiceStatus::Issued,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_MATERIAL,
            'issued_on' => $issuedOn,
            'currency' => 'EUR',
            'tax_rate' => '0.00',
            'subtotal' => '1000.00',
            'tax_amount' => '0.00',
            'total' => '1000.00',
            'tax_context' => ['category' => 'K'],
        ]);
    }

    /** @param list<array<string, mixed>> $lines */
    private function book(string $date, string $memo, array $lines, ?Invoice $source = null): AccountingEntry {
        return app(JournalService::class)->postDirect($this->org, [
            'booked_on' => CarbonImmutable::parse($date),
            'memo' => $memo,
            'source_type' => $source !== null ? MorphMap::alias(Invoice::class) : null,
            'source_id' => $source?->id,
            'source_key' => 'tax-check-test:' . $memo,
            'lines' => $lines,
        ], $this->admin);
    }

    /** IGL an den Kunden ohne USt-IdNr. und eine Erlösbuchung ohne Kennzeichen auf dem Automatikkonto 8400. */
    private function ledgerWithIntraCommunitySupplyAndSuspendedAutomatic(): AccountingEntry {
        $invoice = $this->intraCommunityInvoice('RE-IGL-1', '2026-02-10');
        $igl = $this->book('2026-02-10', 'IGL Musterkunde', [
            ['accounting_account_id' => $this->account('1400')->id, 'debit' => '1000.00', 'credit' => '0.00'],
            ['accounting_account_id' => $this->account('8125')->id, 'debit' => '0.00', 'credit' => '1000.00', 'accounting_tax_code_id' => $this->code('IGL')->id],
        ], $invoice);
        $this->book('2026-02-11', 'Barerlös ohne Kennzeichen', [
            ['accounting_account_id' => $this->account('1200')->id, 'debit' => '119.00', 'credit' => '0.00'],
            ['accounting_account_id' => $this->account('8400')->id, 'debit' => '0.00', 'credit' => '119.00'],
        ]);

        return $igl;
    }

    private function service(): DatevTaxCheckService {
        return app(DatevTaxCheckService::class);
    }

    /** Abnahme 3 (Journal-Übergabe): gesperrt bis begründet freigegeben; BU 40 nur als Hinweis. */
    public function test_intra_community_supply_without_vat_id_blocks_the_ledger_export_until_released(): void {
        $igl = $this->ledgerWithIntraCommunitySupplyAndSuspendedAutomatic();

        $check = $this->service()->forLedger($this->org, $this->from, $this->to);
        $this->assertTrue($check->isBlocked());
        $this->assertSame([DatevTaxCheckKind::MissingVatId], array_map(static fn (DatevTaxCheckFinding $f): DatevTaxCheckKind => $f->kind, $check->openErrors()));
        $this->assertSame('ledger:missing_vat_id:entry:' . $igl->id, $check->openErrors()[0]->key);
        $this->assertSame(route('finance.accounting.journal.show', $igl), $check->openErrors()[0]->url);
        $hints = array_map(static fn (DatevTaxCheckFinding $f): DatevTaxCheckKind => $f->kind, $check->hints());
        $this->assertSame([DatevTaxCheckKind::AutomaticSuspended], $hints, 'BU 40 ist nur ein Hinweis');
        $this->assertStringContainsString('8400', $check->hints()[0]->message);

        $period = ['from' => '2026-02-01', 'to' => '2026-02-28'];
        $this->actingAs($this->admin)->post(route('finance.accounting.closing.datev'), $period)
            ->assertRedirect(route('finance.accounting.closing.datev-check', $period))
            ->assertSessionHas('error', __('accounting.datev_check.error.blocked'));

        $this->actingAs($this->admin)->get(route('finance.accounting.closing.datev-check', $period))
            ->assertOk()
            ->assertSee(DatevTaxCheckKind::MissingVatId->label())
            ->assertSee(DatevTaxCheckKind::AutomaticSuspended->label())
            ->assertSee(__('accounting.datev_check.blocked', ['count' => 1]));

        // Ohne Begründung keine Freigabe.
        $this->actingAs($this->admin)->post(route('finance.accounting.closing.datev-release'), $period + ['reason' => 'kurz'])
            ->assertSessionHasErrors('reason');

        $reason = 'Nachweis der USt-IdNr. liegt der Kanzlei in Papierform vor.';
        $this->actingAs($this->admin)->post(route('finance.accounting.closing.datev-release'), $period + ['reason' => $reason])
            ->assertRedirect(route('finance.accounting.closing.datev-check', $period))
            ->assertSessionHas('status', __('accounting.datev_check.flash.released', ['count' => 1]));

        $event = AccountingEvent::query()->where('organization_id', $this->org->id)->where('event', DatevTaxCheckService::LEDGER_RELEASE_EVENT)->sole();
        $this->assertIsArray($event->payload);
        $this->assertSame($reason, $event->payload['reason']);
        $this->assertSame(['ledger:missing_vat_id:entry:' . $igl->id], $event->payload['keys']);
        $this->assertSame($this->admin->id, (int) $event->actor_user_id);

        $released = $this->service()->forLedger($this->org, $this->from, $this->to);
        $this->assertFalse($released->isBlocked());
        $this->assertTrue($released->isReleased($released->errors()[0]));

        $this->actingAs($this->admin)->post(route('finance.accounting.closing.datev'), $period)
            ->assertOk()
            ->assertHeader('Content-Disposition');
    }

    /** Berichtigt statt freigegeben: Mit der USt-IdNr. am Kunden ist der Fehler weg. */
    public function test_correcting_the_customer_vat_id_clears_the_error(): void {
        $this->ledgerWithIntraCommunitySupplyAndSuspendedAutomatic();
        $this->assertTrue($this->service()->forLedger($this->org, $this->from, $this->to)->isBlocked());

        $this->customer->update(['vat_id' => 'FR00999999999']);

        $check = $this->service()->forLedger($this->org, $this->from, $this->to);
        $this->assertFalse($check->isBlocked());
        $this->assertSame([], $check->errors());
        $this->assertCount(1, $check->hints());
    }

    /** Fehler: Steuer ohne Kennzeichen an der Erlöszeile, stillgelegtes Konto; Hinweis: Satz weicht vom Automatikkonto ab. */
    public function test_missing_tax_code_inactive_account_and_rate_mismatch(): void {
        $other = $this->book('2026-02-12', 'Sonstiger Erlös mit Steuer ohne Kennzeichen', [
            ['accounting_account_id' => $this->account('1200')->id, 'debit' => '119.00', 'credit' => '0.00'],
            ['accounting_account_id' => $this->account('2709')->id, 'debit' => '0.00', 'credit' => '100.00'],
            ['accounting_account_id' => $this->account('1776')->id, 'debit' => '0.00', 'credit' => '19.00'],
        ]);
        $this->book('2026-02-13', 'Erlös 7 % auf 8400', [
            ['accounting_account_id' => $this->account('1400')->id, 'debit' => '107.00', 'credit' => '0.00'],
            ['accounting_account_id' => $this->account('8400')->id, 'debit' => '0.00', 'credit' => '100.00', 'accounting_tax_code_id' => $this->code('USt7')->id, 'tax_amount' => '7.00'],
            ['accounting_account_id' => $this->account('1771')->id, 'debit' => '0.00', 'credit' => '7.00'],
        ]);
        $this->book('2026-02-14', 'Bürobedarf', [
            ['accounting_account_id' => $this->account('4930')->id, 'debit' => '20.00', 'credit' => '0.00'],
            ['accounting_account_id' => $this->account('1200')->id, 'debit' => '0.00', 'credit' => '20.00'],
        ]);
        $this->account('4930')->update(['is_active' => false]);

        $check = $this->service()->forLedger($this->org, $this->from, $this->to);
        $errors = array_map(static fn (DatevTaxCheckFinding $f): string => $f->kind->value, $check->errors());
        sort($errors);
        $this->assertSame(['inactive_account', 'missing_tax_code'], $errors);
        $missing = collect($check->errors())->firstWhere('kind', DatevTaxCheckKind::MissingTaxCode);
        $this->assertNotNull($missing);
        $this->assertStringContainsString('2709', $missing->message);
        $this->assertStringContainsString('1776', $missing->message);
        $this->assertSame(route('finance.accounting.journal.show', $other), $missing->url);

        $mismatch = collect($check->hints())->firstWhere('kind', DatevTaxCheckKind::RateMismatch);
        $this->assertNotNull($mismatch);
        $this->assertFalse($mismatch->blocking);
        $this->assertStringContainsString('USt19', $mismatch->message);
        $this->assertStringContainsString('USt7', $mismatch->message);
    }

    /** Abnahme 3 (belegbasierter Stapel): Finalisieren gesperrt, Freigabe mit Begründung in der Kette des Stapels. */
    public function test_document_batch_with_intra_community_supply_is_blocked_until_released(): void {
        $this->intraCommunityInvoice('RE-IGL-2', '2026-03-05');
        $bookings = app(DatevBookingService::class);
        $config = DatevBookingConfig::forOrganization($this->org);
        $period = ['from' => '2026-03-01', 'to' => '2026-03-31'];
        $batch = $bookings->createDraft($this->org, $period, $bookings->collectBookingReady($this->org, $period), $config, $this->admin);

        $check = $this->service()->forBatch($batch, $config);
        $this->assertTrue($check->isBlocked());
        $this->assertSame(DatevTaxCheckKind::MissingVatId, $check->openErrors()[0]->kind);
        $this->assertStringContainsString('RE-IGL-2', $check->openErrors()[0]->message);

        $this->actingAs($this->admin)->get(route('finance.datev.show', $batch))
            ->assertOk()
            ->assertSee(DatevTaxCheckKind::MissingVatId->label())
            ->assertSee(__('accounting.datev_check.action.release'));

        $this->actingAs($this->admin)->post(route('finance.datev.finalize', $batch))
            ->assertSessionHasErrors(['status' => __('accounting.datev_check.error.blocked')]);
        $this->assertFalse($batch->refresh()->isFinal());

        // Wer den Stapel finalisieren, aber nicht freigeben darf, bekommt 403.
        $exporter = User::factory()->create(['organization_id' => $this->org->id]);
        $exporter->givePermissionTo(Permission::FinanceBookingExport->value);
        $this->actingAs($exporter)->post(route('finance.datev.tax-check.release', $batch), ['reason' => 'Freigabe ohne Recht versucht'])
            ->assertForbidden();

        $this->actingAs($this->admin)->post(route('finance.datev.tax-check.release', $batch), ['reason' => 'USt-IdNr. ist beim Kunden in Klärung, Kanzlei informiert.'])
            ->assertSessionHas('success', __('accounting.datev_check.flash.released', ['count' => 1]));

        $event = DatevBookingEvent::query()->where('datev_booking_batch_id', $batch->id)->where('event', DatevTaxCheckService::BATCH_RELEASE_EVENT)->sole();
        $this->assertIsArray($event->payload);
        $this->assertSame(['batch:missing_vat_id:invoices:' . $batch->sources->first()?->source_id], $event->payload['keys']);
        $this->assertFalse($this->service()->forBatch($batch->refresh(), $config)->isBlocked());

        // Danach sperrt die Steuerprüfung nicht mehr (ohne financial-formats bleibt nur dessen Hinweis).
        $this->actingAs($this->admin)->post(route('finance.datev.finalize', $batch))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors(['status' => __('accounting.datev_check.error.blocked')]);
    }

    /** Ein sauberer Zeitraum lässt sich ohne Umweg exportieren; die Prüfseite sagt es. */
    public function test_clean_period_has_no_findings(): void {
        $this->book('2026-02-15', 'Bürobedarf', [
            ['accounting_account_id' => $this->account('4930')->id, 'debit' => '20.00', 'credit' => '0.00'],
            ['accounting_account_id' => $this->account('1200')->id, 'debit' => '0.00', 'credit' => '20.00'],
        ]);

        $check = $this->service()->forLedger($this->org, $this->from, $this->to);
        $this->assertTrue($check->isEmpty());

        $this->actingAs($this->admin)->get(route('finance.accounting.closing.datev-check', ['from' => '2026-02-01', 'to' => '2026-02-28']))
            ->assertOk()
            ->assertSee(__('accounting.datev_check.clean'));
    }
}
