<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BladeCompilationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Gate: Jedes Blade-Template muss zu syntaktisch gültigem PHP kompilieren.
 * `view:cache` prüft das NICHT — Fehler wie rohe ASCII-Quotes in
 * x-Komponenten-Attributen oder die Paarung von Inline-@php mit einem
 * späteren Block-Ende (lazy Raw-Block-Regex) crashen sonst erst beim
 * Rendern in Produktion (Vorfall 2026-07-21: drei Views betroffen).
 */
class BladeCompilationTest extends TestCase {
    public function test_all_blade_views_compile_to_valid_php(): void {
        $compiler = $this->app['blade.compiler'];
        $broken = [];

        foreach ([resource_path('views'), app_path('Plugins')] as $root) {
            foreach (File::allFiles($root) as $file) {
                if (! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $compiled = $compiler->compileString(File::get($file->getPathname()));

                try {
                    token_get_all($compiled, TOKEN_PARSE);
                } catch (\ParseError $e) {
                    $broken[] = $file->getRelativePathname() . ' — ' . $e->getMessage();
                }
            }
        }

        $this->assertSame([], $broken, "Blade-Kompilate mit PHP-Syntaxfehlern:\n" . implode("\n", $broken));
    }
}
