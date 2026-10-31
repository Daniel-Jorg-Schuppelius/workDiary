<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleLimitsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\{ResaleAccountAllocation, ResalePeriodLink, ResalePlanLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeRecurringTemplate, LexofficeVoucher, LexofficeVoucherLine};
use App\Plugins\Lexoffice\Services\{LexofficeInvoiceMirrorSource, LexofficeRecurringTemplateSync};
use App\Services\Reselling\License\{LicenseInvoiceLinks, LicenseStockException, LicenseStockService};
use App\Services\Reselling\Plan\PlanAudit;
use App\Services\Reselling\Register\{AllocationDeriver, LicenseAccounts, LinkProposer, PeriodLinker, PeriodPlanner, ResaleInvoiceDraftService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Mengen- und Deckungsgrenzen (Phase 167, MVP-1452–1458): nirgends mehr zuordnen,
 * als da ist — Periode, Vertrag, Vorlagenposition, Rechnungsposition.
 */
class ResaleLimitsTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-31');
        $this->exchange = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
    }

    private function customer(string $name, string $contactId): Customer {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => $contactId, 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);

        return $customer;
    }

    /** @return list<LexofficeVoucherLine> */
    private function invoice(string $contactId, string $number, string $date, int $lines, float $quantity = 12, ?string $from = null, ?string $to = null): array {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => $contactId, 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 1, 'currency' => 'EUR', 'archived' => false,
            'service_starts_on' => $from, 'service_ends_on' => $to, 'lines_synced_at' => now(),
        ]);
        $created = [];
        for ($position = 1; $position <= $lines; $position++) {
            $created[] = LexofficeVoucherLine::create([
                'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => $position, 'type' => 'service',
                'external_article_id' => 'art-exo', 'lexoffice_article_id' => $this->exchange->id, 'name' => 'Exchange Online (Plan 1)',
                'quantity' => $quantity, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
            ]);
        }

        return $created;
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(Customer $holder, array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create($attributes + [
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $holder->id, 'article_ref' => 'lex:' . $this->exchange->id, 'quantity' => 1, 'starts_on' => '2025-01-01', 'term_months' => 12,
            'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function template(string $contactId, string $id, float $quantity): int {
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $entry = [
            'id' => $id, 'organizationId' => 'org', 'title' => 'Rechnung Exchange', 'createdDate' => '2025-10-01T10:00:00.000+02:00', 'updatedDate' => '2026-10-01T10:00:00.000+02:00',
            'address' => ['contactId' => $contactId, 'name' => 'Vorlagenkunde'], 'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => 237.0, 'totalGrossAmount' => 282.03],
            'recurringTemplateSettings' => [
                'id' => 'set-' . $id, 'startDate' => '2026-01-01T00:00:00.000+02:00', 'endDate' => null, 'finalize' => true, 'shippingType' => 'serviceperiod',
                'executionInterval' => 'ANNUALLY', 'nextExecutionDate' => '2027-01-01T00:00:00.000+02:00', 'lastExecutionFailed' => false, 'lastExecutionErrorMessage' => null, 'executionStatus' => 'ACTIVE',
            ],
        ];
        $lines = [['type' => 'service', 'id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'quantity' => $quantity, 'unitName' => 'Monat', 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 3.95, 'taxRatePercentage' => 19.0]]];
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/recurring-templates/' . $id => FakePluginHttp::response($entry + ['lineItems' => $lines, 'taxConditions' => ['taxType' => 'net']]),
            'https://api.lexware.io/v1/recurring-templates?*' => FakePluginHttp::response(['content' => [$entry], 'first' => true, 'last' => true, 'totalPages' => 1, 'totalElements' => 1, 'numberOfElements' => 1, 'size' => 250, 'number' => 0, 'sort' => []]),
        ]);
        (new LexofficeRecurringTemplateSync($this->organization->id))->sync($this->organization);

        return (int) LexofficeRecurringTemplate::query()->firstOrFail()->lines()->firstOrFail()->position;
    }

    public function test_assignment_is_capped_at_what_the_period_still_needs_and_a_failed_move_keeps_the_link(): void {
        // MVP-1452: eine 24-Monats-Position deckt eine Jahresperiode nur mit 12, der Rest bleibt für das Folgejahr.
        $admin = $this->orgAdmin();
        $customer = $this->customer('Grenzweg GmbH', 'c-gw');
        $subscription = $this->subscription($customer);
        [$line] = $this->invoice('c-gw', 'RE/2025/0001', '2025-01-03', 1, 24);
        [$first, $second] = $subscription->periods()->get()->all();
        $workspace = route('finance.resale.reconcile.show', $customer);
        $assign = ['period_id' => $first->sqid, 'line_id' => Sqid::encode(LexofficeVoucherLine::class, $line->id), 'confirm_fit' => '1'];

        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.reconcile.assign', $customer), $assign + ['months' => '24'])->assertSessionHasErrors('months');
        $this->assertSame(0.0, $first->fresh()?->coveredMonths());
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.reconcile.assign', $customer), $assign + ['months' => '12'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.reconcile.assign', $customer), ['period_id' => $second->sqid] + $assign + ['months' => '12'])->assertSessionHasNoErrors();

        // Verschieben in eine volle Periode scheitert — der Bezug bleibt, wo er war.
        $link = ResalePeriodLink::query()->where('period_id', $first->id)->firstOrFail();
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.links.move', $link->sqid), ['period_id' => $second->sqid])->assertSessionHasErrors('months');
        $this->assertNotNull($link->fresh(), 'der Bezug ist nach dem gescheiterten Verschieben noch da');
        $this->assertSame(12.0, $first->refresh()->coveredMonths());
    }

    public function test_allocation_leaves_licences_billed_to_the_contract_holder(): void {
        // MVP-1453: 9 Lizenzen, 5 über Rechnungen an den Vertragshalter — die Aufteilung leitet höchstens 4 ab.
        $holder = $this->customer('Kontohalter GmbH', 'c-kh');
        $neighbour = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nachbar GmbH']);
        $contract = $this->subscription($holder, ['quantity' => 9, 'starts_on' => '2025-10-18', 'provider_account' => 'CNL1', 'external_id' => 'CN1', 'label' => 'Exchange Online Plan 1', 'article_ref' => null]);
        $period = $contract->periods()->firstOrFail();
        foreach ($this->invoice('c-kh', 'RE/2025/0009', '2025-10-18', 5) as $line) {
            (new PeriodLinker)->attach($period, $line, 12.0, null, null);
        }
        ResaleAccountAllocation::query()->create([
            'organization_id' => $this->organization->id, 'provider' => 'qualityhosting', 'provider_account' => 'CNL1', 'product_key' => 'name:exchange online plan 1',
            'product_label' => 'Exchange Online Plan 1', 'customer_id' => $neighbour->id, 'quantity' => 9, 'valid_from' => '2025-10-18',
        ]);
        $group = app(LicenseAccounts::class)->group($this->organization, 'qualityhosting:CNL1');
        $this->assertNotNull($group);
        $result = app(AllocationDeriver::class)->apply($this->organization, $group);

        $this->assertSame(4, ResaleSubscription::query()->where('parent_id', $contract->id)->firstOrFail()->quantity);
        $this->assertSame(5, $period->fresh()?->quantity);
        $this->assertSame(5, $result['conflicts'][0]['missing'] ?? null, 'der Rest der Aufteilung steht als Konflikt da');
    }

    public function test_template_line_cannot_be_linked_beyond_its_licences_and_legacy_overbooking_is_reported(): void {
        // MVP-1454: eine Vorlagenposition mit fünf Lizenzen, zwei Abos mit je fünf.
        $customer = $this->customer('Vorlagenkunde GmbH', 'c-vk');
        $a = $this->subscription($customer, ['quantity' => 5, 'starts_on' => '2026-01-01', 'external_id' => 'v1']);
        $b = $this->subscription($customer, ['quantity' => 5, 'starts_on' => '2026-01-01', 'external_id' => 'v2']);
        $position = $this->template('c-vk', 'tpl-v', 60);
        $admin = $this->orgAdmin();
        $store = route('finance.resale.reconcile.plan.store', $customer);
        $this->actingAs($admin)->post($store, ['subscription_id' => $a->sqid, 'plan_key' => 'lexoffice:tpl-v', 'line_position' => $position, 'quantity' => 5])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post($store, ['subscription_id' => $b->sqid, 'plan_key' => 'lexoffice:tpl-v', 'line_position' => $position, 'quantity' => 5])->assertSessionHasErrors('quantity');
        $this->actingAs($admin)->post($store, ['subscription_id' => $b->sqid, 'plan_key' => 'lexoffice:tpl-v', 'line_position' => 99, 'quantity' => 1])->assertSessionHasErrors('quantity');
        // Dieselbe Zuordnung neu speichern zählt die eigene nicht mit.
        $this->actingAs($admin)->post($store, ['subscription_id' => $a->sqid, 'plan_key' => 'lexoffice:tpl-v', 'line_position' => $position, 'quantity' => 5])->assertSessionHasNoErrors();

        // Altbestand an der Prüfung vorbei: der Plan-Abgleich meldet die überbuchte Position.
        ResalePlanLink::query()->create(['organization_id' => $this->organization->id, 'subscription_id' => $b->id, 'plan_key' => 'lexoffice:tpl-v', 'line_position' => $position, 'quantity' => 5, 'origin' => 'manual', 'confirmed_at' => now()]);
        $kinds = array_count_values(array_column(app(PlanAudit::class)->findings($this->organization), 'kind'));
        $this->assertSame(1, $kinds[PlanAudit::LINE_OVERBOOKED] ?? 0);
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $customer))->assertOk()->assertSee(__('resale.plan.finding.line_overbooked_short'));
    }

    public function test_partially_planned_subscription_drafts_the_remainder_by_hand_but_not_in_the_automatic_run(): void {
        // MVP-1455: neun Lizenzen, die Vorlage berechnet fünf — der Rechnungsvorschlag nimmt vier, der Serienlauf keine.
        $customer = $this->customer('Teildeckung GmbH', 'c-td');
        $subscription = $this->subscription($customer, ['quantity' => 9, 'starts_on' => '2026-01-01']);
        $position = $this->template('c-td', 'tpl-t', 60);
        ResalePlanLink::query()->create(['organization_id' => $this->organization->id, 'subscription_id' => $subscription->id, 'plan_key' => 'lexoffice:tpl-t', 'line_position' => $position, 'quantity' => 5, 'origin' => 'manual', 'confirmed_at' => now()]);
        $drafts = app(ResaleInvoiceDraftService::class);

        $entries = $drafts->openEntries($customer);
        $this->assertCount(1, $entries);
        $this->assertSame(48.0, $entries[0]['line']['quantity'], 'Monatsartikel: vier Lizenzen × 12 Monate');
        $this->assertSame('Monat', $entries[0]['line']['unit_name']);
        $this->assertStringContainsString(__('resale.draft.plan_remainder', ['open' => '4 × 12 ' . __('resale.link.months_short'), 'quantity' => 9, 'planned' => 5]), $entries[0]['line']['description']);
        $this->assertSame(48.0, $drafts->draftableMonths($subscription->periods()->firstOrFail()));
        $this->assertCount(0, $drafts->openPeriodsFor($customer, null, automatic: true), 'der automatische Serienlauf lässt die Teildeckung aus');

        // Voll gedeckt: auch der Rechnungsvorschlag bleibt leer.
        ResalePlanLink::query()->where('subscription_id', $subscription->id)->update(['quantity' => 9]);
        $this->assertCount(0, $drafts->openPeriodsFor($customer));
    }

    public function test_an_invoice_line_carries_at_most_its_quantity_of_stock_licences(): void {
        // MVP-1456: Position über eine Lizenz, drei verfügbare Stücklizenzen.
        $stock = app(LicenseStockService::class);
        $actor = $this->orgAdmin();
        $product = $stock->saveProduct($this->organization, null, ['name' => 'Exchange Online (Plan 1)', 'manufacturer' => 'Microsoft', 'key_labels' => ['Seriennummer'], 'reorder_level' => 0], $actor);
        $batch = $stock->createBatch($product, ['reference' => 'P-1', 'purchased_on' => '2026-09-01', 'quantity' => 3], null, $actor);
        foreach ([1, 2, 3] as $position) {
            $stock->saveKeys($batch->units()->where('position', $position)->sole(), ['key_1' => 'SN-' . $position], [], null, $actor);
        }
        $customer = $this->customer('Stückkunde GmbH', 'c-st');
        [$row] = $this->invoice('c-st', 'RE/2026/0001', '2026-09-20', 1, 1);
        $line = (new LexofficeInvoiceMirrorSource)->toLine($row);
        $soldOn = CarbonImmutable::parse('2026-09-20');

        $stock->sell($batch->units()->where('position', 1)->sole(), $customer, null, $soldOn, null, null, $actor, $line);
        $this->assertThrows(fn () => $stock->sell($batch->units()->where('position', 2)->sole(), $customer, null, $soldOn, null, null, $actor, $line), LicenseStockException::class);
        $this->assertSame(1, LicenseInvoiceLinks::capacityOf($line));
        $keys = array_column(app(LicenseInvoiceLinks::class)->candidates($this->organization, $product, $soldOn), 'key');
        $this->assertNotContains($line->key, $keys, 'ausgeschöpfte Position steht nicht zur Auswahl');
    }

    public function test_stub_stays_when_the_successors_first_period_is_already_covered(): void {
        // MVP-1457: die erste Periode des Nachfolgers ist schon voll — der Stummel bleibt mit seinem Bezug stehen.
        $customer = $this->customer('Stummel GmbH', 'c-sm');
        $old = $this->subscription($customer, ['provider' => 'telekom_marketplace', 'starts_on' => '2020-11-28', 'ends_on' => '2023-01-04', 'status' => 'ended']);
        $new = $this->subscription($customer, ['provider' => 'telekom_marketplace', 'starts_on' => '2022-12-30', 'ends_on' => '2026-01-04', 'status' => 'ended']);
        $stub = $old->periods()->reorder('starts_on', 'desc')->firstOrFail();
        [$a] = $this->invoice('c-sm', 'RE/2022/0001', '2022-11-30', 1, 12, '2022-11-30', '2023-11-29');
        [$b] = $this->invoice('c-sm', 'RE/2022/0002', '2022-12-30', 1, 12, '2022-12-30', '2023-12-29');
        (new PeriodLinker)->attach($stub, $a, 1.0, null, null);
        $first = $new->periods()->firstOrFail();
        (new PeriodLinker)->attach($first, $b, 12.0, null, null);

        (new LinkProposer)->propose($this->organization);

        $this->assertSame(12.0, $first->fresh()?->coveredMonths());
        $this->assertNotSame(PeriodStatus::Waived, $stub->fresh()?->status);
        $this->assertSame(1.0, $stub->fresh()?->coveredMonths());
    }

    public function test_transfer_to_the_own_holder_is_refused_and_legacy_cases_offer_the_return(): void {
        // MVP-1458: Abtretung an den Halter des Vertrags nimmt ihm nur Lizenzen weg.
        $admin = $this->orgAdmin();
        $holder = $this->customer('Selbst GmbH', 'c-se');
        $contract = $this->subscription($holder, ['quantity' => 3, 'starts_on' => '2023-08-07', 'ends_on' => '2024-08-06', 'status' => 'ended', 'provider_account' => 'CNL2', 'external_id' => 'CN2']);
        $this->actingAs($admin)->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $holder->sqid, 'quantity' => 3, 'starts_on' => '2023-08-07', 'ends_on' => '2024-08-06',
        ])->assertSessionHasErrors('customer_id');

        // Altfall an der Prüfung vorbei: Lizenzkonto und Mappe zeigen ihn mit Rücknahme.
        $legacy = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'parent_id' => $contract->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $holder->id, 'quantity' => 3, 'starts_on' => '2023-08-07', 'ends_on' => '2024-08-06', 'term_months' => 12, 'interval' => 'yearly',
            'renewal' => 'cancel', 'status' => 'ended', 'currency' => 'EUR', 'external_id' => 'CN2#1',
        ]);
        $this->assertTrue($legacy->isSelfAssignment());
        $group = app(LicenseAccounts::class)->group($this->organization, 'qualityhosting:CNL2');
        $this->assertNotNull($group);
        $this->actingAs($admin)->get(route('finance.resale.accounts.show', $group->routeKey()))->assertOk()
            ->assertSee(__('resale.transfer.self_assignment'))->assertSee(route('finance.resale.destroy', $legacy->sqid), false);
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $holder))->assertOk()->assertSee(__('resale.transfer.self_assignment'));
    }
}
