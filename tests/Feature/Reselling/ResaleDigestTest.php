<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleDigestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Console\Commands\Reselling\ResaleDigestCommand;
use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\{Customer, LexofficeVoucherLine, User};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Notifications\Finance\ResalePeriodsDigestNotification;
use App\Services\Reselling\Register\PeriodPlanner;
use App\Support\NotificationText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Reselling-Digest (Feature 152, Prozesse 6 / Review 2026-09-10 A5):
 * Benachrichtigung nur bei Befund, Empfänger sind die Nutzer mit
 * `reselling.manage`; Kennzahlen fällig/Vorschläge/ohne Halter/
 * Verlängerungen/ohne Rechnung; Scheduler-Registrierung.
 */
class ResaleDigestTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $manager;

    private User $worker;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
        $this->manager = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'quantity' => 1, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_no_notification_without_findings(): void {
        Notification::fake();
        // Eigener Bestand mit offenen Perioden zählt nicht — er wird nie berechnet.
        $this->subscription(['is_own_holding' => true, 'company_name' => 'Eigene GmbH']);

        $this->artisan('resale:digest')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_findings_reach_managers_only_with_all_figures(): void {
        Notification::fake();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);

        // (a) Zwei fällige Perioden (2025, 2026) mit Verkaufspreis → offener Betrag; die 2025er ist älter als 60 Tage → (e).
        $due = $this->subscription(['customer_id' => $customer->id, 'sale_unit_price' => '247.20']);
        // (b) Unbestätigter Vorschlag: Periode „berechnet" ohne Entscheidung, Bezug aus dem Lauf.
        $proposed = $this->subscription(['customer_id' => $customer->id, 'label' => 'Exchange Online (Plan 1)', 'starts_on' => '2026-08-01']);
        $period = $proposed->periods()->firstOrFail();
        ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $period->id, 'subscription_id' => $proposed->id,
            'linkable_type' => (new LexofficeVoucherLine)->getMorphClass(), 'linkable_id' => 1, 'voucher_number' => 'RE/2026/0900', 'voucher_date' => '2026-08-02',
            'quantity' => 1, 'months' => 12, 'amount' => '47.40', 'currency' => 'EUR', 'origin' => LinkOrigin::Proposed,
        ]);
        $period->forceFill(['status' => PeriodStatus::Billed])->save();
        // (c) Abo ohne Halter (Inbox) — Beginn in der Zukunft, damit es nicht zugleich fällig ist.
        $this->subscription(['company_name' => 'Unbekannt UG', 'starts_on' => '2026-10-01']);
        // (d) Gekündigt, Ende in 20 Tagen; die laufende Periode ist entschieden (verzichtet), zählt also nicht als fällig.
        $ending = $this->subscription(['customer_id' => $customer->id, 'label' => 'Domain example.de', 'renewal' => 'cancel', 'starts_on' => '2025-09-24', 'ends_on' => '2026-09-24']);
        ResalePeriod::query()->where('subscription_id', $ending->id)->update(['status' => PeriodStatus::Waived->value, 'decided_at' => now()]);

        $this->artisan('resale:digest')->assertSuccessful()->expectsOutputToContain('Empfänger benachrichtigt');

        Notification::assertSentTo($this->manager, ResalePeriodsDigestNotification::class, function (ResalePeriodsDigestNotification $n) use ($due): bool {
            $this->assertSame(2, $n->dueCount, 'zwei fällige offene Perioden des Kundenabos');
            $this->assertStringContainsString('494,40', $n->dueAmount, '2 × 247,20 € offen');
            $this->assertSame(1, $n->proposedCount);
            $this->assertSame(1, $n->unassignedCount);
            $this->assertSame(1, $n->renewalCount, 'Ende in 20 Tagen zählt als Verlängerungsereignis');
            $this->assertSame(1, $n->staleCount, 'nur das Abo mit Periode > 60 Tage ohne Rechnung');
            $this->assertSame(ResaleDigestCommand::RENEWAL_DAYS, $n->renewalDays);
            $this->assertSame(6, $n->total());
            $this->assertSame($due->periods()->due(ResalePeriod::today())->count(), $n->dueCount);

            $data = $n->toArray($this->manager);
            $this->assertSame(route('finance.resale.periods.index'), $data['url']);
            $this->assertStringContainsString('6', NotificationText::title($data));
            $this->assertStringContainsString('494,40', NotificationText::message($data));

            return true;
        });
        Notification::assertNotSentTo($this->worker, ResalePeriodsDigestNotification::class);
    }

    public function test_renewal_of_an_auto_renewing_subscription_counts_without_other_findings(): void {
        Notification::fake();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        // Nächste Periode beginnt in 10 Tagen (2026-09-14); die laufende ist entschieden → nur (d) schlägt an.
        $running = $this->subscription(['customer_id' => $customer->id, 'starts_on' => '2025-09-14']);
        $running->periods()->firstOrFail()->forceFill(['status' => PeriodStatus::Waived, 'decided_at' => now()])->save();

        $this->artisan('resale:digest')->assertSuccessful();

        Notification::assertSentTo($this->manager, ResalePeriodsDigestNotification::class, static fn(ResalePeriodsDigestNotification $n): bool => $n->dueCount === 0 && $n->renewalCount === 1 && $n->total() === 1);
    }

    public function test_digest_is_registered_in_the_scheduler(): void {
        $jobs = (array) config('scheduler.jobs');
        $this->assertArrayHasKey('resale.digest', $jobs);
        $this->assertSame('resale:digest', $jobs['resale.digest']['command']);
        $this->assertSame('housekeeping', $jobs['resale.digest']['criticality']);
        $this->assertSame('weeklyOn', $jobs['resale.digest']['cadence']['type']);
        foreach (['de', 'en', 'fr', 'it', 'es'] as $locale) {
            $this->assertTrue(app('translator')->has('scheduler.job.resale.digest', $locale, false), "Label fehlt: $locale");
        }
    }
}
