<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldExtensionRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields;

use App\Modules\ModuleRegistry;
use App\Services\Fields\Contracts\FieldExtension;

/**
 * Fachtypen des Feldschema-Bausteins, aus den Modul-Manifesten
 * (`extensions()` → {@see FieldExtension}) gesammelt — kein eigener Katalog
 * im Kern (MVP-863).
 */
class FieldExtensionRegistry {
    /** @var array<string, FieldExtension>|null */
    private ?array $extensions = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    public function get(?string $key): ?FieldExtension {
        return $key === null ? null : ($this->all()[$key] ?? null);
    }

    /** @return array<string, FieldExtension> */
    public function all(): array {
        if ($this->extensions === null) {
            $this->extensions = [];
            foreach ($this->modules->extensions(FieldExtension::class) as $class) {
                $extension = app($class);
                if ($extension instanceof FieldExtension) {
                    $this->extensions[$extension->key()] = $extension;
                }
            }
        }

        return $this->extensions;
    }
}
