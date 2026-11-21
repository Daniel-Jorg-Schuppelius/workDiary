<?php
/*
 * Created on   : Thu Nov 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubscriptionChainTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\SubscriptionStatus;
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\ResaleSubscription;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\{PeriodPlanner, ResaleOverview, SubscriptionChain};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Phase 185 (MVP-1752–1754): Vorgänger und Nachfolger von Hand verketten, „Abo aus
 * Position anlegen“ setzt ein beendetes Abo fort, überzählige Position in der Tafel.
 */
class SubscriptionChainTest extends TestCase {
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

    /** @param  array<string, mixed>  $attributes */
    private function subscription(string $startsOn, ?string $endsOn, array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create($attributes + [
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => $endsOn === null ? 'manual' : 'telekom_marketplace',
            'label' => 'Exchange Online (Plan 1)', 'quantity' => 1, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'status' => $endsOn === null ? 'active' : 'ended', 'currency' => 'EUR', 'sale_unit_price' => '47.40', 'customer_id' => $this->customer->id,
            'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => $startsOn, 'ends_on' => $endsOn,
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function invoice(string $number, string $from, string $to): LexofficeVoucherLine {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => 'c-gw', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $from, 'total_amount' => 56.41, 'currency' => 'EUR', 'archived' => false,
            'service_starts_on' => $from, 'service_ends_on' => $to, 'lines_synced_at' => now(),
        ]);

        return LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => 'art-exo', 'lexoffice_article_id' => $this->exchange->id, 'name' => 'Exchange Online (Plan 1)',
            'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    public function test_ended_subscription_is_chained_to_the_matching_successor_and_released_again(): void {
        $admin = $this->orgAdmin();
        $old = $this->subscription('2023-02-13', '2026-02-13', ['quantity' => 4]);
        $new = $this->subscription('2026-02-13', null, ['quantity' => 4]);
        // Anderes Produkt und weit entfernter Beginn: keine Kandidaten.
        $this->subscription('2026-02-13', null, ['label' => 'Microsoft Teams Essentials', 'article_ref' => 'lex:999']);
        $this->subscription('2024-08-01', null);

        $this->assertSame([$new->id], app(SubscriptionChain::class)->successorsFor($old)->pluck('id')->all());
        $overview = app(ResaleOverview::class)->build($this->organization);
        $this->assertContains('chain', array_column($overview['tasks'], 'key'));

        $detail = route('finance.resale.show', $old->sqid);
        $this->actingAs($admin)->get($detail)->assertOk()->assertSee(__('resale.chain.link'))->assertSee(__('resale.chain.hint'));
        // Kein Kandidat: abgewiesen.
        $this->actingAs($admin)->from($detail)->post(route('finance.resale.chain.store', $old->sqid), ['successor' => $old->sqid])->assertSessionHasErrors('successor');
        $this->actingAs($admin)->from($detail)->post(route('finance.resale.chain.store', $old->sqid), ['successor' => $new->sqid])->assertRedirect($detail)->assertSessionHasNoErrors();

        $old->refresh();
        $this->assertSame($new->id, $old->successor_id);
        $this->assertSame(SubscriptionStatus::Superseded, $old->status);
        $this->assertSame(1, AuditLog::query()->where('event', SubscriptionChain::AUDIT_LINKED)->count());
        $this->actingAs($admin)->get($detail)->assertOk()->assertDontSee(__('resale.chain.hint'))->assertSee(__('resale.chain.unlink'));

        $this->actingAs($admin)->from($detail)->delete(route('finance.resale.chain.destroy', $old->sqid))->assertRedirect($detail);
        $old->refresh();
        $this->assertNull($old->successor_id);
        $this->assertSame(SubscriptionStatus::Ended, $old->status);
    }

    public function test_worklist_preselects_unambiguous_pairs_and_chains_the_chosen_ones(): void {
        $admin = $this->orgAdmin();
        // Eindeutig: ein Vorgänger, ein Kandidat.
        $teamsOld = $this->subscription('2023-11-09', '2025-11-09', ['label' => 'Microsoft Teams Essentials', 'article_ref' => 'lex:501']);
        $teamsNew = $this->subscription('2025-11-09', null, ['label' => 'Microsoft Teams Essentials', 'article_ref' => 'lex:501']);
        // Mehrdeutig: zwei Vorgänger, zwei Kandidaten mit nahen Stichtagen.
        $oldA = $this->subscription('2023-10-13', '2025-10-18');
        $oldB = $this->subscription('2023-10-20', '2025-10-25');
        $newA = $this->subscription('2025-10-18', null);
        $newB = $this->subscription('2025-10-25', null);

        $rows = collect(app(SubscriptionChain::class)->worklist($this->organization->id))->keyBy(static fn(array $row): int => $row['subscription']->id);
        $this->assertSame($teamsNew->id, $rows[$teamsOld->id]['unique']?->id);
        $this->assertNull($rows[$oldA->id]['unique']);
        $this->assertSame([$newA->id, $newB->id], $rows[$oldA->id]['candidates']->pluck('id')->all(), 'nächster Beginn zuerst');

        $this->actingAs($admin)->get(route('finance.resale.chain.index'))->assertOk()
            ->assertSee(__('resale.chain.dialog_title'))->assertSee($teamsOld->sqid)->assertSee($oldB->sqid);
        $overview = collect(app(ResaleOverview::class)->build($this->organization)['tasks'])->firstWhere('key', 'chain');
        $this->assertSame(3, $overview['count'] ?? null);
        $this->assertSame(route('finance.resale.chain.index'), $overview['url'] ?? null);

        // Vorwahl plus eine Handauswahl; die dritte Zeile bleibt aus, ein Nicht-Kandidat wird übergangen.
        $this->actingAs($admin)->post(route('finance.resale.chain.bulk'), ['successors' => [
            $teamsOld->sqid => $teamsNew->sqid, $oldA->sqid => $newA->sqid, $oldB->sqid => $teamsNew->sqid,
        ]])->assertSessionHasNoErrors()->assertSessionHas('success', trans_choice('resale.chain.flash_bulk', 2, ['count' => 2]));

        $this->assertSame($teamsNew->id, $teamsOld->fresh()?->successor_id);
        $this->assertSame($newA->id, $oldA->fresh()?->successor_id);
        $this->assertNull($oldB->fresh()?->successor_id);
    }

    public function test_subscription_created_from_an_invoice_line_continues_the_ended_one(): void {
        $admin = $this->orgAdmin();
        $old = $this->subscription('2023-04-22', '2024-04-27');
        $line = $this->invoice('RE/2024/0630', '2024-04-22', '2025-04-21');
        $key = \App\Support\Sqid::encode(LexofficeVoucherLine::class, $line->id);

        $this->actingAs($admin)->get(route('finance.resale.create', ['customer' => $this->customer->sqid, 'line' => $key]))->assertOk()
            ->assertSee(__('resale.chain.predecessor_hint'))->assertSee($old->sqid);

        $this->actingAs($admin)->post(route('finance.resale.store'), [
            'kind' => 'license', 'provider' => 'manual', 'label' => 'Exchange Online (Plan 1)', 'holder' => 'customer', 'customer_id' => $this->customer->id,
            'quantity' => 1, 'starts_on' => '2024-04-22', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active',
            'sale_unit_price' => '47.40', 'predecessor' => $old->sqid,
        ])->assertSessionHasNoErrors();

        $new = ResaleSubscription::query()->where('provider', 'manual')->firstOrFail();
        $this->assertSame($new->id, $old->fresh()?->successor_id);
        $this->assertSame(SubscriptionStatus::Superseded, $old->fresh()?->status);
    }

    public function test_expired_manual_subscription_is_ended_by_the_daily_run(): void {
        $expired = $this->subscription('2026-02-13', '2026-09-06', ['provider' => 'manual', 'status' => 'active', 'renewal' => 'cancel']);
        $today = $this->subscription('2026-02-13', '2026-09-07', ['provider' => 'manual', 'status' => 'active', 'renewal' => 'cancel']);
        // Importierte Abos führt der Anbieter, auch mit abgelaufenem Ende.
        $imported = $this->subscription('2025-02-13', '2026-02-13', ['status' => 'active']);
        $successor = $this->subscription('2026-09-01', null);
        $chained = $this->subscription('2026-01-01', '2026-08-31', ['provider' => 'manual', 'status' => 'cancelled', 'successor_id' => $successor->id]);

        $this->artisan('resale:sync-periods')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Ended, $expired->fresh()?->status);
        $this->assertSame(SubscriptionStatus::Active, $today->fresh()?->status, 'der letzte Tag gehört noch dazu');
        $this->assertSame(SubscriptionStatus::Active, $imported->fresh()?->status);
        $this->assertSame(SubscriptionStatus::Superseded, $chained->fresh()?->status);
        // Erst beendet erscheint das Handabo in der Kettenliste.
        $this->assertTrue(SubscriptionChain::needsSuccessor($expired->fresh()));
    }

    public function test_subscription_without_prices_takes_them_from_the_one_it_continues(): void {
        $admin = $this->orgAdmin();
        $old = $this->subscription('2023-02-13', '2026-02-13', ['purchase_unit_price' => '40.00']);
        $payload = [
            'kind' => 'license', 'provider' => 'manual', 'label' => 'Exchange Online (Plan 1)', 'holder' => 'customer', 'customer_id' => $this->customer->id,
            'article' => (string) app(\App\Services\Platform\Catalog\ArticleCatalog::class)->find((int) $this->organization->id, 'lex:' . $this->exchange->id)?->formKey, 'quantity' => 1, 'starts_on' => '2026-02-13', 'ends_on' => '2026-09-06', 'term_months' => 7,
            'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'active',
        ];

        $this->actingAs($admin)->post(route('finance.resale.store'), $payload)->assertSessionHasNoErrors()->assertSessionHas('success', __('resale.flash.created_prices'));

        $new = ResaleSubscription::query()->where('provider', 'manual')->firstOrFail();
        $this->assertSame('47.40', $new->sale_unit_price?->withScale(2)->getAmount());
        $this->assertSame('40.00', $new->purchase_unit_price?->withScale(2)->getAmount());
        $this->assertNull($old->fresh()?->successor_id, 'verkettet wird nur ausdrücklich');
        $this->assertSame(SubscriptionStatus::Ended, $new->status, 'Ende liegt schon hinter heute');

        // Eigene Preise bleiben.
        $this->actingAs($admin)->post(route('finance.resale.store'), ['label' => 'Exchange eigen', 'sale_unit_price' => '50.00', 'purchase_unit_price' => '41.00'] + $payload)
            ->assertSessionHas('success', __('resale.flash.created'));
        $own = ResaleSubscription::query()->where('label', 'Exchange eigen')->firstOrFail();
        $this->assertSame('50.00', $own->sale_unit_price?->withScale(2)->getAmount());
    }

    public function test_invoices_after_the_end_of_the_product_count_as_overbilled_in_euro(): void {
        $admin = $this->orgAdmin();
        // Produktwechsel: das Postfach endete 2023, die Serienvorlage berechnete es zwei Jahre weiter.
        $this->subscription('2022-10-18', '2023-10-17');
        $this->invoice('RE/2022/0100', '2022-10-18', '2023-10-17');
        $this->invoice('RE/2024/0100', '2024-10-18', '2025-10-17');
        $this->invoice('RE/2025/0100', '2025-10-18', '2026-10-17');
        app(\App\Services\Reselling\Register\LinkProposer::class)->propose($this->organization);

        $row = collect(app(\App\Services\Reselling\Register\RecipientReconciler::class)->overview($this->organization))->firstWhere('customer.id', $this->customer->id);
        $this->assertNotNull($row);
        $this->assertSame(94.80, $row['overbilled'], 'zwei Jahre à 47,40 € nach dem Abo-Ende');
        $this->assertSame(0.0, $row['deviating']);

        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $this->customer))->assertOk()
            ->assertSee(__('resale.board.surplus.after_end'))
            ->assertDontSee(__('resale.board.surplus.duplicate'));
        $this->actingAs($admin)->get(route('finance.resale.reconcile.index', ['show' => 'overbilled']))->assertOk()->assertSee($this->customer->name);
        // Das Lizenzkonto nennt die freien Positionen des Kunden im Kopf und öffnet zugeordnete Belege im Dialog.
        $subscription = \App\Models\Reselling\ResaleSubscription::query()->firstOrFail();
        $account = route('finance.resale.accounts.show', app(\App\Services\Reselling\Register\LicenseAccounts::class)->keyFor($this->organization, $subscription));
        $this->actingAs($admin)->get($account)->assertOk()
            ->assertSee(trans_choice('resale.account.unassigned', 2, ['count' => 2, 'amount' => '94,80 €', 'name' => $this->customer->name]))
            ->assertSee('94,80')
            ->assertSee(route('finance.resale.reconcile.show', $this->customer) . '#board')
            ->assertSee('finanzen/', false)
            ->assertSee(__('resale.voucher.open'));
        $this->assertStringContainsString('94,80', app(ResaleOverview::class)->build($this->organization)['summary']['overbilled']);
    }

    public function test_correction_sheet_nets_what_to_credit_against_what_to_bill(): void {
        $admin = $this->orgAdmin();
        // Altes Produkt lief nach dem Ende weiter (zwei Rechnungen), das neue Produkt wurde nie berechnet.
        $this->subscription('2022-10-18', '2023-10-17');
        $this->invoice('RE/2022/0200', '2022-10-18', '2023-10-17');
        $this->invoice('RE/2024/0200', '2024-10-18', '2025-10-17');
        $this->invoice('RE/2025/0200', '2025-10-18', '2026-10-17');
        $this->subscription('2026-01-01', null, ['label' => 'Microsoft Teams Essentials', 'article_ref' => 'lex:999']);
        app(\App\Services\Reselling\Register\LinkProposer::class)->propose($this->organization);

        $today = \App\Models\Reselling\ResalePeriod::today();
        $analysis = app(\App\Services\Reselling\Register\RecipientReconciler::class)->forCustomer($this->organization, $this->customer, $today);
        $sheet = \App\Services\Reselling\Register\RecipientCorrection::build($analysis, [], $today);
        $this->assertSame(94.80, $sheet['totals']['credit']);
        $this->assertSame(47.40, $sheet['totals']['bill']);
        $this->assertSame(-47.40, $sheet['totals']['balance'], 'mehr gutzuschreiben als zu berechnen');
        $this->assertCount(2, $sheet['rows'], 'beide Produkte im Lizenzbild');
        $this->assertContains(2022, $sheet['years']);
        $this->assertContains(2026, $sheet['years']);

        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $this->customer))->assertOk()
            ->assertSee(__('resale.correction.title'))
            ->assertSee(__('resale.correction.picture_title'))
            ->assertSee('RE/2024/0200')
            ->assertSee(route('finance.resale.reconcile.correction', $this->customer));
        $this->actingAs($admin)->get(route('finance.resale.reconcile.correction', $this->customer))->assertOk();
    }

    public function test_detail_pages_return_to_the_page_they_were_opened_from(): void {
        $admin = $this->orgAdmin();
        $subscription = $this->subscription('2026-02-13', null);
        $show = route('finance.resale.show', $subscription->sqid);
        $account = route('finance.resale.accounts.show', app(\App\Services\Reselling\Register\LicenseAccounts::class)->keyFor($this->organization, $subscription));
        $back = static fn(string $url): string => 'href="' . e($url) . '"';

        // Aus dem Lizenzkonto geöffnet: zurück geht es dorthin, nicht zur Abo-Liste.
        $this->actingAs($admin)->get($show, ['referer' => $account])->assertOk()->assertSee($back($account), false);
        // Nach einer Aktion auf der Abo-Seite ist sie selbst der Referer: die Herkunft bleibt.
        $this->actingAs($admin)->get($show, ['referer' => $show])->assertOk()->assertSee($back($account), false);
        // Zurück im Lizenzkonto: sein Rückpfeil zeigt weiter auf die Liste, nicht auf das Abo (kein Pendeln).
        $this->actingAs($admin)->get($account, ['referer' => $show])->assertOk()
            ->assertSee($back(route('finance.resale.accounts.index')), false);
        $this->assertSame($account, session('back_origins')[parse_url($show, PHP_URL_PATH)] ?? null);
        $this->assertArrayNotHasKey((string) parse_url($account, PHP_URL_PATH), (array) session('back_origins'));
        // Von der Kundenakte geöffnet: zurück zur Kundenakte.
        $customer = route('customers.show', $this->customer);
        $this->actingAs($admin)->get($show, ['referer' => $customer])->assertOk()->assertSee($back($customer), false);
        // Fremde Herkunft zählt nicht.
        $this->actingAs($admin)->get($show, ['referer' => 'https://example.org/irgendwo'])->assertOk()->assertDontSee('example.org');
    }

    public function test_board_marks_a_surplus_line_and_offers_to_ignore_it(): void {
        $admin = $this->orgAdmin();
        $this->subscription('2023-10-18', '2024-10-17');
        $this->invoice('RE/2023/0100', '2023-10-18', '2024-10-17');
        $this->invoice('RE/2023/0101', '2023-10-20', '2024-10-19');
        app(\App\Services\Reselling\Register\LinkProposer::class)->propose($this->organization);

        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $this->customer))->assertOk()
            ->assertSee(__('resale.board.surplus.duplicate'))
            ->assertSee(route('finance.resale.periods.unlinked.ignore'));
    }
}
