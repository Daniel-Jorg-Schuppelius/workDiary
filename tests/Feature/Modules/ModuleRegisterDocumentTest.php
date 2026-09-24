<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleRegisterDocumentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Modules\{ModuleRegisterDocument, ModuleRegistry};
use Tests\TestCase;

/** MVP-873: Modulregister aus den Manifesten — vollständig und deterministisch. */
final class ModuleRegisterDocumentTest extends TestCase {
    public function test_register_lists_every_module_and_is_deterministic(): void {
        $registry = app(ModuleRegistry::class);
        /** @var array<string, string> $helpRoutes */
        $helpRoutes = (array) config('help-topics.routes', []);
        $document = new ModuleRegisterDocument($registry);

        $first = $document->render($helpRoutes);

        $this->assertSame($first, $document->render($helpRoutes));
        foreach ($registry->all() as $manifest) {
            $this->assertStringContainsString("\n## " . $manifest->code() . "\n", $first);
        }
        $this->assertStringContainsString('`club.members`', $first, 'Hilfethema über das Routenmuster zugeordnet');
        $this->assertStringContainsString('`ContactColumnsRuleTest`', $first);
    }

    public function test_render_does_not_leak_the_locale(): void {
        app()->setLocale('en');
        (new ModuleRegisterDocument(app(ModuleRegistry::class)))->render([]);

        $this->assertSame('en', app()->getLocale());
    }
}
