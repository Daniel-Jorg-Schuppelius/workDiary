<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintOrderUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Print;

use App\Enums\Print\{PreflightStatus, PrintOrderStatus};
use App\Models\{Article, User};
use App\Models\Print\PrintOrder;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Print\PrintOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-459 — Druck-UI: Profil-Gate (404), Modul-Gate (423), Rechte (403),
 * Auftragserstellung + Datei-Upload + Preflight + Freigabe über die
 * Weboberfläche sowie Tenant-Grenzen.
 */
class PrintOrderUiTest extends TestCase {
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

    public function test_profile_module_and_permission_gates(): void {
        $this->get(route('print-orders.index'))->assertNotFound();

        $this->activateProfile();
        $this->get(route('print-orders.index'))->assertOk();

        config(['license.feature_overrides' => ['module.lager' => false]]);
        app(FeatureFlagResolver::class)->flush();
        $this->get(route('print-orders.index'))->assertStatus(423);
        config(['license.feature_overrides' => []]);
        app(FeatureFlagResolver::class)->flush();

        $member = $this->orgUser();
        $this->actingAs($member)->get(route('print-orders.index'))->assertForbidden();
    }

    public function test_order_creation_upload_preflight_and_approval_via_web_ui(): void {
        Storage::fake('local');
        $this->activateProfile();
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true]);

        // Auftrag über den Dialog anlegen (Fertigungsauftrag + Fachakte).
        $this->get(route('print-orders.create'))->assertOk();
        $response = $this->post(route('print-orders.store'), [
            'article_id' => $article->sqid,
            'target_qty' => '250',
            'unit' => 'Stk',
            'output_kind' => 'pickup',
            'due_at' => now()->addDays(4)->toDateString(),
        ]);
        $order = PrintOrder::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('print-orders.show', $order));
        $this->assertSame(PrintOrderStatus::DataCheck, $order->status);
        $this->assertNotNull($order->manufacturingOrder()->first());

        // Produktionsdatei hochladen → Hash-Bindung.
        $this->post(route('print-orders.file', $order), [
            'file' => UploadedFile::fake()->createWithContent('flyer.pdf', "%PDF-1.7\nUI"),
        ])->assertRedirect(route('print-orders.show', $order));
        $order->refresh();
        $this->assertNotNull($order->file_hash);
        $this->assertSame(PreflightStatus::Pending, $order->preflight_status);

        // Preflight + Freigabe.
        $this->post(route('print-orders.preflight.run', $order))->assertRedirect();
        $this->assertSame(PreflightStatus::Passed, $order->refresh()->preflight_status);

        $this->post(route('print-orders.approve', $order), [
            'final_format' => 'DIN A5',
            'color_mode' => '4/4 CMYK',
            'material' => 'Bilderdruck glänzend',
            'grammage' => '170 g/m²',
            'quantity' => '250',
            'due_date' => now()->addDays(4)->toDateString(),
            'finishing' => "schneiden\nfalzen",
        ])->assertRedirect(route('print-orders.show', $order));
        $order->refresh();
        $this->assertSame(PrintOrderStatus::Approved, $order->status);
        $this->assertSame(['schneiden', 'falzen'], data_get($order->production_snapshot, 'finishing'));

        // Detailseite rendert Snapshot + Statuslabel.
        $this->get(route('print-orders.show', $order))
            ->assertOk()
            ->assertSee('DIN A5')
            ->assertSee($order->status->label());
    }

    public function test_foreign_organization_cannot_access_print_orders(): void {
        Storage::fake('local');
        $this->activateProfile();
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true]);
        $manufacturingOrder = app(\App\Services\Manufacturing\ManufacturingOrderService::class)
            ->createDraft($this->organization, $article, null, '10', 'Stk');
        $order = app(PrintOrderService::class)->open($manufacturingOrder, $this->admin);

        $foreignAdmin = User::factory()->admin()->create();
        $foreignOrg = $foreignAdmin->organization;
        $settings = is_array($foreignOrg->settings) ? $foreignOrg->settings : [];
        $settings['branch_profile_code'] = PrintOrderService::PROFILE_CODE;
        $foreignOrg->forceFill(['settings' => $settings])->save();
        $foreignAdmin->unsetRelation('organization');

        $this->actingAs($foreignAdmin)->get(route('print-orders.show', $order))->assertNotFound();
    }
}
