<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BaseInterestRateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Models\Customer\Customer;
use App\Models\Invoicing\{BaseInterestRate, Invoice};
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\{BaseInterestRateService, DunningService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** MVP-879: Basiszinssatz nach § 247 BGB aus der Bundesbank-Reihe und Verzugszins darauf. */
final class BaseInterestRateTest extends TestCase {
    use RefreshDatabase;

    /** Auszug im Format der Bundesbank-CSV (Kopfzeilen, Dezimalkomma, Bemerkungen). */
    private const CSV = <<<'CSV'
        "";BBIN1.M.DE.BBK.BBKBAS2.EUR.ME;BBIN1.M.DE.BBK.BBKBAS2.EUR.ME_FLAGS
        "";Basiszinssatz gemäß BGB / Stand am Monatsende / SU0115;
        Einheit;% p.a.;
        2022-11;-0,88;
        2022-12;-0,88;
        2023-01;1,62;Bemerkung
        2026-04;1,27;
        2026-05;1,27;
        2026-06;1,27;
        2026-07;1,52;Bemerkung
        2026-08;1,52;
        2026-09;1,52;
        "";Bemerkung zu 2026-07: Ab 1. Juli 1,52%;
        CSV;

    private function rates(): BaseInterestRateService {
        return app(BaseInterestRateService::class);
    }

    public function test_ingest_collapses_months_into_periods_and_is_idempotent(): void {
        $this->assertSame(4, $this->rates()->ingest(self::CSV));
        $this->assertSame(0, $this->rates()->ingest(self::CSV));

        $this->assertSame(['2022-11-01', '2023-01-01', '2026-04-01', '2026-07-01'], BaseInterestRate::query()->orderBy('valid_from')->get()->map(fn ($r) => $r->valid_from->toDateString())->all());
        $this->assertSame(-0.88, $this->rates()->rateOn(CarbonImmutable::parse('2022-12-15')));
        $this->assertSame(1.27, $this->rates()->rateOn(CarbonImmutable::parse('2026-06-30')));
        $this->assertSame(1.52, $this->rates()->rateOn(CarbonImmutable::parse('2026-07-01')));
        $this->assertNull($this->rates()->rateOn(CarbonImmutable::parse('2020-01-01')));

        $periods = $this->rates()->periods(CarbonImmutable::parse('2026-06-21'), CarbonImmutable::parse('2026-07-10'));
        $this->assertCount(2, $periods);
        $this->assertSame(['2026-06-21', '2026-06-30', 1.27], [$periods[0]['from']->toDateString(), $periods[0]['to']->toDateString(), $periods[0]['rate']]);
        $this->assertSame(['2026-07-01', '2026-07-10', 1.52], [$periods[1]['from']->toDateString(), $periods[1]['to']->toDateString(), $periods[1]['rate']]);
    }

    public function test_default_interest_on_base_rate_is_calculated_per_period(): void {
        $this->rates()->ingest(self::CSV);
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $settings = (array) ($org->settings ?? []);
        $settings['invoicing']['dunning'] = ['interest_mode' => 'base_rate', 'interest_points' => 9];
        $org->update(['settings' => $settings]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $invoice = Invoice::query()->create([
            'organization_id' => $org->id, 'customer_id' => $customer->id, 'number' => 'R2026-9001',
            'status' => Invoice::STATUS_ISSUED, 'type' => Invoice::TYPE_INVOICE, 'currency' => 'EUR',
            'tax_rate' => '19.00', 'total' => '10000.00',
            'issued_on' => '2026-06-01', 'due_on' => '2026-06-20',
        ]);

        $interest = app(DunningService::class)->interest($invoice, CarbonImmutable::parse('2026-07-10'));

        // 10 Tage zu 1,27 + 9 = 10,27 % und 10 Tage zu 1,52 + 9 = 10,52 %:
        // 10.000 × (10 × 10,27 + 10 × 10,52) / 100 / 365 = 56,96
        $this->assertNotNull($interest);
        $this->assertSame(20, $interest['days']);
        $this->assertSame(10.52, $interest['rate']);
        $this->assertSame('base_rate', $interest['mode']);
        $this->assertSame(9.0, $interest['points']);
        $this->assertSame(56.96, $interest['amount']);

        $html = view('invoices.dunning-pdf', app(\App\Services\Invoicing\DunningPdfRenderer::class)->viewData($invoice, 1, interest: $interest))->render();
        $this->assertStringContainsString(__('finance.dunning.interest_row_base', ['points' => '9,00', 'rate' => '10,52', 'days' => 20]), $html);
    }

    public function test_base_rate_mode_without_imported_rate_shows_no_interest_and_warns(): void {
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $settings = (array) ($org->settings ?? []);
        $settings['invoicing']['dunning'] = ['interest_mode' => 'base_rate', 'interest_points' => 5];
        $org->update(['settings' => $settings]);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->assertTrue(app(DunningService::class)->baseRateMissing());
        $this->assertSame(0.0, app(DunningService::class)->interestRate());
        $this->actingAs($admin)->get(route('finance.dunning.index'))->assertOk()->assertSee(__('finance.dunning.interest_base_missing'));
    }

    public function test_sync_command_imports_from_the_bundesbank(): void {
        $fake = FakePluginHttp::fake(['https://api.statistiken.bundesbank.de/*' => FakePluginHttp::response(self::CSV)]);

        $this->artisan('invoicing:base-rate-sync')->expectsOutputToContain('4 Perioden')->assertSuccessful();
        $this->assertSame(4, BaseInterestRate::query()->count());

        FakePluginHttp::fake(['https://api.statistiken.bundesbank.de/*' => FakePluginHttp::response('', 503)]);
        $this->artisan('invoicing:base-rate-sync')->assertFailed();
        $this->assertNotNull($fake);
    }
}
