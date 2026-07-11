<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NormProfileRegistryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Isms;

use App\Services\Isms\NormProfileRegistry;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Normprofil-Registry (Feature 046, Inkrement A): alle ausgelieferten
 * Profile aus config/isms-norms/ laden (Anzahl Referenzen je Profil,
 * Metadaten-Schema, keine Duplikate), unbekannte Keys und
 * Schema-Verstöße werfen.
 */
class NormProfileRegistryTest extends TestCase {
    /** Erwartete Profile + Anzahl Referenzen (27 HLS; 27001 zusätzlich 93 Annex A; NIST CSF 6 Funktionen + 22 Kategorien). */
    private const EXPECTED_PROFILES = [
        'iso27001-2022' => 120,
        'iso27701-2025' => 27,
        'iso9001-2015' => 27,
        'iso22301-2019' => 27,
        'iso45001-2018' => 27,
        'iso37301-2021' => 27,
        'iso42001-2023' => 27,
        'nist-csf-2-0' => 28,
    ];

    private string $fixtureDir;

    protected function setUp(): void {
        parent::setUp();
        $this->fixtureDir = sys_get_temp_dir() . '/isms-norms-test-' . uniqid();
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

    public function test_all_shipped_profiles_load_with_expected_reference_counts(): void {
        $registry = new NormProfileRegistry;
        $all = $registry->all();

        $this->assertCount(8, $all);
        foreach (self::EXPECTED_PROFILES as $key => $expectedCount) {
            $this->assertArrayHasKey($key, $all, "Profil {$key} fehlt");
            $this->assertSame($expectedCount, $all[$key]['requirements_count'], "Profil {$key}: falsche Anzahl Referenzen");
            $this->assertSame($expectedCount, count($registry->requirements($key)));
        }
    }

    public function test_profiles_expose_metadata_and_unique_ref_numbers(): void {
        $registry = new NormProfileRegistry;

        $profile = $registry->get('iso27001-2022');
        $this->assertSame('ISO/IEC 27001', $profile['norm']);
        $this->assertSame('2022', $profile['edition']);
        $this->assertSame('ISO/IEC 27001:2022 — Informationssicherheit', $profile['label']);

        foreach ($registry->keys() as $key) {
            $requirements = $registry->requirements($key);
            $refs = array_column($requirements, 'ref_no');
            $this->assertSame(count($refs), count(array_unique($refs)), "Profil {$key}: doppelte ref_no");

            // HLS-Hauptkapitel: nur ISO-Managementsystemnormen folgen der
            // Harmonized Structure; NIST CSF hat eine eigene Struktur.
            if (str_starts_with($key, 'iso')) {
                foreach (['4.1', '5.3', '6.2', '7.5', '8.1', '9.3', '10.2'] as $hlsRef) {
                    $this->assertContains($hlsRef, $refs, "Profil {$key}: HLS-Referenz {$hlsRef} fehlt");
                }
            }
        }

        // 27001 enthält zusätzlich die Annex-A-Referenzen.
        $refs27001 = array_column($registry->requirements('iso27001-2022'), 'ref_no');
        $this->assertContains('A.5.1', $refs27001);
        $this->assertContains('A.8.34', $refs27001);

        // NIST CSF 2.0: die sechs Funktionen + Kategorien-Beispiele.
        $refsCsf = array_column($registry->requirements('nist-csf-2-0'), 'ref_no');
        foreach (['GV', 'ID', 'PR', 'DE', 'RS', 'RC'] as $function) {
            $this->assertContains($function, $refsCsf, "NIST CSF: Funktion {$function} fehlt");
        }
        $this->assertContains('GV.SC', $refsCsf);
        $this->assertContains('RC.CO', $refsCsf);
    }

    public function test_unknown_profile_key_throws(): void {
        $registry = new NormProfileRegistry;

        $this->expectException(InvalidArgumentException::class);
        $registry->get('iso99999-0000');
    }

    public function test_missing_metadata_field_throws(): void {
        $this->writeProfile('broken', "<?php return ['key' => 'broken', 'norm' => 'X', 'edition' => '2026', 'requirements' => [['ref_no' => '4', 'title' => 'T']]];");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("'label'");
        (new NormProfileRegistry($this->fixtureDir))->all();
    }

    public function test_key_must_match_filename(): void {
        $this->writeProfile('broken', "<?php return ['key' => 'other-key', 'norm' => 'X', 'edition' => '2026', 'label' => 'L', 'requirements' => [['ref_no' => '4', 'title' => 'T']]];");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dateinamen');
        (new NormProfileRegistry($this->fixtureDir))->all();
    }

    public function test_empty_requirements_throw(): void {
        $this->writeProfile('broken', "<?php return ['key' => 'broken', 'norm' => 'X', 'edition' => '2026', 'label' => 'L', 'requirements' => []];");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("'requirements'");
        (new NormProfileRegistry($this->fixtureDir))->all();
    }

    public function test_requirement_entry_without_title_throws(): void {
        $this->writeProfile('broken', "<?php return ['key' => 'broken', 'norm' => 'X', 'edition' => '2026', 'label' => 'L', 'requirements' => [['ref_no' => '4']]];");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requirements[0]');
        (new NormProfileRegistry($this->fixtureDir))->all();
    }

    public function test_duplicate_ref_no_throws(): void {
        $this->writeProfile('broken', "<?php return ['key' => 'broken', 'norm' => 'X', 'edition' => '2026', 'label' => 'L', 'requirements' => [['ref_no' => '4', 'title' => 'A'], ['ref_no' => '4', 'title' => 'B']]];");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("doppelte ref_no '4'");
        (new NormProfileRegistry($this->fixtureDir))->all();
    }

    public function test_valid_fixture_directory_loads(): void {
        $this->writeProfile('demo-2026', "<?php return ['key' => 'demo-2026', 'norm' => 'DEMO', 'edition' => '2026', 'label' => 'DEMO:2026 — Test', 'requirements' => [['ref_no' => '4', 'title' => 'Kontext']]];");

        $registry = new NormProfileRegistry($this->fixtureDir);

        $this->assertTrue($registry->has('demo-2026'));
        $this->assertSame(['demo-2026'], $registry->keys());
        $this->assertSame(1, $registry->get('demo-2026')['requirements_count']);
        $this->assertSame([['ref_no' => '4', 'title' => 'Kontext']], $registry->requirements('demo-2026'));
    }

    private function writeProfile(string $name, string $content): void {
        file_put_contents($this->fixtureDir . '/' . $name . '.php', $content);
    }
}
