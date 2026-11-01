<?php
/*
 * Created on   : Sun Nov 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleTileFilterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\ResaleSubscription;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\{PeriodPlanner, RecipientReconciler, ReconcileBoard};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kacheln führen zu dem, was sie zählen (Pilot 2026-11-01): Lizenzkonten und
 * Abgleich filtern ihre Liste, die Mappe filtert die Tafel auf die Produkte
 * hinter der Zahl.
 */
class ResaleTileFilterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    private LexofficeArticle $standard;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-07');
        $this->exchange = $this->article('art-exo', 'Exchange Online (Plan 1)', '3.95');
        $this->standard = $this->article('art-m365', 'Microsoft 365 Business Standard', '11.70');
    }

    private function article(string $id, string $name, string $price): LexofficeArticle {
        return LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => $id, 'name' => $name, 'article_number' => strtoupper($id),
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => $price, 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
    }

    private function customer(string $name, string $contact): Customer {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => $contact, 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);

        return $customer;
    }

    private function subscription(Customer $customer, LexofficeArticle $article, string $account): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'provider_account' => $account,
            'label' => $article->name, 'quantity' => 1, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active',
            'currency' => 'EUR', 'sale_unit_price' => '47.40', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $article->id, 'starts_on' => '2025-01-15',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function invoice(string $contact, LexofficeArticle $article, string $number, string $date): void {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => $contact, 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 56.41, 'currency' => 'EUR', 'archived' => false,
            'service_starts_on' => $date, 'service_ends_on' => CarbonImmutable::parse($date)->addYear()->subDay()->toDateString(), 'lines_synced_at' => now(),
        ]);
        LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => $article->external_id, 'lexoffice_article_id' => $article->id, 'name' => $article->name,
            'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    public function test_reconcile_tiles_filter_recipients_and_the_board(): void {
        $admin = $this->orgAdmin();
        // Kachelberg: Exchange-Abo ohne Rechnung (offen, fehlt) und eine Standard-Rechnung ohne Abo (frei, zu viel).
        $open = $this->customer('Kachelberg GmbH', 'c-kb');
        $this->subscription($open, $this->exchange, 'acc-kb');
        $this->invoice('c-kb', $this->standard, 'RE/2026/0901', '2026-02-01');
        // Zahlfeld: nur eine Rechnung ohne Abo (frei, zu viel), nichts offen.
        $this->customer('Zahlfeld AG', 'c-zf');
        $this->invoice('c-zf', $this->exchange, 'RE/2026/0902', '2026-03-01');

        $this->actingAs($admin)->get(route('finance.resale.reconcile.index'))->assertOk()
            ->assertSee('Kachelberg GmbH')->assertSee('Zahlfeld AG')
            ->assertSee(route('finance.resale.reconcile.index', ['show' => 'open']))
            ->assertSee(route('finance.resale.reconcile.index', ['show' => 'surplus']));
        $this->actingAs($admin)->get(route('finance.resale.reconcile.index', ['show' => 'open']))->assertOk()
            ->assertSee('Kachelberg GmbH')->assertDontSee('Zahlfeld AG');
        $this->actingAs($admin)->get(route('finance.resale.reconcile.index', ['show' => 'surplus']))->assertOk()
            ->assertSee('Kachelberg GmbH')->assertSee('Zahlfeld AG');

        // Mappe: „fehlt“ zeigt nur das Exchange-Produkt, „zu viel“ nur Standard; ein unbekannter Filter zeigt alles.
        $result = app(RecipientReconciler::class)->forCustomer($this->organization, $open);
        $board = app(ReconcileBoard::class)->build($result, CarbonImmutable::parse('2026-09-07'));
        $labels = static fn (array $blocks): array => array_map(static fn (array $block): string => $block['product']['label'], $blocks);
        $this->assertEqualsCanonicalizing(['Exchange Online (Plan 1)', 'Microsoft 365 Business Standard'], $labels($board));
        $this->assertSame(['Exchange Online (Plan 1)'], $labels(ReconcileBoard::focus($board, 'missing')));
        $this->assertSame(['Exchange Online (Plan 1)'], $labels(ReconcileBoard::focus($board, 'open')));
        $this->assertSame(['Microsoft 365 Business Standard'], $labels(ReconcileBoard::focus($board, 'surplus')));
        $this->assertSame([], ReconcileBoard::focus($board, 'partial'));

        $workspace = route('finance.resale.reconcile.show', $open);
        $this->actingAs($admin)->get($workspace)->assertOk()
            ->assertSee(route('finance.resale.reconcile.show', [$open, 'focus' => 'surplus']) . '#board')
            ->assertDontSee(__('resale.board.focus_reset'));
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', [$open, 'focus' => 'surplus']))->assertOk()
            ->assertSee(__('resale.board.focus', ['filter' => __('resale.reconcile.kpi.surplus')]), false)
            ->assertSee(__('resale.board.focus_reset'))->assertSee('RE/2026/0901')
            // Die aktive Kachel hebt den Filter wieder auf.
            ->assertSee($workspace . '#board');
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', [$open, 'focus' => 'missing']))->assertOk()
            ->assertDontSee('RE/2026/0901');
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', [$open, 'focus' => 'unbekannt']))->assertOk()
            ->assertDontSee(__('resale.board.focus_reset'))->assertSee('RE/2026/0901');
    }

    public function test_account_tiles_filter_the_account_list(): void {
        $admin = $this->orgAdmin();
        $this->subscription($this->customer('Kachelberg GmbH', 'c-kb'), $this->exchange, 'acc-offen-1');
        $paid = $this->subscription($this->customer('Zahlfeld AG', 'c-zf'), $this->exchange, 'acc-bezahlt-2');
        $paid->periods()->update(['status' => 'billed']);

        $this->actingAs($admin)->get(route('finance.resale.accounts.index'))->assertOk()
            ->assertSee('acc-offen-1')->assertSee('acc-bezahlt-2')
            ->assertSee(route('finance.resale.accounts.index', ['show' => 'open']))
            ->assertSee(route('finance.resale.accounts.index', ['show' => 'conflicts']));
        $this->actingAs($admin)->get(route('finance.resale.accounts.index', ['show' => 'open']))->assertOk()
            ->assertSee('acc-offen-1')->assertDontSee('acc-bezahlt-2');
        $this->actingAs($admin)->get(route('finance.resale.accounts.index', ['show' => 'conflicts']))->assertOk()
            ->assertDontSee('acc-offen-1')->assertSee(__('resale.account.filter_empty'));
        // Unbekannter Filter: ganze Liste.
        $this->actingAs($admin)->get(route('finance.resale.accounts.index', ['show' => 'alles']))->assertOk()
            ->assertSee('acc-offen-1')->assertSee('acc-bezahlt-2');
    }
}
