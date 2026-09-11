<?php
/*
 * Created on   : Tue Sep 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenceTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{PeriodStatus, SubscriptionStatus};
use App\Models\{Customer, ExternalReference, LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Models\Reselling\ResaleSubscription;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lizenzabtretung (Feature 152): zwei Firmen im selben Haus, ein Vertrag
 * beim Anbieter — Schub- und Schleppreederei tritt Lizenzen an Märkische
 * Bunker ab. Der Vertrag plant mit dem Rest, die Abtretung hat eigene
 * Perioden und eigene Rechnungen; beim Nachfolger läuft sie weiter.
 */
class LicenceTransferTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-08');
        $this->exchange = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'ART-EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
    }

    private function customerWithContact(string $name, string $contactId): Customer {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => $contactId, 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);

        return $customer;
    }

    private function invoice(string $contactId, string $number, string $date, int $lines): void {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => $contactId, 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        for ($i = 1; $i <= $lines; $i++) {
            LexofficeVoucherLine::create([
                'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => $i, 'type' => 'service',
                'external_article_id' => 'art-exo', 'lexoffice_article_id' => $this->exchange->id, 'name' => 'Exchange Online (Plan 1)',
                'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
            ]);
        }
    }

    public function test_transfer_splits_periods_by_holder_and_links_each_holders_invoices(): void {
        $admin = $this->orgAdmin();
        $schub = $this->customerWithContact('Schub- und Schleppreederei U. Golka GmbH & CO. KG', 'c-schub');
        $maerkische = $this->customerWithContact('Märkische Bunker- und Service GmbH & Co. KG', 'c-mb');
        $contract = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-9', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $schub->id, 'lexoffice_article_id' => $this->exchange->id, 'quantity' => 9, 'starts_on' => '2024-10-18', 'ends_on' => '2026-10-18',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'cancelled', 'currency' => 'EUR', 'purchase_unit_price' => '43.98', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($contract);
        $this->assertSame([9, 9], $contract->periods()->pluck('quantity')->all());

        $this->actingAs($admin)->get(route('finance.resale.transfer.create', $contract->sqid))->assertOk()->assertSee(__('resale.transfer.title', ['subscription' => $contract->label]));
        // Zu viel geht nicht.
        $this->actingAs($admin)->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $maerkische->sqid, 'quantity' => 10, 'starts_on' => '2024-10-18',
        ])->assertSessionHasErrors('quantity');
        $this->actingAs($admin)->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $maerkische->sqid, 'quantity' => 5, 'starts_on' => '2024-10-18', 'sale_unit_price' => '47.40', 'note' => 'gleiches Haus',
        ])->assertRedirect(route('finance.resale.show', $contract->sqid))->assertSessionHas('success');

        $assignment = ResaleSubscription::query()->where('parent_id', $contract->id)->firstOrFail();
        $this->assertSame('ent-9#1', $assignment->external_id);
        $this->assertSame($maerkische->id, $assignment->customer_id);
        $this->assertSame(5, $assignment->quantity);
        $this->assertSame([5, 5], $assignment->periods()->pluck('quantity')->all(), 'eigene Perioden der Abtretung');
        $this->assertSame([4, 4], $contract->periods()->pluck('quantity')->all(), 'Vertrag plant mit dem Rest');
        $this->assertSame(4, $contract->fresh()?->billableQuantityOn(\Carbon\CarbonImmutable::parse('2025-01-01')));

        // Jeder Halter bekommt seine eigenen Rechnungen zugeordnet.
        $this->invoice('c-schub', 'RE/2024/0719', '2024-10-23', 4);
        $this->invoice('c-mb', 'RE/2024/0720', '2024-10-23', 5);
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PeriodStatus::Billed, $contract->periods()->first()?->status);
        $this->assertSame(PeriodStatus::Billed, $assignment->periods()->first()?->status);
        $this->assertSame(4, $contract->periods()->first()?->links()->count());
        $this->assertSame(5, $assignment->periods()->first()?->links()->count());

        $page = $this->actingAs($admin)->get(route('finance.resale.show', $contract->sqid))->assertOk();
        $page->assertSee('Märkische Bunker- und Service GmbH & Co. KG')->assertSee('×5');
        $this->actingAs($admin)->get(route('finance.resale.show', $assignment->sqid))->assertOk()->assertSee(__('resale.transfer.from'));

        // Vertrag mit Abtretung lässt sich nicht löschen; die Abtretung schon — offene Perioden des
        // Vertrags bekommen die Lizenzen zurück, entschiedene (berechnete) behalten ihre Menge.
        $this->actingAs($admin)->delete(route('finance.resale.destroy', $contract->sqid))->assertRedirect(route('finance.resale.show', $contract->sqid))->assertSessionHas('error');
        \App\Models\Reselling\ResalePeriodLink::query()->delete();
        $assignment->periods()->update(['status' => PeriodStatus::Open->value]);
        $contract->periods()->update(['status' => PeriodStatus::Open->value]);
        $this->actingAs($admin)->delete(route('finance.resale.destroy', $assignment->sqid))->assertRedirect(route('finance.resale.index'));
        $this->assertSame([9, 9], $contract->fresh()?->periods()->pluck('quantity')->all());
    }

    public function test_assignment_is_carried_over_to_the_successor_contract(): void {
        $schub = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schub- und Schleppreederei']);
        $maerkische = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Märkische Bunker']);
        $contract = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-9', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $schub->id, 'quantity' => 9, 'starts_on' => '2024-10-18', 'ends_on' => '2025-10-18', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'cancelled', 'currency' => 'EUR',
        ]);
        $assignment = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'parent_id' => $contract->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-9#1', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $maerkische->id, 'quantity' => 5, 'starts_on' => '2024-10-18', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ]);
        $successor = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'external_id' => 'CNLCON1', 'label' => 'Exchange Online Plan 1',
            'customer_id' => $schub->id, 'quantity' => 9, 'starts_on' => '2025-10-18', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ]);
        $importer = app(\App\Services\Reselling\Register\MarketplaceImporter::class);
        $method = new \ReflectionMethod($importer, 'carryAssignments');
        $method->invoke($importer, $contract, $successor, \Carbon\CarbonImmutable::parse('2026-09-08'));

        $carried = ResaleSubscription::query()->where('parent_id', $successor->id)->firstOrFail();
        $this->assertSame($maerkische->id, $carried->customer_id);
        $this->assertSame(5, $carried->quantity);
        $this->assertSame('2025-10-18', $carried->starts_on->toDateString());
        $this->assertSame('CNLCON1#1', $carried->external_id);
        $this->assertSame('2025-10-17', $assignment->fresh()?->ends_on?->toDateString(), 'alte Abtretung endet mit dem Vorgänger');
        $this->assertSame(SubscriptionStatus::Superseded, $assignment->fresh()?->status);
        $this->assertSame([4, 4], $successor->periods()->pluck('quantity')->all(), 'Nachfolger plant mit dem Rest');
        $this->assertSame([5, 5], $carried->periods()->pluck('quantity')->all());
        // Zweiter Durchlauf legt nichts doppelt an.
        $method->invoke($importer, $contract, $successor, \Carbon\CarbonImmutable::parse('2026-09-08'));
        $this->assertSame(1, ResaleSubscription::query()->where('parent_id', $successor->id)->count());
    }

    public function test_holder_change_over_time_is_a_full_transfer_for_the_old_period(): void {
        // EcoTec: Firma aufgespalten, HLSK insolvent, der Vertrag lief bei Service weiter.
        // 2020 an HLSK berechnet, danach gehört der Vertrag Service.
        $admin = $this->orgAdmin();
        $service = $this->customerWithContact('EcoTec Service GmbH', 'c-eco');
        $hlsk = $this->customerWithContact('EcoTec - HLSK GmbH', 'c-hlsk');
        $contract = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-5', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $service->id, 'lexoffice_article_id' => $this->exchange->id, 'quantity' => 5, 'starts_on' => '2024-04-24', 'ends_on' => '2026-04-24',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'ended', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($contract);
        [$p2024, $p2025] = $contract->periods()->get()->all();
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-171', 'contact_external_id' => 'c-hlsk', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => 'RE/2024/0171', 'voucher_date' => '2024-05-11', 'total_amount' => 282.03, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service', 'external_article_id' => 'art-exo',
            'lexoffice_article_id' => $this->exchange->id, 'name' => 'Exchange Online (Plan 1)', 'quantity' => 5, 'unit_name' => 'Jahr', 'unit_net' => '47.40', 'total_net' => '237.00', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);

        // Der Abgleich bietet die HLSK-Rechnung mit „Periode an … abtreten" an — vorbelegt mit Kunde, Menge und Zeitraum.
        $page = $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $service))->assertOk();
        $page->assertSee(__('resale.transfer.action_period', ['customer' => 'EcoTec - HLSK GmbH']));
        $dialog = $this->actingAs($admin)->get(route('finance.resale.transfer.create', ['subscription' => $contract->sqid, 'customer' => $hlsk->sqid, 'quantity' => 5, 'starts_on' => '2024-04-24', 'ends_on' => '2025-04-23']))->assertOk();
        $dialog->assertSee('value="5"', false)->assertSee('2024-04-24')->assertSee('2025-04-23');

        $this->actingAs($admin)->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $hlsk->sqid, 'quantity' => 5, 'starts_on' => '2024-04-24', 'ends_on' => '2025-04-23',
        ])->assertRedirect(route('finance.resale.show', $contract->sqid));

        $assignment = ResaleSubscription::query()->where('parent_id', $contract->id)->firstOrFail();
        $this->assertSame(['2024-04-24'], $assignment->periods()->pluck('starts_on')->map(static fn($d) => $d->toDateString())->all(), 'HLSK hat nur 2024');
        $this->assertNull($p2024->fresh(), 'ganz abgetreten: keine 2024er-Periode mehr beim Vertrag');
        $this->assertSame(5, $p2025->fresh()?->quantity, '2025 gehört Service');

        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PeriodStatus::Billed, $assignment->periods()->first()?->status, 'RE/2024/0171 deckt HLSKs Periode');
        $this->assertSame(PeriodStatus::Open, $p2025->fresh()?->status, 'Service 2025 nie berechnet — verzichten oder nachberechnen');
    }

    public function test_transfer_after_confirmed_periods_reduces_their_quantity_and_settles_them(): void {
        // Prod-Fall Schub & Schlepp: Perioden waren schon mit 9 Lizenzen bestätigt (4 Schub-Positionen = teilweise),
        // dann kam die Abtretung von 5 an Märkische — die bestätigten Perioden müssen auf 4 fallen und damit voll sein.
        $admin = $this->orgAdmin();
        $schub = $this->customerWithContact('Schub- und Schleppreederei U. Golka GmbH & CO. KG', 'c-schub');
        $maerkische = $this->customerWithContact('Märkische Bunker- und Service GmbH & Co. KG', 'c-mb');
        $contract = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-9', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $schub->id, 'lexoffice_article_id' => $this->exchange->id, 'quantity' => 9, 'starts_on' => '2024-10-13', 'ends_on' => '2026-10-18',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'cancelled', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($contract);
        $this->invoice('c-schub', 'RE/2024/0719', '2024-10-23', 4);
        $this->invoice('c-mb', 'RE/2024/0718', '2024-10-23', 5);
        (new LinkProposer)->propose($this->organization);
        $first = $contract->periods()->firstOrFail();
        $this->assertSame(PeriodStatus::Partial, $first->status, '48 von 108 — Schub allein');
        $this->actingAs($admin)->post(route('finance.resale.periods.confirm', $first->sqid))->assertRedirect();
        $this->assertNotNull($first->fresh()?->decided_at);

        $this->actingAs($admin)->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $maerkische->sqid, 'quantity' => 5, 'starts_on' => '2024-10-13',
        ])->assertRedirect(route('finance.resale.show', $contract->sqid));

        $first->refresh();
        $this->assertSame(4, $first->quantity, 'auch die bestätigte Periode folgt der Abtretung');
        $this->assertSame(PeriodStatus::Billed, $first->status, '48 von 48');
        $this->assertNotNull($first->decided_at, 'Entscheidung bleibt');
        $this->assertSame('189.60', $first->expected_sale?->getAmount(), '4 × 47,40');
        $assignment = ResaleSubscription::query()->where('parent_id', $contract->id)->firstOrFail();
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PeriodStatus::Billed, $assignment->periods()->first()?->status, 'Märkisches fünf Positionen decken die Abtretung');
    }

    public function test_transfer_inside_a_running_period_is_co_termed_with_the_contract(): void {
        // Abtretung ab 01.04.2025 in den laufenden Vertragsjahreslauf 18.10.2024–17.10.2025: die Erstperiode der
        // Abtretung endet mit der Vertragsperiode (7 Monate, Soll anteilig), danach läuft sie an den Vertragsgrenzen;
        // der Vertrag behält in der laufenden Periode alle 9 (bezahlt), ab 18.10.2025 den Rest. Dialog und Controller
        // sind unverändert — die Co-Term-Regel sitzt im PeriodPlanner.
        $admin = $this->orgAdmin();
        $schub = $this->customerWithContact('Schub- und Schleppreederei U. Golka GmbH & CO. KG', 'c-schub');
        $maerkische = $this->customerWithContact('Märkische Bunker- und Service GmbH & Co. KG', 'c-mb');
        $contract = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-9', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $schub->id, 'lexoffice_article_id' => $this->exchange->id, 'quantity' => 9, 'starts_on' => '2024-10-18', 'ends_on' => '2026-10-18',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'cancelled', 'currency' => 'EUR', 'purchase_unit_price' => '43.98', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($contract);

        $this->actingAs($admin)->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $maerkische->sqid, 'quantity' => 5, 'starts_on' => '2025-04-01', 'sale_unit_price' => '47.40',
        ])->assertRedirect(route('finance.resale.show', $contract->sqid))->assertSessionHas('success');

        $assignment = ResaleSubscription::query()->where('parent_id', $contract->id)->firstOrFail();
        $periods = $assignment->periods()->get();
        $this->assertSame([['2025-04-01', '2025-10-17'], ['2025-10-18', '2026-10-17']], $periods->map(static fn($p): array => [$p->starts_on->toDateString(), $p->ends_on->toDateString()])->all(), 'Erstperiode endet mit der Vertragsperiode, danach Vertragsgrenzen; der 18.10.2026 ist Stummel');
        $this->assertSame('138.25', $periods[0]->expected_sale?->getAmount(), '5 × 47,40 × 7/12');
        $this->assertSame(35.0, $periods[0]->requiredMonths(), '5 Lizenzen × 7 Monate');
        $this->assertSame('237.00', $periods[1]->expected_sale?->getAmount(), 'volle Vertragsperiode');
        $this->assertSame([9, 4], $contract->fresh()?->periods()->pluck('quantity')->all(), 'laufende Periode bleibt bei 9, ab der nächsten der Rest');
    }

    public function test_assignment_helpers_compute_peak_quantity_and_next_suffix(): void {
        // Review 2026-09-10 (B8/B10): Verfügbarkeit über die ganze Laufzeit statt nur am Starttag;
        // Suffix aus dem höchsten vorhandenen `#n`, nicht aus der Anzahl.
        $schub = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schub- und Schleppreederei']);
        $maerkische = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Märkische Bunker']);
        $contract = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-9', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $schub->id, 'quantity' => 9, 'starts_on' => '2024-10-18', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ]);
        $assignment = static fn(string $externalId, int $quantity, string $from, ?string $to) => ResaleSubscription::query()->create([
            'organization_id' => $contract->organization_id, 'parent_id' => $contract->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => $externalId, 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $maerkische->id, 'quantity' => $quantity, 'starts_on' => $from, 'ends_on' => $to, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ]);
        $day = static fn(string $d): \Carbon\CarbonImmutable => \Carbon\CarbonImmutable::parse($d);

        $this->assertSame(0, $contract->assignedQuantityBetween($day('2024-10-18'), null), 'ohne Abtretungen nichts');
        $this->assertSame(1, $contract->nextAssignmentSuffix());

        // Zwei Abtretungen: 5 vom 01.01.–30.06.2025, 2 vom 01.07.2025 offen; dazwischen keine Überlappung.
        $assignment('ent-9#1', 5, '2025-01-01', '2025-06-30');
        $assignment('ent-9#3', 2, '2025-07-01', null);
        $contract->unsetRelation('assignments');
        $this->assertSame(5, $contract->assignedQuantityBetween($day('2024-10-18'), null), 'Spitze über die gesamte Laufzeit');
        $this->assertSame(5, $contract->assignedQuantityBetween($day('2025-03-01'), $day('2025-03-31')), 'mitten in der ersten Abtretung');
        $this->assertSame(2, $contract->assignedQuantityBetween($day('2025-07-01'), null), 'nach dem Wechsel nur die offene');
        $this->assertSame(0, $contract->assignedQuantityBetween($day('2024-10-18'), $day('2024-12-31')), 'vor der ersten Abtretung');
        $this->assertSame(5, $contract->assignedQuantityBetween($day('2024-12-01'), $day('2025-01-01')), 'Beginn am letzten Tag des Zeitraums zählt');
        $this->assertSame(5, $contract->assignedQuantityBetween($day('2025-06-30'), $day('2025-07-01')), 'Wechseltag: 5 endet, 2 beginnt — kein Doppelzählen (Spitze 5, nicht 7)');
        $this->assertSame(4, $contract->nextAssignmentSuffix(), 'höchster Suffix + 1, nicht Anzahl + 1 (#2 wäre nach dem Löschen frei gewesen, #3 existiert)');

        // Überlappende Abtretungen addieren sich.
        $assignment('ent-9#4', 3, '2025-05-01', '2025-08-31');
        $contract->unsetRelation('assignments');
        $this->assertSame(8, $contract->assignedQuantityBetween($day('2025-01-01'), null), '5 + 3 im Mai/Juni');
        $this->assertSame(5, $contract->assignedQuantityBetween($day('2025-08-01'), null), '2 + 3 im August');
        $this->assertSame(5, $contract->nextAssignmentSuffix());

        // Ohne Suffix-Muster: Anzahl + 1.
        $assignment('manual-x', 1, '2025-09-01', null);
        $contract->unsetRelation('assignments');
        $this->assertSame(5, $contract->nextAssignmentSuffix(), 'höchster Suffix (#4) + 1');
        $plain = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'manual', 'label' => 'Ohne Muster', 'customer_id' => $schub->id, 'quantity' => 3, 'starts_on' => '2025-01-01', 'currency' => 'EUR', 'status' => 'active',
        ]);
        ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'parent_id' => $plain->id, 'kind' => 'license', 'provider' => 'manual', 'external_id' => 'frei', 'label' => 'Ohne Muster', 'customer_id' => $maerkische->id, 'quantity' => 1, 'starts_on' => '2025-01-01', 'currency' => 'EUR', 'status' => 'active',
        ]);
        $this->assertSame(2, $plain->nextAssignmentSuffix(), 'kein #n: Anzahl + 1');

        $this->assertFalse($contract->isImported());
        $this->assertTrue($contract->replicate()->forceFill(['import_id' => 1])->isImported());
        $this->assertFalse($contract->isDomain());
        $this->assertTrue($contract->replicate()->forceFill(['provider' => \App\Enums\Reselling\SubscriptionProvider::DomainReselling])->isDomain());
    }
}
