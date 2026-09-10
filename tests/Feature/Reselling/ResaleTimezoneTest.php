<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleTimezoneTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\PeriodStatus;
use App\Models\{Customer, ExternalReference};
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner, ResaleInvoiceDraftService};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Tageswechsel und Zeitzone im Reselling-Register (Review 2026-09-10, B12/G):
 * der Stichtag ist der Kalendertag in der Anzeige-Zeitzone (`Tz`), nicht der
 * UTC-Tag. Zwischen 22:00 und 24:00 UTC (Sommerzeit) ist in Europe/Berlin
 * schon der Folgetag — eine Periode, die an diesem lokalen Tag beginnt, ist
 * fällig; eine, die erst morgen (lokal) beginnt, nicht.
 */
class ResaleTimezoneTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        // Organisation aus der Factory trägt Europe/Berlin; ohne angemeldeten Nutzer ist das die Anzeige-Zeitzone.
        $this->setUpOrganization();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-kl', 'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->getKey(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscription(string $startsOn, array $attributes = []): ResaleSubscription {
        return ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $this->customer->id, 'quantity' => 1, 'starts_on' => $startsOn, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => 'active',
        ], $attributes));
    }

    public function test_today_is_the_local_calendar_day_not_the_utc_day(): void {
        $this->assertSame('UTC', config('app.timezone'), 'App-Zeit ist UTC — der Versatz entsteht erst in der Anzeige-Zeitzone');
        $this->assertSame('Europe/Berlin', Tz::current());
        // 22:30 UTC am 04.09. = 00:30 MESZ am 05.09.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 22:30:00', 'UTC'));

        $this->assertSame('2026-09-05 00:30:00', Tz::now()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-04', CarbonImmutable::today()->toDateString(), 'der UTC-Tag ist noch der 4.');
        $today = ResalePeriod::today();
        $this->assertSame('2026-09-05', $today->toDateString(), 'Stichtag = lokaler Kalendertag');
        $this->assertSame('00:00:00', $today->format('H:i:s'), 'Datumswert ohne Zeitanteil …');
        $this->assertSame('UTC', $today->timezoneName, '… in der App-Zeitzone wie die immutable_date-Spalten');
        $this->assertTrue($today->equalTo(CarbonImmutable::parse('2026-09-05')), 'vergleichbar mit starts_on/ends_on');

        // Winterzeit: 22:30 UTC = 23:30 MEZ — noch derselbe Tag.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-04 22:30:00', 'UTC'));
        $this->assertSame('2026-12-04', ResalePeriod::today()->toDateString());
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-04 23:00:00', 'UTC'));
        $this->assertSame('2026-12-05', ResalePeriod::today()->toDateString());

        // Andere Anzeige-Zeitzone der Organisation: 22:30 UTC ist in New York 18:30 — der 4. September.
        $this->organization->forceFill(['timezone' => 'America/New_York'])->save();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 22:30:00', 'UTC'));
        $this->assertSame('America/New_York', Tz::current());
        $this->assertSame('2026-09-04', ResalePeriod::today()->toDateString());
    }

    public function test_period_starting_on_the_local_day_is_due_the_one_starting_tomorrow_is_not(): void {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 22:30:00', 'UTC'));
        $planner = new PeriodPlanner;
        $dueToday = $this->subscription('2026-09-05');
        $tomorrow = $this->subscription('2026-09-06');
        $own = $this->subscription('2026-09-05', ['customer_id' => null, 'is_own_holding' => true]);
        $planner->sync($dueToday);
        $planner->sync($tomorrow);
        $planner->sync($own);
        $this->assertSame(1, $dueToday->periods()->count(), 'Stichtag 05.09. + 90 Tage Horizont: die erste Periode');
        $this->assertSame(1, $tomorrow->periods()->count());

        // Fällig: Beginn erreicht (lokal), kein eigener Bestand.
        $this->assertSame([$dueToday->id], ResalePeriod::query()->due()->pluck('subscription_id')->all(), 'nur die Periode mit Beginn am lokalen Tag');
        $this->assertSame(1, $dueToday->openPeriodCount());
        $this->assertSame(0, $tomorrow->openPeriodCount(), 'Beginn morgen (lokal) ist nicht offen');
        $this->assertSame(0, $own->openPeriodCount(), 'eigener Bestand zählt nie');

        // Der Vorschlagslauf und der Rechnungsvorschlag nehmen denselben Stichtag.
        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(1, $result['periods'], 'nur die fällige Periode wird bewertet');
        $this->assertSame([$dueToday->id], app(ResaleInvoiceDraftService::class)->openPeriodsFor($this->customer)->pluck('subscription_id')->all());
        $this->assertSame(PeriodStatus::Open, $tomorrow->periods()->firstOrFail()->status);

        // Explizit am UTC-Tag gerechnet wäre nichts fällig — genau der Fehler, den today() vermeidet.
        $this->assertSame([], ResalePeriod::query()->due(CarbonImmutable::parse('2026-09-04'))->pluck('id')->all());
        $this->assertSame([], app(ResaleInvoiceDraftService::class)->openPeriodsFor($this->customer, CarbonImmutable::parse('2026-09-04'))->all());

        // Eine Sekunde vor Mitternacht (lokal) ist der 6. noch nicht da, um 00:00 lokal (22:00 UTC) schon.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 21:59:59', 'UTC'));
        $this->assertSame('2026-09-05', ResalePeriod::today()->toDateString());
        $this->assertSame(1, ResalePeriod::query()->due()->count());
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 22:00:00', 'UTC'));
        $this->assertSame('2026-09-06', ResalePeriod::today()->toDateString());
        $this->assertSame(2, ResalePeriod::query()->due()->count(), 'jetzt sind beide fällig');
        $this->assertSame(1, $tomorrow->openPeriodCount());

        // Organisation in einer westlichen Zeitzone: um 22:30 UTC ist dort noch der Vortag — nichts fällig.
        $this->organization->forceFill(['timezone' => 'America/New_York'])->save();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 22:30:00', 'UTC'));
        $this->assertSame('2026-09-04', ResalePeriod::today()->toDateString());
        $this->assertSame(0, ResalePeriod::query()->due()->count());
        $this->assertSame(0, $dueToday->openPeriodCount());
    }

    public function test_planner_uses_the_local_day_as_reference_for_ended_subscriptions(): void {
        // Beendet ohne bekanntes Ende: die Planung endet am Stichtag — dem lokalen Tag, nicht dem UTC-Tag.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 22:30:00', 'UTC'));
        $ended = $this->subscription('2024-08-05', ['status' => 'ended']);
        (new PeriodPlanner)->sync($ended);
        $periods = $ended->periods()->get();
        $this->assertCount(3, $periods);
        $last = $periods->last();
        $this->assertSame('2026-08-05', $last->starts_on->toDateString());
        $this->assertSame('2026-09-05', $last->ends_on->toDateString(), 'letzte Periode endet am lokalen Stichtag');

        // Am Folgetag (UTC 10:00 = 12:00 lokal, immer noch der 5.) ändert sich nichts: idempotent trotz Zeitzone.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 10:00:00', 'UTC'));
        $this->assertSame(['created' => 0, 'updated' => 0, 'removed' => 0, 'kept' => 3], (new PeriodPlanner)->sync($ended));
    }
}
