<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MorphMapGenerateCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use App\Console\Commands\Architecture\MorphMapGenerateCommand;
use App\Support\MorphMap;
use Tests\TestCase;

/**
 * `morph-map:generate` (MVP-860, {@see MorphMapGenerateCommand}): der Lauf mit
 * `--check` bestätigt die eingecheckte Datei, `--init` ist nach dem ersten
 * Einfrieren gesperrt, und ein Prüflauf verändert die Datei nie.
 */
class MorphMapGenerateCommandTest extends TestCase {
    public function test_check_confirms_the_committed_map_without_writing(): void {
        $path = config_path('morph-map.php');
        $before = (string) file_get_contents($path);

        $this->artisan('morph-map:generate', ['--check' => true])
            ->expectsOutputToContain('aktuell')
            ->assertExitCode(0);

        $this->assertSame($before, (string) file_get_contents($path));
        // Legacy-Namen gibt es nur für beim Einfrieren vorhandene Klassen;
        // neue Modelle tragen nur einen Alias (stableKey fällt darauf zurück).
        $this->assertSame([], array_values(array_diff(MorphMap::legacy(), MorphMap::aliases())));
    }

    public function test_init_is_refused_once_legacy_names_are_frozen(): void {
        $this->artisan('morph-map:generate', ['--init' => true])
            ->expectsOutputToContain('bereits eingefroren')
            ->assertExitCode(1);
    }
}
