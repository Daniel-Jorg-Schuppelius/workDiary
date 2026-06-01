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
use App\Plugins\RemoteSupport\Import\RemoteSessionSpec;
use App\Services\Import\Specs\{CustomerSpec, MaterialSpec, ProjectSpec, ScheduledShiftSpec, UserSpec};
use InvalidArgumentException;

/**
 * Lookup-Registry für entitätsspezifische CSV-Import-Spezifikationen.
 */
class EntitySpecRegistry {
    public function __construct(
        private readonly CustomerSpec $customers,
        private readonly ProjectSpec $projects,
        private readonly UserSpec $users,
        private readonly MaterialSpec $materials,
        private readonly ScheduledShiftSpec $scheduledShifts,
        private readonly RemoteSessionSpec $remoteSessions,
    ) {
    }

    public function for(ImportEntity $entity): EntitySpec {
        return match ($entity) {
            ImportEntity::Customers => $this->customers,
            ImportEntity::Projects => $this->projects,
            ImportEntity::Users => $this->users,
            ImportEntity::Materials => $this->materials,
            ImportEntity::ScheduledShifts => $this->scheduledShifts,
            ImportEntity::RemoteSessions => $this->remoteSessions,
        };
    }

    public function byValue(string $entityValue): EntitySpec {
        $entity = ImportEntity::tryFrom($entityValue)
            ?? throw new InvalidArgumentException("Unbekannte Import-Entität: {$entityValue}");

        return $this->for($entity);
    }
}
