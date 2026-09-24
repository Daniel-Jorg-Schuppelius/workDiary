<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainFolderRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use App\Modules\{ManifestChecker, ModuleRegistry};
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Domänenordner" (MVP-862): Kein Modell, Service,
 * Controller, Policy, Request und keine Factory liegt direkt im Schichtordner;
 * jeder Ordnername gehört zu einem Manifest oder steht mit Begründung in
 * {@see ManifestChecker::FOLDER_EXCEPTIONS}. Basisklassen sind die einzige
 * Ausnahme in der Wurzel.
 */
class DomainFolderRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, list<string>> Schichtordner → erlaubte Wurzeldateien (Basisklassen) */
    private const LAYERS = [
        'app/Models' => [],
        'app/Services' => [],
        'app/Http/Controllers' => ['Controller.php'],
        'app/Policies' => ['PermissionPolicy.php'],
        'app/Http/Requests' => ['BaseFormRequest.php'],
        'database/factories' => [],
    ];

    public function test_no_class_lies_directly_in_a_layer_folder(): void {
        $violations = [];
        foreach (self::LAYERS as $layer => $allowed) {
            foreach (glob($this->repoRoot() . '/' . $layer . '/*.php') ?: [] as $file) {
                if (! in_array(basename($file), $allowed, true)) {
                    $violations[] = $this->relativePath($file);
                }
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Klassen direkt im Schichtordner — in den Domänenordner des Manifests verschieben (MVP-862):\n" . implode("\n", $violations));
    }

    public function test_every_domain_folder_belongs_to_a_manifest(): void {
        $registry = $this->app->make(ModuleRegistry::class);
        $violations = [];
        foreach (array_keys(self::LAYERS) as $layer) {
            foreach (glob($this->repoRoot() . '/' . $layer . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $name = basename($dir);
                if (isset(ManifestChecker::FOLDER_EXCEPTIONS[$name]) || $registry->byFolder($name) !== null) {
                    continue;
                }
                $violations[] = $layer . '/' . $name;
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Domänenordner ohne Manifest — folders() des zuständigen Manifests ergänzen:\n" . implode("\n", $violations));
    }
}
