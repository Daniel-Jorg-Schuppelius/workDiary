<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntitySpecRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\Import\ImportEntity;
use App\Modules\ModuleRegistry;
use InvalidArgumentException;

/**
 * Lookup-Registry für entitätsspezifische CSV-Import-Spezifikationen — die
 * Specs melden die Module über `Manifest::extensions()[EntitySpec::class]` (MVP-863).
 */
class EntitySpecRegistry {
    /** @var array<string, EntitySpec>|null Entitätswert → Spec, lazy aus den Manifesten */
    private ?array $specs = null;

    public function __construct(private readonly ModuleRegistry $modules) {}

    public function for(ImportEntity $entity): EntitySpec {
        return $this->specs()[$entity->value]
            ?? throw new InvalidArgumentException("Kein Import-Spec registriert für: {$entity->value}");
    }

    public function byValue(string $entityValue): EntitySpec {
        $entity = ImportEntity::tryFrom($entityValue)
            ?? throw new InvalidArgumentException("Unbekannte Import-Entität: {$entityValue}");

        return $this->for($entity);
    }

    /** @return array<string, EntitySpec> */
    private function specs(): array {
        if ($this->specs === null) {
            $this->specs = [];
            foreach ($this->modules->extensions(EntitySpec::class) as $class) {
                $spec = app($class);
                $this->specs[$spec->entity()->value] = $spec;
            }
        }

        return $this->specs;
    }
}
