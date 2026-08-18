<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Enums\Gaeb\{BoqItemStatus, GaebPhase};
use App\Models\{BillOfQuantity, BoqCatalog, BoqCatalogAssignment, BoqItem, User};
use App\Models\Catalog\{CatalogAssignmentRule, CatalogRegistry};
use App\Services\Gaeb\{CatalogAssignmentService, CatalogSuggestionService};
use Database\Seeders\CatalogRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vorschlagsregeln (Feature 109, MVP-640).
 *
 * Kern der Prüfung: **Der Lauf füllt nur Lücken.** Was importiert oder von Hand
 * gesetzt wurde, bleibt — sonst verlöre man beim nächsten Lauf die eigene
 * Arbeit.
 */
final class CatalogRuleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private BillOfQuantity $bill;
    private BoqCatalog $catalog;
    private CatalogRegistry $registry;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        (new CatalogRegistrySeeder)->run();

        $this->registry = CatalogRegistry::query()->where('key', 'din276-2018')->firstOrFail();

        $this->bill = BillOfQuantity::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Neubau Kita',
            'phase' => GaebPhase::Award,
            'status' => BoqItemStatus::Imported,
            'created_by' => $this->admin->id,
        ]);
        $this->catalog = BoqCatalog::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'catalog_key' => 'idDIN276-18',
            'type' => 'cost group DIN 276 2018-12',
            'name' => 'DIN 276-18',
        ]);
    }

    private function item(string $reference, string $shortText): BoqItem {
        return BoqItem::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'reference_no' => $reference,
            'short_text' => $shortText,
            'quantity' => '10.000',
            'unit' => 'm3',
            'unit_price' => '10.00',
            'status' => BoqItemStatus::Imported,
        ]);
    }

    private function rule(array $attributes = []): CatalogAssignmentRule {
        return CatalogAssignmentRule::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'match_type' => CatalogAssignmentRule::MATCH_KEYWORD,
            'match_value' => 'Erdarbeiten',
            'catalog_registry_id' => $this->registry->id,
            'code' => '310',
            'priority' => 100,
            'active' => true,
        ], $attributes));
    }

    public function test_keyword_rule_fills_the_gap(): void {
        $this->rule();
        $item = $this->item('01.0010', 'Erdarbeiten Baugrube ausheben');

        $result = app(CatalogSuggestionService::class)->apply($this->bill);

        $this->assertSame(1, $result['applied']);
        $assignment = $item->catalogAssignments()->first();
        $this->assertSame('310', $assignment?->code);
        // Ein Vorschlag bleibt als solcher erkennbar.
        $this->assertSame('rule', $assignment?->source);
    }

    /** Was schon zugeordnet ist, bleibt — auch wenn eine Regel greifen würde. */
    public function test_existing_assignments_are_untouched(): void {
        $this->rule();
        $item = $this->item('01.0010', 'Erdarbeiten Baugrube ausheben');
        app(CatalogAssignmentService::class)->assign($item, $this->catalog, '320', CatalogAssignmentService::SOURCE_MANUAL);

        $result = app(CatalogSuggestionService::class)->apply($this->bill);

        $this->assertSame(0, $result['applied']);
        $this->assertSame('320', $item->catalogAssignments()->first()?->code);
        $this->assertSame('manual', $item->catalogAssignments()->first()?->source);
    }

    /** Die erste greifende Regel gewinnt — zwei Vorschläge wären keine Hilfe. */
    public function test_first_matching_rule_wins(): void {
        $this->rule(['match_value' => 'Erd', 'code' => '310', 'priority' => 10]);
        $this->rule(['match_value' => 'Baugrube', 'code' => '320', 'priority' => 20]);
        $item = $this->item('01.0010', 'Erdarbeiten Baugrube ausheben');

        app(CatalogSuggestionService::class)->apply($this->bill);

        $this->assertSame('310', $item->catalogAssignments()->first()?->code);
    }

    /** Der Leistungsbereich wird auf Präfix verglichen: „01" trifft „013". */
    public function test_work_category_rule_matches_by_prefix(): void {
        BoqCatalog::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'catalog_key' => 'LB',
            'type' => 'work category',
            'name' => 'Leistungsbereiche',
        ]);
        $item = $this->item('01.0010', 'Betonwand herstellen');
        BoqCatalogAssignment::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'assignable_type' => $item->getMorphClass(),
            'assignable_id' => $item->id,
            'catalog_key' => 'LB',
            'code' => '013',
            'source' => 'import',
        ]);

        $this->rule([
            'match_type' => CatalogAssignmentRule::MATCH_WORK_CATEGORY,
            'match_value' => '01',
            'code' => '330',
        ]);

        app(CatalogSuggestionService::class)->apply($this->bill);

        $codes = $item->catalogAssignments()->where('catalog_key', 'idDIN276-18')->pluck('code')->all();
        $this->assertSame(['330'], $codes);
    }

    /** Inaktive Regeln greifen nicht. */
    public function test_inactive_rules_are_ignored(): void {
        $this->rule(['active' => false]);
        $item = $this->item('01.0010', 'Erdarbeiten Baugrube ausheben');

        $result = app(CatalogSuggestionService::class)->apply($this->bill);

        $this->assertSame(0, $result['applied']);
        $this->assertSame(0, $item->catalogAssignments()->count());
    }

    // ── Verwaltung ───────────────────────────────────────────────────────

    public function test_rule_can_be_created_and_deleted(): void {
        $this->actingAs($this->admin)->post(route('catalog-rules.store'), [
            'match_type' => 'keyword',
            'match_value' => 'Estrich',
            'registry' => $this->registry->sqid,
            'code' => '350',
            'priority' => 50,
            'active' => '1',
        ])->assertRedirect(route('catalog-rules.index'));

        $rule = CatalogAssignmentRule::query()->firstOrFail();
        $this->assertSame('350', $rule->code);
        $this->assertSame(50, $rule->priority);

        $this->actingAs($this->admin)->delete(route('catalog-rules.destroy', $rule))->assertRedirect();
        $this->assertSame(0, CatalogAssignmentRule::query()->count());
    }

    /** Eine Regel auf eine Nummer, die es nicht gibt, schlüge nie an. */
    public function test_unknown_code_is_rejected_when_creating_a_rule(): void {
        $this->actingAs($this->admin)->post(route('catalog-rules.store'), [
            'match_type' => 'keyword',
            'match_value' => 'Estrich',
            'registry' => $this->registry->sqid,
            'code' => '999',
        ])->assertStatus(422);

        $this->assertSame(0, CatalogAssignmentRule::query()->count());
    }

    // ── Ausgabenwechsel (MVP-641) ────────────────────────────────────────

    /**
     * Der Wechsel schreibt nur, was eindeutig ist — was ohne Entsprechung
     * bleibt, bleibt stehen. Eine geratene Nummer wäre schlimmer als die alte.
     */
    public function test_edition_change_only_touches_unambiguous_codes(): void {
        $old = CatalogRegistry::query()->where('key', 'din276-2008')->firstOrFail();
        $catalog = BoqCatalog::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'catalog_key' => 'KG08',
            'type' => 'cost group DIN 276-1 2008-12',
            'name' => 'DIN 276-1',
        ]);

        // 370 hat 2018 eine Entsprechung (380), 610 nicht.
        foreach ([['01.0100', '370'], ['01.0200', '610']] as [$reference, $code]) {
            $item = $this->item($reference, 'Position ' . $reference);
            BoqCatalogAssignment::query()->create([
                'organization_id' => $this->organization->id,
                'bill_of_quantity_id' => $this->bill->id,
                'assignable_type' => $item->getMorphClass(),
                'assignable_id' => $item->id,
                'catalog_key' => 'KG08',
                'code' => $code,
                'source' => 'import',
            ]);
        }

        $migration = app(\App\Services\Gaeb\CatalogEditionMigrationService::class);
        $preview = $migration->preview($this->bill, $catalog, $old, $this->registry);
        $this->assertSame(1, $preview['mapped']);
        $this->assertSame(1, $preview['unmapped']);

        $result = $migration->apply($this->bill, $catalog, $old, $this->registry);
        $this->assertSame(1, $result['changed']);
        $this->assertSame(1, $result['unmapped']);

        $codes = BoqCatalogAssignment::query()->where('catalog_key', 'KG08')->pluck('code')->sort()->values()->all();
        $this->assertSame(['380', '610'], $codes);
        // Der Katalogkopf spricht danach die neue Ausgabe.
        $this->assertSame('cost group DIN 276 2018-12', $catalog->refresh()->type);
    }

    public function test_edition_page_shows_the_preview(): void {
        // Das LV führt hier nur den alten Katalog — sonst fände die Seite den
        // 2018er aus setUp() als Ausgangsausgabe.
        $this->catalog->delete();
        $catalog = BoqCatalog::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'catalog_key' => 'KG08',
            'type' => 'cost group DIN 276-1 2008-12',
            'name' => 'DIN 276-1',
        ]);
        $item = $this->item('01.0100', 'Einbauten');
        BoqCatalogAssignment::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'assignable_type' => $item->getMorphClass(),
            'assignable_id' => $item->id,
            'catalog_key' => 'KG08',
            'code' => '370',
            'source' => 'import',
        ]);
        unset($catalog);

        $response = $this->actingAs($this->admin)->get(route('bill-of-quantities.catalog-edition', [
            $this->bill, 'to' => $this->registry->sqid,
        ]));

        $response->assertOk()->assertSee('380');
        $this->assertSame(1, $response->viewData('preview')['mapped']);
    }

    public function test_rule_list_renders(): void {
        $this->rule();

        $this->actingAs($this->admin)
            ->get(route('catalog-rules.index'))
            ->assertOk()
            ->assertSee('Erdarbeiten');
    }

    /** Der Regellauf ist auch aus der Zuordnungsseite auslösbar. */
    public function test_rules_can_be_applied_from_the_ui(): void {
        $this->rule();
        $item = $this->item('01.0010', 'Erdarbeiten Baugrube ausheben');

        $this->actingAs($this->admin)
            ->post(route('bill-of-quantities.catalog-rules.apply', $this->bill))
            ->assertRedirect();

        $this->assertSame('310', $item->catalogAssignments()->first()?->code);
    }
}
