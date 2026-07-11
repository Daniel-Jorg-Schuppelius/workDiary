<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrosswalkRegistryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Isms;

use App\Services\Isms\{CrosswalkRegistry, NormProfileRegistry};
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Crosswalk-Registry (Nachtrag NIST): den ausgelieferten Crosswalk
 * NIST CSF 2.0 → ISO/IEC 27001:2022 laden, das Schema prüfen und
 * KREUZVALIDIEREN, dass alle Quell-/Zielreferenzen tatsächlich in den
 * echten Normprofilen (config/isms-norms/) existieren — so fallen Tippfehler
 * in der Zuordnung sofort auf. Unbekannte Keys und Schema-Verstöße werfen.
 */
class CrosswalkRegistryTest extends TestCase {
    private const CSF_KEY = 'nist-csf-2-0--iso27001-2022';

    private string $fixtureDir;

    protected function setUp(): void {
        parent::setUp();
        $this->fixtureDir = sys_get_temp_dir() . '/isms-crosswalks-test-' . uniqid();
        mkdir($this->fixtureDir);
    }

    protected function tearDown(): void {
        foreach (glob($this->fixtureDir . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->fixtureDir)) {
            rmdir($this->fixtureDir);
        }
        parent::tearDown();
    }

    public function test_shipped_crosswalk_loads_with_expected_metadata(): void {
        $registry = new CrosswalkRegistry;

        $this->assertTrue($registry->has(self::CSF_KEY));

        $meta = $registry->get(self::CSF_KEY);
        $this->assertSame('NIST CSF', $meta['source_norm']);
        $this->assertSame('2.0', $meta['source_edition']);
        $this->assertSame('ISO/IEC 27001', $meta['target_norm']);
        $this->assertSame('2022', $meta['target_edition']);
        $this->assertSame(22, $meta['mappings_count'], 'CSF 2.0 hat 22 Kategorien');
    }

    public function test_mappings_have_unique_source_refs_and_nonempty_targets(): void {
        $registry = new CrosswalkRegistry;
        $mappings = $registry->mappings(self::CSF_KEY);

        $sourceRefs = array_column($mappings, 'source_ref');
        $this->assertSame(count($sourceRefs), count(array_unique($sourceRefs)), 'Doppelte source_ref');

        foreach ($mappings as $mapping) {
            $this->assertNotEmpty($mapping['target_refs'], "Leere target_refs bei {$mapping['source_ref']}");
        }
    }

    public function test_all_references_exist_in_the_real_norm_profiles(): void {
        $profiles = new NormProfileRegistry;
        $csfRefs = array_column($profiles->requirements('nist-csf-2-0'), 'ref_no');
        $isoRefs = array_column($profiles->requirements('iso27001-2022'), 'ref_no');

        $registry = new CrosswalkRegistry;
        foreach ($registry->mappings(self::CSF_KEY) as $mapping) {
            $this->assertContains(
                $mapping['source_ref'],
                $csfRefs,
                "source_ref {$mapping['source_ref']} ist keine gültige CSF-Referenz",
            );
            $this->assertStringContainsString('.', $mapping['source_ref'], 'Zuordnung auf Kategorie-Ebene (mit Punkt)');

            foreach ($mapping['target_refs'] as $target) {
                $this->assertContains(
                    $target,
                    $isoRefs,
                    "target_ref {$target} ist keine gültige ISO/IEC-27001:2022-Referenz",
                );
            }
        }
    }

    public function test_find_key_resolves_source_and_target(): void {
        $registry = new CrosswalkRegistry;

        $this->assertSame(self::CSF_KEY, $registry->findKey('NIST CSF', '2.0', 'ISO/IEC 27001', '2022'));
        $this->assertNull($registry->findKey('NIST CSF', '2.0', 'ISO 9001', '2015'));
    }

    public function test_unknown_key_throws(): void {
        $registry = new CrosswalkRegistry;

        $this->expectException(InvalidArgumentException::class);
        $registry->get('does-not-exist');
    }

    public function test_duplicate_source_ref_throws(): void {
        $this->writeCrosswalk('broken-dup', <<<'PHP'
            <?php return [
                'key' => 'broken-dup',
                'source_norm' => 'X', 'source_edition' => '1',
                'target_norm' => 'Y', 'target_edition' => '2',
                'label' => 'Broken',
                'mappings' => [
                    ['source_ref' => 'A', 'target_refs' => ['1']],
                    ['source_ref' => 'A', 'target_refs' => ['2']],
                ],
            ];
            PHP);

        $registry = new CrosswalkRegistry($this->fixtureDir);

        $this->expectException(RuntimeException::class);
        $registry->all();
    }

    public function test_empty_target_refs_throws(): void {
        $this->writeCrosswalk('broken-empty', <<<'PHP'
            <?php return [
                'key' => 'broken-empty',
                'source_norm' => 'X', 'source_edition' => '1',
                'target_norm' => 'Y', 'target_edition' => '2',
                'label' => 'Broken',
                'mappings' => [
                    ['source_ref' => 'A', 'target_refs' => []],
                ],
            ];
            PHP);

        $registry = new CrosswalkRegistry($this->fixtureDir);

        $this->expectException(RuntimeException::class);
        $registry->all();
    }

    private function writeCrosswalk(string $key, string $php): void {
        file_put_contents($this->fixtureDir . '/' . $key . '.php', $php);
    }
}
