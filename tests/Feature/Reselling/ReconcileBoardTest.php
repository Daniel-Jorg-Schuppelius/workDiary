<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconcileBoardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\{PeriodPlanner, RecipientReconciler, ReconcileBoard};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abgleichstafel je Produkt und Passung (Phase 166, MVP-1447/1448/1451): Jahrestag
 * um einen Monat verschoben, berechnet wird weiter am alten Jahrestag.
 */
class ReconcileBoardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-07');
        $this->exchange = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'ART-EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Grünweg Technik GmbH']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-gw', 'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->getKey(),
        ]);
    }

    private function invoice(string $number, string $date, ?string $serviceFrom = null, ?string $serviceTo = null, string $status = 'paid'): LexofficeVoucherLine {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => 'c-gw', 'voucher_type' => 'invoice',
            'voucher_status' => $status, 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 56.41, 'currency' => 'EUR', 'archived' => false,
            'service_starts_on' => $serviceFrom, 'service_ends_on' => $serviceTo, 'lines_synced_at' => now(),
        ]);

        return LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => 'art-exo', 'lexoffice_article_id' => $this->exchange->id, 'name' => 'Exchange Online (Plan 1)',
            'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    public function test_board_shows_periods_and_invoices_side_by_side_and_asks_before_a_doubtful_assignment(): void {
        $admin = $this->orgAdmin();
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'label' => 'Exchange Online (Plan 1)', 'quantity' => 1,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'ended', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
            'customer_id' => $this->customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2022-12-30', 'ends_on' => '2025-12-29',
        ]);
        (new PeriodPlanner)->sync($subscription);
        /** @var list<ResalePeriod> $periods */
        $periods = $subscription->periods()->get()->all();
        $this->assertCount(3, $periods);
        [, $p2023, $p2024] = $periods;
        $line2023 = $this->invoice('RE/2023/0100', '2023-11-30', '2023-11-30', '2024-11-29');
        $line2024 = $this->invoice('RE/2024/0100', '2024-11-30', '2024-11-30', '2025-11-29');
        $this->invoice('RE/2025/0090', '2024-12-28', null, null, 'voided');
        $this->invoice('RE/2019/9001', '2019-11-30', '2019-11-30', '2020-11-29');
        $workspace = route('finance.resale.reconcile.show', $this->customer);

        $page = $this->actingAs($admin)->get($workspace)->assertOk();
        // Ruhige Tafel (Phase 168): „versetzt“ ist normal und wird nicht angezeigt; jede Rechnung steht nur bei ihrer besten Periode.
        $page->assertSee(__('resale.board.col.period'))->assertSee(__('resale.board.col.invoice'))
            ->assertDontSee(__('resale.fit.shifted', ['days' => 30]))
            ->assertDontSee(__('resale.fit.elsewhere', ['period' => $p2024->label()]))
            ->assertSee(__('resale.board.assign', ['amount' => '1 × 12 ' . __('resale.link.months_short')]))
            // Rechnung ohne Periode (vor dem Abo): eigene Zeile an ihrem Datum.
            ->assertSee(__('resale.board.without_period'))->assertSee('RE/2019/9001')
            // Storno dicht an der Periode: Ausfall verbuchen mit vorbelegtem Grund.
            ->assertSee(__('resale.board.writeoff'))
            ->assertSee(route('finance.resale.periods.waive.create', ['period' => $p2024->sqid, 'reason' => __('resale.link.reason_voided', ['voucher' => 'RE/2025/0090'])]));

        $result = app(RecipientReconciler::class)->forCustomer($this->organization, $this->customer);
        $rows = collect(app(ReconcileBoard::class)->build($result, CarbonImmutable::parse('2026-09-07'))[0]['rows'])
            ->filter(static fn (array $row): bool => $row['kind'] === 'period')
            ->mapWithKeys(static fn (array $row): array => [($row['period']?->starts_on->toDateString() ?? '') => array_map(static fn (array $c): ?string => $c['candidate']['row']['line']->voucherNumber, $row['candidates'])]);
        $this->assertSame(['RE/2023/0100'], $rows['2023-12-30']);
        $this->assertSame(['RE/2024/0100'], $rows['2024-12-30'], 'die Rechnung des Folgejahres steht nur bei ihrer Periode');

        // Rechnung des Folgejahres an die Periode 2023/24 (Server-Regel): ohne Bestätigung abgewiesen.
        $assign = ['period_id' => $p2023->sqid, 'line_id' => Sqid::encode(LexofficeVoucherLine::class, $line2024->id), 'months' => '12'];
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.reconcile.assign', $this->customer), $assign)->assertSessionHasErrors('confirm_fit');
        $this->assertSame(0, $p2023->links()->count());
        // Die passende Rechnung (30 Tage versetzt) braucht keine Bestätigung.
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.reconcile.assign', $this->customer), ['line_id' => Sqid::encode(LexofficeVoucherLine::class, $line2023->id)] + $assign)
            ->assertRedirect($workspace)->assertSessionHasNoErrors();
        $this->assertSame('RE/2023/0100', $p2023->links()->first()?->voucher_number);

        // Bezug-Dialog: Passung in der Auswahl, Bestätigungsfeld, Rücksprung in die Mappe.
        $this->actingAs($admin)->get(route('finance.resale.periods.link.create', $p2024->sqid))->assertOk()
            ->assertSee(__('resale.fit.confirm_field'))->assertDontSee(__('resale.fit.shifted', ['days' => 30]));
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.periods.link.store', $p2024->sqid), ['line_id' => Sqid::encode(LexofficeVoucherLine::class, $line2024->id), 'months' => '12'])
            ->assertRedirect($workspace);
        $this->assertSame('RE/2024/0100', $p2024->links()->first()?->voucher_number);
        $this->actingAs($admin)->get($workspace)->assertOk()->assertDontSee(__('resale.fit.elsewhere', ['period' => $p2024->label()]));
    }

    public function test_voided_invoice_offers_a_write_off_with_a_prefilled_reason(): void {
        $admin = $this->orgAdmin();
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'label' => 'Exchange Online (Plan 1)', 'quantity' => 5,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'ended', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
            'customer_id' => $this->customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2020-04-24', 'ends_on' => '2022-04-23',
        ]);
        (new PeriodPlanner)->sync($subscription);
        $period = $subscription->periods()->reorder('starts_on', 'desc')->firstOrFail();
        $workspace = route('finance.resale.reconcile.show', $this->customer);
        $reason = __('resale.link.reason_voided', ['voucher' => 'RE/2021/0900']);

        $this->actingAs($admin)->get(route('finance.resale.periods.waive.create', ['period' => $period->sqid, 'reason' => $reason]))->assertOk()
            ->assertSee($reason)->assertSee('resale-waive-reasons')->assertSee(trans('resale.link.reason_presets')[0]);
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.periods.waive', $period->sqid), ['decision' => 'waived', 'reason' => $reason])->assertRedirect($workspace);
        $period->refresh();
        $this->assertSame(PeriodStatus::Waived, $period->status);
        $this->assertSame($reason, $period->waived_reason);
    }

    public function test_invoice_without_period_is_assigned_through_a_small_dialog(): void {
        // Phase 168, MVP-1462: Rechnung ohne passende Periode — Abo anlegen oder per Dialog einer offenen Periode zuordnen.
        $admin = $this->orgAdmin();
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'label' => 'Exchange Online (Plan 1)', 'quantity' => 1,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'ended', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
            'customer_id' => $this->customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2022-12-30', 'ends_on' => '2023-12-29',
        ]);
        (new PeriodPlanner)->sync($subscription);
        $period = $subscription->periods()->firstOrFail();
        $line = $this->invoice('RE/2019/9001', '2019-11-30', '2019-11-30', '2020-11-29');
        $workspace = route('finance.resale.reconcile.show', $this->customer);
        $key = Sqid::encode(LexofficeVoucherLine::class, $line->id);

        $this->actingAs($admin)->get($workspace)->assertOk()->assertSee(__('resale.board.create_subscription'))
            ->assertSee(route('finance.resale.reconcile.line.create', ['customer' => $this->customer, 'line' => $key]));
        $this->actingAs($admin)->get(route('finance.resale.reconcile.line.create', ['customer' => $this->customer, 'line' => $key]))->assertOk()
            ->assertSee(__('resale.board.line_dialog.title', ['voucher' => 'RE/2019/9001']))->assertSee($period->label())->assertSee(__('resale.fit.confirm_field'));
        // Fremder Empfänger: kein Dialog.
        $stranger = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Fremd GmbH']);
        $this->actingAs($admin)->get(route('finance.resale.reconcile.line.create', ['customer' => $stranger, 'line' => $key]))->assertNotFound();
        // Weit weg von der Periode: nur mit Bestätigung.
        $payload = ['line_id' => $key, 'period_id' => $period->sqid, 'months' => '12'];
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.reconcile.assign', $this->customer), $payload)->assertSessionHasErrors('confirm_fit');
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.reconcile.assign', $this->customer), $payload + ['confirm_fit' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('RE/2019/9001', $period->links()->first()?->voucher_number);
    }
}
