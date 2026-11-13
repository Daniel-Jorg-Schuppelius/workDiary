<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialUsageStockBookingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Material;

use App\Enums\Inventory\{NegativeStockRequestStatus, StockMovementType};
use App\Enums\Material\MaterialStockStatus;
use App\Enums\Project\ProjectStatus;
use App\Enums\User\Permission as P;
use App\Models\Approval\Approval;
use App\Models\Article\{Article, ArticleUnit, ArticleVariant};
use App\Models\Inventory\{InventoryOutboxEntry, NegativeStockRequest, StockMovement, StockValuationLayer, Warehouse};
use App\Models\Material\{Material, MaterialUsage};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\Timesheet;
use App\Services\Inventory\{InventoryLedger, NegativeStockApproval, ValuationService};
use App\Settings\{SettingScope, SettingsRegistry};
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Carbon, Str};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1679: Der Materialverbrauch eines lagergeführten Artikels bucht den
 * Abgang aus dem Standardlager — genau einmal, Änderungen als Differenz,
 * Löschen als Rückbuchung, mit den Regeln des Lagerwegs; online wie offline.
 */
final class MaterialUsageStockBookingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    private Timesheet $timesheet;

    private Warehouse $warehouse;

    private Article $article;

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
        Warehouse::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Aaa Nebenlager']);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk', 'stockable' => true]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default',
        ]);
        $this->material = Material::create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'name' => 'Abzweigdose',
            'unit' => 'Stk.',
            'default_unit_price' => 3.5,
            'is_active' => true,
        ]);
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '10', '2.00');
    }

    private function available(): string {
        return app(InventoryLedger::class)->available($this->variant, $this->warehouse);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, StockMovement> */
    private function movementsOf(MaterialUsage $usage) {
        return StockMovement::query()->where('source_type', $usage->getMorphClass())->where('source_id', $usage->id)->orderBy('id')->get();
    }

    /** @param array<string, mixed> $overrides */
    private function line(array $overrides = []): array {
        return array_merge(['material_id' => $this->material->sqid, 'description' => 'Abzweigdose', 'quantity' => '3', 'unit' => 'Stk.'], $overrides);
    }

    private function store(array $overrides = [], ?User $as = null): \Illuminate\Testing\TestResponse {
        return $this->actingAs($as ?? $this->user)->post(route('projects.timesheets.materials.store', [$this->project, $this->timesheet]), $this->line($overrides));
    }

    public function test_usage_of_a_stocked_article_issues_exactly_once(): void {
        $this->store()->assertSessionHasNoErrors()
            ->assertSessionHas('success', __('Material erfasst.') . ' ' . __('material.stock.notice', ['status' => MaterialStockStatus::Booked->label(), 'warehouse' => 'Hauptlager']) . '.');

        $usage = MaterialUsage::query()->sole();
        $this->assertSame(MaterialStockStatus::Booked, $usage->stock_status);
        $this->assertSame((int) $this->warehouse->id, (int) $usage->stock_warehouse_id);
        $this->assertSame('7.0000', $this->available(), 'Die Schreibweise „Stk.“ zählt als Basiseinheit „Stk“.');
        $movement = $this->movementsOf($usage)->sole();
        $this->assertSame('material_usage:' . $usage->id, $movement->idempotency_key);
        $this->assertSame(StockMovementType::Issue, $movement->movement_type);
        $this->assertSame('2.0000', $movement->cost_unit?->getAmount(), 'Bewertet zum gleitenden Durchschnitt.');

        // Ein zweiter Abgleich ohne Änderung bucht nichts.
        $this->actingAs($this->user)->put(route('projects.timesheets.materials.update', [$this->project, $this->timesheet, $usage]), $this->line(['description' => 'Abzweigdose grau']))
            ->assertSessionHasNoErrors();
        $this->assertCount(1, $this->movementsOf($usage));
        $this->assertSame('7.0000', $this->available());
        $this->actingAs($this->user)->get(route('projects.timesheets.show', [$this->project, $this->timesheet]))->assertOk()
            ->assertSee(__('material.stock.notice', ['status' => MaterialStockStatus::Booked->label(), 'warehouse' => 'Hauptlager']));
    }

    public function test_changing_the_quantity_books_the_difference_and_deleting_returns_it(): void {
        $this->store()->assertSessionHasNoErrors();
        $usage = MaterialUsage::query()->sole();
        $route = route('projects.timesheets.materials.update', [$this->project, $this->timesheet, $usage]);

        $this->actingAs($this->user)->put($route, $this->line(['quantity' => '5']))->assertSessionHasNoErrors();
        $this->assertSame('5.0000', $this->available());
        $this->actingAs($this->user)->put($route, $this->line(['quantity' => '1']))->assertSessionHasNoErrors();
        $this->assertSame('9.0000', $this->available());
        $this->assertSame(
            [['issue', '-3.0000'], ['issue', '-2.0000'], ['return', '4.0000']],
            $this->movementsOf($usage)->map(fn (StockMovement $m): array => [$m->movement_type->value, (string) $m->qty_base])->all(),
        );
        $this->assertSame('2.0000', $this->movementsOf($usage)->last()?->cost_unit?->getAmount(), 'Zurück zum Stückkostenwert des Abgangs.');

        $this->actingAs($this->user)->delete(route('projects.timesheets.materials.destroy', [$this->project, $this->timesheet, $usage]))->assertSessionHasNoErrors();
        $this->assertSame('10.0000', $this->available());
        $this->assertSame(0, MaterialUsage::query()->count());
    }

    public function test_deleting_the_timesheet_returns_its_usages(): void {
        $this->store(['quantity' => '4'])->assertSessionHasNoErrors();
        $this->assertSame('6.0000', $this->available());

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($admin)->delete(route('projects.timesheets.destroy', [$this->project, $this->timesheet]))->assertRedirect();

        $this->assertSame(0, Timesheet::query()->count());
        $this->assertSame('10.0000', $this->available());
    }

    public function test_switching_the_material_returns_the_old_article(): void {
        $this->store()->assertSessionHasNoErrors();
        $usage = MaterialUsage::query()->sole();
        $free = Material::create(['organization_id' => $this->organization->id, 'name' => 'Kleinteile', 'unit' => 'Stk.', 'is_active' => true]);

        $this->actingAs($this->user)->put(route('projects.timesheets.materials.update', [$this->project, $this->timesheet, $usage]), $this->line(['material_id' => $free->sqid]))
            ->assertSessionHasNoErrors();

        $this->assertSame('10.0000', $this->available());
        $this->assertNull($usage->fresh()?->stock_status, 'Ohne lagergeführten Artikel kein Bestandsbezug.');
    }

    public function test_rows_from_before_the_switch_stay_unbooked(): void {
        // So liegen Verbräuche, die vor MVP-1679 erfasst wurden: lagerfähiger Artikel, aber ohne Bestandsbezug.
        $legacy = $this->timesheet->materialUsages()->create(['material_id' => $this->material->id, 'description' => 'Abzweigdose', 'quantity' => 5, 'unit' => 'Stk.']);
        $route = route('projects.timesheets.materials.update', [$this->project, $this->timesheet, $legacy]);

        $this->actingAs($this->user)->put($route, $this->line(['quantity' => '6', 'description' => 'Abzweigdose korrigiert']))->assertSessionHasNoErrors();
        $this->assertNull($legacy->fresh()?->stock_status);
        $this->assertSame('10.0000', $this->available(), 'Eine Mengenkorrektur bucht die Altzeile nicht nach.');

        $this->actingAs($this->user)->delete(route('projects.timesheets.materials.destroy', [$this->project, $this->timesheet, $legacy]))->assertSessionHasNoErrors();
        $this->assertSame('10.0000', $this->available(), 'Löschen bucht nur zurück, was gebucht war.');
    }

    public function test_insufficient_stock_without_permission_is_a_validation_error(): void {
        $this->store(['quantity' => '12'])->assertSessionHasErrors('quantity');
        $this->store(['quantity' => '12', 'allow_negative' => '1'])->assertSessionHasErrors('allow_negative');

        $this->assertSame(0, MaterialUsage::query()->count(), 'Ohne Buchung kein Verbrauch.');
        $this->assertSame('10.0000', $this->available());
    }

    public function test_negative_stock_with_permission_is_booked_and_audited(): void {
        $this->user->givePermissionTo(P::InventoryNegative->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->store(['quantity' => '12'])->assertSessionHasErrors('quantity');
        $this->store(['quantity' => '12', 'allow_negative' => '1'])->assertSessionHasNoErrors();

        $this->assertSame('-2.0000', $this->available());
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.negativeApproved', 'user_id' => $this->user->id, 'auditable_id' => $this->variant->id]);
    }

    public function test_four_eyes_turn_negative_stock_into_a_request(): void {
        Setting::set(NegativeStockApproval::SETTING, true, SettingScope::Organization, $this->organization);
        $this->organization->refresh();
        $this->user->givePermissionTo(P::InventoryNegative->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->store(['quantity' => '12', 'allow_negative' => '1'])->assertSessionHasNoErrors();
        $usage = MaterialUsage::query()->sole();
        $this->assertSame(MaterialStockStatus::Requested, $usage->stock_status);
        $this->assertSame('10.0000', $this->available(), 'Vor der Freigabe wird nichts gebucht.');
        $request = NegativeStockRequest::query()->sole();
        $this->assertSame([$usage->getMorphClass(), (int) $usage->id, '12.0000'], [$request->source_type, (int) $request->source_id, (string) $request->quantity]);

        // Nur Text geändert: der Antrag bleibt offen.
        $this->actingAs($this->user)->put(route('projects.timesheets.materials.update', [$this->project, $this->timesheet, $usage]), $this->line(['quantity' => '12', 'description' => 'Dose']))
            ->assertSessionHasNoErrors();
        $this->assertSame(NegativeStockRequestStatus::Pending, $request->fresh()?->status);

        $second = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $step = Approval::query()->where('approvable_id', $request->id)->where('approvable_type', $request->getMorphClass())->sole();
        $this->actingAs($second)->post(route('approvals.decide', $step), ['decision' => 'approved'])->assertRedirect(route('approvals.index'));

        $this->assertSame(NegativeStockRequestStatus::Approved, $request->fresh()?->status);
        $this->assertSame(MaterialStockStatus::Booked, $usage->fresh()?->stock_status);
        $this->assertSame('-2.0000', $this->available());
        $this->assertSame('negative_stock_request:' . $request->id, $this->movementsOf($usage)->sole()->idempotency_key);
    }

    public function test_changed_or_deleted_usage_withdraws_its_request_and_rejection_books_nothing(): void {
        Setting::set(NegativeStockApproval::SETTING, true, SettingScope::Organization, $this->organization);
        $this->organization->refresh();
        $this->user->givePermissionTo(P::InventoryNegative->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->store(['quantity' => '12', 'allow_negative' => '1'])->assertSessionHasNoErrors();
        $usage = MaterialUsage::query()->sole();
        $first = NegativeStockRequest::query()->sole();

        // Weniger als der Bestand: der Antrag entfällt, der Abgang wird gebucht.
        $this->actingAs($this->user)->put(route('projects.timesheets.materials.update', [$this->project, $this->timesheet, $usage]), $this->line(['quantity' => '4']))
            ->assertSessionHasNoErrors();
        $this->assertSame(NegativeStockRequestStatus::Withdrawn, $first->fresh()?->status);
        $this->assertSame('6.0000', $this->available());
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.negativeWithdrawn', 'auditable_id' => $this->variant->id]);

        // Mehr als der Bestand: neuer Antrag über die Differenz; abgelehnt bleibt der gebuchte Teil.
        $this->actingAs($this->user)->put(route('projects.timesheets.materials.update', [$this->project, $this->timesheet, $usage]), $this->line(['quantity' => '15', 'allow_negative' => '1']))
            ->assertSessionHasNoErrors();
        $second = NegativeStockRequest::query()->latest('id')->firstOrFail();
        $this->assertSame('11.0000', (string) $second->quantity);
        $approver = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $step = Approval::query()->where('approvable_id', $second->id)->where('approvable_type', $second->getMorphClass())->sole();
        $this->actingAs($approver)->post(route('approvals.decide', $step), ['decision' => 'rejected', 'reason' => 'Erst zählen']);
        $this->assertSame(MaterialStockStatus::Rejected, $usage->fresh()?->stock_status);
        $this->assertSame('6.0000', $this->available());

        // Löschen bucht den gebuchten Teil zurück.
        $this->actingAs($this->user)->delete(route('projects.timesheets.materials.destroy', [$this->project, $this->timesheet, $usage]))->assertSessionHasNoErrors();
        $this->assertSame('10.0000', $this->available());
    }

    public function test_external_inventory_goes_to_the_outbox_of_the_leading_system(): void {
        $this->organization->forceFill(['settings' => array_merge($this->organization->settings ?? [], [
            'inventory_mode' => 'external',
            'inventory_plugin_id' => 'jtl_wawi',
        ])])->save();

        $this->store()->assertSessionHasNoErrors();
        $usage = MaterialUsage::query()->sole();

        $entry = InventoryOutboxEntry::query()->where('organization_id', $this->organization->id)->where('idempotency_key', 'material_usage:' . $usage->id)->sole();
        $this->assertSame('-3.0000', (string) data_get($entry->payload, 'qty_base'));
        $this->assertSame(MaterialStockStatus::Booked, $usage->stock_status);

        $this->actingAs($this->user)->delete(route('projects.timesheets.materials.destroy', [$this->project, $this->timesheet, $usage]))->assertSessionHasNoErrors();
        $this->assertSame(2, InventoryOutboxEntry::query()->where('organization_id', $this->organization->id)->count(), 'Auch die Rückbuchung geht an das führende System.');
    }

    public function test_read_only_inventory_records_without_booking(): void {
        $this->organization->forceFill(['settings' => array_merge($this->organization->settings ?? [], [
            'inventory_mode' => 'read_only',
            'inventory_plugin_id' => 'jtl_wawi',
        ])])->save();

        $this->store()->assertSessionHasNoErrors();

        $usage = MaterialUsage::query()->sole();
        $this->assertSame(MaterialStockStatus::ReadOnly, $usage->stock_status);
        $this->assertCount(0, $this->movementsOf($usage));
    }

    public function test_free_text_and_unstocked_articles_book_nothing(): void {
        $this->store(['material_id' => null, 'description' => 'Kleinteile'])->assertSessionHasNoErrors();
        $this->article->forceFill(['stockable' => false])->save();
        $this->store()->assertSessionHasNoErrors();

        $this->assertSame([null, null], MaterialUsage::query()->orderBy('id')->pluck('stock_status')->all());
        $this->assertSame('10.0000', $this->available());
    }

    public function test_without_default_warehouse_the_usage_says_so(): void {
        $this->warehouse->forceFill(['is_default' => false])->save();

        $this->store()->assertSessionHasNoErrors();

        $this->assertSame(MaterialStockStatus::NoWarehouse, MaterialUsage::query()->sole()->stock_status);
        $this->assertSame('10.0000', $this->available());
    }

    public function test_units_are_converted_and_unknown_units_rejected(): void {
        ArticleUnit::query()->create(['article_id' => $this->article->id, 'code' => 'Pack', 'factor_to_base' => '4', 'active' => true]);

        $this->store(['quantity' => '2', 'unit' => 'Pack'])->assertSessionHasNoErrors();
        $this->assertSame('2.0000', $this->available());
        $movement = $this->movementsOf(MaterialUsage::query()->sole())->sole();
        $this->assertSame(['2.0000', 'Pack'], [$movement->original_qty?->getNumericValue(), $movement->original_unit]);

        $this->store(['quantity' => '1', 'unit' => 'Rolle'])->assertSessionHasErrors('unit');
        $this->assertSame(1, MaterialUsage::query()->count());
    }

    public function test_serial_and_lot_obligations_are_not_bypassed(): void {
        $this->article->forceFill(['serial_required' => true])->save();
        $this->store()->assertSessionHasErrors('material_id');

        // Chargenpflicht: der Bestand liegt ohne Charge — eine Entnahme ohne Charge wäre still.
        $this->article->forceFill(['serial_required' => false, 'batch_required' => true])->save();
        $this->store()->assertSessionHasErrors('quantity');

        $this->assertSame(0, MaterialUsage::query()->count());
        $this->assertSame('10.0000', $this->available());
    }

    public function test_fifo_return_restores_a_valuation_layer(): void {
        app(SettingsRegistry::class)->setServiceManaged('inventory.valuation_method', 'fifo', $this->organization);
        $this->organization->refresh();
        $fifoVariant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => Article::factory()->create(['organization_id' => $this->organization->id, 'stockable' => true])->id, 'is_default' => true]);
        app(\App\Services\Inventory\FifoValuationService::class)->receipt($fifoVariant, $this->warehouse, '5', '3.00');
        $material = Material::create(['organization_id' => $this->organization->id, 'article_id' => $fifoVariant->article_id, 'name' => 'Kabelbinder', 'unit' => 'Stk', 'is_active' => true]);

        $this->store(['material_id' => $material->sqid, 'quantity' => '4', 'unit' => 'Stk'])->assertSessionHasNoErrors();
        $this->assertSame('1.0000', bcadd((string) StockValuationLayer::query()->where('article_variant_id', $fifoVariant->id)->sum('qty_remaining'), '0', 4));

        $usage = MaterialUsage::query()->sole();
        $this->actingAs($this->user)->delete(route('projects.timesheets.materials.destroy', [$this->project, $this->timesheet, $usage]))->assertSessionHasNoErrors();

        $this->assertSame('5.0000', bcadd((string) StockValuationLayer::query()->where('article_variant_id', $fifoVariant->id)->sum('qty_remaining'), '0', 4));
        $returned = StockValuationLayer::query()->where('article_variant_id', $fifoVariant->id)->latest('id')->firstOrFail();
        $this->assertSame('3.0000', $returned->unit_cost?->getAmount(), 'Die neue Schicht trägt den Stückkostenwert des Abgangs.');
    }

    public function test_offline_usage_books_once_and_shortage_is_a_conflict(): void {
        $send = fn (array $payload, string $uuid) => $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => [['client_uuid' => $uuid, 'type' => 'material.usage', 'payload' => $payload]],
        ]);
        $payload = ['project_id' => $this->project->sqid, 'date' => '2030-01-15', 'material_id' => $this->material->sqid, 'description' => 'Abzweigdose', 'quantity' => '3', 'unit' => 'Stk.'];
        $uuid = (string) Str::uuid();

        $send($payload, $uuid)->assertJsonPath('results.0.status', 'applied');
        $send($payload, $uuid)->assertJsonPath('results.0.status', 'duplicate');
        $usage = MaterialUsage::query()->sole();
        $this->assertCount(1, $this->movementsOf($usage));
        $this->assertSame('7.0000', $this->available());

        $send(array_merge($payload, ['quantity' => '20']), (string) Str::uuid())
            ->assertJsonPath('results.0.status', 'conflict')
            ->assertJsonPath('results.0.conflict.server.field', 'quantity');
        $this->assertSame(1, MaterialUsage::query()->count(), 'Der Konflikt legt keine Zeile an.');
        $this->assertSame('7.0000', $this->available());
    }
}
