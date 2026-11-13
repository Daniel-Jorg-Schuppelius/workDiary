<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OfflineMaterialStockConflictTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Enums\Inventory\NegativeStockRequestStatus;
use App\Enums\Material\MaterialStockStatus;
use App\Enums\Project\ProjectStatus;
use App\Enums\User\Permission as P;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{NegativeStockRequest, Warehouse};
use App\Models\Material\{Material, MaterialUsage};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Inventory\{InventoryLedger, NegativeStockApproval, ValuationService};
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1691: Fehlt einem offline erfassten Materialverbrauch nur Bestand, sagt
 * der Konflikt das (`shortage`); das Konfliktformular bietet mit Recht
 * `inventory.negative` den Haken „Negativen Bestand zulassen“, und das erneute
 * Senden bucht wie online — mit Vier-Augen-Pflicht als Antrag. Ohne Recht
 * bleibt der Konflikt.
 */
final class OfflineMaterialStockConflictTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    private Warehouse $warehouse;

    private ArticleVariant $variant;

    private Material $material;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2030-01-16 10:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Außendienst Süd',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Hauptlager', 'is_default' => true]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk', 'stockable' => true]);
        $this->variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'is_default' => true, 'option_signature' => 'default']);
        $this->material = Material::create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'name' => 'Abzweigdose',
            'unit' => 'Stk.',
            'default_unit_price' => 3.5,
            'is_active' => true,
        ]);
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '5', '2.00');
    }

    private function grantNegative(): void {
        $this->user->givePermissionTo(P::InventoryNegative->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return TestResponse<\Illuminate\Http\JsonResponse>
     */
    private function send(array $overrides = []): TestResponse {
        return $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => [[
                'client_uuid' => (string) Str::uuid(),
                'type' => 'material.usage',
                'payload' => array_merge([
                    'project_id' => $this->project->sqid,
                    'date' => '2030-01-15',
                    'material_id' => $this->material->sqid,
                    'description' => 'Abzweigdose',
                    'quantity' => '8',
                    'unit' => 'Stk.',
                ], $overrides),
            ]],
        ]);
    }

    private function available(): string {
        return app(InventoryLedger::class)->available($this->variant, $this->warehouse);
    }

    public function test_shortage_conflict_says_so_and_other_stock_rules_do_not(): void {
        $this->send()
            ->assertJsonPath('results.0.status', 'conflict')
            ->assertJsonPath('results.0.conflict.server.field', 'quantity')
            ->assertJsonPath('results.0.conflict.server.shortage', true);

        // Gegenprobe: eine unbekannte Einheit ist kein Fehlbestand — der Haken hilft dort nicht.
        $response = $this->send(['quantity' => '1', 'unit' => 'Rolle'])->assertJsonPath('results.0.status', 'conflict');
        $this->assertSame('unit', $response->json('results.0.conflict.server.field'));
        $this->assertArrayNotHasKey('shortage', (array) $response->json('results.0.conflict.server'));
        $this->assertSame(0, MaterialUsage::query()->count());
    }

    public function test_resending_with_negative_stock_books_with_permission(): void {
        $this->grantNegative();
        $this->send()->assertJsonPath('results.0.conflict.server.shortage', true);

        $this->send(['allow_negative' => '1'])->assertJsonPath('results.0.status', 'applied');

        $usage = MaterialUsage::query()->sole();
        $this->assertSame(MaterialStockStatus::Booked, $usage->stock_status);
        $this->assertSame('-3.0000', $this->available());
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.negativeApproved', 'user_id' => $this->user->id, 'auditable_id' => $this->variant->id]);
    }

    public function test_four_eyes_turn_the_resent_usage_into_a_request(): void {
        Setting::set(NegativeStockApproval::SETTING, true, SettingScope::Organization, $this->organization);
        $this->organization->refresh();
        $this->grantNegative();

        $this->send(['allow_negative' => '1'])->assertJsonPath('results.0.status', 'applied');

        $usage = MaterialUsage::query()->sole();
        $this->assertSame(MaterialStockStatus::Requested, $usage->stock_status);
        $this->assertSame('5.0000', $this->available(), 'Vor der Freigabe wird nichts gebucht.');
        $request = NegativeStockRequest::query()->sole();
        $this->assertSame([NegativeStockRequestStatus::Pending, (int) $usage->id, '8.0000'], [$request->status, (int) $request->source_id, (string) $request->quantity]);
    }

    public function test_without_permission_the_conflict_stays(): void {
        $this->send(['allow_negative' => '1'])
            ->assertJsonPath('results.0.status', 'conflict')
            ->assertJsonPath('results.0.conflict.server.field', 'allow_negative')
            ->assertJsonPath('results.0.conflict.server.stock', __('material.stock.negative_forbidden'));

        $this->assertSame(0, MaterialUsage::query()->count());
        $this->assertSame('5.0000', $this->available());
    }

    public function test_changes_page_offers_the_choice_only_with_permission(): void {
        $this->actingAs($this->user)->get(route('offline.changes'))->assertOk()
            ->assertSee('data-allow-negative="0"', false)
            ->assertSee('data-label-allow-negative-hint="' . e(__('offline.allow_negative_hint')) . '"', false);

        $this->grantNegative();
        $this->actingAs($this->user)->get(route('offline.changes'))->assertOk()
            ->assertSee('data-allow-negative="1"', false);
    }
}
