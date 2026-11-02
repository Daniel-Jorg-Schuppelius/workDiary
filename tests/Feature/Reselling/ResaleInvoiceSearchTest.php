<?php
/*
 * Created on   : Sun Nov 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleInvoiceSearchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\PeriodPlanner;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rechnungen über ihre Nummer finden (Pilot 2026-11-01): Der Bezug-Dialog
 * führt außer dem Zeitfenster alle Rechnungen des Empfängers mit Suchtext und
 * nennt, wo eine vergebene hängt; Abo- und Periodenliste suchen auch nach
 * Rechnungsnummer.
 */
class ResaleInvoiceSearchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-07');
        $this->exchange = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'ART-EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
    }

    private function invoice(string $number, string $from): LexofficeVoucherLine {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => 'c-sw', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $from, 'total_amount' => 56.41, 'currency' => 'EUR', 'archived' => false,
            'service_starts_on' => $from, 'service_ends_on' => CarbonImmutable::parse($from)->addYear()->subDay()->toDateString(), 'lines_synced_at' => now(),
        ]);

        return LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => 'art-exo', 'lexoffice_article_id' => $this->exchange->id, 'name' => 'Exchange Online (Plan 1)',
            'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    public function test_link_dialog_and_lists_find_invoices_by_number(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Suchwald GmbH']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-sw', 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'label' => 'Postfach Suchwald', 'quantity' => 1,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
            'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2024-01-15',
        ]);
        (new PeriodPlanner)->sync($subscription);
        /** @var list<ResalePeriod> $periods */
        $periods = $subscription->periods()->orderBy('starts_on')->get()->all();
        [$p2024, $p2025, $p2026] = $periods;
        $this->invoice('RE/2024/0951', '2024-01-15');
        $late = $this->invoice('RE/2026/0952', '2026-01-15');
        $this->actingAs($admin)->post(route('finance.resale.periods.link.store', $p2026->sqid), ['line_id' => Sqid::encode(LexofficeVoucherLine::class, $late->id), 'months' => '12'])
            ->assertSessionHasNoErrors();

        // Dialog der Periode 2025/26: die Rechnung von 2026 liegt im Fenster und sagt, wo sie hängt; die von 2024 liegt
        // davor — der Dialog lädt zuerst nur das Fenster und bietet „Alle anzeigen“ (perfc-08), erst dann steht sie
        // durchsuchbar darunter.
        $this->actingAs($admin)->get(route('finance.resale.periods.link.create', $p2025->sqid))->assertOk()
            ->assertSee(__('resale.link.search'))->assertSee(__('resale.link.show_all'))
            ->assertSee(route('finance.resale.periods.link.create', [$p2025->sqid, 'all' => 1]), false)
            ->assertSee('data-haystack="re/2026/0952', false)
            ->assertSee(trim(__('resale.board.line_taken', ['periods' => ''])))
            ->assertDontSee(__('resale.link.group_other'))->assertDontSee('data-haystack="re/2024/0951', false);
        $this->actingAs($admin)->get(route('finance.resale.periods.link.create', [$p2025->sqid, 'all' => 1]))->assertOk()
            ->assertSee(__('resale.link.group_other'))->assertDontSee(__('resale.link.show_all'))
            ->assertSee('data-haystack="re/2026/0952', false)->assertSee('data-haystack="re/2024/0951', false);
        $this->actingAs($admin)->get(route('finance.resale.periods.link.create', $p2024->sqid))->assertOk()
            ->assertSee(__('resale.link.group_window'))->assertSee('data-haystack="re/2024/0951', false);

        // Periodenliste: Suche nach der Nummer zeigt nur die Periode, an der sie hängt.
        $this->actingAs($admin)->get(route('finance.resale.periods.index', ['status' => 'billed', 'q' => 'RE/2026/0952']))->assertOk()
            ->assertSee('Postfach Suchwald');
        $this->actingAs($admin)->get(route('finance.resale.periods.index', ['status' => 'billed', 'q' => 'RE/2099/0001']))->assertOk()
            ->assertDontSee('Postfach Suchwald');

        // Aboliste: Suche nach Rechnungsnummer findet das Abo.
        $this->actingAs($admin)->get(route('finance.resale.subscriptions', ['q' => '0952']))->assertOk()->assertSee('Postfach Suchwald');
        $this->actingAs($admin)->get(route('finance.resale.subscriptions', ['q' => 'RE/2099/0001']))->assertOk()->assertDontSee('Postfach Suchwald');
    }
}
