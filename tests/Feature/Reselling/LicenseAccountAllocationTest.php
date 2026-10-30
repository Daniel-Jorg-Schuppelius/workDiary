<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseAccountAllocationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{PeriodStatus, SubscriptionKind, SubscriptionProvider, SubscriptionStatus};
use App\Models\Customer\Customer;
use App\Models\Reselling\{ResaleAccountAllocation, ResaleSubscription};
use App\Services\Reselling\Register\{AllocationDeriver, LicenseAccounts, PeriodPlanner};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lizenzkonto und Aufteilung (Feature 185, MVP-1399–1402): Schub und Schlepp
 * — ein Konto, mehrere Exchange-Verträge, Nordufer Tank hält fünf. Die
 * Aufteilung je Konto leitet die Abtretungen je Vertrag ab (größter Vertrag
 * zuerst), der Nachfolger erbt sie, abgelöste Verträge werden nachgeplant.
 */
class LicenseAccountAllocationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $schub;

    private Customer $nordufer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-26');
        $this->schub = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Hafen- und Schleppbetrieb M. Seewind GmbH & CO. KG']);
        $this->nordufer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordufer Tank- und Service GmbH & Co. KG']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function contract(string $externalId, int $quantity, string $startsOn, array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create($attributes + [
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'external_id' => $externalId, 'external_order_id' => $externalId,
            'provider_account' => 'CNL00010', 'company_name' => 'Hafen- und Schleppbetrieb M. Seewind GmbH & Co.KG', 'label' => 'Exchange Online Plan 1',
            'customer_id' => $this->schub->id, 'quantity' => $quantity, 'starts_on' => $startsOn, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'status' => 'active', 'currency' => 'EUR', 'purchase_unit_price' => '34.32', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_allocation_derives_assignments_from_the_largest_contract_first(): void {
        $admin = $this->orgAdmin();
        $nine = $this->contract('CNLCON00111', 9, '2025-10-18');
        $single = $this->contract('CNLCON00110', 1, '2025-10-25');
        $earlier = $this->contract('CNLCON00106', 1, '2025-10-17');

        $accounts = app(LicenseAccounts::class);
        $groups = $accounts->groups($this->organization);
        $this->assertCount(1, $groups, 'ein Konto für drei Verträge');
        $group = array_values($groups)[0];
        $this->assertSame('qualityhosting:CNL00010', $group->key);
        $this->assertSame(['name:exchange online plan 1' => 'Exchange Online Plan 1'], $group->products());

        $this->actingAs($admin)->get(route('finance.resale.accounts.index'))->assertOk()->assertSee('CNL00010')->assertSee('11 ×');
        $this->actingAs($admin)->get(route('finance.resale.accounts.show', $group->routeKey()))->assertOk()->assertSee(__('resale.account.allocation.empty'));
        $this->actingAs($admin)->get(route('finance.resale.accounts.allocations.create', $group->routeKey()))->assertOk()->assertSee(__('resale.account.allocation.add'));

        $this->actingAs($admin)->post(route('finance.resale.accounts.allocations.store', $group->routeKey()), [
            'mode' => 'customer', 'customer_id' => $this->nordufer->sqid, 'product_key' => 'name:exchange online plan 1', 'quantity' => 5, 'valid_from' => '2025-10-18',
        ])->assertRedirect(route('finance.resale.accounts.show', $group->routeKey()))->assertSessionHas('success')->assertSessionMissing('warning');

        $derived = ResaleSubscription::query()->where('parent_id', $nine->id)->firstOrFail();
        $this->assertSame(5, $derived->quantity, 'fünf aus dem Neuner-Vertrag');
        $this->assertSame($this->nordufer->id, $derived->customer_id);
        $this->assertSame('CNLCON00111#1', $derived->external_id);
        $this->assertTrue($derived->isDerivedAssignment());
        $this->assertSame(0, ResaleSubscription::query()->where('parent_id', $single->id)->count(), 'der Einzelvertrag bleibt beim Vertragshalter');
        $this->assertSame([4, 4], $nine->fresh()?->periods()->pluck('quantity')->all(), 'Vertrag plant mit dem Rest');
        $this->assertSame([5, 5], $derived->periods()->pluck('quantity')->all());

        $page = $this->actingAs($admin)->get(route('finance.resale.accounts.show', $group->routeKey()))->assertOk();
        $page->assertSee('Nordufer Tank- und Service GmbH &amp; Co. KG', false)->assertSee(__('resale.account.allocation.derived'))->assertSee(__('resale.account.grid.missing'));

        // Menge ändern: die Abtretung folgt — erst der größte Vertrag, dann nach Beginn (17.10. vor 25.10.); mehr als der Bestand ergibt einen Konflikt-Hinweis.
        $allocation = ResaleAccountAllocation::query()->firstOrFail();
        $this->actingAs($admin)->put(route('finance.resale.accounts.allocations.update', $allocation->sqid), [
            'mode' => 'customer', 'customer_id' => $this->nordufer->sqid, 'product_key' => 'name:exchange online plan 1', 'quantity' => 10, 'valid_from' => '2025-10-18',
        ])->assertRedirect()->assertSessionHas('success')->assertSessionMissing('warning');
        $this->assertSame(9, $derived->fresh()?->quantity, 'der Neuner-Vertrag ganz');
        $this->assertSame(1, ResaleSubscription::query()->where('parent_id', $earlier->id)->count(), 'die zehnte Lizenz aus dem Vertrag vom 17.10.');
        $this->assertSame(0, ResaleSubscription::query()->where('parent_id', $single->id)->count(), 'der Vertrag vom 25.10. bleibt beim Vertragshalter');
        // Der Vertrag vom 17.10. läuft einen Tag vor der Aufteilung: seine erste Periode bleibt (Menge 1), die nächste entfällt.
        $this->assertSame([1], $earlier->fresh()?->periods()->pluck('quantity')->all(), 'ganz abgetreten ab 18.10.: nur die laufende Periode bleibt');
        $this->actingAs($admin)->put(route('finance.resale.accounts.allocations.update', $allocation->sqid), [
            'mode' => 'customer', 'customer_id' => $this->nordufer->sqid, 'product_key' => 'name:exchange online plan 1', 'quantity' => 12, 'valid_from' => '2025-10-18',
        ])->assertRedirect()->assertSessionHas('warning', __('resale.account.allocation.conflict', ['missing' => 1, 'product' => 'Exchange Online Plan 1']));
        $this->assertSame(1, ResaleSubscription::query()->where('parent_id', $single->id)->count(), '12 > 11: auch der dritte Vertrag ganz, eine fehlt');

        // Löschen nimmt die abgeleiteten Abtretungen zurück.
        $this->actingAs($admin)->delete(route('finance.resale.accounts.allocations.destroy', $allocation->sqid))->assertRedirect();
        $this->assertSame(0, ResaleSubscription::query()->whereNotNull('parent_id')->count());
        $this->assertSame([9, 9], $nine->refresh()->periods()->pluck('quantity')->all());
    }

    public function test_overview_lists_at_most_five_products_and_sums_domains(): void {
        // Pilot 2026-10-27: „Eigener Bestand“ bei DomainReselling zeigte 68 Domainzeilen in einer Kontozeile.
        $admin = $this->orgAdmin();
        foreach (range('A', 'G') as $letter) {
            $this->contract('ent-' . $letter, 1, '2026-01-01', ['label' => 'Produkt ' . $letter, 'article_ref' => 'art:' . $letter]);
        }
        foreach (['rad-um-die-welt.de', 'wolkenhub.com', 'e-cld.net'] as $i => $domain) {
            $this->contract('dom-' . $i, 1, '2026-01-01', [
                'kind' => SubscriptionKind::Domain->value, 'provider' => SubscriptionProvider::DomainReselling->value, 'provider_account' => 'holder:own',
                'label' => $domain, 'customer_id' => null, 'is_own_holding' => true, 'company_name' => null,
            ]);
        }

        $page = $this->actingAs($admin)->get(route('finance.resale.accounts.index'))->assertOk();
        foreach (range('A', 'E') as $letter) {
            $page->assertSee('Produkt ' . $letter);
        }
        $page->assertDontSee('Produkt F')->assertDontSee('Produkt G')
            ->assertSee(__('resale.account.products_more', ['count' => 2]))
            ->assertSee('3 ×')->assertSee(trans_choice('resale.account.products_domains', 3))
            ->assertDontSee('rad-um-die-welt.de')->assertDontSee('wolkenhub.com');
    }

    public function test_allocation_respects_manual_assignments_and_other_holders(): void {
        $nine = $this->contract('CNLCON00111', 9, '2025-10-18');
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Dritte GmbH']);
        ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'parent_id' => $nine->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'external_id' => 'CNLCON00111#1', 'label' => 'Exchange Online Plan 1',
            'customer_id' => $other->id, 'quantity' => 3, 'starts_on' => '2025-10-18', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ]);
        ResaleAccountAllocation::query()->create([
            'organization_id' => $this->organization->id, 'provider' => 'qualityhosting', 'provider_account' => 'CNL00010', 'product_key' => 'name:exchange online plan 1', 'product_label' => 'Exchange Online Plan 1',
            'customer_id' => $this->nordufer->id, 'quantity' => 7, 'valid_from' => '2025-10-18',
        ]);
        $deriver = app(AllocationDeriver::class);
        $group = app(LicenseAccounts::class)->group($this->organization, 'qualityhosting:CNL00010');
        $this->assertNotNull($group);
        $result = $deriver->apply($this->organization, $group);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['conflicts'][0]['missing'] ?? null, 'manuelle 3 + abgeleitete 6 = 9, eine fehlt');
        $derived = ResaleSubscription::query()->where('parent_id', $nine->id)->where('customer_id', $this->nordufer->id)->firstOrFail();
        $this->assertSame(6, $derived->quantity, 'manuelle Abtretung zählt gegen die Kapazität');
        $this->assertSame('CNLCON00111#2', $derived->external_id);
    }

    public function test_successor_inherits_the_allocation_and_predecessor_and_successor_each_get_their_share(): void {
        // Telekom-Vertrag mit Aufteilung → Quality-Hosting-Nachfolger: die Aufteilung folgt dem neuen Konto und gilt je Zeitabschnitt.
        $telekom = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-9', 'provider_account' => '4014813',
            'company_name' => 'Hafen- und Schleppbetrieb M. Seewind GmbH & Co.KG', 'label' => 'Exchange Online (Plan 1)', 'customer_id' => $this->schub->id, 'quantity' => 9,
            'starts_on' => '2022-10-13', 'ends_on' => '2025-10-18', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'superseded', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($telekom);
        $successor = $this->contract('CNLCON00111', 9, '2025-10-18');
        $telekom->forceFill(['successor_id' => $successor->id])->save();
        ResaleAccountAllocation::query()->create([
            'organization_id' => $this->organization->id, 'provider' => 'telekom_marketplace', 'provider_account' => '4014813', 'product_key' => 'name:exchange online plan 1', 'product_label' => 'Exchange Online (Plan 1)',
            'customer_id' => $this->nordufer->id, 'quantity' => 5, 'valid_from' => '2022-10-13',
        ]);

        $accounts = app(LicenseAccounts::class);
        $groups = $accounts->groups($this->organization);
        $this->assertCount(1, $groups, 'Vorgänger läuft unter dem Konto des Nachfolgers');
        $group = array_values($groups)[0];
        $this->assertSame('qualityhosting:CNL00010', $group->key);
        $this->assertCount(2, $group->contracts());

        $deriver = app(AllocationDeriver::class);
        $deriver->carry($telekom, $successor);
        $this->assertSame(1, ResaleAccountAllocation::query()->forAccount(\App\Enums\Reselling\SubscriptionProvider::QualityHosting, 'CNL00010')->count(), 'Aufteilung am Nachfolgerkonto');

        $regrouped = $accounts->group($this->organization, $group->key);
        $this->assertNotNull($regrouped);
        $result = $deriver->apply($this->organization, $regrouped);
        $this->assertSame([], $result['conflicts']);
        $this->assertSame(5, ResaleSubscription::query()->where('parent_id', $successor->id)->value('quantity'), 'Nachfolger: 5 ab 18.10.2025');
        $this->assertSame(5, ResaleSubscription::query()->where('parent_id', $telekom->id)->value('quantity'), 'Vorgänger: 5 für seine Laufzeit');
        $this->assertSame('2025-10-18', ResaleSubscription::query()->where('parent_id', $telekom->id)->first()?->ends_on?->toDateString(), 'endet mit dem Vorgänger');
        $this->assertSame([4, 4, 4], $telekom->fresh()?->periods()->pluck('quantity')->all(), 'abgelöster Vertrag plant mit dem Rest');
        $this->assertSame([4, 4], $successor->fresh()?->periods()->pluck('quantity')->all());
    }

    public function test_schedule_replans_superseded_contracts_after_a_later_assignment(): void {
        $telekom = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-9', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $this->schub->id, 'quantity' => 9, 'starts_on' => '2022-10-13', 'ends_on' => '2025-10-18', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel',
            'status' => 'superseded', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($telekom);
        $first = $telekom->periods()->firstOrFail();
        $first->forceFill(['status' => PeriodStatus::Partial, 'decided_at' => now(), 'decided_by_user_id' => $this->orgAdmin()->id])->save();
        // Abtretung nachträglich von Hand, ohne Planerlauf — so stand #43 am 08.09. in der Produktion.
        ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'parent_id' => $telekom->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-9#1', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $this->nordufer->id, 'quantity' => 5, 'starts_on' => '2022-10-13', 'ends_on' => '2025-10-18', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel', 'status' => 'superseded', 'currency' => 'EUR',
        ]);
        $this->assertSame(9, $first->fresh()?->quantity, 'vor dem Lauf unverändert');

        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('resale:sync-periods'));

        $first->refresh();
        $this->assertSame(4, $first->quantity, 'abgelöster Vertrag nachgeplant: 4 statt 9');
        $this->assertSame(PeriodStatus::Open, $first->status, 'ohne Deckung offen statt teilweise');
        $this->assertNotNull($first->decided_at, 'Entscheidung bleibt');
        $this->assertSame(SubscriptionStatus::Superseded, $telekom->fresh()?->status);
    }

    public function test_replannable_scope_skips_contracts_ended_long_ago(): void {
        $old = $this->contract('CNLCON00001', 1, '2019-01-01', ['status' => 'ended', 'ends_on' => '2021-01-01']);
        $recent = $this->contract('CNLCON00002', 1, '2024-01-01', ['status' => 'ended', 'ends_on' => '2025-01-01']);
        $ids = ResaleSubscription::query()->replannable(CarbonImmutable::parse('2026-10-26'))->pluck('id')->all();
        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }
}
