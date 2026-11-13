<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialUsageVariantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Material;

use App\Enums\Article\ArticleStatus;
use App\Enums\Finance\{TransferChannel, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockMovement, Warehouse};
use App\Models\Material\{Material, MaterialUsage};
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Models\Time\Timesheet;
use App\Services\Finance\{BillingPositionBuilder, BillingTransferService};
use App\Services\Inventory\{InventoryLedger, ValuationService};
use App\Services\Invoicing\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Carbon, Str};
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1690: Hat der lagergeführte Artikel eines Materials mehrere aktive
 * Varianten, wählt der Verbrauch die verbrauchte — am Stundenzettel, auf
 * „Heute“, in der API und offline. Gebucht wird die gewählte Variante, ein
 * Wechsel bucht um; die Abrechnung nennt sie wie die Auslieferung.
 */
final class MaterialUsageVariantTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    private Timesheet $timesheet;

    private Warehouse $warehouse;

    private Article $article;

    private ArticleVariant $grey;

    private ArticleVariant $white;

    private Material $material;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2030-01-16 10:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Beispielbau GmbH',
            'currency' => 'EUR',
            'created_by' => $this->user->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Außendienst Nord',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
        $this->timesheet = $this->project->timesheets()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'work_date' => Carbon::parse('2030-01-15'),
        ]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Hauptlager', 'is_default' => true]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Abzweigdose', 'base_unit' => 'Stk', 'stockable' => true]);
        $this->grey = $this->variant($this->article, 'grau', 'AD-G', default: true);
        $this->white = $this->variant($this->article, 'weiß', 'AD-W', price: '4.0000');
        $this->material = Material::create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'name' => 'Abzweigdose',
            'unit' => 'Stk.',
            'default_unit_price' => 3.5,
            'tax_rate' => 19.00,
            'is_active' => true,
        ]);
        app(ValuationService::class)->receipt($this->grey, $this->warehouse, '10', '2.00');
        app(ValuationService::class)->receipt($this->white, $this->warehouse, '10', '2.50');
    }

    private function variant(Article $article, string $name, string $sku, bool $default = false, ?string $price = null): ArticleVariant {
        return ArticleVariant::factory()->create([
            'organization_id' => $article->organization_id,
            'article_id' => $article->id,
            'name' => $name,
            'sku' => $sku,
            'is_default' => $default,
            'option_signature' => $sku,
            'sale_price' => $price,
        ]);
    }

    private function available(ArticleVariant $variant): string {
        return app(InventoryLedger::class)->available($variant, $this->warehouse);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function line(array $overrides = []): array {
        return array_merge(['material_id' => $this->material->sqid, 'description' => 'Abzweigdose', 'quantity' => '3', 'unit' => 'Stk.'], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function store(array $overrides = []): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->user)->post(route('projects.timesheets.materials.store', [$this->project, $this->timesheet]), $this->line($overrides));
    }

    public function test_dialogs_offer_variants_only_for_stocked_articles_with_several_variants(): void {
        $single = Article::factory()->create(['organization_id' => $this->organization->id, 'stockable' => true]);
        $only = $this->variant($single, 'einzig', 'EZ-1', default: true);
        Material::create(['organization_id' => $this->organization->id, 'article_id' => $single->id, 'name' => 'Kabelbinder', 'unit' => 'Stk', 'is_active' => true]);

        $this->actingAs($this->user)->get(route('projects.timesheets.materials.create', [$this->project, $this->timesheet]))->assertOk()
            ->assertSee('name="article_variant_id"', false)
            ->assertSee('data-depends-strict', false)
            ->assertSee('AD-W · weiß')
            ->assertSee('data-parent="' . $this->material->sqid . '"', false)
            ->assertDontSee($only->sqid);
        $this->actingAs($this->user)->get(route('today.show'))->assertOk()
            ->assertSee('id="today-material-variant"', false)
            ->assertSee('AD-G · grau');

        // Gegenprobe: eine stillgelegte und eine nicht lagergeführte Variante — nichts zu wählen, kein Feld.
        $this->white->forceFill(['status' => ArticleStatus::Retired])->save();
        $this->actingAs($this->user)->get(route('projects.timesheets.materials.create', [$this->project, $this->timesheet]))->assertOk()
            ->assertDontSee('name="article_variant_id"', false);
        $this->white->forceFill(['status' => ArticleStatus::Active])->save();
        $this->article->forceFill(['stockable' => false])->save();
        $this->actingAs($this->user)->get(route('today.show'))->assertOk()
            ->assertDontSee('id="today-material-variant"', false);
    }

    public function test_the_chosen_variant_is_booked_and_priced(): void {
        $this->store(['article_variant_id' => $this->white->sqid])->assertSessionHasNoErrors();

        $usage = MaterialUsage::query()->sole();
        $this->assertSame((int) $this->white->id, (int) $usage->article_variant_id);
        $this->assertSame(['10.0000', '7.0000'], [$this->available($this->grey), $this->available($this->white)]);
        $this->assertSame('4.0000', $usage->unit_price?->getAmount(), 'Ohne Einzelpreis gilt der eigene Preis der Variante.');
        $movement = StockMovement::query()->where('source_id', $usage->id)->where('source_type', $usage->getMorphClass())->sole();
        $this->assertSame([(int) $this->white->id, '2.5000'], [(int) $movement->article_variant_id, $movement->cost_unit?->getAmount()]);

        // Gegenprobe: ohne Wahl die Standardvariante zum Materialpreis wie bisher.
        $this->store()->assertSessionHasNoErrors();
        $plain = MaterialUsage::query()->latest('id')->firstOrFail();
        $this->assertNull($plain->article_variant_id);
        $this->assertSame('3.5000', $plain->unit_price?->getAmount());
        $this->assertSame('7.0000', $this->available($this->grey));

        // Die Zeile am Stundenzettel nennt die Variante.
        $this->actingAs($this->user)->get(route('projects.timesheets.show', [$this->project, $this->timesheet]))->assertOk()
            ->assertSee(__('material.variant.label', ['variant' => 'weiß (AD-W)']));
    }

    public function test_switching_the_variant_returns_the_old_one_and_issues_the_new_one(): void {
        $this->store(['article_variant_id' => $this->grey->sqid])->assertSessionHasNoErrors();
        $usage = MaterialUsage::query()->sole();
        $this->assertSame('7.0000', $this->available($this->grey));

        $this->actingAs($this->user)->put(route('projects.timesheets.materials.update', [$this->project, $this->timesheet, $usage]), $this->line(['article_variant_id' => $this->white->sqid, 'unit_price' => '3.5']))
            ->assertSessionHasNoErrors();

        $this->assertSame(['10.0000', '7.0000'], [$this->available($this->grey), $this->available($this->white)]);
        $this->assertSame(
            [[(int) $this->grey->id, 'issue', '-3.0000'], [(int) $this->grey->id, 'return', '3.0000'], [(int) $this->white->id, 'issue', '-3.0000']],
            StockMovement::query()->where('source_type', $usage->getMorphClass())->where('source_id', $usage->id)->orderBy('id')->get()
                ->map(fn (StockMovement $m): array => [(int) $m->article_variant_id, $m->movement_type->value, (string) $m->qty_base])->all(),
        );

        // Zurück auf die Standardvariante (leere Wahl) bucht wieder um; Löschen gibt alles zurück.
        $this->actingAs($this->user)->put(route('projects.timesheets.materials.update', [$this->project, $this->timesheet, $usage]), $this->line(['article_variant_id' => '', 'unit_price' => '3.5']))
            ->assertSessionHasNoErrors();
        $this->assertSame(['7.0000', '10.0000'], [$this->available($this->grey), $this->available($this->white)]);
        $this->actingAs($this->user)->delete(route('projects.timesheets.materials.destroy', [$this->project, $this->timesheet, $usage]))->assertSessionHasNoErrors();
        $this->assertSame(['10.0000', '10.0000'], [$this->available($this->grey), $this->available($this->white)]);
    }

    public function test_a_variant_of_another_article_or_organization_is_rejected(): void {
        $other = Article::factory()->create(['organization_id' => $this->organization->id, 'stockable' => true]);
        $foreign = $this->variant($other, 'rot', 'FR-1', default: true);
        $this->store(['article_variant_id' => $foreign->sqid])->assertSessionHasErrors(['article_variant_id' => __('material.variant.mismatch')]);
        $this->store(['material_id' => '', 'article_variant_id' => $this->white->sqid])->assertSessionHasErrors('article_variant_id');

        $otherOrg = Organization::factory()->create();
        $alien = ArticleVariant::factory()->create(['organization_id' => $otherOrg->id, 'article_id' => Article::factory()->create(['organization_id' => $otherOrg->id])->id, 'option_signature' => 'x']);
        $this->store(['article_variant_id' => $alien->sqid])->assertSessionHasErrors('article_variant_id');

        $this->white->forceFill(['status' => ArticleStatus::Retired])->save();
        $this->store(['article_variant_id' => $this->white->sqid])->assertSessionHasErrors(['article_variant_id' => __('material.variant.inactive')]);

        $this->assertSame(0, MaterialUsage::query()->count());
        $this->assertSame(['10.0000', '10.0000'], [$this->available($this->grey), $this->available($this->white)]);
    }

    public function test_another_material_drops_the_variant(): void {
        $this->store(['article_variant_id' => $this->white->sqid])->assertSessionHasNoErrors();
        $usage = MaterialUsage::query()->sole();
        $free = Material::create(['organization_id' => $this->organization->id, 'name' => 'Kleinteile', 'unit' => 'Stk.', 'is_active' => true]);

        // Die API sendet kein Variantenfeld: das neue Material nimmt die Variante des alten nicht mit.
        Sanctum::actingAs($this->user, ['timesheets:read', 'timesheets:write']);
        $this->putJson(route('api.timesheets.materials.update', [$this->timesheet, $usage]), $this->line(['material_id' => $free->sqid]))->assertOk()
            ->assertJsonPath('data.article_variant_id', null);

        $this->assertNull($usage->fresh()?->article_variant_id);
        $this->assertSame('10.0000', $this->available($this->white));
    }

    public function test_api_writes_and_reads_the_variant(): void {
        Sanctum::actingAs($this->user, ['timesheets:read', 'timesheets:write']);

        $this->postJson(route('api.timesheets.materials.store', $this->timesheet), $this->line(['article_variant_id' => $this->white->sqid]))
            ->assertSuccessful()
            ->assertJsonPath('data.article_variant_id', $this->white->sqid)
            ->assertJsonPath('data.variant', 'weiß (AD-W)');
        $this->assertSame('7.0000', $this->available($this->white));
        $this->getJson(route('api.timesheets.materials.index', $this->timesheet))->assertOk()
            ->assertJsonPath('data.0.variant', 'weiß (AD-W)');

        // Eine Variante eines fremden Artikels lehnt die API ab.
        $foreign = $this->variant(Article::factory()->create(['organization_id' => $this->organization->id, 'stockable' => true]), 'rot', 'FR-1', default: true);
        $this->postJson(route('api.timesheets.materials.store', $this->timesheet), $this->line(['article_variant_id' => $foreign->sqid]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('article_variant_id');
        $this->assertSame(1, MaterialUsage::query()->count());
    }

    public function test_offline_usage_books_the_chosen_variant(): void {
        $payload = ['project_id' => $this->project->sqid, 'date' => '2030-01-15', 'material_id' => $this->material->sqid, 'article_variant_id' => $this->white->sqid, 'description' => 'Abzweigdose', 'quantity' => '2', 'unit' => 'Stk.'];

        $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => [['client_uuid' => (string) Str::uuid(), 'type' => 'material.usage', 'payload' => $payload]],
        ])->assertJsonPath('results.0.status', 'applied');

        $this->assertSame((int) $this->white->id, (int) MaterialUsage::query()->sole()->article_variant_id);
        $this->assertSame(['10.0000', '8.0000'], [$this->available($this->grey), $this->available($this->white)]);

        // Gegenprobe: fremde Variante offline ist eine Ablehnung wie online, nichts gebucht.
        $foreign = $this->variant(Article::factory()->create(['organization_id' => $this->organization->id, 'stockable' => true]), 'rot', 'FR-1', default: true);
        $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => [['client_uuid' => (string) Str::uuid(), 'type' => 'material.usage', 'payload' => array_merge($payload, ['article_variant_id' => $foreign->sqid])]],
        ])->assertJsonPath('results.0.status', 'rejected');
        $this->assertSame(1, MaterialUsage::query()->count());
    }

    public function test_billing_names_the_variant_like_the_delivery(): void {
        $this->store(['article_variant_id' => $this->white->sqid])->assertSessionHasNoErrors();
        $this->store(['description' => 'Abzweigdose weiß', 'article_variant_id' => $this->white->sqid])->assertSessionHasNoErrors();
        $this->store()->assertSessionHasNoErrors();

        $transfer = app(BillingTransferService::class)->createDraft($this->customer, TransferChannel::Material, TransferTarget::File, ['from' => '2030-01-01', 'to' => '2030-01-31'], null, $this->user);
        $this->assertSame(
            ['Abzweigdose – weiß (AD-W)', 'Abzweigdose weiß', 'Abzweigdose'],
            app(BillingPositionBuilder::class)->build($transfer)->pluck('name')->all(),
        );
        app(BillingTransferService::class)->void($transfer);

        $invoice = app(InvoiceGenerator::class)->fromMaterialUsages($this->customer->refresh(), $this->project);
        $items = $invoice->items()->orderBy('position')->get();
        $this->assertSame(['Abzweigdose – weiß (AD-W)', 'Abzweigdose weiß', 'Abzweigdose'], $items->pluck('description')->all());
        $this->assertSame([(int) $this->white->id, (int) $this->white->id, null], $items->map(fn ($item): ?int => $item->article_variant_id !== null ? (int) $item->article_variant_id : null)->all(), 'Ohne Wahl nennt die Position keine Variante.');
    }
}
