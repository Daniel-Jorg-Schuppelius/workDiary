<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintOrderClaimTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Print;

use App\Models\{Article, User};
use App\Models\Claims\{ClaimCase, ClaimCaseLink};
use App\Models\Print\PrintOrder;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Print\PrintOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-459-Lückenschluss (Issue #75): Reklamationen referenzieren den
 * Druckauftrag strukturell — die Aktion an der Fachakte legt den Fall an
 * und bindet ihn per ClaimCaseLink (role `affected`) an den PrintOrder;
 * darüber bleiben freigegebene Datei, Snapshot und QK referenzierbar.
 */
class PrintOrderClaimTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
    }

    private function activateProfile(): void {
        $settings = is_array($this->organization->settings) ? $this->organization->settings : [];
        $settings['branch_profile_code'] = PrintOrderService::PROFILE_CODE;
        $settings['branch_profile_versions'] = [PrintOrderService::PROFILE_CODE => 1];
        $this->organization->forceFill(['settings' => $settings])->save();
        $this->admin->unsetRelation('organization');
    }

    private function order(): PrintOrder {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true]);
        $this->post(route('print-orders.store'), [
            'article_id' => $article->sqid,
            'target_qty' => '500',
            'unit' => 'Stk',
            'output_kind' => 'pickup',
        ]);

        return PrintOrder::query()->latest('id')->firstOrFail();
    }

    public function test_claim_is_created_and_linked_to_print_order(): void {
        $this->activateProfile();
        $order = $this->order();

        $this->post(route('print-orders.claim', $order), [
            'description' => 'Farbstich Magenta auf der ganzen Auflage.',
            'affected_quantity' => '500',
        ])->assertRedirect();

        $claim = ClaimCase::query()->firstOrFail();
        $this->assertSame($this->organization->id, $claim->organization_id);
        $this->assertStringContainsString((string) $order->manufacturingOrder()->firstOrFail()->number, (string) $claim->title);
        $this->assertSame('Farbstich Magenta auf der ganzen Auflage.', $claim->description);

        $link = ClaimCaseLink::query()->firstOrFail();
        $this->assertSame($claim->id, $link->claim_case_id);
        $this->assertSame($order->getMorphClass(), $link->linkable_type);
        $this->assertSame($order->id, $link->linkable_id);
        $this->assertSame('affected', $link->role);
        $this->assertStringContainsString('500', (string) $link->note);

        // Die Fachakten-Seite zeigt den verknüpften Fall.
        $this->get(route('print-orders.show', $order))
            ->assertOk()
            ->assertSee($claim->number);
    }

    public function test_claim_action_requires_claims_module(): void {
        $this->activateProfile();
        $order = $this->order();

        config(['license.feature_overrides' => ['module.claims' => false]]);
        app(FeatureFlagResolver::class)->flush();

        $this->post(route('print-orders.claim', $order), [
            'description' => 'Testfall',
        ])->assertNotFound();
        $this->assertSame(0, ClaimCase::query()->count());

        config(['license.feature_overrides' => []]);
        app(FeatureFlagResolver::class)->flush();
    }
}
