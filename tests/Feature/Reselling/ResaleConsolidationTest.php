<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleConsolidationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\Customer\Customer;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\Models\LexofficeVoucherLine;
use App\Services\Reselling\Cockpit\ResaleCockpitPanel;
use App\Services\Reselling\Register\{PeriodPlanner, RecipientReconciler, ResaleUnbilledReport};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-11-02: eine Definition „fällige Periode“ für
 * Cockpit, Mappe, Bericht und Modell (kon-02), Periodenaktionen als ein
 * Partial (kon-10), Periodenbezeichnung im Anzeigeformat (kon-15).
 */
class ResaleConsolidationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'quantity' => 2, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '247.20',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /** Teilweise gedeckt: ein bestätigter Bezug über die Hälfte der Lizenzmonate. */
    private function makePartial(ResalePeriod $period): void {
        ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $period->id, 'subscription_id' => $period->subscription_id,
            'linkable_type' => MorphMap::alias(LexofficeVoucherLine::class), 'linkable_id' => 1, 'voucher_number' => 'RE/2025/9001', 'voucher_date' => '2025-08-05',
            'quantity' => 1, 'months' => 12, 'amount' => '247.20', 'currency' => 'EUR', 'origin' => LinkOrigin::Confirmed,
        ]);
        $period->forceFill(['status' => PeriodStatus::Partial])->save();
    }

    public function test_cockpit_workbook_report_and_model_count_the_same_due_periods(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin); // vor der ersten Modulauflösung: der Plan der Organisation kommt im Test über den Nutzer
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH']);
        $subscription = $this->subscription(['customer_id' => $customer->id]); // fällig: 2025-08-05 und 2026-08-05
        $own = $this->subscription(['customer_id' => null, 'is_own_holding' => true]);
        $periods = $subscription->periods()->orderBy('starts_on')->get();
        $this->assertSame(['2025-08-05', '2026-08-05'], $periods->map(static fn(ResalePeriod $p): string => $p->starts_on->toDateString())->all());
        $this->makePartial($periods->firstOrFail());

        // Scope und Speicherform zählen gleich — teilweise gedeckt zählt, eigener Bestand nie.
        $this->assertSame(2, ResalePeriod::query()->due()->count());
        $this->assertSame(1, ResalePeriod::query()->due(withPartial: false)->count());
        $subscription->refresh()->load('periods');
        $this->assertSame(2, $subscription->duePeriods()->count());
        $this->assertSame(1, $subscription->duePeriods(withPartial: false)->count());
        $this->assertSame(2, $subscription->openPeriodCount());
        $this->assertSame(0, $own->load('periods')->openPeriodCount(), 'eigener Bestand wird nie berechnet');
        $this->assertSame([], $own->duePeriods()->all());

        // Cockpit des Kunden … (Team-Kontext neu setzen: Factories verstellen ihn, siehe LicenseInvoiceLinkTest)
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $panel = app(ResaleCockpitPanel::class)->render($customer, $admin);
        $this->assertNotNull($panel);
        $cockpit = $panel->getData()['customerSubscriptions']->firstWhere('id', $subscription->id);
        $this->assertSame(2, (int) $cockpit->open_periods_count, 'Cockpit zählt die teilweise gedeckte Periode mit');

        // … Arbeitsmappe des Empfängers …
        $rows = array_column(app(RecipientReconciler::class)->overview($this->organization), null, 'name');
        $this->assertSame(2, $rows['Badhaus Bäder GmbH']['open'] + $rows['Badhaus Bäder GmbH']['partial'], 'Cockpit-Zahl = Mappe');
        $this->assertSame(1, $rows['Badhaus Bäder GmbH']['partial']);

        // … und Bericht „Abo ohne Rechnung“ nennen dieselben zwei Perioden.
        $report = app(ResaleUnbilledReport::class)->build(0);
        $this->assertCount(1, $report);
        $this->assertSame(2, $report[0]['open_periods']);
        $this->assertSame('741.60', $report[0]['open_amount']?->getAmount(), 'offen: halbe Teilperiode 2025 (247,20) + ganze Periode 2026 (494,40)');
    }

    public function test_period_label_follows_the_display_date_format(): void {
        $admin = $this->orgAdmin();
        $period = $this->subscription(['customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id])->id])->periods()->orderBy('starts_on')->firstOrFail();

        $this->actingAs($admin);
        $this->assertSame('05.08.2025 – 04.08.2026', $period->label());

        $admin->forceFill(['preferences' => ['date_format' => 'Y-m-d']])->save();
        $this->actingAs($admin->refresh());
        $this->assertSame('2025-08-05 – 2026-08-04', $period->label(), 'Bezeichnung folgt dem Anzeigeformat, nicht festem d.m.Y');
    }

    public function test_period_actions_partial_follows_status_and_rights(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $subscription = $this->subscription(['customer_id' => $customer->id]);
        $period = $subscription->periods()->orderBy('starts_on')->firstOrFail();
        $render = static fn(ResalePeriod $period, bool $upcoming = false, bool $canManage = true, bool $dense = false): string => view('finance.resale._period_actions', [
            'period' => $period->refresh()->load('links'), 'upcoming' => $upcoming, 'canManage' => $canManage, 'dense' => $dense,
        ])->render();
        $confirm = route('finance.resale.periods.confirm', $period->sqid);
        $link = route('finance.resale.periods.link.create', $period->sqid);
        $waive = route('finance.resale.periods.waive.create', $period->sqid);
        $reopen = route('finance.resale.periods.reopen', $period->sqid);

        // Offen: Bezug und Verzicht, kein Bestätigen, kein Wieder-öffnen.
        $html = $render($period);
        $this->assertStringContainsString('gap-1', $html);
        $this->assertStringContainsString($link, $html);
        $this->assertStringContainsString($waive, $html);
        $this->assertStringNotContainsString($confirm, $html);
        $this->assertStringNotContainsString($reopen, $html);

        // Teilweise gedeckt: Verzicht und Wieder-öffnen; Tafel mit engem Abstand.
        $this->makePartial($period);
        $html = $render($period, dense: true);
        $this->assertStringContainsString('gap-0.5', $html);
        $this->assertStringContainsString($waive, $html);
        $this->assertStringContainsString($reopen, $html);
        $this->assertStringNotContainsString($confirm, $html, 'bestätigter Bezug: nichts zu bestätigen');

        // Nur Vorschläge: Bestätigen erscheint.
        ResalePeriodLink::query()->where('period_id', $period->id)->update(['origin' => LinkOrigin::Proposed->value]);
        $html = $render($period);
        $this->assertStringContainsString($confirm, $html);

        // Verzichtet: kein Bezug, kein Verzicht, nur Wieder-öffnen.
        $period->forceFill(['status' => PeriodStatus::Waived])->save();
        $html = $render($period);
        $this->assertStringNotContainsString($link, $html);
        $this->assertStringNotContainsString($waive, $html);
        $this->assertStringContainsString($reopen, $html);

        // Bevorstehend oder ohne Recht: keine Aktionen.
        $this->assertSame('', trim($render($period, upcoming: true)));
        $this->assertSame('', trim($render($period, canManage: false)));
    }
}
