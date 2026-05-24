<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\Classification\ClassificationDomain;
use App\Models\{Classification, Organization};
use App\Services\Classification\ClassificationResolver;
use Database\Seeders\ClassificationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ClassificationResolverTest extends TestCase {
    use RefreshDatabase;

    private ClassificationResolver $resolver;

    private Organization $org;

    protected function setUp(): void {
        parent::setUp();
        $this->resolver = new ClassificationResolver;
        $this->org = Organization::factory()->create();
        $this->seed(ClassificationSeeder::class);
        Cache::flush();
    }

    public function test_seeder_creates_platform_defaults_for_all_domains(): void {
        foreach (ClassificationDomain::cases() as $domain) {
            $count = Classification::query()
                ->whereNull('organization_id')
                ->where('domain', $domain->value)
                ->count();
            $this->assertGreaterThan(0, $count, "Domain {$domain->value} seed fehlt");
        }
    }

    public function test_list_returns_platform_defaults_for_org_without_overrides(): void {
        $platformCount = Classification::query()
            ->whereNull('organization_id')
            ->where('domain', ClassificationDomain::Priority->value)
            ->count();

        $rows = $this->resolver->list($this->org->id, ClassificationDomain::Priority);

        $this->assertCount($platformCount, $rows);
        $this->assertTrue($rows->every(fn(Classification $c): bool => $c->isPlatformDefault()));
    }

    public function test_org_override_replaces_platform_default_with_same_code(): void {
        $override = Classification::factory()
            ->forOrganization($this->org->id)
            ->domain(ClassificationDomain::Priority)
            ->create([
                'code' => 'high',
                'label' => 'Org-Hoch (überschrieben)',
                'sort_order' => 50,
            ]);

        $rows = $this->resolver->list($this->org->id, ClassificationDomain::Priority);

        $high = $rows->firstWhere('code', 'high');
        $this->assertNotNull($high);
        $this->assertSame($override->id, $high->id);
        $this->assertSame($this->org->id, $high->organization_id);

        // Platform-Defaults für andere Codes bleiben verfügbar.
        $this->assertNotNull($rows->firstWhere('code', 'low'));
        $this->assertNotNull($rows->firstWhere('code', 'critical'));
    }

    public function test_soft_disabled_org_classification_is_excluded(): void {
        // Org legt eigenen Code an und deaktiviert ihn → Auswahl blendet ihn aus,
        // bestehende historische Referenzen würden den Code aber weiter behalten.
        Classification::factory()
            ->forOrganization($this->org->id)
            ->domain(ClassificationDomain::Activity)
            ->inactive()
            ->create(['code' => 'inspect', 'label' => 'Inspektion (deaktiviert)']);

        $rows = $this->resolver->list($this->org->id, ClassificationDomain::Activity);

        $this->assertNull($rows->firstWhere('code', 'inspect'));
        // Plattform-Defaults bleiben sichtbar.
        $this->assertNotNull($rows->firstWhere('code', 'analysis'));
    }

    public function test_unique_constraint_blocks_duplicate_code_within_org_and_domain(): void {
        Classification::factory()
            ->forOrganization($this->org->id)
            ->domain(ClassificationDomain::Activity)
            ->create(['code' => 'analysis']);

        $this->expectException(QueryException::class);

        Classification::factory()
            ->forOrganization($this->org->id)
            ->domain(ClassificationDomain::Activity)
            ->create(['code' => 'analysis']);
    }

    public function test_list_results_are_cached_until_forget_is_called(): void {
        $first = $this->resolver->list($this->org->id, ClassificationDomain::Result);
        $initialCount = $first->count();

        // Direkte DB-Modifikation, ohne Cache zu invalidieren.
        Classification::query()
            ->whereNull('organization_id')
            ->where('domain', ClassificationDomain::Result->value)
            ->where('code', 'workaround')
            ->update(['active' => false]);

        $cached = $this->resolver->list($this->org->id, ClassificationDomain::Result);
        $this->assertCount($initialCount, $cached, 'Cache muss bestehende ID-Liste liefern');

        $this->resolver->forget($this->org->id, ClassificationDomain::Result);

        $afterForget = $this->resolver->list($this->org->id, ClassificationDomain::Result);
        $this->assertCount($initialCount - 1, $afterForget);
    }

    public function test_resolve_code_returns_specific_classification(): void {
        $entry = $this->resolver->resolveCode($this->org->id, ClassificationDomain::Priority, 'critical');

        $this->assertNotNull($entry);
        $this->assertSame('critical', $entry->code);
        $this->assertTrue($entry->isPlatformDefault());
    }
}
