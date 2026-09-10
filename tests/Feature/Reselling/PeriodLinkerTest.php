<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodLinkerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\{Customer, LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Services\Reselling\Register\{PeriodLinker, PeriodPlanner};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Manuelle Bezüge (Feature 152, MVP-761, Review 2026-09-10 G): Überbuchung,
 * Ersatz statt Verdopplung, Verbrauch je Position mit Ausnahme, und
 * `settle()` auf verzichteten/strittigen Perioden.
 */
class PeriodLinkerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $premium;

    private Customer $customer;

    private ResaleSubscription $subscription;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
        $this->premium = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'article_number' => 'ART-BP',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        $this->subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $this->customer->id, 'lexoffice_article_id' => $this->premium->id, 'quantity' => 1, 'starts_on' => '2024-08-05',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => 'active',
        ]);
        (new PeriodPlanner)->sync($this->subscription);
        $this->assertSame(3, $this->subscription->periods()->count(), '2024, 2025, 2026');
    }

    /** Rechnung mit einer Lizenzposition „:quantity Monat" à 20,60 €, Beleg geladen. */
    private function line(string $number, string $date, float $quantity = 12): LexofficeVoucherLine {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => 'c-kl', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        $line = LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => 'art-bp', 'lexoffice_article_id' => $this->premium->id, 'name' => 'Microsoft 365 Business Premium',
            'quantity' => $quantity, 'unit_name' => 'Monat', 'unit_net' => '20.60', 'total_net' => (string) round(20.60 * $quantity, 2), 'tax_rate' => 19, 'currency' => 'EUR',
        ]);

        return $line->load('voucher');
    }

    /** @return array{0: ResalePeriod, 1: ResalePeriod, 2: ResalePeriod} */
    private function periods(): array {
        /** @var array{0: ResalePeriod, 1: ResalePeriod, 2: ResalePeriod} */
        return $this->subscription->periods()->get()->all();
    }

    public function test_attach_never_overbooks_a_line_and_replaces_instead_of_doubling(): void {
        $linker = new PeriodLinker;
        $userId = $this->orgAdmin()->id;
        $line = $this->line('RE/2025/0100', '2025-08-06');
        [$p2024, $p2025] = $this->periods();
        $this->assertSame(12.0, $linker->freeMonths($line), '12 Monat = 12 Lizenzmonate frei');

        // 8 der 12 Monate an 2024: teilweise gedeckt, entschieden, Betrag 8 × 20,60.
        $first = $linker->attach($p2024, $line, 8.0, 'Teilbetrag', $userId);
        $this->assertSame(LinkOrigin::Manual, $first->origin);
        $this->assertSame('8.00', $first->months);
        $this->assertSame('0.667', $first->quantity, '8/12 einer Jahresperiode');
        $this->assertSame('164.80', $first->amount?->getAmount());
        $this->assertSame('Teilbetrag', $first->note);
        $this->assertSame($userId, $first->created_by_user_id);
        $p2024->refresh();
        $this->assertSame(PeriodStatus::Partial, $p2024->status);
        $this->assertNotNull($p2024->decided_at, 'manueller Bezug ist eine Entscheidung');
        $this->assertSame(4.0, $linker->freeMonths($line));
        $this->assertSame(12.0, $linker->freeMonths($line, $p2024), 'Bezüge an DIESER Periode zählen nicht — ein erneuter Bezug ersetzt sie');

        // Mehr als frei ist, geht nicht: 8 an 2025 überbucht (nur 4 frei).
        try {
            $linker->attach($p2025, $line, 8.0, null, $userId);
            $this->fail('Überbuchung muss abgewiesen werden');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame((string) __('resale.link.error.exceeds', ['amount' => __('resale.link.months_only', ['months' => '4'])]), $e->getMessage());
        }
        $this->assertSame(1, ResalePeriodLink::query()->count(), 'abgewiesener Bezug hinterlässt nichts');
        $this->assertSame(PeriodStatus::Open, $p2025->fresh()?->status);

        // Die restlichen 4 an 2025.
        $linker->attach($p2025, $line, 4.0, null, $userId);
        $this->assertSame(0.0, $linker->freeMonths($line));
        $this->assertSame(2, ResalePeriodLink::query()->count());

        // Zweiter Bezug derselben Position an 2024 (jetzt 6 statt 8): aktualisiert den vorhandenen Bezug statt zu verdoppeln.
        $again = $linker->attach($p2024, $line, 6.0, null, $userId);
        $this->assertSame($first->id, $again->id, 'updateOrCreate über (Periode, Position)');
        $this->assertSame('6.00', $again->months);
        $this->assertSame('123.60', $again->amount?->getAmount(), '6 × 20,60');
        $this->assertSame(2, ResalePeriodLink::query()->count());
        $this->assertSame(2.0, $linker->freeMonths($line), '12 − 6 − 4');

        // Volle Deckung macht die Periode „berechnet".
        $linker->attach($p2024, $line, 8.0, null, $userId);
        $extra = $this->line('RE/2025/0101', '2025-08-07', 4);
        $linker->attach($p2024, $extra, 4.0, null, $userId);
        $this->assertSame(PeriodStatus::Billed, $p2024->fresh()?->status, '8 + 4 = 12 benötigte Monate');
        $this->assertSame(0.0, $linker->freeMonths($line));
        $this->assertSame(0.0, $linker->freeMonths($extra));
    }

    public function test_consumed_months_reports_per_line_targets_and_honours_the_except_period(): void {
        $linker = new PeriodLinker;
        $line = $this->line('RE/2025/0100', '2025-08-06');
        $other = $this->line('RE/2025/0101', '2025-08-07');
        [$p2024, $p2025] = $this->periods();
        $linker->attach($p2024, $line, 6.0, null, null);
        $linker->attach($p2025, $line, 4.0, null, null);

        $this->assertSame([], $linker->consumedMonths([]), 'ohne Positionen keine Abfrage');
        $this->assertSame([], $linker->consumedMonths([$other->id]), 'unverbrauchte Position kommt nicht vor');

        $consumed = $linker->consumedMonths([$line->id, $other->id]);
        $this->assertSame([$line->id], array_keys($consumed));
        $this->assertSame(10.0, $consumed[$line->id]['months']);
        $this->assertCount(2, $consumed[$line->id]['periods']);
        // Ziel = „Halter · ×Menge · Anbieter ab Datum · Zeitraum" — der Halter, weil Partner mehrere Endkunden haben.
        $this->assertSame('Klimpel Bäder GmbH · ' . $this->subscription->identityLabel() . ' · 05.08.2024 – 04.08.2025', $consumed[$line->id]['periods'][0]);
        $this->assertStringContainsString('05.08.2025 – 04.08.2026', $consumed[$line->id]['periods'][1]);
        $this->assertSame($consumed[$line->id]['periods'][0], PeriodLinker::targetLabel(ResalePeriodLink::query()->where('period_id', $p2024->id)->firstOrFail()));

        // except: die Bezüge an dieser Periode zählen nicht (ein erneuter Bezug ersetzt sie).
        $without = $linker->consumedMonths([$line->id], $p2024);
        $this->assertSame(4.0, $without[$line->id]['months']);
        $this->assertCount(1, $without[$line->id]['periods']);
        $this->assertStringContainsString('05.08.2025', $without[$line->id]['periods'][0]);
        $this->assertSame(8.0, $linker->freeMonths($line, $p2024));
        $this->assertSame(2.0, $linker->freeMonths($line));

        // Vorschläge zählen ebenso als Verbrauch wie manuelle Bezüge.
        ResalePeriodLink::query()->where('period_id', $p2025->id)->update(['origin' => LinkOrigin::Proposed->value, 'confirmed_at' => null]);
        $this->assertSame(10.0, $linker->consumedMonths([$line->id])[$line->id]['months']);
    }

    public function test_settle_keeps_waived_and_disputed_periods_untouched(): void {
        // Verzicht und Einspruch sind Nutzerentscheidungen (`ResalePeriod::isLocked()`): ein Bezug, der an einer
        // solchen Periode gelöst oder ergänzt wird, darf die Entscheidung nicht still in „offen"/„berechnet" kippen.
        $linker = new PeriodLinker;
        $userId = $this->orgAdmin()->id;
        $line = $this->line('RE/2025/0100', '2025-08-06');
        [$p2024, $p2025, $p2026] = $this->periods();
        $decidedAt = CarbonImmutable::parse('2026-09-01 10:00:00');

        // Verzichtet — mit einem liegengebliebenen Vorschlag, wie ihn ein Lauf vor dem Verzicht hinterlässt.
        ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $p2024->id, 'subscription_id' => $this->subscription->id,
            'linkable_type' => $line->getMorphClass(), 'linkable_id' => $line->id, 'voucher_number' => 'RE/2025/0100', 'voucher_date' => '2025-08-06',
            'quantity' => 1, 'months' => 12, 'amount' => '247.20', 'currency' => 'EUR', 'origin' => LinkOrigin::Proposed,
        ]);
        $p2024->forceFill(['status' => PeriodStatus::Waived, 'waived_reason' => 'Kulanz', 'decided_by_user_id' => $userId, 'decided_at' => $decidedAt, 'note' => 'telefonisch'])->save();
        $linker->settle($p2024, null, null, false);
        $p2024->refresh();
        $this->assertSame(PeriodStatus::Waived, $p2024->status, 'Verzicht bleibt trotz voller Deckung durch den Vorschlag');
        $this->assertSame('Kulanz', $p2024->waived_reason);
        $this->assertSame('telefonisch', $p2024->note);
        $this->assertNotNull($p2024->decided_at, 'die Entscheidung bleibt');

        // Auch als „entschieden" aufgerufen (Bestätigen/Verknüpfen): der Verzicht steht.
        $linker->settle($p2024, $userId, 'Nachtrag', true);
        $p2024->refresh();
        $this->assertSame(PeriodStatus::Waived, $p2024->status);
        $this->assertSame('Kulanz', $p2024->waived_reason);

        // Strittig ohne jeden Bezug: settle(decided=false) macht daraus kein „offen".
        $p2025->forceFill(['status' => PeriodStatus::Disputed, 'waived_reason' => 'Kunde bestreitet', 'decided_by_user_id' => $userId, 'decided_at' => $decidedAt])->save();
        $linker->settle($p2025, null, null, false);
        $p2025->refresh();
        $this->assertSame(PeriodStatus::Disputed, $p2025->status);
        $this->assertSame('Kunde bestreitet', $p2025->waived_reason);
        $this->assertNotNull($p2025->decided_at);

        // Gegenprobe: eine offene Periode folgt der Deckung wie gehabt.
        $linker->settle($p2026, null, null, false);
        $this->assertSame(PeriodStatus::Open, $p2026->fresh()?->status);
        $this->assertNull($p2026->fresh()?->decided_at);
    }
}
