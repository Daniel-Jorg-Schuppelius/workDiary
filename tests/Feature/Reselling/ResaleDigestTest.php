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
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResalePriceEntry, ResaleSubscription};
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
 * Verlängerungen/ohne Rechnung/Katalogpreis geändert; Scheduler-Registrierung.
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
    /**
     * Review 2026-09-11: eine neue Preisliste (Katalogzeile der letzten 7 Tage),
     * deren Einkaufspreis vom Vertragspreis abweicht, zählt je aktivem Abo —
     * gleiche Laufzeit/Intervall, alte Katalogzeilen und gleiche Preise nicht.
     */
    public function test_catalog_price_change_counts_active_subscriptions_whose_contract_price_differs(): void {
        Notification::fake();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        // Laufende Perioden entschieden, nächste erst in 58 Tagen: kein anderer Befund.
        $changed = $this->subscription(['customer_id' => $customer->id, 'purchase_unit_price' => '187.92', 'starts_on' => '2025-11-01']);
        $same = $this->subscription(['customer_id' => $customer->id, 'label' => 'Exchange Online (Plan 1)', 'purchase_unit_price' => '40.00', 'starts_on' => '2025-11-01']);
        $stale = $this->subscription(['customer_id' => $customer->id, 'label' => 'Microsoft 365 Business Basic', 'purchase_unit_price' => '50.00', 'starts_on' => '2025-11-01']);
        $ended = $this->subscription(['customer_id' => $customer->id, 'label' => 'Microsoft 365 Business Premium', 'purchase_unit_price' => '100.00', 'starts_on' => '2025-11-01', 'status' => 'ended', 'ends_on' => '2026-08-31']);
        ResalePeriod::query()->whereIn('subscription_id', [$changed->id, $same->id, $stale->id, $ended->id])->update(['status' => PeriodStatus::Waived->value, 'decided_at' => now()]);
        $entry = static fn(string $product, string $price, int $term = 12, string $interval = 'yearly'): ResalePriceEntry => ResalePriceEntry::create([
            'organization_id' => $customer->organization_id, 'provider' => 'qualityhosting', 'product' => $product, 'term_months' => $term, 'interval' => $interval,
            'valid_from' => '2026-09-01', 'purchase_unit_price' => $price, 'currency' => 'EUR',
        ]);
        $entry('Microsoft 365 Business Premium', '199.00');          // weicht ab, neu → zählt (nur das aktive Abo)
        $entry('Microsoft 365 Business Premium', '20.00', 1, 'monthly'); // anderes Intervall → nicht das Abo
        $entry('Exchange Online (Plan 1)', '40.00');                  // gleicher Preis → nein
        $old = $entry('Microsoft 365 Business Basic', '60.00');       // weicht ab, aber alt → nein
        ResalePriceEntry::query()->whereKey($old->id)->update(['created_at' => now()->subDays(30)]);

        $this->artisan('resale:digest')->assertSuccessful()->expectsOutputToContain('Katalogpreis geändert 1');

        Notification::assertSentTo($this->manager, ResalePeriodsDigestNotification::class, function (ResalePeriodsDigestNotification $n): bool {
            $this->assertSame(1, $n->catalogChanges);
            $this->assertSame(0, $n->dueCount);
            $this->assertSame(1, $n->total(), 'nur der Katalog-Befund');
            $data = $n->toArray($this->manager);
            $this->assertSame(1, $data['catalog_changes']);
            $this->assertSame(1, $data['message_params']['catalog']);

            return true;
        });
        // Rückwärtskompatibel: ohne Parameter zählt der Katalog 0.
        $this->assertSame(0, (new ResalePeriodsDigestNotification(1, '', 0, 0, 0, 0, 30, 60))->catalogChanges);
    }

    /**
     * Review 2026-09-11 (Kleinigkeiten): der Meldungstext wurde zweimal verlängert
     * (:drafts, dann :catalog). Gespeicherte Datenbank-Benachrichtigungen tragen die
     * älteren Schlüssel — sie müssen weiter übersetzt werden statt deutsch zu bleiben.
     */
    public function test_stored_digests_with_older_message_keys_are_still_translated(): void {
        $current = (new ResalePeriodsDigestNotification(1, '10,00 €', 0, 0, 0, 0, 30, 60))->toArray($this->manager)['message_key'];
        $older = [
            ':due fällige Perioden (offen :amount), :proposed unbestätigte Vorschläge, :unassigned Abos ohne Halter, :renewals Verlängerungen oder Enden in :renewal_days Tagen, :stale Abos ohne Rechnung seit über :stale_days Tagen. Bitte die Periodenseite „Abos & Lizenzen" prüfen.',
            ':due fällige Perioden (offen :amount), :proposed unbestätigte Vorschläge, :unassigned Abos ohne Halter, :renewals Verlängerungen oder Enden in :renewal_days Tagen, :stale Abos ohne Rechnung seit über :stale_days Tagen, :drafts Rechnungsentwürfe der letzten :draft_days Tage noch nicht abgeschlossen. Bitte die Periodenseite „Abos & Lizenzen" prüfen.',
        ];
        $params = ['due' => 2, 'amount' => '494,40 €', 'proposed' => 0, 'unassigned' => 0, 'renewals' => 0, 'renewal_days' => 30, 'stale' => 0, 'stale_days' => 60, 'drafts' => 0, 'draft_days' => 7];
        foreach ($older as $key) {
            $this->assertNotSame($current, $key, 'historischer Schlüssel ist nicht der aktuelle');
            foreach (['en', 'fr', 'it', 'es'] as $locale) {
                $this->assertTrue(app('translator')->has($key, $locale, false), "Alias fehlt in $locale");
            }
        }

        $previous = app()->getLocale();
        app()->setLocale('en');
        try {
            $rendered = NotificationText::message(['message_key' => $older[0], 'message_params' => $params, 'message' => 'deutscher Fallback']);
            $this->assertStringContainsString('2 due periods (open 494,40 €)', $rendered);
            $this->assertStringNotContainsString('fällige', $rendered);
            $this->assertStringContainsString('0 invoice drafts from the last 7 days', NotificationText::message(['message_key' => $older[1], 'message_params' => $params]));
            $this->assertStringContainsString('due periods', NotificationText::message(['message_key' => $current, 'message_params' => $params + ['catalog' => 0]]), 'aktueller Schlüssel weiterhin übersetzt');
        } finally {
            app()->setLocale($previous);
        }
    }
}
