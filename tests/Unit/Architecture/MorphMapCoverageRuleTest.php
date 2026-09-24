<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MorphMapCoverageRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Support\Architecture\ModelScanner;
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Architektur-Gate „Morph-Map" (MVP-860): jedes Modell hat einen Alias, die
 * generierte Datei ist aktuell, jeder Legacy-Name zeigt auf eine existierende
 * Klasse, und beim Schreiben gewinnt der Alias vor dem Legacy-Namen.
 */
class MorphMapCoverageRuleTest extends TestCase {
    public function test_every_model_has_an_alias(): void {
        $aliases = MorphMap::aliases();
        $this->assertNotEmpty($aliases, 'config/morph-map.php fehlt — `php artisan morph-map:generate --init`.');

        $missing = array_values(array_filter(ModelScanner::classes(), static fn (string $class): bool => ! in_array($class, $aliases, true)));
        $this->assertSame([], $missing, "Modelle ohne Morph-Alias (morph-map:generate ausführen):\n" . implode("\n", $missing));
    }

    public function test_generated_file_is_current(): void {
        $exit = Artisan::call('morph-map:generate', ['--check' => true]);
        $this->assertSame(0, $exit, Artisan::output());
    }

    public function test_every_legacy_name_resolves_to_an_existing_class(): void {
        $dangling = array_filter(MorphMap::legacy(), static fn (string $class): bool => ! class_exists($class));
        $this->assertSame([], $dangling, 'Legacy-Namen ohne Klasse — nach einem Klassenumzug die Werte in config/morph-map.php nachführen.');
    }

    public function test_alias_wins_over_legacy_name_when_writing(): void {
        $this->assertSame('customers', Relation::getMorphAlias(Customer::class));
        $this->assertSame('customers', (new Customer())->getMorphClass());
        $this->assertSame('diary_entries', MorphMap::alias(DiaryEntry::class));
        $this->assertTrue(Relation::requiresMorphMap());
    }

    public function test_stable_key_is_the_historic_class_name_for_existing_models(): void {
        $this->assertSame('App\\Models\\Customer', MorphMap::stableKey(Customer::class));
        $this->assertSame(Customer::class, MorphMap::classFor('App\\Models\\Customer'));
        $this->assertSame(Customer::class, MorphMap::classFor('customers'));
        $this->assertTrue(MorphMap::is('App\\Models\\Customer', Customer::class));
        $this->assertTrue(MorphMap::is('customers', Customer::class));
        $this->assertFalse(MorphMap::is('customers', DiaryEntry::class));
        $this->assertSame('Customer', MorphMap::basename('customers'));
        // Nicht-Modelle (Report-Controller im Audit-Log) bleiben unverändert.
        $this->assertSame(self::class, MorphMap::stableKey(self::class));
        $this->assertNull(MorphMap::classFor('gibt-es-nicht'));
    }
}
