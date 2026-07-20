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
use App\Services\Import\Specs\{ArticleSpec, AttendanceSpec, CustomerSpec, MaterialSpec, ProjectSpec, ProjectTimeSpec, ScheduledShiftSpec, SupplierSpec, UserSpec, VehicleSpec};
use InvalidArgumentException;

/**
 * Lookup-Registry für entitätsspezifische CSV-Import-Spezifikationen.
 */
class EntitySpecRegistry {
    public function __construct(
        private readonly CustomerSpec $customers,
        private readonly SupplierSpec $suppliers,
        private readonly ArticleSpec $articles,
        private readonly ProjectSpec $projects,
        private readonly UserSpec $users,
        private readonly MaterialSpec $materials,
        private readonly VehicleSpec $vehicles,
        private readonly ScheduledShiftSpec $scheduledShifts,
        private readonly RemoteSessionSpec $remoteSessions,
        private readonly AttendanceSpec $attendances,
        private readonly ProjectTimeSpec $projectTimes,
    ) {}

    public function for(ImportEntity $entity): EntitySpec {
        return match ($entity) {
            ImportEntity::Customers => $this->customers,
            ImportEntity::Suppliers => $this->suppliers,
            ImportEntity::Articles => $this->articles,
            ImportEntity::Projects => $this->projects,
            ImportEntity::Users => $this->users,
            ImportEntity::Materials => $this->materials,
            ImportEntity::Vehicles => $this->vehicles,
            ImportEntity::ScheduledShifts => $this->scheduledShifts,
            ImportEntity::RemoteSessions => $this->remoteSessions,
            ImportEntity::Attendances => $this->attendances,
            ImportEntity::ProjectTimes => $this->projectTimes,
        };
    }

    public function byValue(string $entityValue): EntitySpec {
        $entity = ImportEntity::tryFrom($entityValue)
            ?? throw new InvalidArgumentException("Unbekannte Import-Entität: {$entityValue}");

        return $this->for($entity);
    }
}
