<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CacheValueRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Services\Licensing\LicenseStatus;
use Illuminate\Support\Facades\Cache;
use LogicException;
use stdClass;
use Tests\TestCase;

/**
 * Der Cache hält nur Arrays, Skalare und Enums (CLAUDE.md „Cache“): kein Store
 * liest Objekte zurück (`cache.serializable_classes` = false), produktiv wurde
 * daraus am 20.10.2026 eine __PHP_Incomplete_Class im Zuordnungsdialog. Die
 * Testsuite bricht jeden Schreibzugriff mit Objekt ab (Tests\TestCase).
 */
final class CacheValueRuleTest extends TestCase {
    public function test_an_object_in_a_cache_value_fails_the_test(): void {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/rule:object.*stdClass/');

        Cache::put('rule:object', ['nested' => [new stdClass]], 10);
    }

    public function test_arrays_scalars_and_enums_pass_and_come_back_unchanged(): void {
        $value = ['n' => 1, 'f' => 1.5, 's' => 'x', 'b' => true, 'none' => null, 'enum' => LicenseStatus::Valid, 'list' => [['k' => 'v']]];

        Cache::put('rule:plain', $value, 10);

        $this->assertSame($value, Cache::get('rule:plain'));
    }

    public function test_the_array_store_reads_objects_back_as_incomplete_like_file_and_database_store(): void {
        // Direkt am Store, am Schutz vorbei: so sieht ein Treffer aus dem Altbestand aus.
        Cache::getStore()->put('rule:store-object', new stdClass, 10);

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, Cache::get('rule:store-object'));
    }
}
