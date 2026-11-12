<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureAssetApplicabilityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Asset\AssetBlockReason;
use App\Models\Article\Product;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureRun;
use App\Models\ServiceTicket\SlaContract;
use App\Services\Asset\AssetBlockService;
use App\Services\Procedure\ProcedureApplicabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Procedure\Concerns\BuildsProcedureRuns;
use Tests\TestCase;

/** Zuordnung nach Kunde, Asset-Kategorie, Produkt und SLA; Start am Asset (MVP-1571). */
class ProcedureAssetApplicabilityTest extends TestCase {
    use BuildsProcedureRuns;
    use RefreshDatabase;

    public function test_resolver_matches_customer_category_product_and_sla_of_the_order(): void {
        $lead = $this->lead();
        [$asset, $customer, $product, $sla] = $this->routerAsset($lead);
        $resolver = app(ProcedureApplicabilityResolver::class);

        $byCategory = $this->publishedTemplate($lead, [['code' => 'a']], ['applicability' => ['asset_categories' => ['router', 'firewall']]]);
        $byProduct = $this->publishedTemplate($lead, [['code' => 'a']], ['applicability' => ['product_ids' => [$product->id]]]);
        $bySla = $this->publishedTemplate($lead, [['code' => 'a']], ['applicability' => ['sla_contract_ids' => [(string) $sla->id]]]);
        $byCustomer = $this->publishedTemplate($lead, [['code' => 'a']], ['applicability' => ['customer_ids' => [$customer->id]]]);
        $otherCategory = $this->publishedTemplate($lead, [['code' => 'a']], ['applicability' => ['asset_categories' => ['drucker']]]);
        $otherCustomer = $this->publishedTemplate($lead, [['code' => 'a']], ['applicability' => ['asset_categories' => ['router'], 'customer_ids' => [$customer->id + 1000]]]);
        $universal = $this->publishedTemplate($lead, [['code' => 'a']]);

        $withAsset = DiaryEntry::factory()->create(['organization_id' => $lead->organization_id, 'user_id' => $lead->id, 'customer_id' => $customer->id, 'asset_id' => $asset->id]);
        $this->assertEqualsCanonicalizing(
            [$byCategory->id, $byProduct->id, $bySla->id, $byCustomer->id, $universal->id],
            $resolver->suggestFor($withAsset)->pluck('id')->all(),
        );

        $withoutAsset = DiaryEntry::factory()->create(['organization_id' => $lead->organization_id, 'user_id' => $lead->id, 'customer_id' => $customer->id]);
        $this->assertEqualsCanonicalizing([$byCustomer->id, $universal->id], $resolver->suggestFor($withoutAsset)->pluck('id')->all());

        // An der Asset-Akte nur, was ein Asset-Kriterium nennt.
        $this->assertEqualsCanonicalizing([$byCategory->id, $byProduct->id, $bySla->id], $resolver->suggestForAsset($asset)->pluck('id')->all());
        $this->assertFalse($resolver->templateAppliesAt($otherCategory, $resolver->contextFor($asset), null, true));
        $this->assertFalse($resolver->templateAppliesAt($otherCustomer, $resolver->contextFor($asset), null, true));
    }

    public function test_designer_maintains_the_assignment_rules_and_keeps_unknown_ones(): void {
        $lead = $this->lead();
        [, $customer, $product, $sla] = $this->routerAsset($lead);
        $foreign = Customer::factory()->create(['organization_id' => User::factory()->create()->organization_id]);
        $template = $this->draftTemplate($lead, [['code' => 'a']], ['applicability' => ['region' => ['nord'], 'customer_ids' => [999]]]);

        $this->actingAs($lead)->get(route('procedures.edit', $template))->assertOk()
            ->assertSee(__('procedure.field.applicabilityCustomers'))
            ->assertSee($customer->name)
            ->assertSee(__('procedure.field.allowBlocked'));

        $this->actingAs($lead)->put(route('procedures.update', $template), [
            'name' => 'Prüfablauf',
            'applicability_tags' => 'fw-update',
            'applicability_customers' => [$customer->sqid, $foreign->sqid],
            'applicability_asset_categories' => ['router', 'gibt-es-nicht'],
            'applicability_products' => [$product->sqid],
            'applicability_sla_contracts' => [$sla->sqid],
            'allow_blocked' => '1',
            'steps' => [['code' => 'a', 'step_type' => 'confirm', 'label' => 'A']],
        ])->assertSessionHasNoErrors();

        $draft = $this->draftOf($template);
        $this->assertSame([
            'region' => ['nord'],
            'tags_any' => ['fw-update'],
            'customer_ids' => [$customer->id],
            'asset_categories' => ['router'],
            'product_ids' => [$product->id],
            'sla_contract_ids' => [$sla->id],
        ], $draft->applicability);
        $this->assertTrue($draft->allow_blocked);

        // Eine neue Version übernimmt Regeln und Schalter.
        $this->actingAs($lead)->post(route('procedures.versions.publish', [$template, $draft]))->assertSessionHasNoErrors();
        $this->actingAs($lead)->post(route('procedures.versions.store', $template))->assertSessionHasNoErrors();
        $next = $this->draftOf($template);
        $this->assertTrue($next->allow_blocked);
        $this->assertSame([$customer->id], $next->applicability['customer_ids']);
    }

    public function test_asset_record_offers_matching_procedures_and_starts_a_run(): void {
        $lead = $this->lead();
        [$asset] = $this->routerAsset($lead);
        $matching = $this->publishedTemplate($lead, [['code' => 'a']], ['applicability' => ['asset_categories' => ['router']]]);
        $universal = $this->publishedTemplate($lead, [['code' => 'a']]);

        $this->actingAs($lead)->get(route('assets.show', $asset))->assertOk()
            ->assertSee(__('procedure.panel.suggestedAsset'))
            ->assertSee(route('procedure-runs.start-asset', [$asset, $matching]))
            ->assertDontSee(route('procedure-runs.start-asset', [$asset, $universal]));

        $this->actingAs($lead)->post(route('procedure-runs.start-asset', [$asset, $universal]))
            ->assertRedirect(route('assets.show', $asset))->assertSessionHas('error', __('procedure.flash.notApplicableAsset'));

        $this->actingAs($lead)->post(route('procedure-runs.start-asset', [$asset, $matching]))->assertSessionHas('success');
        $run = ProcedureRun::query()->firstOrFail();
        $this->assertTrue($asset->is($run->subject));
        $this->actingAs($lead)->get(route('procedure-runs.show', $run))->assertOk()->assertSee(route('assets.show', $asset));
        // Der laufende Lauf steht an der Akte, die Vorlage wird nicht doppelt angeboten.
        $this->actingAs($lead)->get(route('assets.show', $asset))->assertOk()
            ->assertSee(route('procedure-runs.show', $run))
            ->assertDontSee(route('procedure-runs.start-asset', [$asset, $matching]));
    }

    public function test_blocked_asset_starts_only_procedures_that_allow_it(): void {
        $lead = $this->lead();
        [$asset] = $this->routerAsset($lead);
        $rules = ['applicability' => ['asset_categories' => ['router']]];
        $plain = $this->publishedTemplate($lead, [['code' => 'a']], $rules);
        $repair = $this->publishedTemplate($lead, [['code' => 'a']], $rules + ['allow_blocked' => true]);
        app(AssetBlockService::class)->block($asset, AssetBlockReason::Maintenance, $lead, 'Netzteil defekt');

        $this->actingAs($lead)->post(route('procedure-runs.start-asset', [$asset, $plain]))
            ->assertRedirect(route('assets.show', $asset))->assertSessionHas('error');
        $this->assertSame(0, ProcedureRun::query()->count());

        $this->actingAs($lead)->post(route('procedure-runs.start-asset', [$asset, $repair]))->assertSessionHas('success');
        $this->assertSame(1, ProcedureRun::query()->count());
    }

    public function test_start_on_an_asset_needs_the_start_permission_and_the_own_organisation(): void {
        $lead = $this->lead();
        [$asset] = $this->routerAsset($lead);
        $template = $this->publishedTemplate($lead, [['code' => 'a']], ['applicability' => ['asset_categories' => ['router']]]);

        $stranger = User::factory()->teamleitung()->create();
        $this->actingAs($stranger)->post(route('procedure-runs.start-asset', [$asset, $template]))->assertNotFound();
        $this->assertSame(0, ProcedureRun::query()->withoutGlobalScopes()->count());
    }

    /** @return array{0: Asset, 1: Customer, 2: Product, 3: SlaContract} */
    private function routerAsset(User $user): array {
        $customer = Customer::factory()->create(['organization_id' => $user->organization_id]);
        $product = Product::factory()->create(['organization_id' => $user->organization_id]);
        $sla = SlaContract::factory()->create(['organization_id' => $user->organization_id]);
        $asset = Asset::factory()->create([
            'organization_id' => $user->organization_id,
            'customer_id' => $customer->id,
            'category_code' => 'router',
            'product_id' => $product->id,
            'sla_contract_id' => $sla->id,
        ]);

        return [$asset, $customer, $product, $sla];
    }
}
