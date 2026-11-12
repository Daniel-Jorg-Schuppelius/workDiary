<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationManagerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\Classification\ClassificationDomain;
use App\Exceptions\ClassificationValidationException;
use App\Models\Audit\AuditLog;
use App\Models\Classification\Classification;
use App\Models\Platform\{Organization, User};
use App\Services\Classification\{ClassificationManager, ClassificationResolver};
use Database\Seeders\ClassificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class ClassificationManagerTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private ClassificationManager $manager;

    protected function setUp(): void {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->seed(ClassificationSeeder::class);

        $this->actor = User::factory()->geschaeftsfuehrung()->create([
            'organization_id' => $this->org->id,
        ]);
        $this->actingAs($this->actor);

        $this->manager = new ClassificationManager(new ClassificationResolver);
    }

    public function test_override_creates_or_updates_org_entry_for_platform_default(): void {
        $platform = Classification::query()
            ->whereNull('organization_id')
            ->where('domain', ClassificationDomain::Priority->value)
            ->where('code', 'high')
            ->firstOrFail();

        $created = $this->manager->overridePlatformDefault($this->org->id, $platform, [
            'label' => 'Hoch (Org)',
            'sort_order' => 70,
        ]);
        $this->assertSame($this->org->id, $created->organization_id);
        $this->assertSame('high', $created->code);
        $this->assertSame('Hoch (Org)', $created->label);

        $updated = $this->manager->overridePlatformDefault($this->org->id, $platform, [
            'label' => 'Hoch (Org v2)',
            'sort_order' => 80,
        ]);
        $this->assertSame($created->id, $updated->id);
        $this->assertSame('Hoch (Org v2)', $updated->label);
        $this->assertSame(80, $updated->sort_order);
    }

    public function test_deactivate_platform_default_creates_org_local_inactive_override(): void {
        $platform = Classification::query()
            ->whereNull('organization_id')
            ->where('domain', ClassificationDomain::Result->value)
            ->where('code', 'escalated')
            ->firstOrFail();

        $row = $this->manager->deactivatePlatformDefaultForOrganization($this->org->id, $platform);

        $this->assertFalse($row->active);
        $this->assertNotNull($row->deprecated_at);
        $this->assertSame($this->org->id, $row->organization_id);
        $this->assertSame('escalated', $row->code);
    }

    public function test_reorder_updates_sorting_and_writes_sort_changed_audit_event(): void {
        $first = Classification::factory()
            ->forOrganization($this->org->id)
            ->domain(ClassificationDomain::Activity)
            ->create(['code' => 'custom_a', 'label' => 'A', 'sort_order' => 500]);
        $second = Classification::factory()
            ->forOrganization($this->org->id)
            ->domain(ClassificationDomain::Activity)
            ->create(['code' => 'custom_b', 'label' => 'B', 'sort_order' => 510]);

        $this->manager->reorder($this->org->id, ClassificationDomain::Activity, [$second->id, $first->id]);

        $refreshedSecond = $second->fresh();
        $refreshedFirst = $first->fresh();

        $this->assertInstanceOf(Classification::class, $refreshedSecond);
        $this->assertInstanceOf(Classification::class, $refreshedFirst);
        $this->assertSame(10, $refreshedSecond->sort_order);
        $this->assertSame(20, $refreshedFirst->sort_order);

        $sortChanged = AuditLog::query()->where('event', 'classification.sortChanged')->count();
        $this->assertSame(2, $sortChanged);
    }

    public function test_import_csv_is_idempotent_and_writes_imported_audit_events(): void {
        $payload = [
            [
                'domain' => ClassificationDomain::EntryType->value,
                'code' => 'inspection',
                'label' => 'Inspektion',
                'sort_order' => 130,
            ],
            [
                'domain' => ClassificationDomain::EntryType->value,
                'code' => 'inspection',
                'label' => 'Inspektion v2',
                'sort_order' => 140,
            ],
        ];

        $firstRun = $this->manager->importCsv($this->org->id, [$payload[0]]);
        $secondRun = $this->manager->importCsv($this->org->id, [$payload[1]]);

        $this->assertSame(['created' => 1, 'updated' => 0], $firstRun);
        $this->assertSame(['created' => 0, 'updated' => 1], $secondRun);

        $row = Classification::query()
            ->where('organization_id', $this->org->id)
            ->where('domain', ClassificationDomain::EntryType->value)
            ->where('code', 'inspection')
            ->firstOrFail();

        $this->assertSame('Inspektion v2', $row->label);
        $this->assertSame(140, $row->sort_order);

        $imported = AuditLog::query()->where('event', 'classification.imported')->count();
        $this->assertSame(2, $imported);
    }

    public function test_delete_rejects_when_referenced(): void {
        $classification = Classification::factory()
            ->forOrganization($this->org->id)
            ->domain(ClassificationDomain::Activity)
            ->create(['code' => 'referenced_code']);

        // Zuordnung über das Pivot — ohne Anmeldung geschützt, weil der Fremdschlüssel im Schema steht.
        DB::table('classifiables')->insert([
            'classification_id' => $classification->id,
            'classifiable_type' => 'customers',
            'classifiable_id' => 1,
        ]);

        try {
            $this->manager->delete($classification);
            $this->fail('Expected ClassificationValidationException was not thrown.');
        } catch (ClassificationValidationException $e) {
            $this->assertSame(ClassificationValidationException::CODE_REFERENCED, $e->errorCode);
            $this->assertSame(['classifiables' => 1], $e->usage);
        }
        $this->assertDatabaseHas('classifications', ['id' => $classification->id]);
    }

    /** MVP-1588: Jede Spalte, die auf eine Klassifikation zeigt, trägt den Fremdschlüssel, aus dem der Löschschutz liest. */
    public function test_every_classification_column_is_covered_by_the_deletion_guard(): void {
        $unprotected = [];
        $protected = 0;
        foreach (Schema::getTableListing(Schema::getCurrentSchemaListing(), false) as $table) {
            $columns = array_values(array_filter(Schema::getColumnListing($table), static fn (string $c): bool => str_ends_with($c, 'classification_id')));
            if ($columns === []) {
                continue;
            }
            $constrained = [];
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if ($foreignKey['foreign_table'] === 'classifications') {
                    array_push($constrained, ...$foreignKey['columns']);
                }
            }
            foreach ($columns as $column) {
                in_array($column, $constrained, true) ? $protected++ : $unprotected[] = "{$table}.{$column}";
            }
        }

        $this->assertGreaterThanOrEqual(8, $protected);
        $this->assertSame([], $unprotected, 'Spalte auf classifications ohne Fremdschlüssel — der Löschschutz sieht sie nicht: ' . implode(', ', $unprotected));
    }

    public function test_reference_counts_name_every_place_a_value_is_used(): void {
        $classification = Classification::factory()
            ->forOrganization($this->org->id)
            ->domain(ClassificationDomain::AssetCategory)
            ->create(['code' => 'counted_code']);
        $this->assertSame([], $this->manager->referenceCounts($classification));

        DB::table('classifiables')->insert([
            ['classification_id' => $classification->id, 'classifiable_type' => 'customers', 'classifiable_id' => 1],
            ['classification_id' => $classification->id, 'classifiable_type' => 'customers', 'classifiable_id' => 2],
        ]);
        \App\Models\Asset\Asset::factory()->create(['organization_id' => $this->org->id, 'category_code' => 'counted_code']);

        $this->assertSame(['classifiables' => 2, 'assets' => 1], $this->manager->referenceCounts($classification));
    }

    public function test_delete_removes_org_local_classification_when_unreferenced(): void {
        $classification = Classification::factory()
            ->forOrganization($this->org->id)
            ->domain(ClassificationDomain::Activity)
            ->create(['code' => 'unreferenced_code']);

        $this->manager->delete($classification);

        $this->assertDatabaseMissing('classifications', [
            'id' => $classification->id,
        ]);
    }

    public function test_delete_rejects_platform_default(): void {
        $classification = Classification::factory()
            ->platformDefault()
            ->domain(ClassificationDomain::Activity)
            ->create(['code' => 'platform_code']);

        try {
            $this->manager->delete($classification);
            $this->fail('Expected ClassificationValidationException was not thrown.');
        } catch (ClassificationValidationException $e) {
            $this->assertSame(ClassificationValidationException::CODE_PLATFORM_PROTECTED, $e->errorCode);
        }
    }

    public function test_update_rejects_platform_default(): void {
        $classification = Classification::factory()
            ->platformDefault()
            ->domain(ClassificationDomain::Result)
            ->create(['code' => 'platform_update']);

        try {
            $this->manager->update($classification, ['label' => 'Neu']);
            $this->fail('Expected ClassificationValidationException was not thrown.');
        } catch (ClassificationValidationException $e) {
            $this->assertSame(ClassificationValidationException::CODE_PLATFORM_PROTECTED, $e->errorCode);
        }
    }
}
