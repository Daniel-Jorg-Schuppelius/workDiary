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
use App\Models\{AuditLog, Classification, Organization, User};
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

        Schema::create('classification_refs', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('classification_id');
        });

        DB::table('classification_refs')->insert(['classification_id' => $classification->id]);

        $this->manager->registerReference(ClassificationDomain::Activity, 'classification_refs', 'classification_id');

        try {
            $this->manager->delete($classification);
            $this->fail('Expected ClassificationValidationException was not thrown.');
        } catch (ClassificationValidationException $e) {
            $this->assertSame(ClassificationValidationException::CODE_REFERENCED, $e->errorCode);
        } finally {
            Schema::dropIfExists('classification_refs');
        }
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
